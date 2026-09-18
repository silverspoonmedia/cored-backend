<?php

namespace App\Domain\Vtual\Services;

use App\Domain\Vtual\Enums\ContentCategory;
use App\Domain\Vtual\Integrations\Music\ItunesSearchClient;

/**
 * Sub-category layer: is a musical video a cover, an original, or a singing
 * stream? Program signals plus an iTunes catalog check — no AI.
 *
 * Decision order (first match wins). Every branch reports the signal that
 * decided it, so the probe can explain a verdict instead of only showing it:
 *
 *   1. オリジナル曲 / "original song" in the title           → original
 *   2. 歌枠 / singing stream in the title                     → singing_stream
 *   3. 歌ってみた / "covered by" / "(cover)" in the title      → cover
 *   4. composer credit ("提供した楽曲") in title/description   → original
 *   5. the uploader's own tags label the video                 → that label
 *   6. 本家 / 原曲 plus a music link in the description        → cover
 *   7. bare "cover" / "カバー" plus music context              → cover
 *   8. catalog lookup: artist == uploader channel title        → original
 *      artist != uploader channel title                       → cover
 *
 * Step 7 used to carry `feat.`, `acoustic` and a bare `歌`, which mislabelled
 * originals: "DECO*27 - モニタリング (Best Friend Remix) feat. 初音ミク" and
 * "ジャバウォック / wotaku feat. 初音ミク" both became covers. Steps 4 and 8 are
 * the additions that turn remaining nulls into real verdicts:
 * "兎迷夢々さんに提供した楽曲です" proves the uploader wrote the song, and
 * 【GUMI】KING【Kanaria】 is resolved by the catalog artist matching the channel.
 *
 * Cover-art credits ("Cover Art: X", "Illustration: Y") are stripped before
 * matching — an original MV credits its illustrator and that must not read as a
 * cover performance label.
 *
 * Never throws; unknown → null (the async AI audit owns that bucket).
 */
class ContentCategoryClassifier
{
    public function __construct(
        private readonly ItunesSearchClient $itunes,
    ) {}

    /**
     * Full verdict, including which signal decided it.
     *
     * @param  array{
     *     title?: string|null,
     *     description?: string|null,
     *     category_id?: string|null,
     *     duration_seconds?: int|null,
     *     type?: string|null,
     *     tags?: list<string>|null,
     *     channel_title?: string|null,
     * }  $video
     * @return array{category: ?ContentCategory, detail: ?string, signal: string, catalog: ?string}
     */
    public function evaluate(array $video): array
    {
        $none = self::verdict(null);

        $title = $this->stripDecoys(mb_strtolower(trim((string) ($video['title'] ?? ''))));
        if ($title === '') {
            return $none;
        }

        $description = $this->stripDecoys(mb_strtolower(trim((string) ($video['description'] ?? ''))));
        $channelTitle = trim((string) ($video['channel_title'] ?? ''));
        $categoryId = (string) ($video['category_id'] ?? '');
        $duration = $video['duration_seconds'] ?? null;
        $haystack = $title.' '.$description;

        if ($this->containsAny($title, $this->originalKeywords())) {
            return self::verdict(ContentCategory::Original, null, 'original_keyword');
        }

        // 歌枠 / singing stream labels are unambiguous regardless of type
        // (VOD archives of singing streams keep the label).
        if ($this->containsAny($title, $this->singingStreamKeywords())) {
            return self::verdict(ContentCategory::SingingStream, null, 'singing_stream');
        }

        if ($this->containsAny($title, $this->strongCoverKeywords())
            || $this->containsAny($title, $this->coverParenKeywords())) {
            return self::verdict(ContentCategory::Cover, null, 'strong_cover');
        }

        // Composer's own release: "兎迷夢々さんに提供した楽曲です" — the uploader
        // wrote the song, so this video is the original even though the catalog
        // entry carries the performer's name. Checked after the title labels:
        // a cover described as "〇〇様の提供曲を歌ってみた" keeps its cover verdict.
        if ($this->containsAny($haystack, $this->provideMarkers())) {
            return self::verdict(ContentCategory::Original, null, 'original_provide');
        }

        $tagCategory = $this->categoryFromTags($video['tags'] ?? null);
        if ($tagCategory !== null) {
            $catalog = $this->catalogDetail($title, $description, $channelTitle);

            return self::verdict($tagCategory, $catalog, 'tags', $catalog);
        }

        if ($this->creditsOriginal($description)) {
            $catalog = $this->catalogDetail($title, $description, $channelTitle);

            return self::verdict(ContentCategory::Cover, $catalog, 'cover_provenance', $catalog);
        }

        // Weak signals (English "cover", "カバー") need corroboration before the
        // cover label sticks: Music category or song-length video.
        if ($this->containsAny($haystack, $this->weakCoverKeywords())) {
            if (! $this->corroborated($categoryId, $duration)) {
                return $none;
            }

            $catalog = $this->catalogDetail($title, $description, $channelTitle);

            // Known song confirmed but catalog lookup unavailable/missed —
            // still cover: weak keyword + music context is strong enough.
            return self::verdict(ContentCategory::Cover, $catalog, 'weak_cover', $catalog);
        }

        return $this->catalogVerdict($video, $title, $categoryId, $duration) ?? $none;
    }

    /**
     * Verdict without the signal, for callers that only need the label.
     *
     * @param  array{
     *     title?: string|null,
     *     description?: string|null,
     *     category_id?: string|null,
     *     duration_seconds?: int|null,
     *     type?: string|null,
     *     tags?: list<string>|null,
     *     channel_title?: string|null,
     * }  $video
     * @return array{category: ContentCategory, detail: ?string}|null
     */
    public function classify(array $video): ?array
    {
        $verdict = $this->evaluate($video);

        if ($verdict['category'] === null) {
            return null;
        }

        return ['category' => $verdict['category'], 'detail' => $verdict['detail']];
    }

    /**
     * Classification for stored rows, which carry no tags.
     *
     * Runs the catalog fallback too (cached per term), so a keyword-free
     * original can resolve without the AI audit.
     *
     * @param  array{
     *     title?: string|null,
     *     description?: string|null,
     *     category_id?: string|null,
     *     duration_seconds?: int|null,
     *     type?: string|null,
     *     tags?: list<string>|null,
     *     channel_title?: string|null,
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
     * Catalog artist test — the only branch that can name a keyword-free
     * original. Runs last: every explicit marker above is cheaper and more
     * precise, and this one costs an HTTP call (cached per term).
     *
     * @param  array<string, mixed>  $video
     * @return array{category: ?ContentCategory, detail: ?string, signal: string, catalog: ?string}|null
     */
    private function catalogVerdict(array $video, string $title, string $categoryId, mixed $duration): ?array
    {
        if (! (bool) config('vtual.content_classification.itunes.artist_disambiguation', true)) {
            return null;
        }

        $channelTitle = trim((string) ($video['channel_title'] ?? ''));

        // Without the uploader's channel name there is nothing to compare
        // against, and the check only earns its HTTP call in music context.
        if ($channelTitle === '' || ! $this->corroborated($categoryId, $duration)) {
            return null;
        }

        $track = $this->itunes->findTrackData($this->catalogQuery($title), $channelTitle);
        if ($track === null) {
            return null;
        }

        if ($this->itunes->artistMatchesChannel($track['artist'], $channelTitle)) {
            return self::verdict(ContentCategory::Original, null, 'catalog_artist_match', $track['label']);
        }

        return self::verdict(ContentCategory::Cover, $track['label'], 'catalog_artist_mismatch', $track['label']);
    }

    /**
     * Attribution text for a cover verdict ("who wrote the song"), or null.
     *
     * A candidate credited in the description wins: composers are credited there
     * ("Lyrics & Music：内緒のピアス"), and the coverer's own streaming release is
     * exactly what must NOT be reported as the original. The uploader's own
     * release is skipped for the same reason.
     */
    private function catalogDetail(string $title, string $description, string $channelTitle): ?string
    {
        $candidates = $this->detailCandidates($title);
        if ($candidates === []) {
            return null;
        }

        foreach ($candidates as $candidate) {
            if ($candidate['artist'] === '' || $this->itunes->artistMatchesChannel($candidate['artist'], $channelTitle)) {
                continue;
            }

            if ($description !== '' && $this->itunes->creditMentions($description, $candidate['artist'])) {
                return $candidate['label'];
            }
        }

        foreach ($candidates as $candidate) {
            if ($channelTitle === '' || ! $this->itunes->artistMatchesChannel($candidate['artist'], $channelTitle)) {
                return $candidate['label'];
            }
        }

        return null;
    }

    /**
     * Candidates for the attribution text, best first.
     *
     * The song-title query leads: a vTuber title is usually "SONG / uploader", and
     * searching the whole string drags the uploader's name into the query, which
     * can push the original release out of the result page entirely
     * ("プロポーズ/ 9Lana" no longer returns 内緒のピアス). The full term is kept as
     * a fallback for titles whose first segment is the artist, not the song.
     *
     * @return list<array{artist: string, track: string, label: string, released: ?string}>
     */
    private function detailCandidates(string $title): array
    {
        $songTerm = $this->catalogSongQuery($title);
        $fullTerm = $this->catalogQuery($title);

        $merged = [];
        $seen = [];

        foreach ([$songTerm, $fullTerm] as $term) {
            if ($term === '') {
                continue;
            }

            foreach ($this->itunes->search($term) as $candidate) {
                if (isset($seen[$candidate['label']])) {
                    continue;
                }
                $seen[$candidate['label']] = true;
                $merged[] = $candidate;
            }
        }

        return $merged;
    }

    /**
     * Search term for the catalog: the title without decorations and without the
     * cover label itself ("magnet - くろくも cover" → "magnet - くろくも").
     * Leaving "cover" in the term biases iTunes toward cover singles.
     */
    private function catalogQuery(string $title): string
    {
        $term = $this->catalogTerm($title);

        foreach (array_merge($this->weakCoverKeywords(), $this->coverParenKeywords()) as $needle) {
            if ($needle !== '') {
                $term = str_replace($needle, ' ', $term);
            }
        }

        $term = trim(preg_replace('/\s+/u', ' ', $term) ?? $term);

        return $term === '' ? $this->catalogTerm($title) : $term;
    }

    /**
     * Leading segment of the catalog query — the song title in the common
     * "SONG / uploader" and "SONG - uploader" vTuber title shapes. Falls back to
     * the whole term when the split yields nothing usable.
     */
    private function catalogSongQuery(string $title): string
    {
        $term = $this->catalogQuery($title);
        $parts = preg_split('/\s*[\/|｜]\s*|\s+[-–—]\s+/u', $term) ?: [];
        $first = trim((string) ($parts[0] ?? ''));

        return mb_strlen($first) >= 2 ? $first : $term;
    }

    /**
     * @return array{category: ?ContentCategory, detail: ?string, signal: string, catalog: ?string}
     */
    private static function verdict(
        ?ContentCategory $category,
        ?string $detail = null,
        string $signal = 'none',
        ?string $catalog = null,
    ): array {
        return ['category' => $category, 'detail' => $detail, 'signal' => $signal, 'catalog' => $catalog];
    }

    /**
     * Remove cover-ART credit fragments ("Cover Art: X", "Illustration: Y")
     * before keyword matching. An original MV credits its illustrator, and that
     * credit must never read as a cover PERFORMANCE label.
     */
    private function stripDecoys(string $value): string
    {
        foreach ($this->coverDecoyPhrases() as $phrase) {
            if ($phrase !== '') {
                $value = str_replace($phrase, ' ', $value);
            }
        }

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
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

    /**
     * The uploader's own tag labels. Original wins over cover when both are
     * present: artists tag an original MV オリジナル曲 far more deliberately
     * than they abstain from the generic "cover" tag.
     */
    private function categoryFromTags(mixed $tags): ?ContentCategory
    {
        $list = [];
        foreach ((array) $tags as $tag) {
            if (is_string($tag) && trim($tag) !== '') {
                $list[] = mb_strtolower(trim($tag));
            }
        }

        if ($list === []) {
            return null;
        }

        $joined = implode(' ', $list);

        if ($this->containsAny($joined, $this->configList('tag_original', []))) {
            return ContentCategory::Original;
        }

        if ($this->containsAny($joined, $this->configList('tag_singing_stream', []))) {
            return ContentCategory::SingingStream;
        }

        if ($this->containsAny($joined, $this->configList('tag_cover', []))) {
            return ContentCategory::Cover;
        }

        return null;
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
     * Composer-side credit phrases. Phrase-level on purpose: the bare 提供 also
     * appears in "音源提供" (an instrumental licensed TO a cover singer), which
     * must not read as original.
     *
     * @return list<string>
     */
    private function provideMarkers(): array
    {
        return $this->configList('original_provide_markers', ['提供した楽曲', '提供曲']);
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
     * Cover labels wrapped in brackets — the label itself, as opposed to the
     * bare word that also shows up in art credits.
     *
     * @return list<string>
     */
    private function coverParenKeywords(): array
    {
        return $this->configList('cover_paren_keywords', ['(cover)', '（cover）', '( cover )', '(カバー)', '（カバー）']);
    }

    /**
     * Deliberately tiny. Each entry dropped from this list has a real corpus case:
     *   - `feat.`    → "DECO*27 - モニタリング (Best Friend Remix) feat. 初音ミク"
     *                  and "ジャバウォック / wotaku feat. 初音ミク" are originals.
     *   - `acoustic` → an acoustic take on the uploader's own song is original.
     *   - bare `歌`  → matches 歌おう / この歌は / 歌詞 inside any description.
     * Recall is unaffected: 歌ってみた, 歌枠 and カラオケ carry the Japanese cases;
     * "cover by / covered by" and "(cover)" carry the English ones.
     *
     * @return list<string>
     */
    private function weakCoverKeywords(): array
    {
        return ['cover', 'カバー'];
    }

    /**
     * @return list<string>
     */
    private function coverDecoyPhrases(): array
    {
        return $this->configList('cover_decoy_phrases', [
            'cover art', 'cover illustration', 'cover design', 'cover photo',
            'cover image', 'artwork', 'illustration', 'illust', 'jacket',
        ]);
    }

    /**
     * A description crediting an original release, but only when it also links
     * a video — "本家様：https://youtu.be/…" is a cover's provenance block.
     *
     * Only 本家 / 原曲 count here. `original_credit_markers` (which includes the
     * English word "original") belongs to the music/non-music layer: an original
     * MV's own description says "Original MV" right next to a YouTube link.
     */
    private function creditsOriginal(string $description): bool
    {
        if ($description === '' || ! $this->containsAny($description, $this->coverProvenanceMarkers())) {
            return false;
        }

        return $this->containsAny($description, $this->provenanceLinkHosts());
    }

    /**
     * @return list<string>
     */
    private function coverProvenanceMarkers(): array
    {
        return $this->configList('cover_provenance_markers', ['本家', '原曲']);
    }

    /**
     * @return list<string>
     */
    private function provenanceLinkHosts(): array
    {
        return ['youtu.be', 'youtube.com', 'nicovideo.jp', 'nico.ms'];
    }

    /**
     * @param  list<string>  $default
     * @return list<string>
     */
    private function configList(string $key, array $default): array
    {
        /** @var list<string> $values */
        $values = (array) config('vtual.content_classification.'.$key, $default);

        return array_values(array_filter(
            array_map(fn (mixed $value): string => mb_strtolower(trim((string) $value)), $values),
            fn (string $value): bool => $value !== '',
        ));
    }
}
