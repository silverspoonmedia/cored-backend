<?php

namespace App\Domain\Vtual\Integrations\Ai;

use App\Domain\Ai\Services\AiConnectionResolver;
use App\Domain\Ai\Support\AiConnectionData;
use App\Domain\Vtual\Enums\ContentCategory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * AI audit client for content classification. Never runs inline on the hydrate
 * path — it drains the `vtual_content_ai_audit` pool from a scheduled command.
 * OpenAI-compatible chat-completions, JSON mode.
 *
 * Scope: this is the fallback pass for rows the program classifier could not
 * resolve. It may set both layers (content_type and content_category), because
 * the program layer deliberately leaves ambiguous short-form videos unknown.
 * Where the program layer produced a verdict, that verdict is authoritative.
 *
 * Never throws: no bound AI connection, HTTP failure, malformed JSON, or
 * low confidence all return null so the caller leaves the row untouched.
 */
class ContentAiClassifier
{
    public function __construct(
        private readonly AiConnectionResolver $connections,
    ) {}

    /**
     * Decide both layers for one video.
     *
     * `is_music` is always present when a result comes back; `category` is null
     * whenever `is_music` is false, and `detail` is cover-only attribution.
     *
     * @param  array{
     *     external_id?: string|null,
     *     title?: string|null,
     *     description?: string|null,
     *     category_id?: string|null,
     *     duration_seconds?: int|null,
     *     topic_categories?: list<string>|null,
     *     tags?: list<string>|null,
     *     channel_title?: string|null,
     *     program_verdict?: array{
     *         content_type?: string|null,
     *         content_category?: string|null,
     *         content_category_detail?: string|null,
     *         signal?: string|null,
     *         catalog?: string|null,
     *     }|null,
     * }  $video
     * @return array{is_music: bool, category: ?ContentCategory, detail: ?string, confidence: float}|null
     */
    public function classifyContent(array $video): ?array
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
        if (is_array($cached) && isset($cached['is_music'], $cached['confidence'])) {
            return [
                'is_music' => (bool) $cached['is_music'],
                'category' => $cached['category'] ?? null,
                'detail' => $cached['detail'] ?? null,
                'confidence' => (float) $cached['confidence'],
            ];
        }

        $result = $this->request($config, $connection, $video);

        // Cache the whole verdict, including the negatives: a "not music" call
        // is as expensive as a positive one, and the pool must not re-ask.
        Cache::put($cacheKey, [
            'is_music' => $result['is_music'] ?? false,
            'category' => $result['category'] ?? null,
            'detail' => $result['detail'] ?? null,
            'confidence' => $result['confidence'] ?? 0.0,
        ], max(1, (int) ($config['cache_ttl_hours'] ?? 168)) * 3600);

        return $result;
    }

    /**
     * Classify only the music sub-category, for callers that already know the
     * row is music. Returns null when the AI says it is not music at all.
     *
     * @param  array{
     *     external_id?: string|null,
     *     title?: string|null,
     *     description?: string|null,
     *     category_id?: string|null,
     *     duration_seconds?: int|null,
     *     topic_categories?: list<string>|null,
     *     tags?: list<string>|null,
     *     channel_title?: string|null,
     *     program_verdict?: array{
     *         content_type?: string|null,
     *         content_category?: string|null,
     *         content_category_detail?: string|null,
     *         signal?: string|null,
     *         catalog?: string|null,
     *     }|null,
     * }  $video
     * @return array{category: ContentCategory, detail: ?string, confidence: float}|null
     */
    public function classifyMusicContent(array $video): ?array
    {
        $result = $this->classifyContent($video);

        if ($result === null || ! $result['is_music'] || $result['category'] === null) {
            return null;
        }

        return [
            'category' => $result['category'],
            'detail' => $result['detail'],
            'confidence' => $result['confidence'],
        ];
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
     * @param  array<string, mixed>  $video
     * @return array{is_music: bool, category: ?ContentCategory, detail: ?string, confidence: float}|null
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
                'max_tokens' => max(256, (int) ($config['max_tokens'] ?? 4000)),
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
     * @return array{is_music: bool, category: ?ContentCategory, detail: ?string, confidence: float}|null
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

        // A missing/unreadable is_music is a malformed answer, not a "no":
        // guessing false here would wrongly write non_music onto the row.
        $isMusic = $this->toBool($decoded['is_music'] ?? null);
        if ($isMusic === null) {
            return null;
        }

        $confidence = is_numeric($decoded['confidence'] ?? null) ? (float) $decoded['confidence'] : 0.0;
        if ($confidence < $minConfidence) {
            return null;
        }

        if (! $isMusic) {
            return [
                'is_music' => false,
                'category' => null,
                'detail' => null,
                'confidence' => $confidence,
            ];
        }

        // is_music true with no sub-category at all is still a valid layer-1
        // verdict — the music/non-music answer is the point, the sub-category
        // is a bonus. But an explicit *unknown* category next to is_music true
        // is a self-contradictory answer, so treat that as malformed.
        $categoryValue = is_string($decoded['category'] ?? null) ? trim((string) $decoded['category']) : '';
        if ($categoryValue !== '' && ! in_array($categoryValue, ContentCategory::values(), true)) {
            return null;
        }

        $category = $categoryValue !== '' ? ContentCategory::from($categoryValue) : null;

        $detail = is_string($decoded['detail'] ?? null) ? trim((string) $decoded['detail']) : '';

        return [
            'is_music' => true,
            'category' => $category,
            'detail' => $category !== null && $detail !== '' ? $detail : null,
            'confidence' => $confidence,
        ];
    }

    /**
     * Strict boolean reader. Endpoints that ignore json_object and answer in
     * prose often emit "true"/"false" strings instead of real booleans.
     */
    private function toBool(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return match (mb_strtolower(trim($value))) {
                'true', 'yes', '1' => true,
                'false', 'no', '0' => false,
                default => null,
            };
        }

        if (is_int($value)) {
            return match ($value) {
                1 => true,
                0 => false,
                default => null,
            };
        }

        return null;
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
            You classify vTuber (virtual YouTuber) YouTube videos for a catalogue.
            Decide two things: whether the video is music, and if so its sub-category.
            Reply with JSON only: {"is_music": boolean, "category": "cover"|"original"|"singing_stream"|null, "detail": string|null, "confidence": number 0-1}.

            is_music:
            - true for a song recording or a singing performance: original songs/MVs, covers (歌ってみた), singing streams (歌枠), karaoke.
            - false for gameplay, let's plays, talk/雑談 streams, ASMR, unboxing, announcements, clips, variety shows.
            - A video that merely uses background music is NOT music.
            - When is_music is false, category MUST be null.

            category (only when is_music is true):
            - original: an original song / MV by the vTuber or their group (オリジナル曲, オリジナルMV, "original song").
            - singing_stream: a singing/karaoke live stream or its archive (歌枠, カラオケ枠, singing stream).
            - cover: a performance of an existing song written by someone else (歌ってみた, covered by, cover).
            - detail: for covers only, the best-known original song attribution "Artist - Track" if you are confident; otherwise null.

            The program classifier already ran and its verdict is provided as evidence. It is authoritative
            for any layer it decided (a stated content type, or a stated sub-category): do not contradict it,
            and fill in only the layers it left undecided. When the evidence includes a catalogue match,
            treat that as the strongest available signal for original vs cover — a catalogue artist equal to
            the uploader means the uploader wrote the song (original), a different artist means a cover.

            Japanese vTuber culture is the primary context (utaite / vocaloid covers). The category id and
            topics are hints only — vTubers file covers under Gaming or People & Blogs, so trust the
            title and description over the category id.
            If you cannot decide confidently, use confidence below 0.5 rather than guessing.
            TXT;
    }

    /**
     * @param  array<string, mixed>  $video
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

        $categoryId = trim((string) ($video['category_id'] ?? ''));
        if ($categoryId !== '') {
            $lines[] = 'Platform category id: '.$categoryId;
        }

        $topics = $this->stringList($video['topic_categories'] ?? null);
        if ($topics !== []) {
            $lines[] = 'Platform topics: '.implode(', ', array_slice($topics, 0, 10));
        }

        $tags = $this->stringList($video['tags'] ?? null);
        if ($tags !== []) {
            $lines[] = 'Uploader tags: '.implode(', ', array_slice($tags, 0, 25));
        }

        $channelTitle = trim((string) ($video['channel_title'] ?? ''));
        if ($channelTitle !== '') {
            $lines[] = 'Uploader channel: '.$channelTitle;
        }

        $evidence = $this->evidenceLines($video['program_verdict'] ?? null);
        if ($evidence !== []) {
            $lines[] = 'Program classifier verdict (authoritative where stated):';
            foreach ($evidence as $line) {
                $lines[] = '  - '.$line;
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Render the program pass's verdict as prompt evidence.
     *
     * Only layers the program pass actually decided are reported. A null
     * content_type means the program pass abstained, and saying "content type:
     * unknown" would invite the model to fill it in as if it were a finding.
     *
     * @return list<string>
     */
    private function evidenceLines(mixed $verdict): array
    {
        if (! is_array($verdict)) {
            return [];
        }

        $lines = [];

        $type = trim((string) ($verdict['content_type'] ?? ''));
        if ($type !== '') {
            $lines[] = 'content type: '.$type;
        }

        $category = trim((string) ($verdict['content_category'] ?? ''));
        if ($category !== '') {
            $lines[] = 'sub-category: '.$category;
        }

        $detail = trim((string) ($verdict['content_category_detail'] ?? ''));
        if ($detail !== '') {
            $lines[] = 'attribution already found: '.$detail;
        }

        $signal = trim((string) ($verdict['signal'] ?? ''));
        if ($signal !== '' && $signal !== 'none') {
            $lines[] = 'deciding signal: '.$signal;
        }

        $catalog = trim((string) ($verdict['catalog'] ?? ''));
        if ($catalog !== '') {
            $lines[] = 'iTunes catalogue match: '.$catalog;
        }

        return $lines;
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
