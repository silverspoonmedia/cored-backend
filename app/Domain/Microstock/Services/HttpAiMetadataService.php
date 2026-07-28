<?php

namespace App\Domain\Microstock\Services;

use App\Domain\Microstock\Contracts\AiMetadataService;
use App\Domain\Microstock\Exceptions\MicrostockException;
use App\Domain\Microstock\Support\Prompts;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * HTTP AI metadata service (OpenAI-compatible chat-completions shape).
 * Vision drafts, thinking curates. Endpoints/models/keys from config/microstock.php.
 */
class HttpAiMetadataService implements AiMetadataService
{
    /** @var list<array<string, mixed>> */
    private array $stageMetrics = [];

    /**
     * Normalize the configured timeout to seconds. Env historically used
     * milliseconds (e.g. 360000); accept both by treating large values as ms.
     * The upper clamp follows the adaptive grace budget (microstock.ai.phase
     * .grace_after) because vision/thinking models perform multi-step reasoning
     * that can run for many minutes; cutting the connection early (cURL error
     * 28) aborts an otherwise-valid generation. The watchdog — not this timeout
     * — is the authority that declares a hung analysis failed.
     */
    private function timeoutSeconds(mixed $raw): int
    {
        $v = (int) ($raw ?? 60);
        if ($v > 1000) {
            $v = (int) round($v / 1000);
        }

        $max = (int) config('microstock.ai.phase.grace_after', 1500);

        return max(10, min($max, $v));
    }

    private function httpRetries(): int
    {
        return max(0, (int) config('microstock.ai.http_retries', 1));
    }

    /**
     * @param  list<array{role: string, content: string|array<int, array<string, mixed>>}>  $messages
     * @return array<string, mixed>
     */
    private function chatCompletionPayload(array $messages, string $model, int $maxTokens): array
    {
        return [
            'model' => $model,
            'stream' => false,
            'response_format' => ['type' => 'json_object'],
            'temperature' => 0.2,
            'max_tokens' => $maxTokens,
            'messages' => $messages,
        ];
    }

    /**
     * @param  array<string, mixed>  $cfg
     */
    private function httpClient(array $cfg): PendingRequest
    {
        $retries = $this->httpRetries();

        $client = Http::withToken($cfg['api_key'])
            ->timeout($this->timeoutSeconds($cfg['timeout'] ?? 60))
            ->connectTimeout(10);

        if ($retries > 0) {
            $client = $client->retry($retries, 1000, throw: false);
        }

        return $client;
    }

    public function generate(string $imageUrl, ?string $hint = null): array
    {
        $this->stageMetrics = [];

        $draft = $this->vision($imageUrl, $hint);
        $curated = $this->thinking(
            $draft['title'],
            $draft['keywords'],
            $hint,
            $draft['description'] ?? '',
            $draft['category'] ?? '',
            $imageUrl,
        );

        return [
            'title' => $curated['title'],
            'keywords' => $curated['keywords'],
            'description' => $curated['description'] ?? '',
            'category' => $curated['category'] ?? '',
            'raw' => [
                'vision' => $draft['raw'] ?? null,
                'thinking' => $curated['raw'] ?? null,
            ],
            'stage_metrics' => $this->stageMetrics,
        ];
    }

    /**
     * @param  array<int, array{id: string, image: string}>  $items
     * @return array<string, array{title: string, keywords: list<string>, description: string, category: string, raw: array<string, mixed>}>
     */
    public function generateBatch(array $items, ?string $hint = null): array
    {
        if ($items === []) {
            return [];
        }

        $cfg = config('microstock.ai.vision');
        $this->assertConfigured($cfg, 'vision');

        $content = [];
        foreach ($items as $item) {
            $content[] = ['type' => 'text', 'text' => 'ASSET '.$item['id'].':'];
            $content[] = ['type' => 'image_url', 'image_url' => ['url' => $item['image']]];
        }
        $content[] = ['type' => 'text', 'text' => Prompts::batchUser($hint)];

        $messages = [
            ['role' => 'system', 'content' => Prompts::batchSystem()],
            ['role' => 'user', 'content' => $content],
        ];

        $response = $this->postChatCompletion($cfg, 'batch', $messages, 4000);

        if (! $response->successful()) {
            throw new MicrostockException(__('api/microstock.ai_failed'), 502);
        }

        return $this->decodeBatch($this->parseResponsePayload($response, 'batch'), $items);
    }

    /**
     * @param  list<string>  $keywords
     * @return array{title: string, keywords: list<string>, description: string, category: string, raw: mixed, stage_metrics: list<array<string, mixed>>}
     */
    public function curateDraft(
        string $title,
        array $keywords,
        ?string $hint = null,
        string $description = '',
        string $category = '',
        ?string $imageUrl = null,
    ): array {
        $this->stageMetrics = [];

        $result = $this->thinking($title, $keywords, $hint, $description, $category, $imageUrl);
        $result['stage_metrics'] = $this->stageMetrics;

        return $result;
    }

    /**
     * @return array{title: string, keywords: list<string>, description: string, category: string, raw: mixed}
     */
    private function vision(string $imageUrl, ?string $hint = null): array
    {
        $cfg = config('microstock.ai.vision');
        $this->assertConfigured($cfg, 'vision');

        $messages = [
            ['role' => 'system', 'content' => Prompts::visionSystem()],
            [
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => Prompts::visionUser($hint)],
                    ['type' => 'image_url', 'image_url' => ['url' => $imageUrl]],
                ],
            ],
        ];

        $response = $this->postChatCompletion($cfg, 'vision', $messages, 2000, strlen($imageUrl));

        if (! $response->successful()) {
            throw new MicrostockException(__('api/microstock.ai_failed'), 502);
        }

        return $this->decode($this->parseResponsePayload($response, 'vision'));
    }

    /**
     * Curate the vision draft. When an image preview is provided the thinking
     * model verifies the draft against the image before curating. Degrades
     * gracefully on gateway quirks:
     *   A. system + multimodal user (image + draft)
     *   B. instructions fused into multimodal user (no system role)
     *   C. text-only system + user (no image)
     *   D. fused text-only user
     *   E. fall back to the raw vision draft when all fail.
     *
     * @param  list<string>  $keywords
     * @return array{title: string, keywords: list<string>, description: string, category: string, raw: mixed}
     */
    private function thinking(
        string $title,
        array $keywords,
        ?string $hint = null,
        string $description = '',
        string $category = '',
        ?string $imageUrl = null,
    ): array {
        $cfg = config('microstock.ai.thinking');
        $this->assertConfigured($cfg, 'thinking');

        $userText = Prompts::thinkingUser($title, $keywords, $hint, $description, $category, $imageUrl !== null);
        $system = Prompts::thinkingSystem();
        $attempts = [];
        $visualAttempts = $imageUrl !== null
            ? max(0, (int) config('microstock.ai.thinking_visual_attempts', 2))
            : 0;

        if ($imageUrl !== null && $visualAttempts > 0) {
            $attempts[] = [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $this->thinkingUserContent($userText, $imageUrl)],
            ];
            if ($visualAttempts > 1) {
                $attempts[] = [
                    ['role' => 'user', 'content' => $this->thinkingUserContent($system."\n\n".$userText, $imageUrl)],
                ];
            }
        }

        $attempts[] = [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $userText],
        ];

        if ($imageUrl !== null) {
            $attempts[] = [
                ['role' => 'user', 'content' => $system."\n\n".$userText],
            ];
        }

        foreach ($attempts as $messages) {
            try {
                return $this->thinkingRequest($cfg, $messages, $imageUrl !== null ? strlen((string) $imageUrl) : null);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return [
            'title' => $title,
            'keywords' => $keywords,
            'description' => $description,
            'category' => $category,
            'raw' => null,
        ];
    }

    /**
     * @return list<array{type: string, text?: string, image_url?: array{url: string}}>|string
     */
    private function thinkingUserContent(string $text, ?string $imageUrl): array|string
    {
        if ($imageUrl === null) {
            return $text;
        }

        return [
            ['type' => 'text', 'text' => $text],
            ['type' => 'image_url', 'image_url' => ['url' => $imageUrl]],
        ];
    }

    /**
     * @param  array<string, mixed>  $cfg
     * @param  list<array{role: string, content: string|array<int, array<string, mixed>>}>  $messages
     * @return array{title: string, keywords: list<string>, description: string, category: string, raw: mixed}
     */
    private function thinkingRequest(array $cfg, array $messages, ?int $imageBytes = null): array
    {
        $response = $this->postChatCompletion($cfg, 'thinking', $messages, 2000, $imageBytes);

        if (! $response->successful()) {
            throw new MicrostockException(__('api/microstock.ai_failed'), 502);
        }

        return $this->decode($this->parseResponsePayload($response, 'thinking'));
    }

    /**
     * @param  array<string, mixed>  $cfg
     * @param  list<array{role: string, content: string|array<int, array<string, mixed>>}>  $messages
     */
    private function postChatCompletion(
        array $cfg,
        string $stage,
        array $messages,
        int $maxTokens,
        ?int $imageBytes = null,
    ): Response {
        $payload = $this->chatCompletionPayload($messages, (string) $cfg['model'], $maxTokens);

        Log::info('microstock.ai.stage', [
            'stage' => $stage,
            'model' => $cfg['model'],
            'event' => 'start',
            'payload_bytes' => strlen(json_encode($payload)),
            'image_bytes' => $imageBytes,
        ]);

        $started = microtime(true);

        $response = $this->httpClient($cfg)->post($cfg['endpoint'], $payload);

        $body = $response->body();
        $finishReason = null;
        $parsed = $response->json();
        if (! is_array($parsed)) {
            $parsed = json_decode($this->stripSseTrailers($body), true);
        }
        if (is_array($parsed)) {
            $finishReason = $parsed['choices'][0]['finish_reason'] ?? null;
        }

        if (is_array($parsed)) {
            $usage = is_array($parsed['usage'] ?? null) ? $parsed['usage'] : null;
            $resolvedModel = is_string($parsed['model'] ?? null) ? $parsed['model'] : null;
            app(AiBillingService::class)->recordUsage(
                $stage,
                (string) $cfg['model'],
                $usage,
                $resolvedModel,
            );
        }

        Log::info('microstock.ai.stage', [
            'stage' => $stage,
            'model' => $cfg['model'],
            'event' => 'end',
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'http_status' => $response->status(),
            'body_bytes' => strlen($body),
            'body_prefix' => mb_substr(trim($body), 0, 80),
            'finish_reason' => $finishReason,
        ]);

        $durationMs = (int) round((microtime(true) - $started) * 1000);
        $usage = is_array($parsed) && is_array($parsed['usage'] ?? null) ? $parsed['usage'] : null;
        $resolvedModel = is_array($parsed) && is_string($parsed['model'] ?? null) ? $parsed['model'] : null;

        $this->stageMetrics[] = [
            'stage' => $stage,
            'duration_ms' => $durationMs,
            'resolved_model' => $resolvedModel,
            'multimodal' => $this->messagesContainImage($messages),
            'http_status' => $response->status(),
            'tokens_per_second' => $this->tokensPerSecond($usage, $durationMs),
            'status' => $response->successful() ? 'ok' : 'http_error',
        ];

        return $response;
    }

    /**
     * @param  list<array{role: string, content: string|array<int, array<string, mixed>>}>  $messages
     */
    private function messagesContainImage(array $messages): bool
    {
        foreach ($messages as $message) {
            $content = $message['content'] ?? null;
            if (! is_array($content)) {
                continue;
            }
            foreach ($content as $block) {
                if (is_array($block) && ($block['type'] ?? '') === 'image_url') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>|null  $usage
     */
    private function tokensPerSecond(?array $usage, int $durationMs): ?float
    {
        if ($durationMs <= 0 || ! is_array($usage)) {
            return null;
        }

        $total = (int) ($usage['total_tokens'] ?? 0);
        if ($total <= 0) {
            $total = (int) ($usage['prompt_tokens'] ?? 0) + (int) ($usage['completion_tokens'] ?? 0);
        }
        if ($total <= 0) {
            return null;
        }

        return round($total / ($durationMs / 1000), 2);
    }

    /**
     * @param  array<string, mixed>|null  $cfg
     */
    private function assertConfigured(?array $cfg, string $stage): void
    {
        if (! is_array($cfg)
            || empty($cfg['endpoint'])
            || empty($cfg['model'])
            || empty($cfg['api_key'])
        ) {
            throw new MicrostockException(__('api/microstock.ai_not_configured', ['stage' => $stage]), 500);
        }
    }

    /**
     * Parse the HTTP response body into the OpenAI chat-completions payload.
     * Some Open WebUI / upstream paths return SSE streams or append `data: [DONE]`
     * trailers even on non-streaming requests.
     *
     * @return array<string, mixed>
     */
    private function parseResponsePayload(Response $response, string $stage): array
    {
        $body = $response->body();
        $payload = $response->json();

        if (! is_array($payload)) {
            $clean = $this->stripSseTrailers($body);
            $payload = json_decode($clean, true);
        }

        if (! is_array($payload) && $this->looksLikeSseStream($body)) {
            $payload = $this->aggregateSseStream($body);
        }

        if (! is_array($payload)) {
            report(new \RuntimeException('AI gateway returned an unparseable response', 0, null));
            $this->reportDecodeFailure($stage, $response->status(), $body, 'payload_not_json');

            throw new MicrostockException(__('api/microstock.ai_invalid_response'), 502);
        }

        return $payload;
    }

    private function looksLikeSseStream(string $body): bool
    {
        return str_starts_with(trim($body), 'data:');
    }

    /**
     * Assemble delta chunks from a full SSE response into one OpenAI payload.
     *
     * @return array<string, mixed>|null
     */
    private function aggregateSseStream(string $body): ?array
    {
        $content = '';
        $finishReason = null;

        foreach (preg_split('/\r\n|\n|\r/', $body) as $line) {
            $line = trim($line);
            if ($line === '' || $line === 'data: [DONE]') {
                continue;
            }
            if (! str_starts_with($line, 'data:')) {
                continue;
            }

            $chunk = json_decode(trim(substr($line, 5)), true);
            if (! is_array($chunk)) {
                continue;
            }

            $choice = $chunk['choices'][0] ?? null;
            if (! is_array($choice)) {
                continue;
            }

            if (is_string($choice['finish_reason'] ?? null)) {
                $finishReason = $choice['finish_reason'];
            }

            $delta = $choice['delta'] ?? null;
            if (is_array($delta) && is_string($delta['content'] ?? null)) {
                $content .= $delta['content'];

                continue;
            }

            $message = $choice['message'] ?? null;
            if (is_array($message) && is_string($message['content'] ?? null)) {
                $content .= $message['content'];
            }
        }

        if ($content === '') {
            return null;
        }

        return [
            'choices' => [[
                'message' => ['role' => 'assistant', 'content' => $content],
                'finish_reason' => $finishReason ?? 'stop',
            ]],
        ];
    }

    /**
     * Remove SSE trailer lines appended after the JSON body by some gateways.
     */
    private function stripSseTrailers(string $body): string
    {
        $body = trim($body);

        if (preg_match('/^(\{.*\}|\[.*\])\s*(?:data:|$)/s', $body, $m)) {
            return $m[1];
        }

        return (string) preg_replace('/\s*data:\s*\[DONE\]\s*$/s', '', $body);
    }

    /**
     * Extract the assistant message text from an OpenAI-compatible payload.
     */
    private function extractContent(array $payload): ?string
    {
        if (is_string($payload['output'] ?? null) && $payload['output'] !== '') {
            return $payload['output'];
        }

        $message = $payload['choices'][0]['message'] ?? null;
        if (! is_array($message)) {
            return null;
        }

        $finishReason = $payload['choices'][0]['finish_reason'] ?? null;
        $content = $message['content'] ?? null;

        if (is_string($content) && $content !== '') {
            return $content;
        }

        if (is_array($content)) {
            $parts = [];
            foreach ($content as $block) {
                if (! is_array($block)) {
                    continue;
                }
                if (($block['type'] ?? '') === 'text' && is_string($block['text'] ?? null)) {
                    $parts[] = $block['text'];
                }
            }

            if ($parts !== []) {
                return implode("\n", $parts);
            }
        }

        foreach (['text', 'reasoning_content', 'reasoning'] as $key) {
            if (is_string($message[$key] ?? null) && $message[$key] !== '') {
                return $message[$key];
            }
        }

        if ($finishReason === 'length') {
            $this->reportDecodeFailure('extract', 200, '', 'finish_reason_length_empty_content');
        }

        return null;
    }

    /**
     * Parse a JSON metadata blob from the assistant message, tolerating fences.
     *
     * @return array<string, mixed>|null
     */
    private function parseMetadataJson(string $raw): ?array
    {
        $raw = trim($raw);

        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/i', $raw, $m)) {
            $raw = trim($m[1]);
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/(\{[\s\S]*\}|\[[\s\S]*\])/', $raw, $m)) {
            $decoded = json_decode($m[1], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{title: string, keywords: list<string>, description: string, category: string, raw: mixed}
     */
    private function decode(array $payload): array
    {
        $content = $this->extractContent($payload);
        $decoded = is_string($content) ? $this->parseMetadataJson($content) : null;

        $title = is_array($decoded) && is_string($decoded['title'] ?? null)
            ? trim($decoded['title'])
            : '';
        $keywords = is_array($decoded) && is_array($decoded['keywords'] ?? null)
            ? array_values(array_filter(array_map(
                static fn ($k): string => is_string($k) ? trim($k) : '',
                $decoded['keywords'],
            ), static fn (string $k): bool => $k !== ''))
            : [];

        if ($title === '' || $keywords === []) {
            $this->reportDecodeFailure('decode', 200, is_string($content) ? $content : '', json_last_error_msg());

            throw new MicrostockException(__('api/microstock.ai_invalid_response'), 502);
        }

        $description = is_array($decoded) && is_string($decoded['description'] ?? null)
            ? trim($decoded['description'])
            : '';
        $category = is_array($decoded) && is_string($decoded['category'] ?? null)
            ? trim($decoded['category'])
            : '';

        return [
            'title' => $title,
            'keywords' => $keywords,
            'description' => $description,
            'category' => $category,
            'raw' => $decoded,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, array{id: string, image: string}>  $items
     * @return array<string, array{title: string, keywords: list<string>, description: string, category: string, raw: array<string, mixed>}>
     */
    private function decodeBatch(array $payload, array $items): array
    {
        $content = $this->extractContent($payload);
        $decoded = is_string($content) ? $this->parseMetadataJson($content) : null;

        $rows = [];
        if (is_array($decoded)) {
            if (isset($decoded['assets']) && is_array($decoded['assets'])) {
                $rows = $decoded['assets'];
            } elseif (array_is_list($decoded)) {
                $rows = $decoded;
            } else {
                foreach ($decoded as $value) {
                    if (is_array($value) && array_is_list($value)) {
                        $rows = $value;
                        break;
                    }
                }
            }
        }

        $validIds = array_fill_keys(array_column($items, 'id'), true);
        $out = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $id = is_string($row['id'] ?? null) ? $row['id'] : null;
            if ($id === null || ! isset($validIds[$id])) {
                continue;
            }
            $title = is_string($row['title'] ?? null) ? trim($row['title']) : '';
            $keywords = is_array($row['keywords'] ?? null)
                ? array_values(array_filter(array_map(
                    static fn ($k): string => is_string($k) ? trim($k) : '',
                    $row['keywords'],
                ), static fn (string $k): bool => $k !== ''))
                : [];
            if ($title === '' || $keywords === []) {
                continue;
            }
            $out[$id] = [
                'title' => $title,
                'keywords' => $keywords,
                'description' => is_string($row['description'] ?? null) ? trim($row['description']) : '',
                'category' => is_string($row['category'] ?? null) ? trim($row['category']) : '',
                'raw' => $row,
            ];
        }

        return $out;
    }

    private function reportDecodeFailure(string $stage, int $status, string $content, string $reason): void
    {
        report(new \RuntimeException(sprintf(
            'Microstock AI decode failed [%s] http=%d reason=%s content=%s',
            $stage,
            $status,
            $reason,
            mb_substr($content, 0, 500),
        )));
    }
}
