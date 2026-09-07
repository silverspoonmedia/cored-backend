<?php

namespace App\Domain\Vtual\Enums;

/**
 * Twitch EventSub subscription types handled by this app. Version values must
 * match the Twitch EventSub subscription API contract:
 * channel.update v2, stream.online v1, stream.offline v1 (webhook transport,
 * app access token authorization).
 */
enum TwitchEventSubType: string
{
    case ChannelUpdate = 'channel.update';
    case StreamOnline = 'stream.online';
    case StreamOffline = 'stream.offline';

    public function version(): string
    {
        return match ($this) {
            self::ChannelUpdate => '2',
            self::StreamOnline, self::StreamOffline => '1',
        };
    }

    /**
     * @return array<int, self>
     */
    public static function all(): array
    {
        return [self::ChannelUpdate, self::StreamOnline, self::StreamOffline];
    }
}
