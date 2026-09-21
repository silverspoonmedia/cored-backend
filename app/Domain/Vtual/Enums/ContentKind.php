<?php

namespace App\Domain\Vtual\Enums;

/**
 * Primary classification layer: music vs non-music. Null = unknown.
 *
 * `fromCategoryId` is only signal #2 of the layer — vTubers file covers under
 * Gaming / People & Blogs instead of Music, so it is NOT sufficient alone.
 * The decision belongs to ContentKindClassifier, which also reads the YouTube
 * Music topic, title/tag keywords, original-credit links and duration.
 * Kept for the categoryId-only callers and tests that need the raw mapping.
 */
enum ContentKind: string
{
    case Music = 'music';
    case NonMusic = 'non_music';

    /**
     * Resolve from a platform categoryId alone. Empty/null → null (unknown);
     * any other non-empty id that is not the music category → non_music.
     *
     * Prefer ContentKindClassifier — this ignores every other signal.
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
