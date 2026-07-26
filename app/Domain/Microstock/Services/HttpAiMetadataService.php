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
    public function generate(string $imageUrl): array
    {
        $draft = $this->vision($imageUrl);
        $curated = $this->thinking($draft['title'], $draft['keywords']);

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
     * @return array{title: string, keywords: list<string>, raw: mixed}
     */
    private function vision(string $imageUrl): array
    {
        $cfg = config('microstock.ai.vision');
        $this->assertConfigured($cfg, 'vision');

        $response = Http::withToken($cfg['api_key'])
            ->timeout((int) ($cfg['timeout'] ?? 60))
            ->post($cfg['endpoint'], [
                'model' => $cfg['model'],
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => Prompts::visionSystem()],
                    [
                        'role' => 'user',
                        'content' => [
                            ['type' => 'text', 'text' => Prompts::visionUser()],
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
     * @param  list<string>  $keywords
     * @return array{title: string, keywords: list<string>, raw: mixed}
     */
    private function thinking(string $title, array $keywords): array
    {
        $cfg = config('microstock.ai.thinking');
        $this->assertConfigured($cfg, 'thinking');

        $response = Http::withToken($cfg['api_key'])
            ->timeout((int) ($cfg['timeout'] ?? 60))
            ->post($cfg['endpoint'], [
                'model' => $cfg['model'],
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => Prompts::thinkingSystem()],
                    ['role' => 'user', 'content' => Prompts::thinkingUser($title, $keywords)],
                ],
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
}
