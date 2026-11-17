<?php

namespace App\Domain\Archivd\Support\Billing;

use App\Domain\Archivd\Services\ArchivdQuotaRecomputeService;
use App\Domain\Archivd\Services\EnforceFreeTierQuotaService;
use App\Domain\Archivd\Services\QuotaService;
use App\Domain\Archivd\Support\ArchivdCatalog;
use App\Domain\Service\Support\BillingPeriod;
use App\Support\Billing\Contracts\FulfillmentHandler;
use App\Support\Billing\FulfillmentContext;

/**
 * What a paid Archivd catalog SKU means for Archivd itself.
 *
 * The billing package activates/expires the entitlement; this handler answers
 * the Archivd half: recompute the denormalized quota line and, on expiry, purge
 * storage over the new effective limit. Free-tier storage is never a SKU — it is
 * always stacked from `config('archivd.default_quota_bytes')`.
 */
final class ArchivdFulfillmentHandler implements FulfillmentHandler
{
    public function __construct(
        private readonly ArchivdQuotaRecomputeService $quotaRecompute,
        private readonly EnforceFreeTierQuotaService $purge,
    ) {}

    public function targets(): array
    {
        return [ArchivdCatalog::FULFILLMENT_TARGET];
    }

    public function freeCodes(): array
    {
        return [ArchivdCatalog::CODE_FREE];
    }

    public function fixedPeriodDays(): ?int
    {
        return BillingPeriod::DAYS;
    }

    public function guardCheckout(FulfillmentContext $context): void
    {
        // No extra Archivd gate: the billing package already rejects the retired
        // free SKU and a non-paid main.
    }

    public function onActivated(FulfillmentContext $context): void
    {
        if ($context->contributesStorage()) {
            $this->quotaRecompute->recompute($context->userId);
        }
    }

    public function onExtended(FulfillmentContext $context): void
    {
        $this->quotaRecompute->recompute($context->userId);
    }

    public function onExpired(FulfillmentContext $context): array
    {
        if (! $context->contributesStorage()) {
            return [];
        }

        $computed = $this->quotaRecompute->recompute($context->userId);
        $purge = $this->purge->enforceToLimit($context->userId, $computed['quota_bytes']);

        return [
            'user_id' => $context->userId,
            'quota' => $computed,
            'purge' => $purge,
        ];
    }

    public function onEntitlementsMutated(string $userId): void
    {
        app(QuotaService::class)->forgetStorageState($userId);
    }
}
