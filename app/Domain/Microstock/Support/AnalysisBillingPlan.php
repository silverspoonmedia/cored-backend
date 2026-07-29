<?php

namespace App\Domain\Microstock\Support;

use App\Domain\Microstock\Enums\BillingSource;

final readonly class AnalysisBillingPlan
{
    public function __construct(
        public int $dailySlots,
        public int $prepaidSlots,
    ) {}

    public function billingSourceForIndex(int $index): BillingSource
    {
        return $index < $this->dailySlots
            ? BillingSource::DailyFree
            : BillingSource::Prepaid;
    }
}
