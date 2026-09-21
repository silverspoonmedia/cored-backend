<?php

namespace App\Domain\Vtual\Services;

use App\Domain\Vtual\Enums\ContentKind;

/**
 * Primary layer: music vs non-music. Pure program signals — no AI, no HTTP.
 *
 * Why not categoryId alone: vTubers routinely file covers under 20 (Gaming),
 * 22 (People & Blogs) or 24 (Entertainment) instead of 10 (Music). Live probe
 * of real cover videos showed categoryId 22 and 20 while all of them carried
 * the YouTube Music topic. So categoryId is one signal among several, never
 * the decider.
 *
 * Signal order (first match wins):
 *
 *   1. topicCategories contains the Music topic      → music
 *   2. categoryId === the Music category             → music
 *   3. music keyword in the title or the uploader's tags → music
 *   4. description credits an original + links a video   → music
 *   5. nothing matched and the video is long-form    → non_music
 *   6. nothing matched, short or unknown duration    → null (unknown)
 *
 * The null bucket is deliberate: a short video we cannot read is exactly what
 * the asynchronous AI audit exists for, and guessing "non_music" there is the
 * false negative this class was written to remove.
 *
 * Keywords are read from the title and tags only — never the description.
 * Gaming videos credit their background music, so a description-only keyword
 * match would flip them to music.
 *
 * Never throws; unresolvable → null.
 */
class ContentKindClassifier
{
    public function __construct(
        private readonly ContentCategoryClassifier $categories,
    ) {}

    /**
     * @param  array{
     *     title?: string|null,
     *     description?: string|null,
     *     category_id?: string|null,
     *     duration_seconds?: int|null,
     *     topic_categories?: list<string>|null,
     *     tags?: list<string>|null,
     * }  $video
     * @return array{kind: ?ContentKind, signal: ?string}
     */
    public function classify(array $video): array
    {
        $title = mb_strtolower(trim((string) ($video['title'] ?? '')));
        $description = mb_strtolower(trim((string) ($video['description'] ?? '')));
        $categoryId = trim((string) ($video['category_id'] ?? ''));
        $topics = $this->stringList($video['topic_categories'] ?? null);
        $tags = $this->stringList($video['tags'] ?? null);
        $duration = $video['duration_seconds'] ?? null;

        if ($this->hasMusicTopic($topics)) {
            return ['kind' => ContentKind::Music, 'signal' => 'topic'];
        }

        if ($categoryId !== '' && $categoryId === $this->musicCategoryId()) {
            return ['kind' => ContentKind::Music, 'signal' => 'category_id'];
        }

        if ($this->hasMusicKeyword($title, $tags)) {
            return ['kind' => ContentKind::Music, 'signal' => 'keyword'];
        }

        if ($this->creditsOriginal($description)) {
            return ['kind' => ContentKind::Music, 'signal' => 'original_credit'];
        }

        if (is_numeric($duration) && (int) $duration >= $this->longFormMinSeconds()) {
            return ['kind' => ContentKind::NonMusic, 'signal' => 'long_form'];
        }

        return ['kind' => null, 'signal' => null];
    }

    /**
     * Convenience for callers that only need the verdict.
     *
     * @param  array{
     *     title?: string|null,
     *     description?: string|null,
     *     category_id?: string|null,
     *     duration_seconds?: int|null,
     *     topic_categories?: list<string>|null,
     *     tags?: list<string>|null,
     * }  $video
     */
    public function kind(array $video): ?ContentKind
    {
        return $this->classify($video)['kind'];
    }

    /**
     * @param  list<string>  $topics
     */
    private function hasMusicTopic(array $topics): bool
    {
        $needle = mb_strtolower($this->musicTopic());
        if ($needle === '') {
            return false;
        }

        foreach ($topics as $topic) {
            if (mb_strtolower($topic) === $needle) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $tags
     */
    private function hasMusicKeyword(string $title, array $tags): bool
    {
        $haystacks = [$title];
        foreach ($tags as $tag) {
            $haystacks[] = mb_strtolower($tag);
        }

        foreach ($haystacks as $haystack) {
            if ($haystack === '') {
                continue;
            }

            foreach ($this->musicKeywords() as $keyword) {
                if ($this->matchesKeyword($haystack, $keyword)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Latin keywords match on a boundary so "cover" cannot fire on "discover"
     * or "coverage", and "mv" cannot fire inside a longer word. CJK keywords
     * have no word boundaries — plain substring matching is correct there.
     */
    private function matchesKeyword(string $haystack, string $keyword): bool
    {
        if ($keyword === '') {
            return false;
        }

        if (preg_match('/^[\x20-\x7E]+$/', $keyword) !== 1) {
            return str_contains($haystack, $keyword);
        }

        $pattern = preg_quote($keyword, '/');
        if (preg_match('/^[a-z0-9]/', $keyword) === 1) {
            $pattern = '(?<![a-z0-9])'.$pattern;
        }
        if (preg_match('/[a-z0-9]$/', $keyword) === 1) {
            $pattern .= '(?![a-z0-9])';
        }

        return preg_match('/'.$pattern.'/u', $haystack) === 1;
    }

    /**
     * A description crediting an original release, but only when it also links
     * a video — "本家様：https://youtu.be/…" is a cover's provenance block.
     * The bare marker without a link is too weak (album promos say オリジナル曲
     * too, and gaming descriptions say "original" about all sorts of things).
     */
    private function creditsOriginal(string $description): bool
    {
        if ($description === '' || ! $this->containsAny($description, $this->originalCreditMarkers())) {
            return false;
        }

        return $this->containsAny($description, $this->originalCreditLinkHosts());
    }

    /**
     * Recall-oriented vocabulary: the goal is to catch a real music video whose
     * categoryId lies, and a false positive here costs one AI audit row.
     *
     * Deliberately excluded: the bare "歌" that ContentCategoryClassifier's
     * weakCoverKeywords() carries — it appears inside 歌枠/歌ってみた anyway, and
     * on its own it matches ordinary talk titles.
     *
     * @return list<string>
     */
    private function musicKeywords(): array
    {
        return array_merge(
            [
                'mv', 'music video', 'ミュージックビデオ', 'off vocal', 'instrumental',
                'カラオケ', '楽曲', 'feat.',
            ],
            $this->categories->originalKeywords(),
            $this->categories->strongCoverKeywords(),
            $this->categories->singingStreamKeywords(),
            ['cover', 'カバー'],
        );
    }

    /**
     * @return list<string>
     */
    private function originalCreditMarkers(): array
    {
        /** @var list<string> $markers */
        $markers = (array) config('vtual.content_classification.original_credit_markers', ['本家', '原曲', 'original']);

        return array_values(array_filter(
            array_map(fn (mixed $marker): string => mb_strtolower(trim((string) $marker)), $markers),
            fn (string $marker): bool => $marker !== '',
        ));
    }

    /**
     * @return list<string>
     */
    private function originalCreditLinkHosts(): array
    {
        return ['youtu.be', 'youtube.com', 'nicovideo.jp', 'nico.ms'];
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

    private function musicCategoryId(): string
    {
        return trim((string) config('vtual.content_classification.music_category_id', '10'));
    }

    private function musicTopic(): string
    {
        return trim((string) config('vtual.content_classification.music_topic', 'https://en.wikipedia.org/wiki/Music'));
    }

    private function longFormMinSeconds(): int
    {
        return max(1, (int) config('vtual.content_classification.long_form_min_seconds', 600));
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $entry) {
            if (is_string($entry) && trim($entry) !== '') {
                $out[] = $entry;
            }
        }

        return $out;
    }
}
