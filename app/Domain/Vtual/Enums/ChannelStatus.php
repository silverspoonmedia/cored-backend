<?php

namespace App\Domain\Vtual\Enums;

enum ChannelStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Disconnected = 'disconnected';
    case Archived = 'archived';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
