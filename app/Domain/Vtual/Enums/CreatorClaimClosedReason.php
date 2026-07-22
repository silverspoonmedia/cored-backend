<?php

namespace App\Domain\Vtual\Enums;

enum CreatorClaimClosedReason: string
{
    /** Another claim for the same creator completed successfully. */
    case Superseded = 'superseded';

    /** Claimant cancelled their own pending claim. */
    case UserCancel = 'user_cancel';

    /** Claim TTL expired. */
    case Expired = 'expired';

    /** Creator profile was absorbed into another profile (merge). */
    case Merged = 'merged';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
