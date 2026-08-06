<?php

namespace App\Domain\Vtual\Integrations\Support;

use App\Domain\Vtual\Enums\PlatformKey;
use Illuminate\Validation\ValidationException;

/**
 * Reject profile URLs whose host clearly belongs to another platform than selected.
 */
final class ChannelProfilePlatformGuard
{
    public static function assertMatchesPlatform(PlatformKey $selected, string $profile): void
    {
        $detected = self::detectHost($profile);

        if ($detected === null || $detected === $selected) {
            return;
        }

        throw ValidationException::withMessages([
            'profile' => [__('api/vtual.channel.profile_platform_mismatch')],
        ]);
    }

    public static function detectHost(string $profile): ?PlatformKey
    {
        $trimmed = trim($profile);
        if ($trimmed === '') {
            return null;
        }

        if (self::isYoutubeHost($trimmed)) {
            return PlatformKey::Youtube;
        }

        if (self::isTwitchHost($trimmed)) {
            return PlatformKey::Twitch;
        }

        return null;
    }

    private static function isYoutubeHost(string $raw): bool
    {
        if (preg_match('#^(?:https?://)?(?:www\.)?(?:youtube\.com|youtu\.be)/#i', $raw) === 1) {
            return true;
        }

        $lower = strtolower($raw);

        return str_starts_with($lower, 'youtube.com/')
            || str_starts_with($lower, 'www.youtube.com/')
            || str_starts_with($lower, 'youtu.be/');
    }

    private static function isTwitchHost(string $raw): bool
    {
        if (preg_match('#^(?:https?://)?(?:www\.)?(?:twitch\.tv|m\.twitch\.tv)/#i', $raw) === 1) {
            return true;
        }

        $lower = strtolower($raw);

        return str_starts_with($lower, 'twitch.tv/')
            || str_starts_with($lower, 'www.twitch.tv/')
            || str_starts_with($lower, 'm.twitch.tv/');
    }
}
