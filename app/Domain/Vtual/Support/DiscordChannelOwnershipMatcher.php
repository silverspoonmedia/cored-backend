<?php

namespace App\Domain\Vtual\Support;

use App\Domain\Vtual\Enums\PlatformKey;
use App\Domain\Vtual\Models\Channel;

/**
 * Match vTual channel rows against Discord user connection payloads.
 */
final class DiscordChannelOwnershipMatcher
{
    /**
     * @param  list<array{type: string, id: string, name: string, verified: bool}>  $connections
     */
    public static function matches(Channel $channel, array $connections): bool
    {
        $channel->loadMissing('platform');
        $platformKey = $channel->platform?->key;
        if (! $platformKey instanceof PlatformKey) {
            return false;
        }

        $discordType = match ($platformKey) {
            PlatformKey::Youtube => 'youtube',
            PlatformKey::Twitch => 'twitch',
            PlatformKey::Kick => null,
        };

        if ($discordType === null) {
            return false;
        }

        $externalId = (string) $channel->external_id;
        if ($externalId === '') {
            return false;
        }

        foreach ($connections as $connection) {
            if (($connection['type'] ?? '') !== $discordType) {
                continue;
            }

            if (! ($connection['verified'] ?? false)) {
                continue;
            }

            if ((string) ($connection['id'] ?? '') === $externalId) {
                return true;
            }
        }

        return false;
    }
}
