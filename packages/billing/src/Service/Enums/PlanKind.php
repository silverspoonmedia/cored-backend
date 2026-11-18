<?php

namespace App\Domain\Service\Enums;

enum PlanKind: string
{
    case Main = 'main';
    case Addon = 'addon';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
