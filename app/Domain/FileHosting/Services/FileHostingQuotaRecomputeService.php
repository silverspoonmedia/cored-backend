<?php

namespace App\Domain\FileHosting\Services;

use App\Domain\FileHosting\Models\UserQuota;
use App\Domain\Service\Enums\EntitlementStatus;
use App\Domain\Service\Enums\PlanKind;
use App\Domain\Service\Models\ServiceEntitlement;

/**
 * Recompute denorm UserQuota from free tier + active/grace main + addon×quantity.
 */
class FileHostingQuotaRecomputeService
{
    /**
     * @return array{quota_bytes: int, main_bytes: int, addon_bytes: int, free_bytes: int}
     */
    public function recompute(string $userId): array
    {
        $free = (int) config('file-hosting.default_quota_bytes', 5 * 1024 * 1024 * 1024);
        $mainBytes = 0;
        $addonBytes = 0;

        $rows = ServiceEntitlement::query()
            ->where('user_id', $userId)
            ->whereIn('status', [EntitlementStatus::Active, EntitlementStatus::Grace])
            ->with('service')
            ->get();

        foreach ($rows as $entitlement) {
            $service = $entitlement->service;

            if ($service === null || ! $service->isFileHostingStoragePlan()) {
                continue;
            }

            $contribution = $entitlement->storageContributionBytes();

            if ($service->plan_kind === PlanKind::Main) {
                // At most one main should be active; take max if data ever drifts.
                $mainBytes = max($mainBytes, $contribution);
            } elseif ($service->plan_kind === PlanKind::Addon) {
                $addonBytes += $contribution;
            }
        }

        $total = $free + $mainBytes + $addonBytes;

        UserQuota::query()->updateOrCreate(
            ['user_id' => $userId],
            [
                'quota_bytes' => $total,
                'source' => 'service_recompute',
                'source_id' => null,
            ],
        );

        return [
            'quota_bytes' => $total,
            'main_bytes' => $mainBytes,
            'addon_bytes' => $addonBytes,
            'free_bytes' => $free,
        ];
    }

    public function computeWithoutPersist(string $userId): int
    {
        return $this->recompute($userId)['quota_bytes'];
    }
}
