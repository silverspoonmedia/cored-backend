<?php

namespace App\Domain\Service\Services;

use App\Domain\Service\Enums\BillingInterval;
use App\Domain\Service\Enums\EntitlementStatus;
use App\Domain\Service\Enums\PlanKind;
use App\Domain\Service\Models\ServiceEntitlement;
use App\Domain\Service\Models\ServicePurchase;
use App\Domain\Service\Support\FileHostingCatalog;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class ServiceEntitlementService
{
    /**
     * Activate or extend entitlement from a paid purchase.
     *
     * Addon: each paid purchase increments quantity while active/grace.
     * Main: quantity always 1.
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

        $quantity = $this->nextQuantity($entitlement, $service->isAddonPlan());

        if ($interval->isOneTime()) {
            $entitlement->fill([
                'status' => EntitlementStatus::Active,
                'quantity' => $quantity,
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
            'quantity' => $quantity,
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

    /**
     * Expire other paid main entitlements so only $keepServiceId remains active.
     */
    public function expireOtherPaidMains(string $userId, string $keepServiceId): int
    {
        $count = 0;

        $rows = ServiceEntitlement::query()
            ->where('user_id', $userId)
            ->where('service_id', '!=', $keepServiceId)
            ->whereIn('status', [EntitlementStatus::Active, EntitlementStatus::Grace])
            ->whereHas('service', function ($q): void {
                $q->where('fulfillment_target', FileHostingCatalog::FULFILLMENT_TARGET)
                    ->where('plan_kind', PlanKind::Main)
                    ->where('code', '!=', FileHostingCatalog::CODE_FREE);
            })
            ->get();

        foreach ($rows as $row) {
            $this->markExpired($row);
            $count++;
        }

        return $count;
    }

    /**
     * @return Collection<int, ServiceEntitlement>
     */
    public function activePaidFileHostingEntitlements(string $userId): Collection
    {
        return ServiceEntitlement::query()
            ->where('user_id', $userId)
            ->where('status', EntitlementStatus::Active)
            ->whereHas('service', function ($q): void {
                $q->where('fulfillment_target', FileHostingCatalog::FULFILLMENT_TARGET)
                    ->whereIn('plan_kind', [PlanKind::Main, PlanKind::Addon])
                    ->where('code', '!=', FileHostingCatalog::CODE_FREE)
                    ->where('storage_bytes', '>', 0);
            })
            ->with('service')
            ->get();
    }

    /**
     * @return Collection<int, ServiceEntitlement>
     */
    public function graceFileHostingEntitlements(string $userId): Collection
    {
        return ServiceEntitlement::query()
            ->where('user_id', $userId)
            ->where('status', EntitlementStatus::Grace)
            ->whereHas('service', function ($q): void {
                $q->where('fulfillment_target', FileHostingCatalog::FULFILLMENT_TARGET)
                    ->whereIn('plan_kind', [PlanKind::Main, PlanKind::Addon])
                    ->where('code', '!=', FileHostingCatalog::CODE_FREE)
                    ->where('storage_bytes', '>', 0);
            })
            ->with('service')
            ->get();
    }

    public function hasActivePaidFileHosting(string $userId): bool
    {
        return $this->activePaidFileHostingEntitlements($userId)->isNotEmpty();
    }

    public function hasGraceFileHosting(string $userId): bool
    {
        return $this->graceFileHostingEntitlements($userId)->isNotEmpty();
    }

    /** @deprecated Use hasGraceFileHosting */
    public function graceFileHostingFor(string $userId): ?ServiceEntitlement
    {
        return $this->graceFileHostingEntitlements($userId)->first();
    }

    /** @deprecated Unlimited removed; use hasActivePaidFileHosting */
    public function activeUnlimitedFileHostingFor(string $userId): ?ServiceEntitlement
    {
        return $this->activePaidFileHostingEntitlements($userId)->first();
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

    private function nextQuantity(ServiceEntitlement $entitlement, bool $isAddon): int
    {
        if (! $isAddon) {
            return 1;
        }

        if (! $entitlement->exists) {
            return 1;
        }

        $wasContributing = in_array(
            $entitlement->status,
            [EntitlementStatus::Active, EntitlementStatus::Grace],
            true,
        );

        if ($wasContributing) {
            return max(1, (int) $entitlement->quantity) + 1;
        }

        return 1;
    }
}
