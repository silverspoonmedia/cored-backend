<?php

namespace App\Domain\Vtual\Enums;

enum VtualEventStatus: string
{
    case Scheduled = 'scheduled';
    case Live = 'live';
    case Ended = 'ended';
    case Cancelled = 'cancelled';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Statuses exposed on the guest-safe public surface.
     *
     * @return list<self>
     */
    public static function publicVisible(): array
    {
        return [self::Scheduled, self::Live];
    }
}
