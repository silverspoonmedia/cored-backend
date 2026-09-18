<?php

namespace App\Domain\Vtual\Integrations\Music;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * iTunes Search API client (public, keyless — NOT a platform gateway contract).
 *
 * Used by content classification to verify that a weak "cover" signal refers
 * to a real, known song. Never throws: any failure returns null so the caller
 * can leave content_category unset.
 */
class ItunesSearchClient
{
    public function __construct(
        private readonly ?string $country = 'JP',
    ) {}

    /**
     * Look up a track by free-text term. Returns the best-match display label
     * ("Artist - Track") or null when no confident match.
     */
    public function findTrack(string $term): ?string
    {
        $term = trim($term);
        if ($term === '') {
            return null;
        }

        $config = (array) config('vtual.content_classification.itunes', []);
        if (! ($config['enabled'] ?? true)) {
            return null;
        }

        $cacheKey = 'vtual:itunes:search:'.md5($term);
        $cached = Cache::get($cacheKey);
        if (is_string($cached)) {
            return $cached === '' ? null : $cached;
        }

        $result = $this->search($term);

        Cache::put($cacheKey, $result ?? '', (int) ($config['cache_ttl_hours'] ?? 168) * 3600);

        return $result;
    }

    private function search(string $term): ?string
    {
        $config = (array) config('vtual.content_classification.itunes', []);
        $limit = max(1, min(10, (int) ($config['limit'] ?? 5)));

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

            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $results = $response->json('results');
        if (! is_array($results) || $results === []) {
            return null;
        }

        $best = $this->bestMatch($term, $results);

        if ($best === null) {
            return null;
        }

        $artist = is_string($best['artistName'] ?? null) ? trim((string) $best['artistName']) : '';
        $track = is_string($best['trackName'] ?? null) ? trim((string) $best['trackName']) : '';

        if ($track === '') {
            return null;
        }

        return $artist !== '' ? $artist.' - '.$track : $track;
    }

    /**
     * Pick the result whose normalized track/artist tokens overlap the query
     * most. iTunes ranking alone is too loose (exact "term" hit required here).
     *
     * @param  list<array<string, mixed>>  $results
     * @return array<string, mixed>|null
     */
    private function bestMatch(string $term, array $results): ?array
    {
        $queryTokens = $this->tokens($term);
        if ($queryTokens === []) {
            return null;
        }

        $best = null;
        $bestScore = 0.0;
        foreach ($results as $result) {
            if (! is_array($result)) {
                continue;
            }

            $track = is_string($result['trackName'] ?? null) ? (string) $result['trackName'] : '';
            if ($track === '') {
                continue;
            }

            $trackTokens = $this->tokens($track);
            if ($trackTokens === []) {
                continue;
            }

            $overlap = count(array_intersect($queryTokens, $trackTokens)) / count($trackTokens);
            if ($overlap > $bestScore) {
                $bestScore = $overlap;
                $best = $result;
            }
        }

        // Require most of the track title to be covered by the query.
        return $bestScore >= 0.75 ? $best : null;
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
}
