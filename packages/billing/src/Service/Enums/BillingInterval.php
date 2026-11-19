<?php

namespace App\Domain\Service\Enums;

use App\Support\Billing\Contracts\SubscriptionInterval;
use Carbon\CarbonInterface;

enum BillingInterval: string implements SubscriptionInterval
{
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Yearly = 'yearly';
    case Once = 'once';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function isSubscription(): bool
    {
        return $this !== self::Once;
    }

    public function isOneTime(): bool
    {
        return $this === self::Once;
    }

    public function addPeriod(CarbonInterface $from): CarbonInterface
    {
        return match ($this) {
            self::Monthly => $from->copy()->addMonth(),
            self::Quarterly => $from->copy()->addMonths(3),
            self::Yearly => $from->copy()->addYear(),
            self::Once => $from->copy(),
        };
    }
}
