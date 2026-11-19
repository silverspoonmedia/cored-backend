<?php

namespace App\Domain\Service\Tests\Support;

use App\Support\Billing\Contracts\FulfillmentHandler;
use App\Support\Billing\FulfillmentContext;

/**
 * Minimal handler that makes `test_target` bill a fixed 30-day period.
 *
 * The package test needs the fixed-period path without installing a product, so
 * it registers this through the same kernel seam a product would use.
 */
final class FixedPeriodTestHandler implements FulfillmentHandler
{
    public const TARGET = 'test_target';

    public function targets(): array
    {
        return [self::TARGET];
    }

    public function freeCodes(): array
    {
        return [];
    }

    public function fixedPeriodDays(): int
    {
        return self::PERIOD_DAYS;
    }

    public function guardCheckout(FulfillmentContext $context): void {}

    public function onActivated(FulfillmentContext $context): void {}

    public function onExtended(FulfillmentContext $context): void {}

    public function onExpired(FulfillmentContext $context): array
    {
        return [];
    }

    public function onEntitlementsMutated(string $userId): void {}
}
