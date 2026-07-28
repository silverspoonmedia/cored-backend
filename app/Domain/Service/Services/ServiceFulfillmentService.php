<?php

namespace App\Domain\Service\Services;

use App\Domain\FileHosting\Services\FileHostingQuotaRecomputeService;
use App\Domain\Identity\Models\User;
use App\Domain\Microstock\Services\AiCreditService;
use App\Domain\Service\Enums\PurchaseStatus;
use App\Domain\Service\Models\ServiceAddonRenewal;
use App\Domain\Service\Models\ServicePurchase;
use App\Domain\Service\Support\FileHostingCatalog;
use App\Domain\Service\Support\MicrostockCatalog;
use App\Support\Product\ProductRegistry;
use App\Support\Product\Services\ProductActivationService;
use Illuminate\Support\Facades\Log;

class ServiceFulfillmentService
{
    public function __construct(
        private readonly ServiceEntitlementService $entitlements,
        private readonly FileHostingQuotaRecomputeService $quotaRecompute,
        private readonly ProductActivationService $productActivations,
        private readonly AiCreditService $aiCredits,
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

        if ($service?->entitlement === MicrostockCatalog::ENTITLEMENT_AI_CREDITS) {
            $this->aiCredits->creditFromPurchase($purchase);
        }

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
            $this->ensureProductActive($purchase->user_id, $target);

            return;
        }

        if ($target !== null) {
            $this->ensureProductActive($purchase->user_id, $target);

            Log::info('service.fulfillment.target_dispatched', [
                'purchase_id' => $purchase->id,
                'fulfillment_target' => $target,
                'entitlement' => $service?->entitlement,
                'plan_kind' => $service?->plan_kind?->value ?? $service?->plan_kind,
            ]);

            return;
        }
    }

    /**
     * Keep the product activation row Active when an entitlement activates.
     * Implicit products: lazy-create if missing. Explicit products: no-op
     * here (admin approval is the gate). Logs unhandled targets for review.
     */
    private function ensureProductActive(string $userId, string $fulfillmentTarget): void
    {
        $productKey = ProductRegistry::productKeyByFulfillmentTarget($fulfillmentTarget);

        if ($productKey === null) {
            Log::info('service.fulfillment.unhandled_target', [
                'fulfillment_target' => $fulfillmentTarget,
            ]);

            return;
        }

        $user = User::query()->find($userId);

        if ($user === null) {
            return;
        }

        if (ProductRegistry::isImplicit($productKey)) {
            $this->productActivations->activateImplicit($user, $productKey, 'fulfillment');
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
