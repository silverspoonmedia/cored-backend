<?php

namespace App\Domain\Vtual\Enums;

enum ContentStatus: string
{
    case Scheduled = 'scheduled';
    case Live = 'live';
    case Ended = 'ended';
    case Archived = 'archived';
    case Uploaded = 'uploaded';
    case Unknown = 'unknown';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
