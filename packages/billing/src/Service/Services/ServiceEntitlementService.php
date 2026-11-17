<?php

namespace App\Domain\Service\Services;

use App\Domain\Service\Enums\BillingInterval;
use App\Domain\Service\Enums\EntitlementStatus;
use App\Domain\Service\Enums\PlanKind;
use App\Domain\Service\Enums\PurchaseChangeType;
use App\Domain\Service\Models\Service;
use App\Domain\Service\Models\ServiceEntitlement;
use App\Domain\Service\Models\ServicePurchase;
use App\Support\Billing\BillingClock;
use App\Support\Billing\Fulfillment;
use App\Support\Billing\FulfillmentRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Entitlement ledger, generic across fulfillment targets.
 *
 * Nothing here knows a product: a "paid" probe is "Active/Grace, a Main or Addon
 * plan, storage-contributing, and not a code the target retired", and the target
 * is always a parameter. Product-specific reactions (quota recompute, tier
 * counters, balance credits) arrive through the kernel {@see FulfillmentRegistry}.
 */
class ServiceEntitlementService
{
    /**
     * @var array<string, bool>
     */
    private array $hasPaidMemo = [];

    /**
     * @var array<string, bool>
     */
    private array $hasGraceMemo = [];

    public function __construct(
        private readonly MainPlanChangeCalculator $planChange,
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

        // Capture the tier BEFORE the mutation: the observer transfers the user's
        // contribution from the old bucket, so a post-mutation read would be wrong.
        $target = $service->fulfillment_target;
        $oldTier = $this->tierFor($purchase->user_id, $target);

        $changeType = $purchase->change_type instanceof PurchaseChangeType
            ? $purchase->change_type
            : ($purchase->change_type
                ? PurchaseChangeType::tryFrom((string) $purchase->change_type)
                : null);

        $isAddon = $service->isAddonPlan();
        $quantity = $this->nextQuantity($entitlement, $isAddon, $purchase);

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

            $this->forgetProbeMemo($purchase->user_id);
            $this->syncTier($purchase->user_id, $target, $oldTier);

            return [
                'entitlement' => $entitlement,
                'period_start' => null,
                'period_end' => null,
            ];
        }

        if ($isAddon) {
            $result = $this->activateAddon($entitlement, $purchase, $interval, $now, $quantity);
            $this->forgetProbeMemo($purchase->user_id);
            $this->syncTier($purchase->user_id, $target, $oldTier);

            return $result;
        }

        $result = $this->activateMain($entitlement, $purchase, $interval, $now, $changeType);
        $this->forgetProbeMemo($purchase->user_id);
        $this->syncTier($purchase->user_id, $target, $oldTier);

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
     * Addons share one anniversary clock across targets — a storage addon and a
     * workspace-slot addon bought in the same month renew in one invoice.
     *
     * @return array{extended: int, period_end: ?Carbon, entitlements: Collection<int, ServiceEntitlement>}
     */
    public function extendAllAddons(string $userId): array
    {
        $rows = ServiceEntitlement::query()
            ->where('user_id', $userId)
            ->whereIn('status', [EntitlementStatus::Active, EntitlementStatus::Grace])
            ->whereHas('service', fn (Builder $q) => $q->where('plan_kind', PlanKind::Addon))
            ->with('service')
            ->get();

        if ($rows->isEmpty()) {
            return ['extended' => 0, 'period_end' => null, 'entitlements' => $rows];
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

        $periodEnd = $this->addPeriod(BillingInterval::Monthly, $base, $rows->first()->service);

        foreach ($rows as $row) {
            $row->status = EntitlementStatus::Active;
            $row->ends_at = $periodEnd->copy();
            $row->grace_ends_at = null;
            $row->grace_notified_at = null;
            $row->save();
        }

        $this->forgetProbeMemo($userId);

        return ['extended' => $rows->count(), 'period_end' => $periodEnd, 'entitlements' => $rows];
    }

    /**
     * Main plans are exclusive per target: activating one retires the others.
     */
    public function expireOtherPaidMains(string $userId, string $keepServiceId, ?string $target = null): int
    {
        $count = 0;

        $rows = ServiceEntitlement::query()
            ->where('user_id', $userId)
            ->where('service_id', '!=', $keepServiceId)
            ->whereIn('status', [EntitlementStatus::Active, EntitlementStatus::Grace])
            ->whereHas('service', function (Builder $q) use ($target): void {
                $q->where('plan_kind', PlanKind::Main)
                    ->where('storage_bytes', '>', 0);

                if ($target !== null) {
                    $q->where('fulfillment_target', $target);
                }

                $this->scopeExcludeRetiredCodes($q);
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
    public function activePaidFor(string $userId, ?string $target = null): Collection
    {
        return ServiceEntitlement::query()
            ->where('user_id', $userId)
            ->where('status', EntitlementStatus::Active)
            ->whereHas('service', function (Builder $q) use ($target): void {
                $this->scopePaidCatalog($q, $target);
            })
            ->with('service')
            ->get();
    }

    /**
     * @return Collection<int, ServiceEntitlement>
     */
    public function graceFor(string $userId, ?string $target = null): Collection
    {
        return ServiceEntitlement::query()
            ->where('user_id', $userId)
            ->where('status', EntitlementStatus::Grace)
            ->whereHas('service', function (Builder $q) use ($target): void {
                $this->scopePaidCatalog($q, $target);
            })
            ->with('service')
            ->get();
    }

    /**
     * @return Collection<int, ServiceEntitlement>
     */
    public function activeAddonEntitlements(string $userId, ?string $target = null): Collection
    {
        return ServiceEntitlement::query()
            ->where('user_id', $userId)
            ->whereIn('status', [EntitlementStatus::Active, EntitlementStatus::Grace])
            ->whereHas('service', function (Builder $q) use ($target): void {
                // Includes storage addons and slot SKUs (storage_bytes may be 0).
                $q->where('plan_kind', PlanKind::Addon);

                if ($target !== null) {
                    $q->where('fulfillment_target', $target);
                }
            })
            ->with('service')
            ->get();
    }

    /**
     * Active/grace quantity sum for the target's slot addons.
     *
     * The entitlement column is what distinguishes a slot addon from a storage
     * addon, so the caller passes the product's own entitlement name.
     */
    public function addonSlotQuantity(string $userId, string $target, string $entitlement, ?string $code = null): int
    {
        $rows = ServiceEntitlement::query()
            ->where('user_id', $userId)
            ->whereIn('status', [EntitlementStatus::Active, EntitlementStatus::Grace])
            ->whereHas('service', function (Builder $q) use ($target, $entitlement, $code): void {
                $q->where('fulfillment_target', $target)
                    ->where('plan_kind', PlanKind::Addon)
                    ->where(function (Builder $inner) use ($entitlement, $code): void {
                        $inner->where('entitlement', $entitlement);

                        if ($code !== null) {
                            $inner->orWhere('code', $code);
                        }
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
     * True when the user holds any active/grace entitlement with this entitlement
     * name for the target — the product's own activation probe.
     */
    public function hasSlotEntitlement(string $userId, string $target, string $entitlement): bool
    {
        return ServiceEntitlement::query()
            ->where('user_id', $userId)
            ->whereIn('status', [EntitlementStatus::Active, EntitlementStatus::Grace])
            ->whereHas('service', function (Builder $q) use ($target, $entitlement): void {
                $q->where('fulfillment_target', $target)
                    ->where('entitlement', $entitlement);
            })
            ->exists();
    }

    public function hasActivePaidFor(string $userId, ?string $target = null): bool
    {
        return $this->hasPaidMemo[$this->memoKey($userId, $target)]
            ??= $this->paidExists($userId, $target);
    }

    public function hasGraceFor(string $userId, ?string $target = null): bool
    {
        return $this->hasGraceMemo[$this->memoKey($userId, $target)]
            ??= $this->graceExists($userId, $target);
    }

    private function paidExists(string $userId, ?string $target): bool
    {
        return ServiceEntitlement::query()
            ->where('user_id', $userId)
            ->where('status', EntitlementStatus::Active)
            ->whereHas('service', function (Builder $q) use ($target): void {
                $this->scopePaidCatalog($q, $target);
            })
            ->exists();
    }

    private function graceExists(string $userId, ?string $target): bool
    {
        return ServiceEntitlement::query()
            ->where('user_id', $userId)
            ->where('status', EntitlementStatus::Grace)
            ->whereHas('service', function (Builder $q) use ($target): void {
                $this->scopePaidCatalog($q, $target);
            })
            ->exists();
    }

    /**
     * "Paid" means: a Main/Addon plan that contributes storage and is not a code
     * the target retired. A slot addon is not paid (storage_bytes = 0) — slots are
     * a feature of a paid plan, never a substitute for one.
     */
    private function scopePaidCatalog(Builder $q, ?string $target): void
    {
        $q->whereIn('plan_kind', [PlanKind::Main, PlanKind::Addon])
            ->where('storage_bytes', '>', 0);

        if ($target !== null) {
            $q->where('fulfillment_target', $target);
        }

        $this->scopeExcludeRetiredCodes($q);
    }

    private function scopeExcludeRetiredCodes(Builder $q): void
    {
        $codes = Fulfillment::registry()->freeCodes();

        if ($codes !== []) {
            $q->whereNotIn('code', $codes);
        }
    }

    private function memoKey(string $userId, ?string $target): string
    {
        return ($target ?? '*').':'.$userId;
    }

    private function forgetProbeMemo(string $userId): void
    {
        foreach (array_keys($this->hasPaidMemo) as $key) {
            if (str_ends_with($key, ':'.$userId)) {
                unset($this->hasPaidMemo[$key]);
            }
        }

        foreach (array_keys($this->hasGraceMemo) as $key) {
            if (str_ends_with($key, ':'.$userId)) {
                unset($this->hasGraceMemo[$key]);
            }
        }

        // Product-side request memos (quota state) are cleared by their handlers.
        Fulfillment::entitlementsMutated($userId);
    }

    /**
     * Public invalidation for flows that mutate entitlements outside the
     * purchase/fulfillment pipeline (e.g. test fixtures, manual seeding).
     */
    public function forgetStorageState(string $userId): void
    {
        $this->forgetProbeMemo($userId);
    }

    public function enterGrace(ServiceEntitlement $entitlement): ServiceEntitlement
    {
        $entitlement->loadMissing('service');
        $target = $entitlement->service?->fulfillment_target;
        $oldTier = $this->tierFor($entitlement->user_id, $target);

        $graceDays = max(1, (int) setting_config('service.grace_days', 7));
        $endsAt = $entitlement->ends_at ?? now();

        $entitlement->status = EntitlementStatus::Grace;
        $entitlement->grace_ends_at = $endsAt->copy()->addDays($graceDays);
        $entitlement->save();

        $this->forgetProbeMemo($entitlement->user_id);
        $this->syncTier($entitlement->user_id, $target, $oldTier);

        return $entitlement->refresh();
    }

    public function markExpired(ServiceEntitlement $entitlement): ServiceEntitlement
    {
        $entitlement->loadMissing('service');
        $target = $entitlement->service?->fulfillment_target;
        $oldTier = $this->tierFor($entitlement->user_id, $target);

        $entitlement->status = EntitlementStatus::Expired;
        $entitlement->save();

        $this->forgetProbeMemo($entitlement->user_id);
        $this->syncTier($entitlement->user_id, $target, $oldTier);

        return $entitlement->refresh();
    }

    /**
     * The user's current tier for a target, derived from the generic probes.
     *
     * @return 'paid'|'grace'|'free'
     */
    public function tierFor(string $userId, ?string $target): string
    {
        if ($this->hasGraceFor($userId, $target)) {
            return 'grace';
        }

        if ($this->hasActivePaidFor($userId, $target)) {
            return 'paid';
        }

        return 'free';
    }

    /**
     * Hand the before/after tier to the target's observer. No observer, or an
     * unchanged tier, is a no-op — a host without that product records nothing.
     */
    private function syncTier(string $userId, ?string $target, string $oldTier): void
    {
        if ($target === null) {
            return;
        }

        Fulfillment::registry()->tierChanged(
            $target,
            $userId,
            $oldTier,
            $this->tierFor($userId, $target),
        );
    }

    private function nextQuantity(ServiceEntitlement $entitlement, bool $isAddon, ServicePurchase $purchase): int
    {
        if (! $isAddon) {
            return 1;
        }

        $purchaseQty = max(1, (int) $purchase->quantity);

        if (! $entitlement->exists) {
            return $purchaseQty;
        }

        $wasContributing = in_array(
            $entitlement->status,
            [EntitlementStatus::Active, EntitlementStatus::Grace],
            true,
        )
            && $entitlement->ends_at !== null
            && $entitlement->ends_at->greaterThan(now());

        if ($wasContributing) {
            return max(1, (int) $entitlement->quantity) + $purchaseQty;
        }

        return $purchaseQty;
    }

    /**
     * Fixed-day targets (storage plans) bill the same period the credit math
     * uses; everything else keeps the catalog's calendar interval.
     */
    private function addPeriod(BillingInterval $interval, Carbon $from, ?Service $service): Carbon
    {
        $next = BillingClock::addPeriod($service?->fulfillment_target, $from, $interval);

        return $next instanceof Carbon ? $next : Carbon::instance($next);
    }
}
