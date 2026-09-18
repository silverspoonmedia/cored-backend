<?php

namespace App\Domain\Ai\Services\Probes;

use App\Domain\Ai\Contracts\AiConnectionProbe;
use App\Domain\Ai\Enums\AiConnectionDriver;
use App\Domain\Ai\Models\AiConnection;
use App\Domain\Ai\Support\AiConnectionTestResult;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Probes an [OI]-compatible chat-completions endpoint with a 1-token request.
 * Cheapest call that distinguishes "endpoint + key work" from "they do not".
 */
class OpenAiCompatibleProbe implements AiConnectionProbe
{
    private const TIMEOUT_SECONDS = 20;

    public function supports(AiConnection $connection): bool
    {
        return $connection->driver === AiConnectionDriver::OpenAiCompatible;
    }

    public function probe(AiConnection $connection, array $secretPayload): AiConnectionTestResult
    {
        $apiKey = trim((string) ($secretPayload['api_key'] ?? ''));
        $endpoint = trim((string) $connection->endpoint);
        $model = trim((string) $connection->test_model);

        if ($apiKey === '' || $endpoint === '' || $model === '') {
            return new AiConnectionTestResult(
                ok: false,
                message: __('api/ai.connection.test_not_configured'),
            );
        }

        $startedAt = microtime(true);

        try {
            $request = Http::withToken($apiKey)
                ->timeout(max(5, (int) ($connection->timeout_seconds ?? self::TIMEOUT_SECONDS)))
                ->connectTimeout(max(1, (int) ($connection->connect_timeout_seconds ?? 10)))
                ->acceptJson();

            if (is_array($connection->extra_headers) && $connection->extra_headers !== []) {
                $request = $request->withHeaders($connection->extra_headers);
            }

            $response = $request->post($endpoint, [
                'model' => $model,
                'stream' => false,
                'max_tokens' => 1,
                'temperature' => 0.0,
                'messages' => [
                    ['role' => 'user', 'content' => 'ping'],
                ],
            ]);
        } catch (Throwable $e) {
            return new AiConnectionTestResult(
                ok: false,
                message: __('api/ai.connection.test_unreachable', ['error' => $e->getMessage()]),
                latencyMs: $this->elapsedMs($startedAt),
            );
        }

        $latencyMs = $this->elapsedMs($startedAt);

        if ($response->status() === 401 || $response->status() === 403) {
            return new AiConnectionTestResult(
                ok: false,
                message: __('api/ai.connection.test_unauthorized'),
                latencyMs: $latencyMs,
            );
        }

        if (! $response->successful()) {
            // The upstream body is the only thing that separates "wrong model
            // alias" from "malformed request" — surface a trimmed copy of it.
            $detail = trim(preg_replace('/\s+/', ' ', (string) $response->body()) ?? '');

            return new AiConnectionTestResult(
                ok: false,
                message: $detail === ''
                    ? __('api/ai.connection.test_http_failed', ['status' => $response->status()])
                    : __('api/ai.connection.test_http_failed_with_body', [
                        'status' => $response->status(),
                        'detail' => mb_substr($detail, 0, 160),
                    ]),
                latencyMs: $latencyMs,
            );
        }

        return new AiConnectionTestResult(
            ok: true,
            message: __('api/ai.connection.test_passed'),
            latencyMs: $latencyMs,
        );
    }

    private function elapsedMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
