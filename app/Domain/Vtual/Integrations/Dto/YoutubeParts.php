<?php

namespace App\Domain\Vtual\Integrations\Dto;

use App\Domain\Vtual\Exceptions\VtualException;

/**
 * Whitelisted YouTube Data API `part` lists (videos / channels).
 */
final class YoutubeParts
{
    /**
     * @param  list<string>|null  $parts
     * @return list<string>
     */
    public static function forVideos(?array $parts = null): array
    {
        $resolved = $parts ?? (array) config('vtual.youtube.default_video_parts', []);
        $allowed = (array) config('vtual.youtube.allowed_video_parts', []);

        return self::validate($resolved, $allowed, 'video');
    }

    /**
     * @param  list<string>|null  $parts
     * @return list<string>
     */
    public static function forChannels(?array $parts = null): array
    {
        $resolved = $parts ?? (array) config('vtual.youtube.default_channel_parts', []);
        $allowed = (array) config('vtual.youtube.allowed_channel_parts', []);

        return self::validate($resolved, $allowed, 'channel');
    }

    /**
     * @param  list<string>  $parts
     * @param  list<string>  $allowed
     * @return list<string>
     */
    private static function validate(array $parts, array $allowed, string $resource): array
    {
        $clean = [];
        foreach ($parts as $part) {
            if (! is_string($part)) {
                throw new VtualException(__('api/vtual.integrations.invalid_part', [
                    'part' => (string) $part,
                    'resource' => $resource,
                ]), 422);
            }
            $trimmed = trim($part);
            if ($trimmed === '') {
                continue;
            }
            if (! in_array($trimmed, $allowed, true)) {
                throw new VtualException(__('api/vtual.integrations.invalid_part', [
                    'part' => $trimmed,
                    'resource' => $resource,
                ]), 422);
            }
            $clean[] = $trimmed;
        }

        $clean = array_values(array_unique($clean));
        if ($clean === []) {
            throw new VtualException(__('api/vtual.integrations.parts_required', [
                'resource' => $resource,
            ]), 422);
        }

        return $clean;
    }
}
