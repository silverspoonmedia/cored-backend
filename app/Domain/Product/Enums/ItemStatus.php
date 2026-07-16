<?php

namespace App\Domain\Product\Enums;

enum ItemStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Archived = 'archived';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
