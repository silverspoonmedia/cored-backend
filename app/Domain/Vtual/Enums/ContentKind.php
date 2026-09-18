<?php

namespace App\Domain\Vtual\Enums;

/**
 * Primary classification layer: music vs non-music, derived from the
 * platform categoryId (YouTube "10" = Music). Null = unknown (categoryId
 * unavailable, e.g. Twitch streams).
 */
enum ContentKind: string
{
    case Music = 'music';
    case NonMusic = 'non_music';

    /**
     * Resolve from a platform categoryId. Empty/null → null (unknown);
     * any other non-empty id that is not the music category → non_music.
     */
    public static function fromCategoryId(?string $categoryId): ?self
    {
        $categoryId = trim((string) $categoryId);
        if ($categoryId === '') {
            return null;
        }

        $musicCategory = (string) config('vtual.content_classification.music_category_id', '10');

        return $categoryId === $musicCategory ? self::Music : self::NonMusic;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
