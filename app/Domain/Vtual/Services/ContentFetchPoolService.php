<?php

namespace App\Domain\Vtual\Services;

use App\Domain\Vtual\Enums\ChannelStatus;
use App\Domain\Vtual\Enums\ContentFetchPoolSource;
use App\Domain\Vtual\Enums\ContentFetchPoolStatus;
use App\Domain\Vtual\Enums\PlatformKey;
use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Models\ContentFetchPoolItem;
use App\Domain\Vtual\Models\Platform;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Batches video id hydration (feed poll / WebSub / reconcile).
 */
class ContentFetchPoolService
{
    public function __construct(
        private readonly ChannelContentFetchService $contentFetch,
        private readonly ContentService $contents,
    ) {}

    public function enqueue(
        Channel $channel,
        string $videoExternalId,
        ContentFetchPoolSource|string $source = ContentFetchPoolSource::FeedPoll,
    ): ContentFetchPoolItem {
        $channel->loadMissing('platform');
        $platform = $channel->platform;
        if ($platform === null) {
            throw new \InvalidArgumentException('Channel platform missing.');
        }

        $videoExternalId = trim($videoExternalId);
        $sourceEnum = $source instanceof ContentFetchPoolSource
            ? $source
            : ContentFetchPoolSource::from($source);

        $item = DB::transaction(function () use ($platform, $channel, $videoExternalId, $sourceEnum): ContentFetchPoolItem {
            /** @var ContentFetchPoolItem|null $existing */
            $existing = ContentFetchPoolItem::query()
                ->where('platform_id', $platform->id)
                ->where('video_external_id', $videoExternalId)
                ->whereIn('status', [
                    ContentFetchPoolStatus::Pending->value,
                    ContentFetchPoolStatus::Processing->value,
                ])
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                $existing->forceFill([
                    'channel_id' => $channel->id,
                    'source' => $sourceEnum,
                ])->save();

                return $existing->refresh();
            }

            return ContentFetchPoolItem::query()->create([
                'platform_id' => $platform->id,
                'video_external_id' => $videoExternalId,
                'channel_id' => $channel->id,
                'source' => $sourceEnum,
                'status' => ContentFetchPoolStatus::Pending,
                'requested_at' => now(),
            ]);
        }, 3);

        $threshold = max(1, (int) setting_config('vtual.content_pool.threshold', 50));
        $pending = ContentFetchPoolItem::query()
            ->where('platform_id', $platform->id)
            ->where('status', ContentFetchPoolStatus::Pending)
            ->count();

        if ($pending >= $threshold) {
            $this->flush($platform->toPlatformKey(), force: false);
        }

        return $item;
    }

    /**
     * @return array{
     *     platforms: array<string, array{status: string, claimed: int, upserted: int, failed: int, http: int}>,
     * }
     */
    public function flush(?PlatformKey $only = null, bool $force = false): array
    {
        $platforms = Platform::query()
            ->where('enabled', true)
            ->when($only !== null, fn ($q) => $q->where('key', $only->value))
            ->get();

        $summary = ['platforms' => []];

        foreach ($platforms as $platform) {
            $key = $platform->toPlatformKey();
            if ($key !== PlatformKey::Youtube) {
                continue;
            }

            $summary['platforms'][$key->value] = $this->flushPlatform($platform, $force);
        }

        return $summary;
    }

    /**
     * @return array{status: string, claimed: int, upserted: int, failed: int, http: int}
     */
    private function flushPlatform(Platform $platform, bool $force): array
    {
        $lockSeconds = max(5, (int) setting_config('vtual.content_pool.flush_lock_seconds', 30));
        $lock = Cache::lock('vtual.content_pool.flush.'.$platform->id, $lockSeconds);

        if (! $lock->get()) {
            return [
                'status' => 'lock_busy',
                'claimed' => 0,
                'upserted' => 0,
                'failed' => 0,
                'http' => 0,
            ];
        }

        try {
            $claimed = $this->claimPending($platform, $force);
            if ($claimed->isEmpty()) {
                return [
                    'status' => 'skipped',
                    'claimed' => 0,
                    'upserted' => 0,
                    'failed' => 0,
                    'http' => 0,
                ];
            }

            $upserted = 0;
            $failed = 0;
            $http = 0;

            $byChannel = $claimed->groupBy('channel_id');
            foreach ($byChannel as $channelId => $items) {
                /** @var Channel|null $channel */
                $channel = Channel::query()->with('platform')->find($channelId);
                if ($channel === null) {
                    $this->markFailed($items, 'Channel not found.');
                    $failed += $items->count();

                    continue;
                }

                $videoIds = $items->pluck('video_external_id')->unique()->values()->all();

                if ($channel->status === ChannelStatus::Archived) {
                    foreach ($items as $item) {
                        $this->contents->upsertStubFromPrimary($channel, [
                            'external_id' => (string) $item->video_external_id,
                        ]);
                        $item->forceFill([
                            'status' => ContentFetchPoolStatus::Done,
                            'processed_at' => now(),
                            'error' => null,
                        ])->save();
                    }
                    $upserted += $items->count();

                    continue;
                }

                $batchMax = max(1, (int) setting_config('vtual.http.batch.videos', 50));

                foreach (array_chunk($videoIds, $batchMax) as $chunk) {
                    $chunkItems = $items->whereIn('video_external_id', $chunk);

                    try {
                        $result = $this->contentFetch->fetchVideosAndPersist($channel, $chunk);
                        $http++;
                        $upserted += $result['upserted'];

                        foreach ($chunkItems as $item) {
                            $item->forceFill([
                                'status' => ContentFetchPoolStatus::Done,
                                'processed_at' => now(),
                                'error' => null,
                            ])->save();
                        }
                    } catch (Throwable $e) {
                        $this->markFailed($chunkItems, $e->getMessage());
                        $failed += $chunkItems->count();
                    }
                }
            }

            return [
                'status' => 'flushed',
                'claimed' => $claimed->count(),
                'upserted' => $upserted,
                'failed' => $failed,
                'http' => $http,
            ];
        } finally {
            $lock->release();
        }
    }

    /**
     * @return Collection<int, ContentFetchPoolItem>
     */
    private function claimPending(Platform $platform, bool $force): Collection
    {
        $threshold = max(1, (int) setting_config('vtual.content_pool.threshold', 50));
        $maxWait = max(1, (int) setting_config('vtual.content_pool.max_wait_minutes', 15));
        $batchMax = max(1, min(
            $threshold,
            (int) setting_config('vtual.http.batch.videos', 50),
        ));

        return DB::transaction(function () use ($platform, $force, $threshold, $maxWait, $batchMax): Collection {
            /** @var Collection<int, ContentFetchPoolItem> $pending */
            $pending = ContentFetchPoolItem::query()
                ->where('platform_id', $platform->id)
                ->where('status', ContentFetchPoolStatus::Pending)
                ->orderBy('requested_at')
                ->limit($batchMax)
                ->lockForUpdate()
                ->get();

            if ($pending->isEmpty()) {
                return $pending;
            }

            if (! $force) {
                $countAll = ContentFetchPoolItem::query()
                    ->where('platform_id', $platform->id)
                    ->where('status', ContentFetchPoolStatus::Pending)
                    ->count();

                $oldest = $pending->first()?->requested_at;
                $ageOk = $oldest !== null && $oldest->lte(now()->subMinutes($maxWait));
                $sizeOk = $countAll >= $threshold;

                if (! $sizeOk && ! $ageOk) {
                    return new Collection;
                }
            }

            $ids = $pending->pluck('id')->all();
            ContentFetchPoolItem::query()
                ->whereIn('id', $ids)
                ->update(['status' => ContentFetchPoolStatus::Processing->value]);

            return ContentFetchPoolItem::query()
                ->whereIn('id', $ids)
                ->get();
        }, 3);
    }

    /**
     * @param  Collection<int, ContentFetchPoolItem>  $items
     */
    private function markFailed(Collection $items, string $error): void
    {
        $message = mb_substr($error, 0, 2000);
        foreach ($items as $item) {
            $item->forceFill([
                'status' => ContentFetchPoolStatus::Failed,
                'processed_at' => now(),
                'error' => $message,
            ])->save();
        }
    }
}
