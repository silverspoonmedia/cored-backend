<?php

namespace App\Domain\Vtual\Enums;

enum ChannelDiscoverySource: string
{
    case DiscordConnection = 'discord_connection';
    case StaffSeed = 'staff_seed';
    case ChannelClaim = 'channel_claim';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
