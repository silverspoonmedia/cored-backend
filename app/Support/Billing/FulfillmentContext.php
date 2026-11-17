<?php

namespace App\Support\Billing;

use Carbon\CarbonInterface;

/**
 * Kernel-owned, data-only snapshot of one entitlement mutation.
 *
 * The billing package owns the catalog and the ledger; a product domain owns
 * what fulfillment *means* for its own target. Handing a product a shape like
 * this keeps the package from importing a product service — and keeps a host
 * without that product booting normally, because no handler is registered.
 */
final class FulfillmentContext
{
    /** A purchase just paid and the entitlement is now Active. */
    public const PHASE_ACTIVATED = 'activated';

    /** A stack renewal extended every active/grace addon. */
    public const PHASE_EXTENDED = 'extended';

    /** An entitlement passed its grace window and expired. */
    public const PHASE_EXPIRED = 'expired';

    /**
     * @param  list<string>  $activeCodes  Service codes currently Active/Grace for the user's target.
     */
    public function __construct(
        public readonly string $userId,
        public readonly ?string $purchaseId,
        public readonly ?string $entitlementId,
        public readonly ?string $serviceId,
        public readonly string $serviceCode,
        public readonly string $fulfillmentTarget,
        public readonly ?string $entitlementCode,
        public readonly int $quantity,
        public readonly int $storageBytes,
        public readonly ?CarbonInterface $periodStart,
        public readonly ?CarbonInterface $periodEnd,
        public readonly string $phase,
        public readonly array $activeCodes = [],
    ) {}

    public function contributesStorage(): bool
    {
        return $this->storageBytes > 0;
    }

    /**
     * Snapshot one entitlement row for a handler call.
     *
     * Built from ids and scalars only — the package hands over data, never a
     * model, so a handler cannot mutate ledger state by accident.
     */
    public static function fromEntitlement(
        \App\Domain\Service\Models\ServiceEntitlement $entitlement,
        ?\App\Domain\Service\Models\ServicePurchase $purchase = null,
        string $phase = self::PHASE_ACTIVATED,
    ): self {
        $entitlement->loadMissing('service');
        $service = $entitlement->service;

        return new self(
            userId: (string) $entitlement->user_id,
            purchaseId: $purchase !== null ? (string) $purchase->id : null,
            entitlementId: (string) $entitlement->id,
            serviceId: $service?->id,
            serviceCode: (string) ($service?->code ?? ''),
            fulfillmentTarget: (string) ($service?->fulfillment_target ?? ''),
            entitlementCode: $service?->entitlement,
            quantity: max(0, (int) $entitlement->quantity),
            storageBytes: (int) ($service?->storage_bytes ?? 0),
            periodStart: $entitlement->starts_at,
            periodEnd: $entitlement->ends_at,
            phase: $phase,
        );
    }
}
