<?php

namespace App\Domain\Ai\Services;

use App\Domain\Ai\Enums\AiConnectionStatus;
use App\Domain\Ai\Models\AiConnection;
use App\Domain\Ai\Support\AiConnectionData;
use Illuminate\Support\Facades\Cache;

/**
 * Single entry point for consumers that need to *call* AI.
 *
 * Contract:
 * - Never throws. `null` means "no usable AI connection" — every consumer
 *   already has a "not configured" branch and must keep working without one.
 * - Resolution order: explicit slug (per-consumer binding) → `is_default` row.
 * - Only `active` rows resolve. `pending` (never probed / edited since) and
 *   `disabled` never do.
 *
 * Cache: the default slug is a forever primitive (cache policy — no Eloquent
 * models in forever keys). The row itself is a unique-slug lookup; decrypted
 * plaintext is cached by the envelope cast, not here.
 */
class AiConnectionResolver
{
    public const DEFAULT_SLUG_CACHE_KEY = 'ai.connection.default_slug';

    public function resolve(?string $slug = null): ?AiConnectionData
    {
        $model = $this->resolveModel($slug);

        if ($model === null) {
            return null;
        }

        $payload = $model->secret_payload;
        if (! is_array($payload)) {
            return null;
        }

        $apiKey = trim((string) ($payload['api_key'] ?? ''));
        $endpoint = trim((string) $model->endpoint);
        if ($apiKey === '' || $endpoint === '') {
            return null;
        }

        $headers = [];
        foreach (($model->extra_headers ?? []) as $name => $value) {
            if (is_string($name) && is_scalar($value)) {
                $headers[$name] = (string) $value;
            }
        }

        return new AiConnectionData(
            id: (string) $model->id,
            slug: (string) $model->slug,
            driver: $model->driver,
            endpoint: $endpoint,
            apiKey: $apiKey,
            timeoutSeconds: $model->timeout_seconds,
            connectTimeoutSeconds: $model->connect_timeout_seconds,
            maxRetries: $model->max_retries,
            headers: $headers,
        );
    }

    /**
     * Whether a usable connection exists for the given binding (or the default).
     */
    public function configured(?string $slug = null): bool
    {
        return $this->resolve($slug) !== null;
    }

    public function defaultSlug(): ?string
    {
        $slug = Cache::rememberForever(self::DEFAULT_SLUG_CACHE_KEY, function (): ?string {
            $value = AiConnection::query()
                ->where('is_default', true)
                ->where('status', AiConnectionStatus::Active)
                ->value('slug');

            return is_string($value) && $value !== '' ? $value : null;
        });

        return is_string($slug) && $slug !== '' ? $slug : null;
    }

    public function forget(?string $slug = null): void
    {
        Cache::forget(self::DEFAULT_SLUG_CACHE_KEY);

        if ($slug !== null && $slug !== '') {
            $connection = AiConnection::query()->where('slug', $slug)->first();
            if ($connection !== null) {
                app(AiConnectionService::class)->invalidateDecryptCache($connection);
            }
        }
    }

    private function resolveModel(?string $slug): ?AiConnection
    {
        $slug = is_string($slug) ? trim($slug) : '';

        if ($slug === '') {
            $defaultSlug = $this->defaultSlug();

            return $defaultSlug === null ? null : $this->activeBySlug($defaultSlug);
        }

        return $this->activeBySlug($slug);
    }

    private function activeBySlug(string $slug): ?AiConnection
    {
        $connection = AiConnection::query()->where('slug', $slug)->first();

        if ($connection === null || ! $connection->isActive()) {
            return null;
        }

        return $connection;
    }
}
