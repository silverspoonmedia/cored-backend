<?php

namespace App\Domain\Vtual\Enums;

enum ContentType: string
{
    case Live = 'live';
    case Archive = 'archive';
    case Vod = 'vod';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
