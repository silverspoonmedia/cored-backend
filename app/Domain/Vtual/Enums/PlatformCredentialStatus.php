<?php

namespace App\Domain\Vtual\Enums;

enum PlatformCredentialStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Disabled = 'disabled';
    case Revoked = 'revoked';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
