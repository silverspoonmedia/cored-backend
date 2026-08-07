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
     * @param  iterable<Channel>  $channels
     * @param  list<array{type: string, id: string, name: string, verified: bool}>  $connections
     */
    public static function matchesAny(iterable $channels, array $connections): bool
    {
        return self::firstMatch($channels, $connections) !== null;
    }

    /**
     * @param  iterable<Channel>  $channels
     * @param  list<array{type: string, id: string, name: string, verified: bool}>  $connections
     */
    public static function firstMatch(iterable $channels, array $connections): ?Channel
    {
        foreach ($channels as $channel) {
            if (! $channel instanceof Channel) {
                continue;
            }

            if (self::matches($channel, $connections)) {
                return $channel;
            }
        }

        return null;
    }

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

        return self::matchesExternalId($platformKey, (string) $channel->external_id, $connections);
    }

    /**
     * @param  list<array{type: string, id: string, name: string, verified: bool}>  $connections
     */
    public static function matchesExternalId(PlatformKey $platformKey, string $externalId, array $connections): bool
    {
        $discordType = match ($platformKey) {
            PlatformKey::Youtube => 'youtube',
            PlatformKey::Twitch => 'twitch',
            PlatformKey::Kick => null,
        };

        if ($discordType === null || $externalId === '') {
            return false;
        }

        foreach ($connections as $connection) {
            if (($connection['type'] ?? '') !== $discordType) {
                continue;
            }

            if (($connection['revoked'] ?? false) === true) {
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
