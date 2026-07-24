<?php

namespace App\Domain\Vtual\Enums;

enum PlatformKey: string
{
    case Youtube = 'youtube';
    case Twitch = 'twitch';
    case Kick = 'kick';

    public function defaultName(): string
    {
        return match ($this) {
            self::Youtube => 'YouTube',
            self::Twitch => 'Twitch',
            self::Kick => 'Kick',
        };
    }

    public function supportsVodByDefault(): bool
    {
        return $this === self::Youtube;
    }

    public function enabledByDefault(): bool
    {
        // Kick deferred until official API strategy is approved.
        return $this !== self::Kick;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
