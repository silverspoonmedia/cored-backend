<?php

namespace App\Domain\Service\Services;

use App\Domain\FileHosting\Services\FileHostingQuotaRecomputeService;
use App\Domain\Service\Enums\PurchaseStatus;
use App\Domain\Service\Models\ServicePurchase;
use App\Domain\Service\Support\FileHostingCatalog;
use Illuminate\Support\Facades\Log;

class ServiceFulfillmentService
{
    public function __construct(
        private readonly ServiceEntitlementService $entitlements,
        private readonly FileHostingQuotaRecomputeService $quotaRecompute,
    ) {}

    public function fulfill(ServicePurchase $purchase): void
    {
        $purchase->loadMissing('service');

        if ($purchase->status === PurchaseStatus::Paid) {
            return;
        }

        $service = $purchase->service;

        // Free catalog SKU is display-only; never grant via payment path.
        if ($service !== null && FileHostingCatalog::isFreeCode($service->code)) {
            Log::info('service.fulfillment.skipped_free_catalog', [
                'purchase_id' => $purchase->id,
                'service_code' => $service->code,
            ]);
            $purchase->status = PurchaseStatus::Paid;
            $purchase->save();

            return;
        }

        $result = $this->entitlements->activateFromPurchase($purchase);

        $purchase->status = PurchaseStatus::Paid;
        $purchase->period_start = $result['period_start'];
        $purchase->period_end = $result['period_end'];
        $purchase->save();

        $target = $service?->fulfillment_target;

        if ($target === FileHostingCatalog::FULFILLMENT_TARGET && $service?->isFileHostingStoragePlan()) {
            if ($service->isPaidMainPlan()) {
                $this->entitlements->expireOtherPaidMains($purchase->user_id, $service->id);
            }

            $this->quotaRecompute->recompute($purchase->user_id);

            return;
        }

        if ($target !== null) {
            Log::info('service.fulfillment.unhandled_target', [
                'purchase_id' => $purchase->id,
                'fulfillment_target' => $target,
                'entitlement' => $service?->entitlement,
                'plan_kind' => $service?->plan_kind?->value ?? $service?->plan_kind,
            ]);
        }
    }

    public function markCancelled(ServicePurchase $purchase): void
    {
        if (in_array($purchase->status, [PurchaseStatus::Paid, PurchaseStatus::Cancelled], true)) {
            return;
        }

        $purchase->status = PurchaseStatus::Cancelled;
        $purchase->save();
    }

    public function markFailed(ServicePurchase $purchase): void
    {
        if ($purchase->status === PurchaseStatus::Paid) {
            return;
        }

        $purchase->status = PurchaseStatus::Failed;
        $purchase->save();
    }

    /**
     * @deprecated Prefer recompute after expire; kept for callers that still revoke denorm.
     */
    public function revokeFileHostingUnlimited(string $userId): void
    {
        $this->quotaRecompute->recompute($userId);
    }
}
