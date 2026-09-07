<?php

namespace App\Domain\Vtual\Services;

use App\Domain\Vtual\Enums\ChannelStatus;
use App\Domain\Vtual\Enums\ChannelVisibility;
use App\Domain\Vtual\Enums\ContentStatus;
use App\Domain\Vtual\Enums\ContentType;
use App\Domain\Vtual\Enums\EntityStatus;
use App\Domain\Vtual\Enums\VtualEventStatus;
use App\Domain\Vtual\Exceptions\VtualException;
use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Models\Content;
use App\Domain\Vtual\Models\Creator;
use App\Domain\Vtual\Models\VtualEvent;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

/**
 * Read-only public profile aggregate (no membership gate).
 */
class VtuberProfileService
{
    public function __construct(
        private readonly ContentService $contents,
    ) {}

    /**
     * Public directory of active creators that expose at least one
     * active + public channel. Guest-safe; no membership gate.
     *
     * @return LengthAwarePaginator<int, Creator>
     */
    public function directory(?string $search = null, int $perPage = 12): LengthAwarePaginator
    {
        return Creator::query()
            ->where('status', EntityStatus::Active)
            ->whereHas('channels', function ($query): void {
                $query->where('status', ChannelStatus::Active)
                    ->where('visibility', ChannelVisibility::Public);
            })
            ->when(
                $search !== null && trim($search) !== '',
                fn ($query) => $query->where('display_name', 'like', '%'.trim($search).'%'),
            )
            ->with([
                'channels' => function ($query): void {
                    $query->where('status', ChannelStatus::Active)
                        ->where('visibility', ChannelVisibility::Public)
                        ->with('platform')
                        ->orderByDesc('subscriber_count');
                },
            ])
            ->withExists(['contents as is_live' => function ($query): void {
                $query->where('type', ContentType::Live)
                    ->where('status', ContentStatus::Live)
                    ->whereDoesntHave(
                        'channel',
                        fn ($channel) => $channel->where('status', ChannelStatus::Archived),
                    );
            }])
            ->orderByDesc('is_live')
            ->orderBy('display_name')
            ->paginate($perPage);
    }

    public function show(string $key, int $perPage = 12): Creator
    {
        $creator = $this->resolveActive($key);

        $creator->load([
            'channels' => function ($query): void {
                $query->where('status', ChannelStatus::Active)
                    ->where('visibility', ChannelVisibility::Public)
                    ->with('platform')
                    ->orderBy('created_at');
            },
            'externalLinks' => fn ($query) => $query->orderBy('sort_order'),
        ]);

        $creator->setRelation(
            'profileContents',
            $this->contents->paginateForPublicProfile($creator->id, $perPage),
        );
        $creator->setAttribute('is_live', $this->hasLiveContent($creator->id));

        return $creator;
    }

    public function showForPublic(string $key, int $perPage = 12): Creator
    {
        $creator = $this->resolvePubliclyListed($key);

        $creator->load([
            'channels' => function ($query): void {
                $query->where('status', ChannelStatus::Active)
                    ->where('visibility', ChannelVisibility::Public)
                    ->with('platform')
                    ->orderBy('created_at');
            },
            'externalLinks' => fn ($query) => $query->orderBy('sort_order'),
        ]);

        $creator->setRelation(
            'profileContents',
            $this->contents->paginateForPublicProfile($creator->id, $perPage),
        );
        $creator->setAttribute('is_live', $this->hasLiveContent($creator->id));

        return $creator;
    }

    /**
     * Guest landing feed across every publicly listed creator.
     *
     * @return LengthAwarePaginator<int, \App\Domain\Vtual\Models\Content>
     */
    public function paginatePublicContents(int $perPage = 12): LengthAwarePaginator
    {
        return $this->contents->paginateForPublicDirectory($perPage);
    }

    /**
     * Live contents across every publicly listed creator, newest first.
     * Powers the landing hero spotlight + "live now" strip.
     *
     * @return Collection<int, Content>
     */
    public function liveNow(int $limit = 8): Collection
    {
        return Content::query()
            ->where('type', ContentType::Live)
            ->where('status', ContentStatus::Live)
            ->whereNotNull('title')
            ->where('title', '!=', '')
            ->whereNotNull('url')
            ->where('url', '!=', '')
            ->whereHas('creator', function ($query): void {
                $query->where('status', EntityStatus::Active)
                    ->whereHas('channels', function ($channels): void {
                        $channels->where('status', ChannelStatus::Active)
                            ->where('visibility', ChannelVisibility::Public);
                    });
            })
            ->whereHas('channel', function ($query): void {
                $query->where('status', ChannelStatus::Active)
                    ->where('visibility', ChannelVisibility::Public);
            })
            ->with(['channel', 'creator', 'platform'])
            ->orderByDesc('started_at')
            ->orderByDesc('published_at')
            ->limit(max(1, $limit))
            ->get();
    }

    /**
     * Upcoming scheduled streams (scheduled_at in the future), soonest first.
     * Powers the landing events section; empty → SPA falls back to settings event.
     *
     * @return Collection<int, Content>
     */
    public function upcomingSchedule(int $limit = 6): Collection
    {
        return Content::query()
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '>=', now())
            ->whereIn('status', [ContentStatus::Scheduled, ContentStatus::Live, ContentStatus::Unknown])
            ->whereNotNull('title')
            ->where('title', '!=', '')
            ->whereHas('creator', function ($query): void {
                $query->where('status', EntityStatus::Active)
                    ->whereHas('channels', function ($channels): void {
                        $channels->where('status', ChannelStatus::Active)
                            ->where('visibility', ChannelVisibility::Public);
                    });
            })
            ->whereHas('channel', function ($query): void {
                $query->where('status', ChannelStatus::Active)
                    ->where('visibility', ChannelVisibility::Public);
            })
            ->with(['channel', 'creator', 'platform'])
            ->orderBy('scheduled_at')
            ->limit(max(1, $limit))
            ->get();
    }

    /**
     * Upcoming management-submitted events (scheduled/live, starts_at in the
     * future), soonest first. Powers the landing events section; empty → SPA
     * falls back to the derived scheduled-streams view.
     *
     * @return Collection<int, VtualEvent>
     */
    public function upcomingEvents(int $limit = 6): Collection
    {
        return VtualEvent::query()
            ->whereIn('status', VtualEventStatus::publicVisible())
            ->where('starts_at', '>=', now())
            ->with(['management', 'creator'])
            ->orderBy('starts_at')
            ->orderBy('is_featured', 'desc')
            ->limit(max(1, $limit))
            ->get();
    }

    /**
     * Aggregate counts for the landing stats strip. Primitive array — safe to
     * cache forever (invalidated on content/creator writes by the caller).
     *
     * @return array{creators: int, channels: int, contents: int, subscribers_total: int, subscribers_total_formatted: string}
     */
    public function publicStats(): array
    {
        $creators = Creator::query()
            ->where('status', EntityStatus::Active)
            ->whereHas('channels', function ($query): void {
                $query->where('status', ChannelStatus::Active)
                    ->where('visibility', ChannelVisibility::Public);
            })
            ->count();

        $channels = Channel::query()
            ->where('status', ChannelStatus::Active)
            ->where('visibility', ChannelVisibility::Public)
            ->count();

        $contents = Content::query()
            ->whereNotNull('title')
            ->where('title', '!=', '')
            ->whereHas('creator', fn ($query) => $query->where('status', EntityStatus::Active))
            ->whereHas('channel', function ($query): void {
                $query->where('status', ChannelStatus::Active)
                    ->where('visibility', ChannelVisibility::Public);
            })
            ->count();

        $subscribers = (int) Channel::query()
            ->where('status', ChannelStatus::Active)
            ->where('visibility', ChannelVisibility::Public)
            ->sum('subscriber_count');

        return [
            'creators' => $creators,
            'channels' => $channels,
            'contents' => $contents,
            'subscribers_total' => $subscribers,
            'subscribers_total_formatted' => Number::format($subscribers),
        ];
    }

    /**
     * Featured event from Tier B settings (Settings → vTual → landing.event).
     * Null when no valid start date — the SPA hides the countdown banner then.
     *
     * @return array{title: string, starts_at: string, body: string}|null
     */
    public function landingEvent(): ?array
    {
        $title = trim((string) setting('vtual.landing.event.title', ''));
        $startsAt = trim((string) setting('vtual.landing.event.starts_at', ''));
        $body = trim((string) setting('vtual.landing.event.body', ''));

        if ($startsAt === '' || strtotime($startsAt) === false) {
            return null;
        }

        return [
            'title' => $title,
            'starts_at' => $startsAt,
            'body' => $body,
        ];
    }

    /**
     * Lightweight any-live check across the creator's full catalogue
     * (non-archived channels). Independent of the paginated contents page.
     */
    private function hasLiveContent(string $creatorId): bool
    {
        return Content::query()
            ->where('type', ContentType::Live)
            ->where('status', ContentStatus::Live)
            ->where(function ($q) use ($creatorId): void {
                $q->where('creator_id', $creatorId)
                    ->orWhereHas('appearances', fn ($a) => $a->where('creator_id', $creatorId));
            })
            ->whereDoesntHave('channel', fn ($q) => $q->where('status', ChannelStatus::Archived))
            ->exists();
    }

    private function resolveActive(string $key): Creator
    {
        $query = Creator::query()->where('status', EntityStatus::Active);

        if (Str::isUuid($key)) {
            $query->whereKey($key);
        } else {
            $query->where('slug', $key);
        }

        $creator = $query->first();

        if ($creator === null) {
            throw new VtualException(__('api/vtual.vtuber.not_found'), 404);
        }

        return $creator;
    }

    private function resolvePubliclyListed(string $key): Creator
    {
        $query = Creator::query()
            ->where('status', EntityStatus::Active)
            ->whereHas('channels', function ($query): void {
                $query->where('status', ChannelStatus::Active)
                    ->where('visibility', ChannelVisibility::Public);
            });

        if (Str::isUuid($key)) {
            $query->whereKey($key);
        } else {
            $query->where('slug', $key);
        }

        $creator = $query->first();

        if ($creator === null) {
            throw new VtualException(__('api/vtual.vtuber.not_found'), 404);
        }

        return $creator;
    }
}
