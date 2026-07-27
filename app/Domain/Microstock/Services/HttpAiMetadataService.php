<?php

namespace App\Domain\Microstock\Services;

use App\Domain\Microstock\Contracts\AiMetadataService;
use App\Domain\Microstock\Exceptions\MicrostockException;
use App\Domain\Microstock\Support\Prompts;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * HTTP AI metadata service (OpenAI-compatible chat-completions shape).
 * Vision drafts, thinking curates. Endpoints/models/keys from config/microstock.php.
 */
class HttpAiMetadataService implements AiMetadataService
{
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

    public function generate(string $imageUrl, ?string $hint = null): array
    {
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

        $response = Http::withToken($cfg['api_key'])
            ->timeout($this->timeoutSeconds($cfg['timeout'] ?? 60))
            ->connectTimeout(10)
            ->retry(2, 1000, throw: false)
            ->post($cfg['endpoint'], [
                'model' => $cfg['model'],
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.2,
                'max_tokens' => 4000,
                'messages' => [
                    ['role' => 'system', 'content' => Prompts::batchSystem()],
                    ['role' => 'user', 'content' => $content],
                ],
            ]);

        if (! $response->successful()) {
            throw new MicrostockException(__('api/microstock.ai_failed'), 502);
        }

        return $this->decodeBatch($this->parseResponsePayload($response, 'batch'), $items);
    }

    /**
     * @param  list<string>  $keywords
     * @return array{title: string, keywords: list<string>, description: string, category: string, raw: mixed}
     */
    public function curateDraft(
        string $title,
        array $keywords,
        ?string $hint = null,
        string $description = '',
        string $category = '',
        ?string $imageUrl = null,
    ): array {
        return $this->thinking($title, $keywords, $hint, $description, $category, $imageUrl);
    }

    /**
     * @return array{title: string, keywords: list<string>, description: string, category: string, raw: mixed}
     */
    private function vision(string $imageUrl, ?string $hint = null): array
    {
        $cfg = config('microstock.ai.vision');
        $this->assertConfigured($cfg, 'vision');

        $response = Http::withToken($cfg['api_key'])
            ->timeout($this->timeoutSeconds($cfg['timeout'] ?? 60))
            ->connectTimeout(10)
            ->retry(2, 1000, throw: false)
            ->post($cfg['endpoint'], [
                'model' => $cfg['model'],
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.2,
                'max_tokens' => 2000,
                'messages' => [
                    ['role' => 'system', 'content' => Prompts::visionSystem()],
                    [
                        'role' => 'user',
                        'content' => [
                            ['type' => 'text', 'text' => Prompts::visionUser($hint)],
                            ['type' => 'image_url', 'image_url' => ['url' => $imageUrl]],
                        ],
                    ],
                ],
            ]);

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
     *   D. fall back to the raw vision draft when all fail.
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

        if ($imageUrl !== null) {
            $attempts[] = [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $this->thinkingUserContent($userText, $imageUrl)],
            ];
            $attempts[] = [
                ['role' => 'user', 'content' => $this->thinkingUserContent($system."\n\n".$userText, $imageUrl)],
            ];
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
                return $this->thinkingRequest($cfg, $messages);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        // All attempts failed: keep the vision draft so the asset still completes.
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
    private function thinkingRequest(array $cfg, array $messages): array
    {
        $response = Http::withToken($cfg['api_key'])
            ->timeout($this->timeoutSeconds($cfg['timeout'] ?? 60))
            ->connectTimeout(10)
            ->retry(2, 1000, throw: false)
            ->post($cfg['endpoint'], [
                'model' => $cfg['model'],
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.2,
                'max_tokens' => 2000,
                'messages' => $messages,
            ]);

        if (! $response->successful()) {
            throw new MicrostockException(__('api/microstock.ai_failed'), 502);
        }

        return $this->decode($this->parseResponsePayload($response, 'thinking'));
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
     * Some gateways (9Router) append SSE trailer bytes (`data: [DONE]`) even on
     * non-streaming responses, which breaks Laravel's json() helper.
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

        if (! is_array($payload)) {
            report(new \RuntimeException('AI gateway returned an unparseable response', 0, null));
            $this->reportDecodeFailure($stage, $response->status(), $body, 'payload_not_json');

            throw new MicrostockException(__('api/microstock.ai_invalid_response'), 502);
        }

        return $payload;
    }

    /**
     * Remove SSE trailer lines appended after the JSON body by some gateways.
     */
    private function stripSseTrailers(string $body): string
    {
        $body = trim($body);

        // Truncate at the first SSE line break after the JSON object/array.
        if (preg_match('/^(\{.*\}|\[.*\])\s*(?:data:|$)/s', $body, $m)) {
            return $m[1];
        }

        // Fallback: strip any trailing `data: ...` lines.
        return (string) preg_replace('/\s*data:\s*\[DONE\]\s*$/s', '', $body);
    }

    /**
     * Extract the assistant message text from an OpenAI-compatible payload.
     */
    private function extractContent(array $payload): ?string
    {
        $message = $payload['choices'][0]['message'] ?? null;
        if (! is_array($message)) {
            return null;
        }

        $content = $message['content'] ?? null;

        if (is_string($content)) {
            return $content;
        }

        // Anthropic-style content blocks translated by the gateway.
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

            return $parts !== [] ? implode("\n", $parts) : null;
        }

        // Reasoning models that park the final answer elsewhere.
        foreach (['text', 'reasoning_content'] as $key) {
            if (is_string($message[$key] ?? null) && $message[$key] !== '') {
                return $message[$key];
            }
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

        // Last resort: extract the first JSON object or array substring.
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

        // Optional new fields: absent on old providers / partial responses.
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
     * Decode a batch response into a per-id map. The AI may return a bare JSON
     * array, an `assets` wrapper, or any object with a single array value.
     * Ids that are missing or malformed are omitted (caller falls back per-file).
     *
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
