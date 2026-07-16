<?php

namespace App\Domain\Service\Services;

use App\Domain\Service\Enums\BillingInterval;
use App\Domain\Service\Enums\EntitlementStatus;
use App\Domain\Service\Models\Service;
use App\Domain\Service\Models\ServiceEntitlement;
use App\Domain\Service\Models\ServicePurchase;
use Illuminate\Support\Carbon;

class ServiceEntitlementService
{
    /**
     * Activate or extend entitlement from a paid purchase.
     *
     * @return array{entitlement: ServiceEntitlement, period_start: ?Carbon, period_end: ?Carbon}
     */
    public function activateFromPurchase(ServicePurchase $purchase): array
    {
        $purchase->loadMissing('service');
        $service = $purchase->service;

        if ($service === null) {
            throw new \RuntimeException('Purchase has no service.');
        }

        $interval = $service->billing_interval instanceof BillingInterval
            ? $service->billing_interval
            : BillingInterval::from((string) $service->billing_interval);

        $now = now();
        $entitlement = ServiceEntitlement::query()->firstOrNew([
            'user_id' => $purchase->user_id,
            'service_id' => $service->id,
        ]);

        if ($interval->isOneTime()) {
            $entitlement->fill([
                'status' => EntitlementStatus::Active,
                'starts_at' => $entitlement->starts_at ?? $now,
                'ends_at' => null,
                'grace_ends_at' => null,
                'grace_notified_at' => null,
                'source_purchase_id' => $purchase->id,
            ]);
            $entitlement->save();

            return [
                'entitlement' => $entitlement,
                'period_start' => null,
                'period_end' => null,
            ];
        }

        $base = $now;
        if ($entitlement->exists
            && $entitlement->ends_at !== null
            && $entitlement->ends_at->greaterThan($now)
            && in_array($entitlement->status, [EntitlementStatus::Active, EntitlementStatus::Grace], true)
        ) {
            $base = $entitlement->ends_at;
        }

        $periodStart = $base->copy();
        $periodEnd = $interval->addPeriod($base);

        $entitlement->fill([
            'status' => EntitlementStatus::Active,
            'starts_at' => $entitlement->starts_at ?? $now,
            'ends_at' => $periodEnd,
            'grace_ends_at' => null,
            'grace_notified_at' => null,
            'source_purchase_id' => $purchase->id,
        ]);
        $entitlement->save();

        return [
            'entitlement' => $entitlement,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
        ];
    }

    public function activeUnlimitedFileHostingFor(string $userId): ?ServiceEntitlement
    {
        return ServiceEntitlement::query()
            ->where('user_id', $userId)
            ->where('status', EntitlementStatus::Active)
            ->whereHas('service', function ($q): void {
                $q->where('fulfillment_target', 'file_hosting')
                    ->where('entitlement', 'unlimited_quota');
            })
            ->orderByDesc('ends_at')
            ->first();
    }

    public function graceFileHostingFor(string $userId): ?ServiceEntitlement
    {
        return ServiceEntitlement::query()
            ->where('user_id', $userId)
            ->where('status', EntitlementStatus::Grace)
            ->whereHas('service', function ($q): void {
                $q->where('fulfillment_target', 'file_hosting')
                    ->where('entitlement', 'unlimited_quota');
            })
            ->orderByDesc('grace_ends_at')
            ->first();
    }

    public function enterGrace(ServiceEntitlement $entitlement): ServiceEntitlement
    {
        $graceDays = max(1, (int) config('service.grace_days', 7));
        $endsAt = $entitlement->ends_at ?? now();

        $entitlement->status = EntitlementStatus::Grace;
        $entitlement->grace_ends_at = $endsAt->copy()->addDays($graceDays);
        $entitlement->save();

        return $entitlement->refresh();
    }

    public function markExpired(ServiceEntitlement $entitlement): ServiceEntitlement
    {
        $entitlement->status = EntitlementStatus::Expired;
        $entitlement->save();

        return $entitlement->refresh();
    }
}
