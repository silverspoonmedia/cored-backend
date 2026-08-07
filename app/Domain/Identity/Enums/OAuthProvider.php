<?php

namespace App\Domain\Identity\Enums;

enum OAuthProvider: string
{
    case Discord = 'discord';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
