<?php

namespace App\Domain\Service\Support;

use Carbon\CarbonInterface;

/**
 * Fixed-length billing period used by the credit math.
 *
 * `ServiceEntitlementService` and `MainPlanChangeCalculator` must agree on one
 * period length or the unused-gap credit drifts against the actual entitlement,
 * so both read this — never their own copy.
 *
 * Targets that bill a fixed number of days (storage plans) select it through a
 * product {@see App\Support\Billing\Contracts\FulfillmentHandler}; everything
 * else keeps the catalog's calendar interval.
 */
final class BillingPeriod
{
    public const DAYS = 30;

    public static function add(CarbonInterface $from): CarbonInterface
    {
        return $from->copy()->addDays(self::DAYS);
    }
}
