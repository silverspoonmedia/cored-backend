<?php

namespace App\Domain\Vtual\Enums;

enum ContentCategory: string
{
    case Cover = 'cover';
    case Original = 'original';
    case SingingStream = 'singing_stream';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
