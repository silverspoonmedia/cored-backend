<?php

namespace App\Domain\Vtual\Services;

use App\Domain\Vtual\Enums\ChannelStatus;
use App\Domain\Vtual\Enums\PlatformKey;
use App\Domain\Vtual\Exceptions\VtualException;
use App\Domain\Vtual\Integrations\Support\ChannelProfileResolver;
use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Models\Platform;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Batch-resolve pending channel registrations (staff orphan seed).
 * Flush when pending count >= threshold OR oldest pending age >= max_wait — per platform.
 */
class ChannelRegistrationFlushService
{
    public function __construct(
        private readonly ChannelProfileResolver $profiles,
        private readonly ChannelActivationService $activation,
    ) {}

    /**
     * @return array{
     *     platforms: array<string, array{status: string, claimed: int, applied: int, failed: int}>,
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
            if ($key === PlatformKey::Kick) {
                continue;
            }

            $summary['platforms'][$key->value] = $this->flushPlatform($platform, $key, $force);
        }

        return $summary;
    }

    /**
     * @return array{status: string, claimed: int, applied: int, failed: int}
     */
    private function flushPlatform(Platform $platform, PlatformKey $key, bool $force): array
    {
        $lockSeconds = max(5, (int) setting_config('vtual.channel_pool.flush_lock_seconds', 30));
        $lock = Cache::lock('vtual.channel_registration.flush.'.$key->value, $lockSeconds);

        if (! $lock->get()) {
            return [
                'status' => 'lock_busy',
                'claimed' => 0,
                'applied' => 0,
                'failed' => 0,
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
                ];
            }

            $applied = 0;
            $failed = 0;

            foreach ($claimed as $channel) {
                try {
                    $profile = trim((string) $channel->profile_input);
                    if ($profile === '') {
                        throw new VtualException('Missing profile input.');
                    }

                    $dto = $this->profiles->resolve($key, $profile);
                    $this->activation->activatePendingFromDto($channel, $platform, $dto);
                    $applied++;
                } catch (Throwable $e) {
                    $channel->forceFill([
                        'resolve_error' => mb_substr($e->getMessage(), 0, 2000),
                    ])->save();
                    $failed++;
                }
            }

            return [
                'status' => 'flushed',
                'claimed' => $claimed->count(),
                'applied' => $applied,
                'failed' => $failed,
            ];
        } finally {
            $lock->release();
        }
    }

    /**
     * @return Collection<int, Channel>
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
            /** @var Collection<int, Channel> $pending */
            $pending = Channel::query()
                ->where('platform_id', $platform->id)
                ->where('status', ChannelStatus::Pending)
                ->whereNotNull('profile_input')
                ->orderBy('created_at')
                ->limit($batchMax)
                ->lockForUpdate()
                ->get();

            if ($pending->isEmpty()) {
                return $pending;
            }

            if (! $force) {
                $countAll = Channel::query()
                    ->where('platform_id', $platform->id)
                    ->where('status', ChannelStatus::Pending)
                    ->whereNotNull('profile_input')
                    ->count();

                $oldest = $pending->first()?->created_at;
                $ageOk = $oldest !== null && $oldest->lte(now()->subMinutes($maxWait));
                $sizeOk = $countAll >= $threshold;

                if (! $sizeOk && ! $ageOk) {
                    return new Collection;
                }
            }

            return $pending;
        });
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
