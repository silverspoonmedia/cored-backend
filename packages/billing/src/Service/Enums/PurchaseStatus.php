<?php

namespace App\Domain\Service\Enums;

enum PurchaseStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Cancelled = 'cancelled';
    case Failed = 'failed';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
