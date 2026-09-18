<?php

namespace App\Domain\Vtual\Services;

use App\Domain\Vtual\Enums\ContentCategory;
use App\Domain\Vtual\Integrations\Music\ItunesSearchClient;

/**
 * Classify a video as cover / original / singing stream from title +
 * description + YouTube categoryId + duration.
 *
 * Majority of vTuber covers are vocaloid/utaite culture, so Japanese signals
 * (歌ってみた, 歌枠, オリジナル曲) are first-class. "original" beats "cover"
 * when both appear (e.g. "オリジナル曲 covered by X" → original is wrong only
 * in rare edge cases; self-labeling オリジナル wins).
 *
 * Never throws; unknown → null.
 */
class ContentCategoryClassifier
{
    public function __construct(
        private readonly ItunesSearchClient $itunes,
    ) {}

    /**
     * @param  array{
     *     title?: string|null,
     *     description?: string|null,
     *     category_id?: string|null,
     *     duration_seconds?: int|null,
     *     type?: string|null,
     * }  $video
     * @return array{category: ContentCategory, detail: ?string}|null
     */
    public function classify(array $video): ?array
    {
        $title = mb_strtolower(trim((string) ($video['title'] ?? '')));
        if ($title === '') {
            return null;
        }

        $description = mb_strtolower(trim((string) ($video['description'] ?? '')));
        $categoryId = (string) ($video['category_id'] ?? '');
        $duration = $video['duration_seconds'] ?? null;
        $haystack = $title.' '.$description;

        if ($this->containsAny($title, $this->originalKeywords())) {
            return ['category' => ContentCategory::Original, 'detail' => null];
        }

        // 歌枠 / singing stream labels are unambiguous regardless of type
        // (VOD archives of singing streams keep the label).
        if ($this->containsAny($title, $this->singingStreamKeywords())) {
            return ['category' => ContentCategory::SingingStream, 'detail' => null];
        }

        if ($this->containsAny($title, $this->strongCoverKeywords())) {
            return ['category' => ContentCategory::Cover, 'detail' => null];
        }

        // Weak signals (English "cover", "feat.", "/歌") need corroboration
        // before the cover label sticks: Music category or song-length video.
        if ($this->containsAny($haystack, $this->weakCoverKeywords())) {
            if ($this->corroborated($categoryId, $duration)) {
                $detail = $this->verifyWithCatalog($title);
                if ($detail !== null) {
                    return ['category' => ContentCategory::Cover, 'detail' => $detail];
                }

                // Known song confirmed but catalog lookup unavailable/missed —
                // still cover: weak keyword + music context is strong enough.
                return ['category' => ContentCategory::Cover, 'detail' => null];
            }

            return null;
        }

        return null;
    }

    /**
     * Heuristic-only classification for stored rows (no iTunes lookup).
     *
     * @param  array{
     *     title?: string|null,
     *     description?: string|null,
     *     category_id?: string|null,
     *     duration_seconds?: int|null,
     *     type?: string|null,
     * }  $video
     */
    public function classifyStored(array $video): ?ContentCategory
    {
        return $this->classify($video)['category'] ?? null;
    }

    /**
     * Strip decorative brackets/parentheses for catalog term extraction:
     * "【ヴィラン】歌ってみた / covered by ななし" → "歌ってみた / covered by ななし".
     */
    public function catalogTerm(string $title): string
    {
        $stripped = preg_replace('/【[^】]*】|\([^)]*\)|\[[^\]]*\]|「[^」]*」/u', ' ', $title) ?? $title;
        $stripped = trim(preg_replace('/\s+/u', ' ', $stripped) ?? '');

        if ($stripped === '' || mb_strlen($stripped) < 2) {
            return trim($title);
        }

        return $stripped;
    }

    /**
     * @return array{category_id: bool, duration: bool}|bool
     */
    private function corroborated(string $categoryId, int|string|null $duration): bool
    {
        $musicCategory = (string) config('vtual.content_classification.music_category_id', '10');
        if ($categoryId === $musicCategory) {
            return true;
        }

        $min = (int) config('vtual.content_classification.cover_duration.min', 90);
        $max = (int) config('vtual.content_classification.cover_duration.max', 420);

        return is_numeric($duration) && (int) $duration >= $min && (int) $duration <= $max;
    }

    private function verifyWithCatalog(string $title): ?string
    {
        return $this->itunes->findTrack($this->catalogTerm($title));
    }

    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Public so ContentKindClassifier (music/non-music layer) reuses the exact
     * same vocabulary instead of drifting from this list.
     *
     * @return list<string>
     */
    public function originalKeywords(): array
    {
        return ['オリジナル曲', 'オリジナル楽曲', 'オリジナルmv', 'original song', 'original mv', 'original tune'];
    }

    /**
     * @return list<string>
     */
    public function strongCoverKeywords(): array
    {
        return ['歌ってみた', '歌ってみました', '弾き語り', 'cover by', 'covered by', 'cover】', '】cover', 'cover]', '[cover'];
    }

    /**
     * @return list<string>
     */
    public function singingStreamKeywords(): array
    {
        return ['歌枠', 'カラオケ枠', 'singing stream', 'karaoke stream'];
    }

    /**
     * @return list<string>
     */
    private function weakCoverKeywords(): array
    {
        return ['cover', 'カバー', 'feat.', 'acoustic', '歌'];
    }
}
