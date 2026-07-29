<?php

namespace App\Domain\Microstock\Enums;

enum BillingSource: string
{
    case DailyFree = 'daily_free';
    case Prepaid = 'prepaid';

    public function isFreeTier(): bool
    {
        return $this === self::DailyFree;
    }
}
