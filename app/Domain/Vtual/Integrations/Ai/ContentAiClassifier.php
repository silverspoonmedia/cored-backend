<?php

namespace App\Domain\Vtual\Integrations\Ai;

use App\Domain\Ai\Services\AiConnectionResolver;
use App\Domain\Ai\Support\AiConnectionData;
use App\Domain\Vtual\Enums\ContentCategory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * AI audit client for the music sub-category layer (cover / original /
 * singing_stream). OpenAI-compatible chat-completions, JSON mode.
 *
 * Scope guard: this client NEVER decides the music/non-music layer — that
 * stays program-only (ContentKind::fromCategoryId). It only fills sub-category
 * gaps the heuristic classifier could not resolve.
 *
 * Never throws: no bound AI connection, HTTP failure, malformed JSON, or
 * low confidence all return null so the caller leaves content_category null.
 */
class ContentAiClassifier
{
    public function __construct(
        private readonly AiConnectionResolver $connections,
    ) {}

    /**
     * Classify one music video's sub-category.
     *
     * @param  array{
     *     external_id?: string|null,
     *     title?: string|null,
     *     description?: string|null,
     *     duration_seconds?: int|null,
     * }  $video
     * @return array{category: ContentCategory, detail: ?string, confidence: float}|null
     */
    public function classifyMusicContent(array $video): ?array
    {
        $config = $this->config();
        $connection = $this->connection($config);
        if (! $this->configured($config) || $connection === null) {
            return null;
        }

        $externalId = trim((string) ($video['external_id'] ?? ''));
        $title = trim((string) ($video['title'] ?? ''));
        if ($title === '') {
            return null;
        }

        $cacheKey = 'vtual:ai:classify:'.md5($externalId !== '' ? $externalId : $title);
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached['category'] !== null && isset($cached['confidence'])
                ? $cached
                : null;
        }

        $result = $this->request($config, $connection, $video);

        Cache::put($cacheKey, [
            'category' => $result !== null ? $result['category'] : null,
            'detail' => $result['detail'] ?? null,
            'confidence' => $result['confidence'] ?? 0.0,
        ], max(1, (int) ($config['cache_ttl_hours'] ?? 168)) * 3600);

        return $result;
    }

    /**
     * Whether the AI audit pass is enabled AND has a usable connection.
     */
    public function enabled(): bool
    {
        $config = $this->config();

        return $this->configured($config) && $this->connection($config) !== null;
    }

    /**
     * Tier B knobs + the (optional) per-consumer connection binding.
     *
     * @return array<string, mixed>
     */
    private function config(): array
    {
        return [
            'enabled' => (bool) setting_config('vtual.content_classification.ai.enabled', false),
            'connection' => trim((string) setting_config('vtual.content_classification.ai.connection', '')),
            'model' => trim((string) setting_config('vtual.content_classification.ai.model', '')),
            'timeout' => (int) setting_config('vtual.content_classification.ai.timeout', 15),
            'max_tokens' => (int) setting_config('vtual.content_classification.ai.max_tokens', 200),
            'min_confidence' => (float) setting_config('vtual.content_classification.ai.min_confidence', 0.7),
            'cache_ttl_hours' => (int) setting_config('vtual.content_classification.ai.cache_ttl_hours', 168),
        ];
    }

    /**
     * Endpoint + API key come from the core Ai domain (Tier C).
     *
     * @param  array<string, mixed>  $config
     */
    private function connection(array $config): ?AiConnectionData
    {
        $slug = trim((string) ($config['connection'] ?? ''));

        return $this->connections->resolve($slug !== '' ? $slug : null);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function configured(array $config): bool
    {
        return ($config['enabled'] ?? false) === true
            && trim((string) ($config['model'] ?? '')) !== '';
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array{title?: string|null, description?: string|null, duration_seconds?: int|null}  $video
     * @return array{category: ContentCategory, detail: ?string, confidence: float}|null
     */
    private function request(array $config, AiConnectionData $connection, array $video): ?array
    {
        $timeout = $connection->timeoutSeconds ?? (int) $config['timeout'];
        $connectTimeout = $connection->connectTimeoutSeconds ?? 10;

        try {
            $client = Http::withToken($connection->apiKey)
                ->timeout(max(5, $timeout))
                ->connectTimeout(max(1, $connectTimeout))
                ->acceptJson();

            if ($connection->headers !== []) {
                $client = $client->withHeaders($connection->headers);
            }

            $response = $client->post($connection->endpoint, [
                'model' => (string) $config['model'],
                'stream' => false,
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.0,
                'max_tokens' => max(50, (int) ($config['max_tokens'] ?? 200)),
                'messages' => [
                    ['role' => 'system', 'content' => $this->systemPrompt()],
                    ['role' => 'user', 'content' => $this->userPrompt($video)],
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('vtual.ai_classification_failed', ['error' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        return $this->parse($response->json('choices.0.message.content'), (float) $config['min_confidence']);
    }

    /**
     * @return array{category: ContentCategory, detail: ?string, confidence: float}|null
     */
    private function parse(?string $content, float $minConfidence): ?array
    {
        if (! is_string($content) || trim($content) === '') {
            return null;
        }

        $decoded = $this->decodeJson($content);

        if (! is_array($decoded)) {
            return null;
        }

        $categoryValue = is_string($decoded['category'] ?? null) ? trim((string) $decoded['category']) : '';
        if (! in_array($categoryValue, ContentCategory::values(), true)) {
            return null;
        }

        $confidence = is_numeric($decoded['confidence'] ?? null) ? (float) $decoded['confidence'] : 0.0;
        if ($confidence < $minConfidence) {
            return null;
        }

        $detail = is_string($decoded['detail'] ?? null) ? trim((string) $decoded['detail']) : '';

        return [
            'category' => ContentCategory::from($categoryValue),
            'detail' => $detail !== '' ? $detail : null,
            'confidence' => $confidence,
        ];
    }

    /**
     * Chat-completions endpoints frequently wrap the JSON body in a markdown
     * fence (```json … ```) or pad it with prose, even under
     * `response_format: json_object`. Decode the raw body first, then the
     * outermost `{…}` object.
     *
     * @return array<string, mixed>|null
     */
    private function decodeJson(string $content): ?array
    {
        $decoded = json_decode($content, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/\{.*\}/s', $content, $matches) === 1) {
            $decoded = json_decode($matches[0], true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    private function systemPrompt(): string
    {
        return <<<'TXT'
            You audit vTuber (virtual YouTuber) YouTube videos that are already known to be music.
            Decide the sub-category. Reply with JSON only: {"category": "cover"|"original"|"singing_stream", "detail": string|null, "confidence": number 0-1}.

            Definitions:
            - original: an original song / MV by the vTuber or their group (オリジナル曲, オリジナルMV, "original song").
            - singing_stream: a singing/karaoke live stream or its archive (歌枠, カラオケ枠, singing stream).
            - cover: a performance of an existing song written by someone else (歌ってみた, covered by, cover).
            - detail: for covers only, the best-known original song attribution "Artist - Track" if you are confident; otherwise null.

            Japanese vTuber culture is the primary context (utaite / vocaloid covers). If you cannot
            decide confidently, use confidence below 0.5 rather than guessing.
            TXT;
    }

    /**
     * @param  array{title?: string|null, description?: string|null, duration_seconds?: int|null}  $video
     */
    private function userPrompt(array $video): string
    {
        $lines = ['Title: '.trim((string) ($video['title'] ?? ''))];

        $description = trim((string) ($video['description'] ?? ''));
        if ($description !== '') {
            $lines[] = 'Description: '.mb_substr($description, 0, 600);
        }

        $duration = $video['duration_seconds'] ?? null;
        if (is_numeric($duration)) {
            $lines[] = 'Duration: '.(int) $duration.' seconds';
        }

        return implode("\n", $lines);
    }
}
