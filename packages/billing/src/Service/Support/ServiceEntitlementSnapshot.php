<?php

namespace App\Domain\Service\Support;

use App\Domain\Service\Models\ServiceEntitlement;
use App\Domain\Service\Models\ServicePurchase;
use App\Support\Billing\FulfillmentContext;

/**
 * Snapshot builder for {@see FulfillmentContext}.
 *
 * The context itself is a kernel-owned, data-only shape (product handlers receive
 * it without importing a product service). Building it needs `ServiceEntitlement`
 * and `ServicePurchase`, which this package owns — so the construction lives here
 * and the kernel stays free of any `App\Domain\*` import.
 *
 * Built from ids and scalars only: the package hands over data, never a model, so
 * a handler cannot mutate ledger state by accident.
 */
final class ServiceEntitlementSnapshot
{
    public static function fromEntitlement(
        ServiceEntitlement $entitlement,
        ?ServicePurchase $purchase = null,
        string $phase = FulfillmentContext::PHASE_ACTIVATED,
    ): FulfillmentContext {
        $entitlement->loadMissing('service');
        $service = $entitlement->service;

        return new FulfillmentContext(
            userId: (string) $entitlement->user_id,
            purchaseId: $purchase !== null ? (string) $purchase->id : null,
            entitlementId: (string) $entitlement->id,
            serviceId: $service?->id,
            serviceCode: (string) ($service->code ?? ''),
            fulfillmentTarget: (string) ($service->fulfillment_target ?? ''),
            entitlementCode: $service?->entitlement,
            quantity: max(0, (int) $entitlement->quantity),
            storageBytes: (int) ($service->storage_bytes ?? 0),
            periodStart: $entitlement->starts_at,
            periodEnd: $entitlement->ends_at,
            phase: $phase,
        );
    }
}
