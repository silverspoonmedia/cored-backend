<?php

namespace App\Domain\Vtual\Services;

use App\Domain\Vtual\Enums\CreatorClaimVerifyPoolStatus;
use App\Domain\Vtual\Enums\PlatformKey;
use App\Domain\Vtual\Exceptions\VtualException;
use App\Domain\Vtual\Integrations\Dto\PlatformChannelDto;
use App\Domain\Vtual\Integrations\PlatformApiGateway;
use App\Domain\Vtual\Models\CreatorClaim;
use App\Domain\Vtual\Models\CreatorClaimVerifyPoolItem;
use App\Domain\Vtual\Models\Platform;
use App\Domain\Vtual\Support\VtualClaimRealtime;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Queued creator-claim bio verification. Flush when pending >= threshold OR oldest age >= max_wait.
 */
class CreatorClaimVerifyPoolService
{
    public function __construct(
        private readonly PlatformApiGateway $gateway,
    ) {}

    public function enqueue(CreatorClaim $claim): CreatorClaimVerifyPoolItem
    {
        $open = CreatorClaimVerifyPoolItem::query()
            ->where('claim_id', $claim->id)
            ->whereIn('status', [
                CreatorClaimVerifyPoolStatus::Pending->value,
                CreatorClaimVerifyPoolStatus::Processing->value,
            ])
            ->exists();

        if ($open) {
            throw new VtualException(__('api/vtual.claim.verify_already_queued'), 422);
        }

        return CreatorClaimVerifyPoolItem::query()->create([
            'claim_id' => $claim->id,
            'status' => CreatorClaimVerifyPoolStatus::Pending,
            'requested_at' => now(),
        ]);
    }

    /**
     * @return array{status: string, claimed: int, verified: int, failed: int, http: int}
     */
    public function flush(bool $force = false): array
    {
        $lockSeconds = max(5, (int) setting_config('vtual.claim_verify_pool.flush_lock_seconds', 30));
        $lock = Cache::lock('vtual.claim_verify_pool.flush', $lockSeconds);

        if (! $lock->get()) {
            return [
                'status' => 'lock_busy',
                'claimed' => 0,
                'verified' => 0,
                'failed' => 0,
                'http' => 0,
            ];
        }

        try {
            $claimed = $this->claimPending($force);
            if ($claimed->isEmpty()) {
                return [
                    'status' => 'skipped',
                    'claimed' => 0,
                    'verified' => 0,
                    'failed' => 0,
                    'http' => 0,
                ];
            }

            $verified = 0;
            $failed = 0;
            $http = 0;

            foreach ($claimed as $poolItem) {
                $processingClaim = $poolItem->claim;
                if ($processingClaim !== null) {
                    app(\App\Domain\Vtual\Support\VtualClaimRealtime::class)
                        ->progress($processingClaim, 'verify_processing');
                }
            }

            $byPlatform = $claimed->groupBy(function (CreatorClaimVerifyPoolItem $item): string {
                $item->loadMissing('claim.channel.platform');

                return (string) $item->claim?->channel?->platform_id;
            });

            foreach ($byPlatform as $platformId => $items) {
                if ($platformId === '' || $items->isEmpty()) {
                    $this->markPoolFailed($items, 'Missing claim channel platform.');
                    $failed += $items->count();

                    continue;
                }

                $platform = Platform::query()->find($platformId);
                if ($platform === null || ! $platform->isEnabled()) {
                    $this->markPoolFailed($items, 'Platform disabled or missing.');
                    $failed += $items->count();

                    continue;
                }

                $key = $this->platformKey($platform);
                if ($key === PlatformKey::Kick) {
                    $this->markPoolFailed($items, 'Kick is not enabled.');
                    $failed += $items->count();

                    continue;
                }

                $externalIds = $items
                    ->map(fn (CreatorClaimVerifyPoolItem $item) => (string) $item->claim?->channel?->external_id)
                    ->filter(fn (string $id): bool => $id !== '')
                    ->unique()
                    ->values()
                    ->all();

                if ($externalIds === []) {
                    $this->markPoolFailed($items, 'Missing channel external id.');
                    $failed += $items->count();

                    continue;
                }

                try {
                    /** @var Collection<int, PlatformChannelDto> $dtos */
                    $dtos = $this->gateway->fetchChannels($key, $externalIds);
                    $http++;
                } catch (Throwable $e) {
                    $this->markPoolFailed($items, $e->getMessage());
                    $failed += $items->count();

                    continue;
                }

                $byExternal = $dtos->keyBy(fn (PlatformChannelDto $dto) => $dto->externalId);

                foreach ($items as $item) {
                    $claim = $item->claim;
                    if ($claim === null) {
                        $this->markPoolItemFailed($item, 'Claim missing.');
                        $failed++;

                        continue;
                    }

                    $externalId = (string) $claim->channel?->external_id;
                    $dto = $byExternal->get($externalId);
                    $description = $dto?->description;

                    if ($dto !== null && $claim->channel !== null) {
                        $claim->channel->forceFill(
                            app(ChannelFetchPoolService::class)->snapshotAttributesFromDto($dto)
                        )->save();
                    }

                    try {
                        $result = app(CreatorClaimService::class)->processQueuedVerify($claim, $description);
                        if ($result === CreatorClaimProcessResult::Verified) {
                            $verified++;
                            $item->forceFill([
                                'status' => CreatorClaimVerifyPoolStatus::Done,
                                'processed_at' => now(),
                                'error' => null,
                            ])->save();
                        } else {
                            $failed++;
                            $item->forceFill([
                                'status' => CreatorClaimVerifyPoolStatus::Done,
                                'processed_at' => now(),
                                'error' => 'Token not found in channel description.',
                            ])->save();
                        }
                    } catch (Throwable $e) {
                        $failed++;
                        $this->markPoolItemFailed($item, $e->getMessage());
                    }
                }
            }

            return [
                'status' => 'flushed',
                'claimed' => $claimed->count(),
                'verified' => $verified,
                'failed' => $failed,
                'http' => $http,
            ];
        } finally {
            $lock->release();
        }
    }

    public function openQueueStatusForClaim(string $claimId): ?CreatorClaimVerifyPoolStatus
    {
        /** @var CreatorClaimVerifyPoolItem|null $item */
        $item = CreatorClaimVerifyPoolItem::query()
            ->where('claim_id', $claimId)
            ->whereIn('status', [
                CreatorClaimVerifyPoolStatus::Pending->value,
                CreatorClaimVerifyPoolStatus::Processing->value,
            ])
            ->orderByDesc('requested_at')
            ->first();

        return $item?->status;
    }

    public function finalizeOpenItemForClaim(
        string $claimId,
        CreatorClaimVerifyPoolStatus $terminalStatus,
        ?string $error = null,
    ): void {
        CreatorClaimVerifyPoolItem::query()
            ->where('claim_id', $claimId)
            ->whereIn('status', [
                CreatorClaimVerifyPoolStatus::Pending->value,
                CreatorClaimVerifyPoolStatus::Processing->value,
            ])
            ->update([
                'status' => $terminalStatus->value,
                'processed_at' => now(),
                'error' => $error !== null ? mb_substr($error, 0, 2000) : null,
            ]);
    }

    /**
     * @return Collection<int, CreatorClaimVerifyPoolItem>
     */
    private function claimPending(bool $force): Collection
    {
        $threshold = max(1, (int) setting_config('vtual.claim_verify_pool.threshold', 50));
        $maxWait = max(1, (int) setting_config('vtual.claim_verify_pool.max_wait_minutes', 15));
        $batchMax = max(1, min(
            $threshold,
            (int) setting_config('vtual.http.batch.channels', 50),
        ));

        return DB::transaction(function () use ($force, $threshold, $maxWait, $batchMax): Collection {
            /** @var Collection<int, CreatorClaimVerifyPoolItem> $pending */
            $pending = CreatorClaimVerifyPoolItem::query()
                ->where('status', CreatorClaimVerifyPoolStatus::Pending)
                ->with(['claim.channel.platform', 'claim.verifiedChannel.platform'])
                ->orderBy('requested_at')
                ->limit($batchMax)
                ->lockForUpdate()
                ->get();

            if ($pending->isEmpty()) {
                return $pending;
            }

            if (! $force) {
                $countAll = CreatorClaimVerifyPoolItem::query()
                    ->where('status', CreatorClaimVerifyPoolStatus::Pending)
                    ->count();

                $oldest = $pending->first()?->requested_at;
                $ageOk = $oldest !== null && $oldest->lte(now()->subMinutes($maxWait));
                $sizeOk = $countAll >= $threshold;

                if (! $sizeOk && ! $ageOk) {
                    return new Collection;
                }
            }

            $ids = $pending->pluck('id')->all();
            CreatorClaimVerifyPoolItem::query()
                ->whereIn('id', $ids)
                ->update(['status' => CreatorClaimVerifyPoolStatus::Processing->value]);

            return CreatorClaimVerifyPoolItem::query()
                ->whereIn('id', $ids)
                ->with(['claim.channel.platform', 'claim.verifiedChannel.platform'])
                ->get();
        });
    }

    /**
     * @param  Collection<int, CreatorClaimVerifyPoolItem>  $items
     */
    private function markPoolFailed(Collection $items, string $error): void
    {
        foreach ($items as $item) {
            $this->markPoolItemFailed($item, $error);
        }
    }

    private function markPoolItemFailed(CreatorClaimVerifyPoolItem $item, string $error): void
    {
        $item->forceFill([
            'status' => CreatorClaimVerifyPoolStatus::Failed,
            'processed_at' => now(),
            'error' => mb_substr($error, 0, 2000),
        ])->save();

        $item->loadMissing('claim.creator', 'claim.channel.platform', 'claim.user');
        $claim = $item->claim;
        if ($claim !== null) {
            app(VtualClaimRealtime::class)->progress(
                $claim->fresh(['creator', 'channel.platform', 'user']) ?? $claim,
                'verify_failed',
            );
        }
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
