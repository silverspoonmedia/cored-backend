<?php

namespace App\Domain\Vtual\Enums;

enum CreatorClaimStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
