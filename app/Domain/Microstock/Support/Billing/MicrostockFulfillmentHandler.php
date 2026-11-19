<?php

namespace App\Domain\Microstock\Support\Billing;

use App\Domain\Microstock\Services\AiCreditService;
use App\Domain\Microstock\Support\MicrostockCatalog;
use App\Domain\Service\Exceptions\ServiceException;
use App\Domain\Service\Services\ServiceEntitlementService;
use App\Domain\Service\Support\BillingPeriod;
use App\Support\Billing\Contracts\FulfillmentHandler;
use App\Support\Billing\FulfillmentContext;

/**
 * What a Microstock catalog SKU means for Microstock itself.
 *
 * Two product rules live here rather than in the billing package: the free
 * registration is one-time (blocked once a slot entitlement is active), and the
 * prepaid AI-credit pack must land in the Microstock balance the moment the
 * purchase is paid.
 */
final class MicrostockFulfillmentHandler implements FulfillmentHandler
{
    public function __construct(
        private readonly ServiceEntitlementService $entitlements,
        private readonly AiCreditService $aiCredits,
    ) {}

    public function targets(): array
    {
        return [MicrostockCatalog::FULFILLMENT_TARGET];
    }

    public function freeCodes(): array
    {
        return [];
    }

    public function fixedPeriodDays(): int
    {
        // Slot addons renew with the shared stack anniversary, so they follow the
        // same fixed 30-day clock the storage addons they sit next to use.
        return BillingPeriod::DAYS;
    }

    public function guardCheckout(FulfillmentContext $context): void
    {
        if (! MicrostockCatalog::isFreeCode($context->serviceCode)) {
            return;
        }

        if ($this->entitlements->hasSlotEntitlement(
            $context->userId,
            MicrostockCatalog::FULFILLMENT_TARGET,
            MicrostockCatalog::ENTITLEMENT_WORKSPACE_SLOTS,
        )) {
            throw new ServiceException(__('api/services.already_activated'), 422);
        }
    }

    public function onActivated(FulfillmentContext $context): void
    {
        if ($context->purchaseId === null || $context->entitlementCode !== MicrostockCatalog::ENTITLEMENT_AI_CREDITS) {
            return;
        }

        $this->aiCredits->creditFromPurchaseId($context->purchaseId);
    }

    public function onExtended(FulfillmentContext $context): void
    {
        // Slot addons renew with the shared anniversary; no balance to top up.
    }

    public function onExpired(FulfillmentContext $context): array
    {
        return [];
    }

    public function onEntitlementsMutated(string $userId): void
    {
        // No request-scoped memo on the Microstock side yet; the billing package
        // drops its own slot probes on every mutation.
    }
}
