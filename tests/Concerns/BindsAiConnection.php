<?php

namespace Tests\Concerns;

use App\Domain\Ai\Enums\AiConnectionDriver;
use App\Domain\Ai\Services\AiConnectionResolver;
use App\Domain\Ai\Support\AiConnectionData;

/**
 * Swap the AI resolver for a stub so consumer tests do not need the
 * `ai_connections` table (envelope encryption + DB round-trip).
 */
trait BindsAiConnection
{
    private const AI_CONNECTION_TEST_ENDPOINT = 'https://ai.test/v1/chat/completions';

    protected function aiTestEndpoint(): string
    {
        return self::AI_CONNECTION_TEST_ENDPOINT;
    }

    protected function bindAiConnection(
        string $endpoint = self::AI_CONNECTION_TEST_ENDPOINT,
        string $apiKey = 'test-key',
        ?int $timeoutSeconds = null,
        ?int $connectTimeoutSeconds = null,
        ?int $maxRetries = null,
        array $headers = [],
        string $id = 'ai-connection-test',
        string $slug = 'test-connection',
    ): AiConnectionData {
        $data = new AiConnectionData(
            id: $id,
            slug: $slug,
            driver: AiConnectionDriver::OpenAiCompatible,
            endpoint: $endpoint,
            apiKey: $apiKey,
            timeoutSeconds: $timeoutSeconds,
            connectTimeoutSeconds: $connectTimeoutSeconds,
            maxRetries: $maxRetries,
            headers: $headers,
        );

        $this->app->instance(AiConnectionResolver::class, new class($data, $slug) extends AiConnectionResolver
        {
            public function __construct(
                private readonly AiConnectionData $data,
                private readonly string $slug,
            ) {}

            public function resolve(?string $slug = null): ?AiConnectionData
            {
                return $this->data;
            }

            public function configured(?string $slug = null): bool
            {
                return true;
            }

            public function defaultSlug(): ?string
            {
                return $this->slug;
            }

            public function forget(?string $slug = null): void {}
        });

        return $data;
    }

    protected function bindNoAiConnection(): void
    {
        $this->app->instance(AiConnectionResolver::class, new class extends AiConnectionResolver
        {
            public function resolve(?string $slug = null): ?AiConnectionData
            {
                return null;
            }

            public function configured(?string $slug = null): bool
            {
                return false;
            }

            public function defaultSlug(): ?string
            {
                return null;
            }

            public function forget(?string $slug = null): void {}
        });
    }
}
