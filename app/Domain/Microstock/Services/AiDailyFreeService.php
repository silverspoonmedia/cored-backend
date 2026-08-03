<?php

namespace App\Domain\Microstock\Services;

use App\Domain\Microstock\Models\DailyFreeUsage;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class AiDailyFreeService
{
    public function isEnabled(): bool
    {
        return (bool) setting_config('microstock.daily_free.enabled', false);
    }

    public function limit(): int
    {
        return max(0, (int) setting_config('microstock.daily_free.limit_per_user', 0));
    }

    public function today(): CarbonImmutable
    {
        $tz = (string) setting_config('microstock.daily_free.timezone', 'UTC');

        return CarbonImmutable::now($tz)->startOfDay();
    }

    public function resetsAt(): CarbonImmutable
    {
        return $this->today()->addDay();
    }

    public function remaining(string $userId): int
    {
        if (! $this->isEnabled()) {
            return 0;
        }

        $limit = $this->limit();
        if ($limit === 0) {
            return 0;
        }

        return max(0, $limit - $this->usedToday($userId));
    }

    /**
     * @return array{enabled: bool, limit: int, used: int, remaining: int, resets_at: string|null}
     */
    public function snapshot(string $userId): array
    {
        if (! $this->isEnabled()) {
            return [
                'enabled' => false,
                'limit' => 0,
                'used' => 0,
                'remaining' => 0,
                'resets_at' => null,
            ];
        }

        $limit = $this->limit();
        $used = $this->usedToday($userId);

        return [
            'enabled' => true,
            'limit' => $limit,
            'used' => $used,
            'remaining' => max(0, $limit - $used),
            'resets_at' => $this->resetsAt()->toIso8601String(),
        ];
    }

    /**
     * Allocate daily-first slots (transactional).
     *
     * @return array{daily: int, prepaid: int}
     */
    public function allocateInTransaction(string $userId, int $assetCount): array
    {
        return DB::transaction(function () use ($userId, $assetCount): array {
            if ($assetCount <= 0) {
                return ['daily' => 0, 'prepaid' => 0];
            }

            if (! $this->isEnabled()) {
                return ['daily' => 0, 'prepaid' => $assetCount];
            }

            $limit = $this->limit();
            if ($limit === 0) {
                return ['daily' => 0, 'prepaid' => $assetCount];
            }

            $todayStr = $this->today()->toDateString();
            $row = DailyFreeUsage::query()
                ->where('user_id', $userId)
                ->whereDate('usage_date', $todayStr)
                ->lockForUpdate()
                ->first();

            $used = $row ? (int) $row->analyses_used : 0;
            $daily = min(max(0, $limit - $used), $assetCount);

            if ($daily > 0) {
                if ($row === null) {
                    DailyFreeUsage::query()->create([
                        'user_id' => $userId,
                        'usage_date' => $todayStr,
                        'analyses_used' => $daily,
                    ]);
                } else {
                    $row->forceFill(['analyses_used' => $used + $daily])->save();
                }
            }

            return [
                'daily' => $daily,
                'prepaid' => $assetCount - $daily,
            ];
        });
    }

    private function usedToday(string $userId): int
    {
        $row = DailyFreeUsage::query()
            ->where('user_id', $userId)
            ->whereDate('usage_date', $this->today()->toDateString())
            ->first();

        return $row ? (int) $row->analyses_used : 0;
    }
}
