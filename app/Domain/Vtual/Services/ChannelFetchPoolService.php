<?php

namespace App\Domain\Vtual\Services;

use App\Domain\Vtual\Enums\ChannelFetchPoolReason;
use App\Domain\Vtual\Enums\ChannelFetchPoolStatus;
use App\Domain\Vtual\Enums\ChannelStatus;
use App\Domain\Vtual\Enums\PlatformKey;
use App\Domain\Vtual\Integrations\Dto\PlatformChannelDto;
use App\Domain\Vtual\Integrations\PlatformApiGateway;
use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Models\ChannelFetchPoolItem;
use App\Domain\Vtual\Models\Platform;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Batches non-interactive channel snapshot fetches (refresh / scheduled resync).
 * Flush when pending count >= threshold OR oldest pending age >= max_wait.
 * Single Cache::lock + claim-to-processing prevents double HTTP for the same ids.
 */
class ChannelFetchPoolService
{
    public function __construct(
        private readonly PlatformApiGateway $gateway,
    ) {}

    /**
     * Coalesce open work for (platform, external_id). Triggers size flush when threshold hit.
     */
    public function enqueue(
        Platform|string $platform,
        string $externalId,
        ?string $channelId = null,
        ChannelFetchPoolReason|string $reason = ChannelFetchPoolReason::Refresh,
    ): ChannelFetchPoolItem {
        $platformModel = $this->resolvePlatform($platform);
        $externalId = trim($externalId);
        $reasonEnum = $reason instanceof ChannelFetchPoolReason
            ? $reason
            : ChannelFetchPoolReason::from($reason);

        $item = DB::transaction(function () use ($platformModel, $externalId, $channelId, $reasonEnum): ChannelFetchPoolItem {
            /** @var ChannelFetchPoolItem|null $existing */
            $existing = ChannelFetchPoolItem::query()
                ->where('platform_id', $platformModel->id)
                ->where('external_id', $externalId)
                ->whereIn('status', [
                    ChannelFetchPoolStatus::Pending->value,
                    ChannelFetchPoolStatus::Processing->value,
                ])
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                $existing->forceFill([
                    'channel_id' => $channelId ?? $existing->channel_id,
                    'reason' => $reasonEnum,
                    // Keep original requested_at for age gate (do not reset wait clock).
                ])->save();

                return $existing->refresh();
            }

            return ChannelFetchPoolItem::query()->create([
                'platform_id' => $platformModel->id,
                'external_id' => $externalId,
                'channel_id' => $channelId,
                'reason' => $reasonEnum,
                'status' => ChannelFetchPoolStatus::Pending,
                'requested_at' => now(),
            ]);
        });

        $threshold = max(1, (int) setting_config('vtual.channel_pool.threshold', 50));
        $pending = ChannelFetchPoolItem::query()
            ->where('platform_id', $platformModel->id)
            ->where('status', ChannelFetchPoolStatus::Pending)
            ->count();

        if ($pending >= $threshold) {
            $this->flush($this->platformKey($platformModel), force: false);
        }

        return $item;
    }

    /**
     * Enqueue active channels with stale/null last_synced_at (scheduled resync).
     *
     * @return int Number of channels considered for enqueue
     */
    public function enqueueStaleActiveChannels(?PlatformKey $only = null): int
    {
        $staleHours = max(1, (int) setting_config('vtual.channel_pool.resync_stale_hours', 24));
        $limit = max(1, (int) setting_config('vtual.channel_pool.resync_enqueue_limit', 200));
        $cutoff = now()->subHours($staleHours);

        $query = Channel::query()
            ->with('platform')
            ->where('status', ChannelStatus::Active)
            ->where(function ($q) use ($cutoff): void {
                $q->whereNull('last_synced_at')
                    ->orWhere('last_synced_at', '<=', $cutoff);
            })
            ->orderBy('last_synced_at')
            ->limit($limit);

        if ($only !== null) {
            $platformId = Platform::query()->where('key', $only->value)->value('id');
            if ($platformId === null) {
                return 0;
            }
            $query->where('platform_id', $platformId);
        }

        $count = 0;
        foreach ($query->get() as $channel) {
            $platform = $channel->platform;
            if ($platform === null || ! $platform->isEnabled()) {
                continue;
            }
            $this->enqueue(
                $platform,
                (string) $channel->external_id,
                (string) $channel->id,
                ChannelFetchPoolReason::ScheduledResync,
            );
            $count++;
        }

        return $count;
    }

    /**
     * Flush pending pool items for one or all enabled platforms.
     *
     * @return array{
     *     platforms: array<string, array{status: string, claimed: int, applied: int, failed: int, http: int}>,
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
            $key = $this->platformKey($platform);
            $summary['platforms'][$key->value] = $this->flushPlatform($platform, $key, $force);
        }

        return $summary;
    }

    /**
     * @return array{status: string, claimed: int, applied: int, failed: int, http: int}
     */
    private function flushPlatform(Platform $platform, PlatformKey $key, bool $force): array
    {
        $lockSeconds = max(5, (int) setting_config('vtual.channel_pool.flush_lock_seconds', 30));
        $lock = Cache::lock('vtual.channel_pool.flush.'.$key->value, $lockSeconds);

        if (! $lock->get()) {
            return [
                'status' => 'lock_busy',
                'claimed' => 0,
                'applied' => 0,
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
                    'applied' => 0,
                    'failed' => 0,
                    'http' => 0,
                ];
            }

            $ids = $claimed->pluck('external_id')->unique()->values()->all();

            try {
                /** @var Collection<int, PlatformChannelDto> $dtos */
                $dtos = $this->gateway->fetchChannels($key, $ids);
            } catch (Throwable $e) {
                $this->markFailed($claimed, $e->getMessage());

                return [
                    'status' => 'failed',
                    'claimed' => $claimed->count(),
                    'applied' => 0,
                    'failed' => $claimed->count(),
                    'http' => 1,
                ];
            }

            $byExternal = $dtos->keyBy(fn (PlatformChannelDto $dto) => $dto->externalId);
            $applied = 0;
            $failed = 0;

            foreach ($claimed as $item) {
                $dto = $byExternal->get($item->external_id);
                if ($dto === null) {
                    $item->forceFill([
                        'status' => ChannelFetchPoolStatus::Failed,
                        'processed_at' => now(),
                        'error' => 'Channel not returned by platform batch.',
                    ])->save();
                    $failed++;

                    continue;
                }

                $this->applyDtoToChannels($platform, $dto);
                $item->forceFill([
                    'status' => ChannelFetchPoolStatus::Done,
                    'processed_at' => now(),
                    'error' => null,
                ])->save();
                $applied++;
            }

            return [
                'status' => 'flushed',
                'claimed' => $claimed->count(),
                'applied' => $applied,
                'failed' => $failed,
                'http' => 1,
            ];
        } finally {
            $lock->release();
        }
    }

    /**
     * @return Collection<int, ChannelFetchPoolItem>
     */
    private function claimPending(Platform $platform, bool $force): Collection
    {
        $threshold = max(1, (int) setting_config('vtual.channel_pool.threshold', 50));
        $maxWait = max(1, (int) setting_config('vtual.channel_pool.max_wait_minutes', 15));
        $batchMax = max(1, min(
            $threshold,
            (int) setting_config('vtual.http.batch.channels', 50),
        ));

        return DB::transaction(function () use ($platform, $force, $threshold, $maxWait, $batchMax): Collection {
            /** @var Collection<int, ChannelFetchPoolItem> $pending */
            $pending = ChannelFetchPoolItem::query()
                ->where('platform_id', $platform->id)
                ->where('status', ChannelFetchPoolStatus::Pending)
                ->orderBy('requested_at')
                ->limit($batchMax)
                ->lockForUpdate()
                ->get();

            if ($pending->isEmpty()) {
                return $pending;
            }

            if (! $force) {
                $countAll = ChannelFetchPoolItem::query()
                    ->where('platform_id', $platform->id)
                    ->where('status', ChannelFetchPoolStatus::Pending)
                    ->count();

                $oldest = $pending->first()?->requested_at;
                $ageOk = $oldest !== null && $oldest->lte(now()->subMinutes($maxWait));
                $sizeOk = $countAll >= $threshold;

                if (! $sizeOk && ! $ageOk) {
                    return new Collection;
                }
            }

            $ids = $pending->pluck('id')->all();
            ChannelFetchPoolItem::query()
                ->whereIn('id', $ids)
                ->update(['status' => ChannelFetchPoolStatus::Processing->value]);

            return ChannelFetchPoolItem::query()
                ->whereIn('id', $ids)
                ->get();
        });
    }

    /**
     * @param  Collection<int, ChannelFetchPoolItem>  $items
     */
    private function markFailed(Collection $items, string $error): void
    {
        $message = mb_substr($error, 0, 2000);
        foreach ($items as $item) {
            $item->forceFill([
                'status' => ChannelFetchPoolStatus::Failed,
                'processed_at' => now(),
                'error' => $message,
            ])->save();
        }
    }

    private function applyDtoToChannels(Platform $platform, PlatformChannelDto $dto): void
    {
        $channels = Channel::query()
            ->where('platform_id', $platform->id)
            ->where('external_id', $dto->externalId)
            ->get();

        foreach ($channels as $channel) {
            if ($channel->status === ChannelStatus::Archived) {
                continue;
            }

            $channel->forceFill($this->snapshotAttributesFromDto($dto))->save();
        }
    }

    /**
     * Snapshot only — does not force status=active (preserves disconnected etc.).
     *
     * @return array<string, mixed>
     */
    public function snapshotAttributesFromDto(PlatformChannelDto $dto): array
    {
        return [
            'handle' => $dto->handle,
            'display_name' => $dto->displayName,
            'description' => $dto->description,
            'avatar_url' => $dto->thumbnailUrl,
            'banner_url' => $dto->bannerUrl,
            'url' => $dto->url,
            'platform_created_at' => $dto->platformCreatedAt,
            'last_synced_at' => now(),
        ];
    }

    private function resolvePlatform(Platform|string $platform): Platform
    {
        if ($platform instanceof Platform) {
            return $platform;
        }

        $model = Platform::query()->where('key', $platform)->first();
        if ($model === null) {
            throw new \InvalidArgumentException('Unknown platform: '.$platform);
        }

        return $model;
    }

    private function platformKey(Platform $platform): PlatformKey
    {
        $key = $platform->key;
        if ($key instanceof PlatformKey) {
            return $key;
        }

        return PlatformKey::from((string) $key);
    }
}
