<?php

namespace App\Domain\Microstock\Services;

use App\Domain\Microstock\Contracts\AiMetadataService;
use App\Domain\Microstock\Exceptions\MicrostockException;
use App\Domain\Microstock\Support\Prompts;
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
     * The upper clamp is generous because vision/thinking models perform
     * multi-step reasoning that can run for minutes; cutting the connection
     * early (cURL error 28) aborts an otherwise-valid generation.
     */
    private function timeoutSeconds(mixed $raw): int
    {
        $v = (int) ($raw ?? 60);
        if ($v > 1000) {
            $v = (int) round($v / 1000);
        }

        return max(10, min(600, $v));
    }

    public function generate(string $imageUrl, ?string $hint = null): array
    {
        $draft = $this->vision($imageUrl, $hint);
        $curated = $this->thinking($draft['title'], $draft['keywords'], $hint);

        return [
            'title' => $curated['title'],
            'keywords' => $curated['keywords'],
            'raw' => [
                'vision' => $draft['raw'] ?? null,
                'thinking' => $curated['raw'] ?? null,
            ],
        ];
    }

    /**
     * @param  array<int, array{id: string, image: string}>  $items
     * @return array<string, array{title: string, keywords: list<string>, raw: array<string, mixed>}>
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
                'messages' => [
                    ['role' => 'system', 'content' => Prompts::batchSystem()],
                    ['role' => 'user', 'content' => $content],
                ],
            ]);

        if (! $response->successful()) {
            throw new MicrostockException(__('api/microstock.ai_failed'), 502);
        }

        return $this->decodeBatch($response->json(), $items);
    }

    /**
     * @return array{title: string, keywords: list<string>, raw: mixed}
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

        return $this->decode($response->json());
    }

    /**
     * Curate the vision draft. The `thinking` model on some gateways hangs
     * (0 bytes) on long system prompts, so degrade gracefully:
     *   A. system + user (normal)
     *   B. instructions fused into user (no system role) when A fails
     *   C. fall back to the raw vision draft when both fail — better than
     *      failing the whole asset.
     *
     * @param  list<string>  $keywords
     * @return array{title: string, keywords: list<string>, raw: mixed}
     */
    private function thinking(string $title, array $keywords, ?string $hint = null): array
    {
        $cfg = config('microstock.ai.thinking');
        $this->assertConfigured($cfg, 'thinking');

        $user = Prompts::thinkingUser($title, $keywords, $hint);
        $attempts = [
            [
                ['role' => 'system', 'content' => Prompts::thinkingSystem()],
                ['role' => 'user', 'content' => $user],
            ],
            [
                ['role' => 'user', 'content' => Prompts::thinkingSystem()."\n\n".$user],
            ],
        ];

        foreach ($attempts as $messages) {
            try {
                return $this->thinkingRequest($cfg, $messages);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        // Both attempts failed: keep the vision draft so the asset still completes.
        return ['title' => $title, 'keywords' => $keywords, 'raw' => null];
    }

    /**
     * @param  array<string, mixed>  $cfg
     * @param  list<array{role: string, content: string}>  $messages
     * @return array{title: string, keywords: list<string>, raw: mixed}
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
                'messages' => $messages,
            ]);

        if (! $response->successful()) {
            throw new MicrostockException(__('api/microstock.ai_failed'), 502);
        }

        return $this->decode($response->json());
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
     * @param  array<string, mixed>  $payload
     * @return array{title: string, keywords: list<string>, raw: mixed}
     */
    private function decode(array $payload): array
    {
        $content = $payload['choices'][0]['message']['content'] ?? null;
        $decoded = is_string($content) ? json_decode($content, true) : null;

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
            throw new MicrostockException(__('api/microstock.ai_invalid_response'), 502);
        }

        return ['title' => $title, 'keywords' => $keywords, 'raw' => $decoded];
    }

    /**
     * Decode a batch response into a per-id map. The AI may return a bare JSON
     * array or an object wrapping the array; tolerate both. Ids that are
     * missing or malformed are omitted (caller falls back per-file).
     *
     * @param  array<string, mixed>  $payload
     * @param  array<int, array{id: string, image: string}>  $items
     * @return array<string, array{title: string, keywords: list<string>, raw: array<string, mixed>}>
     */
    private function decodeBatch(array $payload, array $items): array
    {
        $content = $payload['choices'][0]['message']['content'] ?? null;
        $decoded = is_string($content) ? json_decode($content, true) : null;

        $rows = [];
        if (is_array($decoded)) {
            // Bare list of row objects, or an object with a single array value.
            if (array_is_list($decoded)) {
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
            $out[$id] = ['title' => $title, 'keywords' => $keywords, 'raw' => $row];
        }

        return $out;
    }
}
