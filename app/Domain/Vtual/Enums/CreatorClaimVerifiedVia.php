<?php

namespace App\Domain\Vtual\Enums;

enum CreatorClaimVerifiedVia: string
{
    case Bio = 'bio';
    case AcceptAnyway = 'accept_anyway';
    case DiscordConnection = 'discord_connection';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
