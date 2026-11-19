<?php

namespace App\Domain\Service\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Service\Enums\PurchaseStatus;
use App\Domain\Service\Models\ServiceAddonRenewal;
use App\Domain\Service\Models\ServiceEntitlement;
use App\Domain\Service\Models\ServicePurchase;
use App\Domain\Service\Support\ServiceEntitlementSnapshot;
use App\Domain\Service\Support\ServiceRealtime;
use App\Support\Billing\Fulfillment;
use App\Support\Billing\FulfillmentContext;
use App\Support\Billing\FulfillmentRegistry;
use App\Support\Product\ProductRegistry;
use App\Support\Product\Services\ProductActivationService;
use Illuminate\Support\Facades\Log;

/**
 * Turn a paid purchase into live entitlements, then let the owning product act.
 *
 * The package owns activation and period math; what a target *does* with a fresh
 * entitlement (recompute a quota, credit a balance) is a product decision,
 * routed through the kernel {@see FulfillmentRegistry}.
 * No handler registered = nothing to do, which is exactly what a host without
 * that product gets.
 */
class ServiceFulfillmentService
{
    public function __construct(
        private readonly ServiceEntitlementService $entitlements,
        private readonly ProductActivationService $productActivations,
        private readonly ServiceRealtime $realtime,
    ) {}

    public function fulfill(ServicePurchase $purchase): void
    {
        $purchase->loadMissing('service');

        if ($purchase->status === PurchaseStatus::Paid) {
            return;
        }

        $service = $purchase->service;

        if ($service !== null && in_array($service->code, Fulfillment::registry()->freeCodes(), true)) {
            Log::info('service.fulfillment.skipped_free_catalog', [
                'purchase_id' => $purchase->id,
                'service_code' => $service->code,
            ]);
            $purchase->status = PurchaseStatus::Paid;
            $purchase->save();
            $this->realtime->purchaseChanged($purchase->id, 'paid');

            return;
        }

        $result = $this->entitlements->activateFromPurchase($purchase);

        $purchase->status = PurchaseStatus::Paid;
        $purchase->period_start = $result['period_start'];
        $purchase->period_end = $result['period_end'];
        $purchase->save();
        $this->realtime->purchaseChanged($purchase->id, 'paid');

        $entitlement = $result['entitlement'] ?? null;
        $target = $service?->fulfillment_target;

        // A target holds one paid main at a time: activating another retires the
        // previous one. Generic, so the package owns it rather than a handler.
        if ($service?->isPaidMainPlan()) {
            $this->entitlements->expireOtherPaidMains($purchase->user_id, $service->id, $target);
        }

        if ($target !== null) {
            $this->ensureProductActive($purchase->user_id, $target);
        }

        if ($service !== null && $entitlement instanceof ServiceEntitlement) {
            $handler = Fulfillment::handler($target);

            if ($handler !== null) {
                $handler->onActivated(ServiceEntitlementSnapshot::fromEntitlement(
                    $entitlement,
                    $purchase,
                    FulfillmentContext::PHASE_ACTIVATED,
                ));

                Fulfillment::entitlementsMutated($purchase->user_id);
            }
        }

        if ($service !== null && $target === null) {
            Log::info('service.fulfillment.unhandled_target', [
                'purchase_id' => $purchase->id,
                'entitlement' => $service->entitlement,
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

        foreach ($result['entitlements'] ?? [] as $entitlement) {
            if (! $entitlement instanceof ServiceEntitlement) {
                continue;
            }

            $handler = Fulfillment::handler($entitlement->service?->fulfillment_target);

            $handler?->onExtended(ServiceEntitlementSnapshot::fromEntitlement(
                $entitlement->loadMissing('service'),
                null,
                FulfillmentContext::PHASE_EXTENDED,
            ));
        }

        Fulfillment::entitlementsMutated($renewal->user_id);
    }

    public function markCancelled(ServicePurchase $purchase): void
    {
        if (in_array($purchase->status, [PurchaseStatus::Paid, PurchaseStatus::Cancelled], true)) {
            return;
        }

        $purchase->status = PurchaseStatus::Cancelled;
        $purchase->save();
        $this->realtime->purchaseChanged($purchase->id, 'cancelled');
    }

    public function markFailed(ServicePurchase $purchase): void
    {
        if ($purchase->status === PurchaseStatus::Paid) {
            return;
        }

        $purchase->status = PurchaseStatus::Failed;
        $purchase->save();
        $this->realtime->purchaseChanged($purchase->id, 'failed');
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
     * Keep the product activation row Active when an entitlement activates.
     * Implicit products: lazy-create if missing. Explicit products: no-op
     * here (admin approval is the gate).
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
}
