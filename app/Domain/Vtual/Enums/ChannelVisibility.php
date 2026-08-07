<?php

namespace App\Domain\Vtual\Enums;

enum ChannelVisibility: string
{
    case Public = 'public';
    case Private = 'private';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
