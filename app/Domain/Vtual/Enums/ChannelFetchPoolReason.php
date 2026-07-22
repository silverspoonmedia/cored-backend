<?php

namespace App\Domain\Vtual\Enums;

enum ChannelFetchPoolReason: string
{
    case Refresh = 'refresh';
    case ScheduledResync = 'scheduled_resync';
    case Manual = 'manual';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
