<?php

namespace App\Domain\Service\Services;

use App\Domain\Service\Enums\BillingInterval;
use App\Domain\Service\Enums\EntitlementStatus;
use App\Domain\Service\Enums\PlanKind;
use App\Domain\Service\Enums\PurchaseChangeType;
use App\Domain\Service\Models\Service;
use App\Domain\Service\Models\ServiceEntitlement;
use App\Domain\Service\Models\ServicePurchase;
use App\Domain\Service\Support\FileHostingBillingPeriod;
use App\Domain\Service\Support\FileHostingCatalog;
use App\Domain\Service\Support\MicrostockCatalog;
use App\Support\Product\Services\ProductAnalyticsService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class ServiceEntitlementService
{
    public function __construct(
        private readonly MainPlanChangeCalculator $planChange,
        private readonly ProductAnalyticsService $analytics,
    ) {}

    /**
     * Activate or extend entitlement from a paid purchase.
     *
     * Main renew: stack period from remaining ends_at.
     * Main up/down: now + period + surplus_days.
     * Addon unit: quantity++; ends_at fixed (shared anniversary).
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

        // Capture the user's File Hosting tier BEFORE the entitlement mutation —
        // needed by moveUserTier to transfer the contribution between buckets.
        // Only relevant when this purchase is a paid FH storage plan; otherwise
        // the value is unused.
        $oldTier = $this->fileHostingTierForUser($purchase->user_id);

        $changeType = $purchase->change_type instanceof PurchaseChangeType
            ? $purchase->change_type
            : ($purchase->change_type
                ? PurchaseChangeType::tryFrom((string) $purchase->change_type)
                : null);

        $isAddon = $service->isAddonPlan();
        $quantity = $this->nextQuantity($entitlement, $isAddon);

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

            $this->syncFileHostingTier($purchase->user_id, $service, $oldTier);

            return [
                'entitlement' => $entitlement,
                'period_start' => null,
                'period_end' => null,
            ];
        }

        if ($isAddon) {
            $result = $this->activateAddon($entitlement, $purchase, $interval, $now, $quantity);
            $this->syncFileHostingTier($purchase->user_id, $service, $oldTier);

            return $result;
        }

        $result = $this->activateMain($entitlement, $purchase, $interval, $now, $changeType);
        $this->syncFileHostingTier($purchase->user_id, $service, $oldTier);

        return $result;
    }

    /**
     * @return array{entitlement: ServiceEntitlement, period_start: Carbon, period_end: Carbon}
     */
    private function activateAddon(
        ServiceEntitlement $entitlement,
        ServicePurchase $purchase,
        BillingInterval $interval,
        Carbon $now,
        int $quantity,
    ): array {
        $wasContributing = $entitlement->exists
            && in_array($entitlement->status, [EntitlementStatus::Active, EntitlementStatus::Grace], true)
            && $entitlement->ends_at !== null
            && $entitlement->ends_at->greaterThan($now);

        if ($wasContributing) {
            // quantity++ only; anniversary clock frozen
            $periodEnd = $entitlement->ends_at->copy();
            $periodStart = $now->copy();
        } else {
            $shared = $this->planChange->sharedAddonEndsAt($purchase->user_id);
            if ($shared !== null && $shared->greaterThan($now)) {
                $periodEnd = $shared->copy();
            } else {
                $periodEnd = $this->addPeriod($interval, $now, $purchase->service);
            }
            $periodStart = $now->copy();
        }

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
     * @return array{entitlement: ServiceEntitlement, period_start: Carbon, period_end: Carbon}
     */
    private function activateMain(
        ServiceEntitlement $entitlement,
        ServicePurchase $purchase,
        BillingInterval $interval,
        Carbon $now,
        ?PurchaseChangeType $changeType,
    ): array {
        $surplusDays = max(0, (int) ($purchase->surplus_days ?? 0));
        $isSwitch = $changeType?->isMainSwitch() ?? false;
        $service = $purchase->service;

        if ($isSwitch) {
            $periodStart = $now->copy();
            $periodEnd = $this->addPeriod($interval, $now, $service)->addDays($surplusDays);
        } else {
            // renew or new: stack remaining time when same SKU active/grace
            $base = $now;
            if ($entitlement->exists
                && $entitlement->ends_at !== null
                && $entitlement->ends_at->greaterThan($now)
                && in_array($entitlement->status, [EntitlementStatus::Active, EntitlementStatus::Grace], true)
            ) {
                $base = $entitlement->ends_at;
            }
            $periodStart = $base->copy();
            $periodEnd = $this->addPeriod($interval, $base, $service);
            if ($surplusDays > 0) {
                $periodEnd = $periodEnd->addDays($surplusDays);
            }
        }

        $entitlement->fill([
            'status' => EntitlementStatus::Active,
            'quantity' => 1,
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
     * Extend all active/grace addon entitlements by one billing period (stack renewal).
     *
     * @return array{extended: int, period_end: ?Carbon}
     */
    public function extendAllAddons(string $userId): array
    {
        $rows = ServiceEntitlement::query()
            ->where('user_id', $userId)
            ->whereIn('status', [EntitlementStatus::Active, EntitlementStatus::Grace])
            ->whereHas('service', function ($q): void {
                // Storage + workspace-slot addons (FH and Microstock) share the
                // stack anniversary.
                $q->whereIn('fulfillment_target', [
                    FileHostingCatalog::FULFILLMENT_TARGET,
                    MicrostockCatalog::FULFILLMENT_TARGET,
                ])->where('plan_kind', PlanKind::Addon);
            })
            ->with('service')
            ->get();

        if ($rows->isEmpty()) {
            return ['extended' => 0, 'period_end' => null];
        }

        $sharedEnd = null;
        foreach ($rows as $row) {
            if ($row->ends_at === null) {
                continue;
            }
            if ($sharedEnd === null || $row->ends_at->lessThan($sharedEnd)) {
                $sharedEnd = $row->ends_at->copy();
            }
        }

        $base = $sharedEnd !== null && $sharedEnd->greaterThan(now())
            ? $sharedEnd
            : now();

        $periodEnd = FileHostingBillingPeriod::add($base);
        $count = 0;

        foreach ($rows as $row) {
            $row->status = EntitlementStatus::Active;
            $row->ends_at = $periodEnd->copy();
            $row->grace_ends_at = null;
            $row->grace_notified_at = null;
            $row->save();
            $count++;
        }

        return ['extended' => $count, 'period_end' => $periodEnd];
    }

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

    /**
     * @return Collection<int, ServiceEntitlement>
     */
    public function activeAddonEntitlements(string $userId): Collection
    {
        return ServiceEntitlement::query()
            ->where('user_id', $userId)
            ->whereIn('status', [EntitlementStatus::Active, EntitlementStatus::Grace])
            ->whereHas('service', function ($q): void {
                // Includes storage addons and workspace-slot SKUs (storage_bytes may be 0).
                $q->where('fulfillment_target', FileHostingCatalog::FULFILLMENT_TARGET)
                    ->where('plan_kind', PlanKind::Addon);
            })
            ->with('service')
            ->get();
    }

    /**
     * Active/grace workspace-slot addon quantity sum (extra owned team slots).
     */
    public function workspaceSlotExtraQuantity(string $userId): int
    {
        $rows = ServiceEntitlement::query()
            ->where('user_id', $userId)
            ->whereIn('status', [EntitlementStatus::Active, EntitlementStatus::Grace])
            ->whereHas('service', function ($q): void {
                $q->where('fulfillment_target', FileHostingCatalog::FULFILLMENT_TARGET)
                    ->where('plan_kind', PlanKind::Addon)
                    ->where(function ($inner): void {
                        $inner->where('code', FileHostingCatalog::CODE_ADDON_WORKSPACE_SLOT)
                            ->orWhere('entitlement', FileHostingCatalog::ENTITLEMENT_WORKSPACE_SLOTS);
                    });
            })
            ->get();

        $total = 0;
        foreach ($rows as $row) {
            $total += max(0, (int) $row->quantity);
        }

        return $total;
    }

    /**
     * Active/grace Microstock workspace-slot addon quantity sum (extra slots).
     * Standalone from FH team workspace slots (MicrostockCatalog).
     */
    public function microstockWorkspaceSlotExtraQuantity(string $userId): int
    {
        $rows = ServiceEntitlement::query()
            ->where('user_id', $userId)
            ->whereIn('status', [EntitlementStatus::Active, EntitlementStatus::Grace])
            ->whereHas('service', function ($q): void {
                $q->where('fulfillment_target', MicrostockCatalog::FULFILLMENT_TARGET)
                    ->where('plan_kind', PlanKind::Addon)
                    ->where(function ($inner): void {
                        $inner->where('code', MicrostockCatalog::CODE_ADDON_WORKSPACE_SLOT)
                            ->orWhere('entitlement', MicrostockCatalog::ENTITLEMENT_WORKSPACE_SLOTS);
                    });
            })
            ->get();

        $total = 0;
        foreach ($rows as $row) {
            $total += max(0, (int) $row->quantity);
        }

        return $total;
    }

    /**
     * True when the user holds any active/grace Microstock slot entitlement
     * (free registration main OR slot addon). Gates Microstock activation.
     */
    public function hasActiveMicrostockWorkspaceSlot(string $userId): bool
    {
        return ServiceEntitlement::query()
            ->where('user_id', $userId)
            ->whereIn('status', [EntitlementStatus::Active, EntitlementStatus::Grace])
            ->whereHas('service', function ($q): void {
                $q->where('fulfillment_target', MicrostockCatalog::FULFILLMENT_TARGET)
                    ->where('entitlement', MicrostockCatalog::ENTITLEMENT_WORKSPACE_SLOTS);
            })
            ->exists();
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
        $service = $entitlement->service;
        $oldTier = $service !== null && $service->isFileHostingStoragePlan()
            ? $this->fileHostingTierForUser($entitlement->user_id)
            : 'free';

        $graceDays = max(1, (int) config('service.grace_days', 7));
        $endsAt = $entitlement->ends_at ?? now();

        $entitlement->status = EntitlementStatus::Grace;
        $entitlement->grace_ends_at = $endsAt->copy()->addDays($graceDays);
        $entitlement->save();

        $this->syncFileHostingTierFromEntitlement($entitlement, 'grace', $oldTier);

        return $entitlement->refresh();
    }

    public function markExpired(ServiceEntitlement $entitlement): ServiceEntitlement
    {
        $service = $entitlement->service;
        $oldTier = $service !== null && $service->isFileHostingStoragePlan()
            ? $this->fileHostingTierForUser($entitlement->user_id)
            : 'free';

        $entitlement->status = EntitlementStatus::Expired;
        $entitlement->save();

        $this->syncFileHostingTierAfterExpiry($entitlement, $oldTier);

        return $entitlement->refresh();
    }

    /**
     * Resolve the user's current File Hosting tier. Used to capture the
     * "before" tier for moveUserTier on entitlement mutations.
     */
    private function fileHostingTierForUser(string $userId): string
    {
        if ($this->hasGraceFileHosting($userId)) {
            return 'grace';
        }

        if ($this->hasActivePaidFileHosting($userId)) {
            return 'paid';
        }

        return 'free';
    }

    /**
     * Move the user's FileHosting contribution to the paid bucket after an
     * entitlement activation. Only fires for paid FH storage plans.
     */
    private function syncFileHostingTier(string $userId, ?Service $service, string $oldTier): void
    {
        if ($service === null || ! $service->isFileHostingStoragePlan()) {
            return;
        }

        $this->analytics->moveUserTier('FileHosting', $userId, $oldTier, 'paid');
    }

    /**
     * Move the user's FileHosting contribution to the given tier (grace).
     */
    private function syncFileHostingTierFromEntitlement(ServiceEntitlement $entitlement, string $tier, string $oldTier): void
    {
        $service = $entitlement->service;
        if ($service === null || ! $service->isFileHostingStoragePlan()) {
            return;
        }

        $this->analytics->moveUserTier('FileHosting', $entitlement->user_id, $oldTier, $tier);
    }

    /**
     * After expiry, resolve the new tier: if the user still has any active or
     * grace paid FH storage entitlement, keep that label; otherwise fall to free.
     */
    private function syncFileHostingTierAfterExpiry(ServiceEntitlement $entitlement, string $oldTier): void
    {
        $service = $entitlement->service;
        if ($service === null || ! $service->isFileHostingStoragePlan()) {
            return;
        }

        if ($this->hasGraceFileHosting($entitlement->user_id)) {
            $this->analytics->moveUserTier('FileHosting', $entitlement->user_id, $oldTier, 'grace');

            return;
        }

        if ($this->hasActivePaidFileHosting($entitlement->user_id)) {
            $this->analytics->moveUserTier('FileHosting', $entitlement->user_id, $oldTier, 'paid');

            return;
        }

        $this->analytics->moveUserTier('FileHosting', $entitlement->user_id, $oldTier, 'free');
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
        )
            && $entitlement->ends_at !== null
            && $entitlement->ends_at->greaterThan(now());

        if ($wasContributing) {
            return max(1, (int) $entitlement->quantity) + 1;
        }

        return 1;
    }

    /**
     * FH storage plans use fixed 30-day periods (aligned with credit math).
     * Other services keep calendar interval via {@see BillingInterval::addPeriod}.
     */
    private function addPeriod(BillingInterval $interval, Carbon $from, ?Service $service): Carbon
    {
        if ($service?->isFileHostingStoragePlan()) {
            return FileHostingBillingPeriod::add($from);
        }

        return $interval->addPeriod($from);
    }
}
