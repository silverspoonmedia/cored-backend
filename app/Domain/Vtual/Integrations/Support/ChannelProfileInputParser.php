<?php

namespace App\Domain\Vtual\Integrations\Support;

use App\Domain\Vtual\Enums\PlatformKey;
use App\Domain\Vtual\Exceptions\VtualException;

/**
 * Pure string rules: map free-form profile input to channel_id or handle/login.
 * No HTTP. Platform-specific (YouTube UC vs @handle; Twitch numeric vs login).
 */
final class ChannelProfileInputParser
{
    public static function parse(PlatformKey $platform, string $raw): ChannelProfileInput
    {
        $trimmed = trim($raw);
        if ($trimmed === '') {
            throw new VtualException(__('api/vtual.channel.profile_required'), 422);
        }

        return match ($platform) {
            PlatformKey::Youtube => self::parseYoutube($trimmed),
            PlatformKey::Twitch => self::parseTwitch($trimmed),
            PlatformKey::Kick => throw new VtualException(__('api/vtual.channel.platform_disabled'), 422),
        };
    }

    private static function parseYoutube(string $raw): ChannelProfileInput
    {
        if (preg_match('#^(?:https?://)?(?:www\.)?(?:youtube\.com|youtu\.be)/#i', $raw) === 1
            || str_starts_with(strtolower($raw), 'youtube.com/')
            || str_starts_with(strtolower($raw), 'www.youtube.com/')) {
            return self::parseYoutubeUrl($raw);
        }

        if (self::isYoutubeChannelId($raw)) {
            return new ChannelProfileInput($raw, ChannelProfileInputKind::ChannelId, $raw);
        }

        $handle = ltrim($raw, '@');
        if ($handle === '' || ! self::isPlausibleHandle($handle)) {
            throw new VtualException(__('api/vtual.channel.profile_invalid'), 422);
        }

        return new ChannelProfileInput($raw, ChannelProfileInputKind::Handle, $handle);
    }

    private static function parseYoutubeUrl(string $raw): ChannelProfileInput
    {
        $url = $raw;
        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://'.$url;
        }

        $parts = parse_url($url);
        $path = isset($parts['path']) ? (string) $parts['path'] : '';
        $path = trim($path, '/');
        $segments = $path === '' ? [] : explode('/', $path);

        // /channel/UCxxxx
        if (isset($segments[0], $segments[1]) && strtolower($segments[0]) === 'channel') {
            $id = $segments[1];
            if (self::isYoutubeChannelId($id)) {
                return new ChannelProfileInput($raw, ChannelProfileInputKind::ChannelId, $id);
            }
        }

        // /@handle or /c/name or /user/name
        if (isset($segments[0]) && str_starts_with($segments[0], '@')) {
            $handle = ltrim($segments[0], '@');
            if ($handle !== '' && self::isPlausibleHandle($handle)) {
                return new ChannelProfileInput($raw, ChannelProfileInputKind::Handle, $handle);
            }
        }

        if (isset($segments[0], $segments[1]) && in_array(strtolower($segments[0]), ['c', 'user'], true)) {
            $handle = $segments[1];
            if ($handle !== '' && self::isPlausibleHandle($handle)) {
                return new ChannelProfileInput($raw, ChannelProfileInputKind::Handle, $handle);
            }
        }

        // Single path segment that looks like handle or UC id
        if (count($segments) === 1 && $segments[0] !== '') {
            $seg = $segments[0];
            if (self::isYoutubeChannelId($seg)) {
                return new ChannelProfileInput($raw, ChannelProfileInputKind::ChannelId, $seg);
            }
            $handle = ltrim($seg, '@');
            if ($handle !== '' && self::isPlausibleHandle($handle)) {
                return new ChannelProfileInput($raw, ChannelProfileInputKind::Handle, $handle);
            }
        }

        throw new VtualException(__('api/vtual.channel.profile_invalid'), 422);
    }

    private static function parseTwitch(string $raw): ChannelProfileInput
    {
        if (preg_match('#^(?:https?://)?(?:www\.)?(?:twitch\.tv|m\.twitch\.tv)/#i', $raw) === 1
            || str_starts_with(strtolower($raw), 'twitch.tv/')
            || str_starts_with(strtolower($raw), 'www.twitch.tv/')) {
            return self::parseTwitchUrl($raw);
        }

        if (self::isTwitchUserId($raw)) {
            return new ChannelProfileInput($raw, ChannelProfileInputKind::ChannelId, $raw);
        }

        $login = strtolower(ltrim($raw, '@'));
        if ($login === '' || ! self::isPlausibleTwitchLogin($login)) {
            throw new VtualException(__('api/vtual.channel.profile_invalid'), 422);
        }

        return new ChannelProfileInput($raw, ChannelProfileInputKind::Handle, $login);
    }

    private static function parseTwitchUrl(string $raw): ChannelProfileInput
    {
        $url = $raw;
        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://'.$url;
        }

        $parts = parse_url($url);
        $path = isset($parts['path']) ? (string) $parts['path'] : '';
        $path = trim($path, '/');
        $segments = $path === '' ? [] : explode('/', $path);

        // Ignore common non-profile first segments
        $skip = ['directory', 'videos', 'settings', 'inventory', 'subscriptions', 'wallet', 'p', 'moderator'];
        $login = $segments[0] ?? '';
        if ($login !== '' && in_array(strtolower($login), $skip, true)) {
            throw new VtualException(__('api/vtual.channel.profile_invalid'), 422);
        }

        $login = strtolower(ltrim($login, '@'));
        if ($login === '' || ! self::isPlausibleTwitchLogin($login)) {
            throw new VtualException(__('api/vtual.channel.profile_invalid'), 422);
        }

        return new ChannelProfileInput($raw, ChannelProfileInputKind::Handle, $login);
    }

    private static function isYoutubeChannelId(string $value): bool
    {
        // Standard channel ids: UC + 22 base64url-ish chars (total 24). Allow slightly longer.
        return (bool) preg_match('/^UC[\w-]{20,34}$/', $value);
    }

    private static function isTwitchUserId(string $value): bool
    {
        return (bool) preg_match('/^\d{1,20}$/', $value);
    }

    private static function isPlausibleHandle(string $value): bool
    {
        return (bool) preg_match('/^[\w.\-]{1,100}$/u', $value);
    }

    private static function isPlausibleTwitchLogin(string $value): bool
    {
        // Twitch logins: 4–25 chars typically; allow 1–25 for parser leniency
        return (bool) preg_match('/^[a-z0-9_]{1,25}$/', $value);
    }
}
