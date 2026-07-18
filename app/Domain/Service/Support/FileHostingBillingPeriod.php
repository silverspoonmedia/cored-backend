<?php

namespace App\Domain\Service\Support;

use Carbon\CarbonInterface;

/**
 * Fixed-length billing period for File Hosting storage plans.
 * Aligned with {@see MainPlanChangeCalculator::PERIOD_DAYS} credit math.
 */
final class FileHostingBillingPeriod
{
    public const DAYS = 30;

    public static function add(CarbonInterface $from): CarbonInterface
    {
        return $from->copy()->addDays(self::DAYS);
    }
}
