<?php

namespace App\Domain\Vtual\Support;

use App\Domain\Vtual\Enums\ExternalLinkKind;

/**
 * Map Discord connection types to creator external link rows.
 */
final class DiscordConnectionExternalLinkMapper
{
    /**
     * @param  array<string, mixed>  $connection
     * @return array{url: string, label: string, kind: ExternalLinkKind}|null
     */
    public static function map(array $connection): ?array
    {
        $type = strtolower((string) ($connection['type'] ?? ''));
        $name = trim((string) ($connection['name'] ?? ''));
        $id = trim((string) ($connection['id'] ?? ''));

        if ($type === '' || $id === '') {
            return null;
        }

        $url = match ($type) {
            'github' => $name !== '' ? 'https://github.com/'.$name : null,
            'twitter' => $name !== '' ? 'https://twitter.com/'.$name : null,
            'reddit' => $name !== '' ? 'https://www.reddit.com/user/'.$name : null,
            'steam' => $id !== '' ? 'https://steamcommunity.com/profiles/'.$id : null,
            'spotify' => $id !== '' ? 'https://open.spotify.com/user/'.$id : null,
            'facebook' => $name !== '' ? 'https://www.facebook.com/'.$name : null,
            'instagram' => $name !== '' ? 'https://www.instagram.com/'.$name : null,
            'tiktok' => $name !== '' ? 'https://www.tiktok.com/@'.$ltrim($name, '@') : null,
            'youtube', 'twitch' => null,
            default => self::guessUrl($type, $name, $id),
        };

        if ($url === null || $url === '') {
            return null;
        }

        $label = $name !== '' ? $name : strtoupper($type);
        $kind = self::kindForType($type);

        return [
            'url' => $url,
            'label' => $label,
            'kind' => $kind,
        ];
    }

    private static function guessUrl(string $type, string $name, string $id): ?string
    {
        if ($name !== '' && filter_var($name, FILTER_VALIDATE_URL)) {
            return $name;
        }

        if (str_contains($type, 'http')) {
            return null;
        }

        return null;
    }

    private static function kindForType(string $type): ExternalLinkKind
    {
        return match ($type) {
            'github', 'twitter', 'reddit', 'facebook', 'instagram', 'tiktok' => ExternalLinkKind::Social,
            default => ExternalLinkKind::Other,
        };
    }
}
