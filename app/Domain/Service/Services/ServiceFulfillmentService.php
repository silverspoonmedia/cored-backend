<?php

namespace App\Domain\Service\Services;

use App\Domain\FileHosting\Services\FileHostingQuotaRecomputeService;
use App\Domain\Service\Enums\PurchaseStatus;
use App\Domain\Service\Models\ServiceAddonRenewal;
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

    public function fulfillAddonRenewal(ServiceAddonRenewal $renewal): void
    {
        if ($renewal->status === PurchaseStatus::Paid) {
            return;
        }

        $result = $this->entitlements->extendAllAddons($renewal->user_id);

        $renewal->status = PurchaseStatus::Paid;
        $renewal->period_start = now();
        $renewal->period_end = $result['period_end'];
        $renewal->save();

        $this->quotaRecompute->recompute($renewal->user_id);
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

    public function markAddonRenewalCancelled(ServiceAddonRenewal $renewal): void
    {
        if (in_array($renewal->status, [PurchaseStatus::Paid, PurchaseStatus::Cancelled], true)) {
            return;
        }

        $renewal->status = PurchaseStatus::Cancelled;
        $renewal->save();
    }

    public function markAddonRenewalFailed(ServiceAddonRenewal $renewal): void
    {
        if ($renewal->status === PurchaseStatus::Paid) {
            return;
        }

        $renewal->status = PurchaseStatus::Failed;
        $renewal->save();
    }

    /**
     * @deprecated Prefer recompute after expire.
     */
    public function revokeFileHostingUnlimited(string $userId): void
    {
        $this->quotaRecompute->recompute($userId);
    }
}
