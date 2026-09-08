<?php

namespace App\Domain\Service\Support;

use Carbon\CarbonInterface;

/**
 * Fixed-length billing period for Archivd storage plans.
 * Aligned with {@see MainPlanChangeCalculator::PERIOD_DAYS} credit math.
 */
final class ArchivdBillingPeriod
{
    public const DAYS = 30;

    public static function add(CarbonInterface $from): CarbonInterface
    {
        return $from->copy()->addDays(self::DAYS);
    }
}
