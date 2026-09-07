<?php

namespace App\Domain\Vtual\Services;

use App\Domain\Vtual\Enums\ChannelStatus;
use App\Domain\Vtual\Enums\ChannelVisibility;
use App\Domain\Vtual\Enums\ContentAppearanceRole;
use App\Domain\Vtual\Enums\ContentStatus;
use App\Domain\Vtual\Enums\ContentType;
use App\Domain\Vtual\Enums\EntityStatus;
use App\Domain\Vtual\Exceptions\VtualException;
use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Models\Content;
use App\Domain\Vtual\Models\ContentAppearance;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Worker-facing content persistence: one row per platform external_id (canonical).
 * Primary host channel is API truth; extra channels are appearances (not multi-ownership).
 */
class ContentService
{
    /**
     * Create or update canonical content for a registered primary (host) channel.
     * Syncs denormalized FKs and the primary appearance row.
     *
     * @param  array{
     *     external_id: string,
     *     type?: string|ContentType|null,
     *     status?: string|ContentStatus|null,
     *     title?: string|null,
     *     description?: string|null,
     *     thumbnail_url?: string|null,
     *     url?: string|null,
     *     concurrent_viewers?: int|null,
     *     scheduled_at?: mixed,
     *     started_at?: mixed,
     *     ended_at?: mixed,
     *     published_at?: mixed,
     * }  $data
     */
    public function upsertFromPrimary(Channel $primaryChannel, array $data): Content
    {
        $externalId = trim((string) ($data['external_id'] ?? ''));
        if ($externalId === '') {
            throw new VtualException(__('api/vtual.content.external_id_required'), 422);
        }

        return DB::transaction(function () use ($primaryChannel, $data, $externalId): Content {
            $lockedChannel = Channel::query()->whereKey($primaryChannel->id)->lockForUpdate()->firstOrFail();

            $content = Content::query()
                ->where('platform_id', $lockedChannel->platform_id)
                ->where('external_id', $externalId)
                ->lockForUpdate()
                ->first();

            $attrs = [
                'creator_id' => $lockedChannel->creator_id,
                'channel_id' => $lockedChannel->id,
                'platform_id' => $lockedChannel->platform_id,
                'external_id' => $externalId,
                'type' => $this->resolveType($data['type'] ?? null),
                'status' => $this->resolveStatus($data['status'] ?? null),
                'title' => $data['title'] ?? null,
                'description' => $data['description'] ?? null,
                'thumbnail_url' => $data['thumbnail_url'] ?? null,
                'url' => $data['url'] ?? null,
                'concurrent_viewers' => $data['concurrent_viewers'] ?? null,
                'scheduled_at' => $data['scheduled_at'] ?? null,
                'started_at' => $data['started_at'] ?? null,
                'ended_at' => $data['ended_at'] ?? null,
                'published_at' => $data['published_at'] ?? null,
                'hydrated_at' => now(),
                // Re-available: a successful hydrate clears any tombstone.
                'first_unavailable_at' => null,
            ];

            if ($content === null) {
                $content = new Content($attrs);
                $content->id = (string) Str::uuid7();
                $content->save();
            } else {
                // Keep existing non-primary appearances; only re-host primary FKs + metadata.
                $content->fill($attrs)->save();
            }

            $this->syncPrimaryAppearance($content, $lockedChannel);

            return $content->fresh(['channel', 'creator', 'platform', 'appearances']) ?? $content;
        }, 3);
    }

    /**
     * Minimal ingest from feed/WebSub while channel is archived (no API hydrate).
     * Never downgrades a row that already has hydrated_at set.
     *
     * @param  array{
     *     external_id: string,
     *     title?: string|null,
     *     url?: string|null,
     *     published_at?: mixed,
     * }  $data
     */
    public function upsertStubFromPrimary(Channel $primaryChannel, array $data): Content
    {
        $externalId = trim((string) ($data['external_id'] ?? ''));
        if ($externalId === '') {
            throw new VtualException(__('api/vtual.content.external_id_required'), 422);
        }

        return DB::transaction(function () use ($primaryChannel, $data, $externalId): Content {
            $lockedChannel = Channel::query()->whereKey($primaryChannel->id)->lockForUpdate()->firstOrFail();

            $content = Content::query()
                ->where('platform_id', $lockedChannel->platform_id)
                ->where('external_id', $externalId)
                ->lockForUpdate()
                ->first();

            if ($content !== null && $content->hydrated_at !== null) {
                return $content->fresh(['channel', 'creator', 'platform', 'appearances']) ?? $content;
            }

            $url = $data['url'] ?? null;
            if ($url === null || trim((string) $url) === '') {
                $url = 'https://www.youtube.com/watch?v='.$externalId;
            }

            $attrs = [
                'creator_id' => $lockedChannel->creator_id,
                'channel_id' => $lockedChannel->id,
                'platform_id' => $lockedChannel->platform_id,
                'external_id' => $externalId,
                'type' => ContentType::Vod,
                'status' => ContentStatus::Unknown,
                'title' => $data['title'] ?? null,
                'url' => $url,
                'published_at' => $data['published_at'] ?? null,
                'hydrated_at' => null,
            ];

            if ($content === null) {
                $content = new Content($attrs);
                $content->id = (string) Str::uuid7();
                $content->save();
            } else {
                $content->fill($attrs)->save();
            }

            $this->syncPrimaryAppearance($content, $lockedChannel);

            return $content->fresh(['channel', 'creator', 'platform', 'appearances']) ?? $content;
        }, 3);
    }

    /**
     * Tombstone: platform no longer returns this id (deleted / private).
     * Idempotent — first_unavailable_at is set only on the first mark.
     */
    public function markUnavailable(Channel $primaryChannel, string $externalId): void
    {
        $externalId = trim($externalId);
        if ($externalId === '') {
            return;
        }

        DB::transaction(function () use ($primaryChannel, $externalId): void {
            $content = Content::query()
                ->where('platform_id', $primaryChannel->platform_id)
                ->where('external_id', $externalId)
                ->lockForUpdate()
                ->first();

            if ($content === null) {
                return;
            }

            $content->forceFill([
                'status' => ContentStatus::Unavailable,
                'first_unavailable_at' => $content->first_unavailable_at ?? now(),
            ])->save();
        }, 3);
    }

    /**
     * End every live content row of a channel (Twitch stream.offline / poll
     * backstop). Idempotent — already-ended rows are not touched and never
     * overwrite an existing ended_at.
     */
    public function endLiveStreamsForChannel(Channel $primaryChannel): int
    {
        return (int) DB::transaction(function () use ($primaryChannel): int {
            $liveRows = Content::query()
                ->where('platform_id', $primaryChannel->platform_id)
                ->where('channel_id', $primaryChannel->id)
                ->where('type', ContentType::Live)
                ->where('status', ContentStatus::Live)
                ->lockForUpdate()
                ->get();

            foreach ($liveRows as $content) {
                $content->forceFill([
                    'type' => ContentType::Archive,
                    'status' => ContentStatus::Ended,
                    'ended_at' => $content->ended_at ?? now(),
                ])->save();
            }

            return $liveRows->count();
        }, 3);
    }

    /**
     * Link an additional registered channel as an appearance (collaborator or network).
     * Cannot attach role=primary — use setPrimary.
     */
    public function attachAppearance(
        Content $content,
        Channel $channel,
        ContentAppearanceRole $role,
    ): ContentAppearance {
        if ($role === ContentAppearanceRole::Primary) {
            throw new VtualException(__('api/vtual.content.appearance_primary_via_set'), 422);
        }

        if (! in_array($role, ContentAppearanceRole::nonPrimary(), true)) {
            throw new VtualException(__('api/vtual.content.appearance_role_invalid'), 422);
        }

        return DB::transaction(function () use ($content, $channel, $role): ContentAppearance {
            $locked = Content::query()->whereKey($content->id)->lockForUpdate()->firstOrFail();
            $lockedChannel = Channel::query()->whereKey($channel->id)->lockForUpdate()->firstOrFail();

            if ((string) $lockedChannel->platform_id !== (string) $locked->platform_id) {
                throw new VtualException(__('api/vtual.content.appearance_platform_mismatch'), 422);
            }

            if ((string) $lockedChannel->id === (string) $locked->channel_id) {
                throw new VtualException(__('api/vtual.content.appearance_already_primary'), 422);
            }

            $existing = ContentAppearance::query()
                ->where('content_id', $locked->id)
                ->where('channel_id', $lockedChannel->id)
                ->first();

            if ($existing !== null) {
                throw new VtualException(__('api/vtual.content.appearance_already_attached'), 422);
            }

            $appearance = new ContentAppearance([
                'content_id' => $locked->id,
                'channel_id' => $lockedChannel->id,
                'creator_id' => $lockedChannel->creator_id,
                'role' => $role,
            ]);
            $appearance->id = (string) Str::uuid7();
            $appearance->save();

            return $appearance->load(['channel', 'creator', 'content']);
        });
    }

    /**
     * Remove a non-primary appearance. Primary host cannot be detached this way.
     */
    public function detachAppearance(Content $content, Channel $channel): void
    {
        DB::transaction(function () use ($content, $channel): void {
            $locked = Content::query()->whereKey($content->id)->lockForUpdate()->firstOrFail();

            $appearance = ContentAppearance::query()
                ->where('content_id', $locked->id)
                ->where('channel_id', $channel->id)
                ->lockForUpdate()
                ->first();

            if ($appearance === null) {
                throw new VtualException(__('api/vtual.content.appearance_not_found'), 422);
            }

            if ($appearance->role === ContentAppearanceRole::Primary) {
                throw new VtualException(__('api/vtual.content.cannot_detach_primary'), 422);
            }

            $appearance->delete();
        });
    }

    /**
     * Move primary host to another registered channel on the same platform.
     * Previous primary becomes demotedRole (network|collaborator).
     */
    public function setPrimary(
        Content $content,
        Channel $newPrimary,
        ContentAppearanceRole $demotedRole = ContentAppearanceRole::Network,
    ): Content {
        if ($demotedRole === ContentAppearanceRole::Primary) {
            throw new VtualException(__('api/vtual.content.appearance_role_invalid'), 422);
        }

        return DB::transaction(function () use ($content, $newPrimary, $demotedRole): Content {
            $locked = Content::query()->whereKey($content->id)->lockForUpdate()->firstOrFail();
            $channel = Channel::query()->whereKey($newPrimary->id)->lockForUpdate()->firstOrFail();

            if ((string) $channel->platform_id !== (string) $locked->platform_id) {
                throw new VtualException(__('api/vtual.content.appearance_platform_mismatch'), 422);
            }

            if ((string) $channel->id === (string) $locked->channel_id) {
                return $locked->fresh(['channel', 'creator', 'platform', 'appearances']) ?? $locked;
            }

            $oldPrimaryId = (string) $locked->channel_id;

            $locked->forceFill([
                'channel_id' => $channel->id,
                'creator_id' => $channel->creator_id,
            ])->save();

            // Demote previous primary appearance (or create if missing).
            $oldAppearance = ContentAppearance::query()
                ->where('content_id', $locked->id)
                ->where('channel_id', $oldPrimaryId)
                ->lockForUpdate()
                ->first();

            if ($oldAppearance !== null) {
                $oldAppearance->forceFill(['role' => $demotedRole])->save();
            } else {
                $oldChannel = Channel::query()->whereKey($oldPrimaryId)->first();
                if ($oldChannel !== null) {
                    $row = new ContentAppearance([
                        'content_id' => $locked->id,
                        'channel_id' => $oldChannel->id,
                        'creator_id' => $oldChannel->creator_id,
                        'role' => $demotedRole,
                    ]);
                    $row->id = (string) Str::uuid7();
                    $row->save();
                }
            }

            $this->syncPrimaryAppearance($locked, $channel);

            return $locked->fresh(['channel', 'creator', 'platform', 'appearances']) ?? $locked;
        });
    }

    /**
     * Contents where channel is primary host or has any appearance.
     *
     * @return Collection<int, Content>
     */
    public function listForChannel(string $channelId): Collection
    {
        return Content::query()
            ->where(function ($q) use ($channelId): void {
                $q->where('channel_id', $channelId)
                    ->orWhereHas('appearances', fn ($a) => $a->where('channel_id', $channelId));
            })
            ->with(['channel', 'creator', 'platform', 'appearances'])
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Contents where creator is primary host or appears via any linked channel.
     *
     * @return Collection<int, Content>
     */
    public function listForCreator(string $creatorId): Collection
    {
        return Content::query()
            ->where(function ($q) use ($creatorId): void {
                $q->where('creator_id', $creatorId)
                    ->orWhereHas('appearances', fn ($a) => $a->where('creator_id', $creatorId));
            })
            ->with(['channel', 'creator', 'platform', 'appearances'])
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Public vTuber profile: exclude content whose primary channel is archived.
     * Live rows first, then newest published. Hard cap keeps the guest payload bounded.
     *
     * @return Collection<int, Content>
     */
    public function listForPublicProfile(string $creatorId, int $limit = 12): Collection
    {
        return Content::query()
            ->where(function ($q) use ($creatorId): void {
                $q->where('creator_id', $creatorId)
                    ->orWhereHas('appearances', fn ($a) => $a->where('creator_id', $creatorId));
            })
            ->whereDoesntHave('channel', fn ($q) => $q->where('status', ChannelStatus::Archived))
            ->where('status', '!=', ContentStatus::Unavailable)
            ->with(['channel', 'creator', 'platform', 'appearances'])
            ->orderByRaw(
                'CASE WHEN type = ? AND status = ? THEN 0 ELSE 1 END',
                [ContentType::Live->value, ContentStatus::Live->value],
            )
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->limit(max(1, $limit))
            ->get();
    }

    /**
     * Public vTuber profile contents, paginated. Same visibility + live-first
     * ordering as listForPublicProfile, but as a paginator so the SPA can page
     * through a creator's full catalogue instead of a hard-capped slice.
     *
     * @return LengthAwarePaginator<int, Content>
     */
    public function paginateForPublicProfile(string $creatorId, int $perPage = 12): LengthAwarePaginator
    {
        return Content::query()
            ->where(function ($q) use ($creatorId): void {
                $q->where('creator_id', $creatorId)
                    ->orWhereHas('appearances', fn ($a) => $a->where('creator_id', $creatorId));
            })
            ->whereDoesntHave('channel', fn ($q) => $q->where('status', ChannelStatus::Archived))
            ->where('status', '!=', ContentStatus::Unavailable)
            ->with(['channel', 'creator', 'platform', 'appearances'])
            ->orderByRaw(
                'CASE WHEN type = ? AND status = ? THEN 0 ELSE 1 END',
                [ContentType::Live->value, ContentStatus::Live->value],
            )
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->paginate(max(1, $perPage));
    }

    /**
     * Guest landing feed: contents from every publicly listed creator.
     * Live rows first, then newest published. Stubs without title/url are hidden.
     *
     * @return LengthAwarePaginator<int, Content>
     */
    public function paginateForPublicDirectory(int $perPage = 12): LengthAwarePaginator
    {
        return Content::query()
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
            ->orderByRaw(
                'CASE WHEN type = ? AND status = ? THEN 0 ELSE 1 END',
                [ContentType::Live->value, ContentStatus::Live->value],
            )
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->paginate(max(1, $perPage));
    }

    private function syncPrimaryAppearance(Content $content, Channel $primaryChannel): void
    {
        // Ensure only one primary role row.
        ContentAppearance::query()
            ->where('content_id', $content->id)
            ->where('role', ContentAppearanceRole::Primary)
            ->where('channel_id', '!=', $primaryChannel->id)
            ->update(['role' => ContentAppearanceRole::Network->value]);

        $appearance = ContentAppearance::query()
            ->where('content_id', $content->id)
            ->where('channel_id', $primaryChannel->id)
            ->first();

        if ($appearance === null) {
            $appearance = new ContentAppearance([
                'content_id' => $content->id,
                'channel_id' => $primaryChannel->id,
                'creator_id' => $primaryChannel->creator_id,
                'role' => ContentAppearanceRole::Primary,
            ]);
            $appearance->id = (string) Str::uuid7();
            $appearance->save();

            return;
        }

        $appearance->forceFill([
            'creator_id' => $primaryChannel->creator_id,
            'role' => ContentAppearanceRole::Primary,
        ])->save();
    }

    private function resolveType(mixed $type): ContentType
    {
        if ($type instanceof ContentType) {
            return $type;
        }
        if (is_string($type) && $type !== '') {
            return ContentType::from($type);
        }

        return ContentType::Vod;
    }

    private function resolveStatus(mixed $status): ContentStatus
    {
        if ($status instanceof ContentStatus) {
            return $status;
        }
        if (is_string($status) && $status !== '') {
            return ContentStatus::from($status);
        }

        return ContentStatus::Unknown;
    }
}
