<?php

namespace App\Domain\Vtual\Integrations\Music;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * iTunes Search API client (public, keyless — NOT a platform gateway contract).
 *
 * Used by sub-category classification for two things:
 *   1. proving that a "cover" signal names a real, known song (detail text);
 *   2. comparing the catalog artist against the uploader's channel title — the
 *      same name means the uploader released the song (original), a different
 *      name means they performed someone else's song (cover).
 *
 * Candidates are ranked by how much of the track title the query covers, NOT by
 * iTunes relevance or release date. Both alternatives are unreliable for short
 * Japanese titles: "プロポーズ" exists as unrelated songs by TUBE (2004) and
 * なとり (2025) before the vTuber release, and a cover single often chart-ranks
 * above the original.
 *
 * Never throws: any failure returns null/[] so the caller can leave
 * content_category unset.
 */
class ItunesSearchClient
{
    /**
     * Minimum share of a candidate's track title that the query must cover
     * before the candidate is considered at all. Guards against iTunes' loose
     * relevance ranking returning an unrelated same-word song.
     */
    private const MIN_TRACK_OVERLAP = 0.75;

    /**
     * Track-title tokens that mark a catalog entry as a cover release. Such an
     * entry is never evidence that the uploader owns the song — vTubers publish
     * their covers to streaming services under their own name.
     */
    private const COVER_TRACK_TOKENS = ['cover', 'カバー', '歌ってみた', 'selfcover'];

    public function __construct(
        private readonly ?string $country = 'JP',
    ) {}

    /**
     * Ranked catalog candidates for a term, best title match first. Cached per
     * term so one entry serves every caller (the artist hint is applied by the
     * caller, not baked into the cache).
     *
     * @return list<array{artist: string, track: string, label: string, released: ?string}>
     */
    public function search(string $term): array
    {
        $term = trim($term);
        if ($term === '') {
            return [];
        }

        $config = (array) config('vtual.content_classification.itunes', []);
        if (! ($config['enabled'] ?? true)) {
            return [];
        }

        // v2: the entry used to cache a plain label string; a shape change must
        // never be read back as an array.
        $cacheKey = 'vtual:itunes:candidates:v2:'.md5($term);

        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            /** @var list<array{artist: string, track: string, label: string, released: ?string}> $cached */
            return $cached;
        }

        $rows = $this->request($term, $config);

        Cache::put($cacheKey, $rows, (int) ($config['cache_ttl_hours'] ?? 168) * 3600);

        return $rows;
    }

    /**
     * Best single candidate. With an `$artistHint`, the first candidate whose
     * artist matches it wins outright — that is the "uploader released this
     * song" case, and it must not be shadowed by a higher-ranked cover single.
     *
     * @return array{artist: string, track: string, label: string, released: ?string}|null
     */
    public function findTrackData(string $term, ?string $artistHint = null): ?array
    {
        $candidates = $this->search($term);
        if ($candidates === []) {
            return null;
        }

        $hint = trim((string) $artistHint);
        if ($hint !== '') {
            foreach ($candidates as $candidate) {
                if ($this->artistMatchesChannel($candidate['artist'], $hint)) {
                    return $candidate;
                }
            }
        }

        return $candidates[0];
    }

    /**
     * Display label ("Artist - Track") of the best candidate, or null.
     */
    public function findTrack(string $term): ?string
    {
        return $this->findTrackData($term)['label'] ?? null;
    }

    /**
     * Channel-vs-catalog artist equality, tolerant of platform decorations:
     * "Kanaria - Topic", "Kanaria Official" and "KANARIA" all match "Kanaria".
     */
    public function artistMatchesChannel(string $artist, string $channelTitle): bool
    {
        $left = $this->normalizeName($artist);
        $right = $this->normalizeName($channelTitle);

        if ($left === '' || $right === '') {
            return false;
        }

        if ($left === $right) {
            return true;
        }

        // Containment only above a floor length: "ai" inside "Kairi" is noise.
        return mb_strlen($left) >= 4 && mb_strlen($right) >= 4
            && (str_contains($left, $right) || str_contains($right, $left));
    }

    /**
     * Does a free-text description credit this artist? Used to name the original
     * artist on a cover ("Lyrics & Music：内緒のピアス", "Original - … / supercell")
     * instead of reporting the coverer's own streaming release as the original.
     */
    public function creditMentions(string $description, string $artist): bool
    {
        $needle = $this->normalizeName($artist);
        if (mb_strlen($needle) < 2 || $description === '') {
            return false;
        }

        return str_contains($this->normalizeName($description), $needle);
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<array{artist: string, track: string, label: string, released: ?string}>
     */
    private function request(string $term, array $config): array
    {
        $limit = max(1, min(50, (int) ($config['limit'] ?? 25)));

        try {
            $response = Http::timeout((int) ($config['timeout'] ?? 5))
                ->acceptJson()
                ->retry(1, 200)
                ->get(($config['base_url'] ?? 'https://itunes.apple.com').'/search', [
                    'term' => $term,
                    'media' => 'music',
                    'entity' => 'song',
                    'limit' => $limit,
                    ...(is_string($this->country) && $this->country !== '' ? ['country' => $this->country] : []),
                ]);
        } catch (\Throwable $e) {
            Log::warning('iTunes Search lookup failed', ['term' => $term, 'error' => $e->getMessage()]);

            return [];
        }

        if (! $response->successful()) {
            return [];
        }

        $results = $response->json('results');
        if (! is_array($results)) {
            return [];
        }

        return $this->rank($term, $results);
    }

    /**
     * Keep the results whose track title is mostly covered by the query, best
     * overlap first.
     *
     * @param  list<mixed>  $results
     * @return list<array{artist: string, track: string, label: string, released: ?string}>
     */
    private function rank(string $term, array $results): array
    {
        $queryTokens = $this->tokens($term);
        if ($queryTokens === []) {
            return [];
        }

        $scored = [];
        foreach ($results as $result) {
            if (! is_array($result)) {
                continue;
            }

            $track = is_string($result['trackName'] ?? null) ? trim((string) $result['trackName']) : '';
            if ($track === '') {
                continue;
            }

            $trackTokens = $this->tokens($track);
            if ($trackTokens === []) {
                continue;
            }

            // A cover release is never evidence that the uploader owns the song.
            if (array_intersect($trackTokens, self::COVER_TRACK_TOKENS) !== []) {
                continue;
            }

            $overlap = count(array_intersect($queryTokens, $trackTokens)) / count($trackTokens);
            if ($overlap < self::MIN_TRACK_OVERLAP) {
                continue;
            }

            $artist = is_string($result['artistName'] ?? null) ? trim((string) $result['artistName']) : '';

            $scored[] = [
                'score' => $overlap,
                'artist' => $artist,
                'track' => $track,
                'label' => $artist !== '' ? $artist.' - '.$track : $track,
                'released' => is_string($result['releaseDate'] ?? null) ? $result['releaseDate'] : null,
            ];
        }

        usort($scored, fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_values(array_map(
            fn (array $row): array => [
                'artist' => $row['artist'],
                'track' => $row['track'],
                'label' => $row['label'],
                'released' => $row['released'],
            ],
            $scored,
        ));
    }

    /**
     * Lowercase alphanumeric token set (ASCII + katakana/hiragana/kanji runs kept).
     *
     * @return list<string>
     */
    private function tokens(string $value): array
    {
        $value = mb_strtolower(trim($value));
        if ($value === '') {
            return [];
        }

        $normalized = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $value) ?? '';
        $parts = preg_split('/\s+/u', trim($normalized)) ?: [];

        return array_values(array_unique(array_filter($parts, fn (string $p): bool => $p !== '')));
    }

    /**
     * Comparison-safe form: lowercase, platform decorations dropped, every
     * separator/punctuation/symbol removed.
     */
    private function normalizeName(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/(\s*[-–—]\s*topic|\s*(official|公式|channel|ch))\s*$/u', '', $value) ?? $value;

        return trim(preg_replace('/[\s\p{P}\p{S}]+/u', '', $value) ?? $value);
    }
}
