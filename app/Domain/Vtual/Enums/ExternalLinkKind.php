<?php

namespace App\Domain\Vtual\Enums;

enum ExternalLinkKind: string
{
    case Social = 'social';
    case Donation = 'donation';
    case Website = 'website';
    case Other = 'other';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
