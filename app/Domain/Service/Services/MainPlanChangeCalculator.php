<?php

namespace App\Domain\Service\Services;

use App\Domain\Service\Enums\EntitlementStatus;
use App\Domain\Service\Enums\PlanKind;
use App\Domain\Service\Enums\PurchaseChangeType;
use App\Domain\Service\Enums\PurchaseStatus;
use App\Domain\Service\Models\Service;
use App\Domain\Service\Models\ServiceEntitlement;
use App\Domain\Service\Models\ServicePurchase;
use App\Domain\Service\Support\FileHostingBillingPeriod;
use App\Domain\Service\Support\FileHostingCatalog;
use App\Domain\Service\Support\PlanChangeQuote;
use Illuminate\Support\Carbon;

/**
 * Classify main renew/upgrade/downgrade and compute unused-gap credit.
 *
 * Credit base = last paid **list** price of the current plan period (not net charge).
 * Period length fixed at {@see FileHostingBillingPeriod::DAYS} days.
 */
class MainPlanChangeCalculator
{
    public const PERIOD_DAYS = FileHostingBillingPeriod::DAYS;

    public function quote(Service $target, string $userId, float $listAmount): PlanChangeQuote
    {
        if ($target->isAddonPlan()) {
            return PlanChangeQuote::fullPrice(PurchaseChangeType::AddonUnit, $listAmount);
        }

        if (! $target->isPaidMainPlan()) {
            return PlanChangeQuote::fullPrice(PurchaseChangeType::New, $listAmount);
        }

        $current = $this->currentPaidMain($userId);

        if ($current === null) {
            return PlanChangeQuote::fullPrice(PurchaseChangeType::New, $listAmount);
        }

        if ($current->service_id === $target->id) {
            return PlanChangeQuote::fullPrice(PurchaseChangeType::Renew, $listAmount, $current);
        }

        $currentStorage = (int) ($current->service?->storage_bytes ?? 0);
        $targetStorage = (int) $target->storage_bytes;
        $changeType = $targetStorage > $currentStorage
            ? PurchaseChangeType::Upgrade
            : PurchaseChangeType::Downgrade;

        $unusedCredit = $this->unusedCredit($current);
        $net = round($listAmount - $unusedCredit, 2);
        $charge = max(0.0, $net);
        $surplus = max(0.0, $unusedCredit - $listAmount);
        $surplusDays = 0;

        if ($listAmount > 0 && $surplus > 0) {
            $surplusDays = (int) floor(($surplus / $listAmount) * self::PERIOD_DAYS);
        }

        return new PlanChangeQuote(
            changeType: $changeType,
            listAmount: $listAmount,
            creditAmount: round($unusedCredit, 2),
            chargeAmount: $charge,
            surplusDays: $surplusDays,
            previousEntitlementId: $current->id,
            periodDays: self::PERIOD_DAYS,
        );
    }

    public function currentPaidMain(string $userId): ?ServiceEntitlement
    {
        return ServiceEntitlement::query()
            ->where('user_id', $userId)
            ->whereIn('status', [EntitlementStatus::Active, EntitlementStatus::Grace])
            ->whereHas('service', function ($q): void {
                $q->where('fulfillment_target', FileHostingCatalog::FULFILLMENT_TARGET)
                    ->where('plan_kind', PlanKind::Main)
                    ->where('code', '!=', FileHostingCatalog::CODE_FREE)
                    ->where('storage_bytes', '>', 0);
            })
            ->with('service')
            ->orderByDesc('ends_at')
            ->first();
    }

    /**
     * Shared anniversary end among active/grace addons (earliest ends_at).
     */
    public function sharedAddonEndsAt(string $userId): ?Carbon
    {
        $ends = ServiceEntitlement::query()
            ->where('user_id', $userId)
            ->whereIn('status', [EntitlementStatus::Active, EntitlementStatus::Grace])
            ->whereNotNull('ends_at')
            ->whereHas('service', function ($q): void {
                $q->where('fulfillment_target', FileHostingCatalog::FULFILLMENT_TARGET)
                    ->where('plan_kind', PlanKind::Addon)
                    ->where('storage_bytes', '>', 0);
            })
            ->orderBy('ends_at')
            ->value('ends_at');

        return $ends !== null ? Carbon::parse($ends) : null;
    }

    /**
     * True when any active/grace FH addon is past ends_at (stack renewal required).
     */
    public function hasDueAddonAnniversary(string $userId): bool
    {
        return ServiceEntitlement::query()
            ->where('user_id', $userId)
            ->whereIn('status', [EntitlementStatus::Active, EntitlementStatus::Grace])
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', now())
            ->whereHas('service', function ($q): void {
                $q->where('fulfillment_target', FileHostingCatalog::FULFILLMENT_TARGET)
                    ->where('plan_kind', PlanKind::Addon)
                    ->where('storage_bytes', '>', 0);
            })
            ->exists();
    }

    private function unusedCredit(ServiceEntitlement $current): float
    {
        if ($current->ends_at === null) {
            return 0.0;
        }

        $now = now();
        if ($current->ends_at->lessThanOrEqualTo($now)) {
            return 0.0;
        }

        $remainingDays = max(0.0, $now->floatDiffInDays($current->ends_at));
        $periodDays = (float) self::PERIOD_DAYS;
        $periodValue = $this->lastPaidPeriodValue($current);

        if ($periodValue <= 0) {
            return 0.0;
        }

        return ($remainingDays / $periodDays) * $periodValue;
    }

    /**
     * Period value for unused-gap credit: catalog list of last paid period, never net charge.
     */
    private function lastPaidPeriodValue(ServiceEntitlement $entitlement): float
    {
        if ($entitlement->source_purchase_id !== null) {
            $fromSource = $this->periodValueFromPurchaseId($entitlement->source_purchase_id);
            if ($fromSource > 0) {
                return $fromSource;
            }
        }

        $latest = ServicePurchase::query()
            ->where('user_id', $entitlement->user_id)
            ->where('service_id', $entitlement->service_id)
            ->where('status', PurchaseStatus::Paid)
            ->orderByDesc('created_at')
            ->first(['list_amount', 'display_amount']);

        if ($latest !== null) {
            $value = $this->periodValueFromRow($latest);
            if ($value > 0) {
                return $value;
            }
        }

        // Last resort: catalog price of current main service (covers $0 switch with missing list).
        $entitlement->loadMissing('service.prices');
        $price = $entitlement->service?->priceFor('USD');

        return $price !== null ? (float) $price->amount : 0.0;
    }

    private function periodValueFromPurchaseId(string $purchaseId): float
    {
        $row = ServicePurchase::query()
            ->whereKey($purchaseId)
            ->where('status', PurchaseStatus::Paid)
            ->first(['list_amount', 'display_amount']);

        return $row !== null ? $this->periodValueFromRow($row) : 0.0;
    }

    private function periodValueFromRow(ServicePurchase $purchase): float
    {
        if ($purchase->list_amount !== null && (float) $purchase->list_amount > 0) {
            return (float) $purchase->list_amount;
        }

        // Legacy rows without list_amount only.
        if ($purchase->display_amount !== null && (float) $purchase->display_amount > 0) {
            return (float) $purchase->display_amount;
        }

        return 0.0;
    }
}
