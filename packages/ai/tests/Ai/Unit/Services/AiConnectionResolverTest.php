<?php

namespace App\Domain\Ai\Tests\Unit\Services;

use App\Domain\Ai\Enums\AiConnectionStatus;
use App\Domain\Ai\Models\AiConnection;
use App\Domain\Ai\Services\AiConnectionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AiConnectionResolverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    #[Test]
    public function no_connection_resolves_to_null(): void
    {
        $resolver = app(AiConnectionResolver::class);

        $this->assertNull($resolver->resolve());
        $this->assertNull($resolver->resolve('anything'));
        $this->assertFalse($resolver->configured());
        $this->assertNull($resolver->defaultSlug());
    }

    #[Test]
    public function active_connection_resolves_with_the_decrypted_key(): void
    {
        $this->make('named', AiConnectionStatus::Active, apiKey: 'sk-named-1234');

        $data = app(AiConnectionResolver::class)->resolve('named');

        $this->assertNotNull($data);
        $this->assertSame('named', $data->slug);
        $this->assertSame('sk-named-1234', $data->apiKey);
        $this->assertSame('https://ai.example.test/v1/chat/completions', $data->endpoint);
        $this->assertSame(45, $data->timeoutSeconds);
        $this->assertSame(5, $data->connectTimeoutSeconds);
        $this->assertSame(2, $data->maxRetries);
        $this->assertSame(['X-Tenant' => 'acme'], $data->headers);
    }

    #[Test]
    public function pending_and_disabled_connections_never_resolve(): void
    {
        $this->make('pending-one', AiConnectionStatus::Pending);
        $this->make('disabled-one', AiConnectionStatus::Disabled);

        $resolver = app(AiConnectionResolver::class);

        $this->assertNull($resolver->resolve('pending-one'));
        $this->assertNull($resolver->resolve('disabled-one'));
    }

    #[Test]
    public function an_empty_slug_falls_back_to_the_default_connection(): void
    {
        $this->make('only-default', AiConnectionStatus::Active, isDefault: true);

        $resolver = app(AiConnectionResolver::class);

        $this->assertSame('only-default', $resolver->defaultSlug());
        $this->assertSame('only-default', $resolver->resolve()?->slug);
        $this->assertSame('only-default', $resolver->resolve('')?->slug);
    }

    #[Test]
    public function a_pending_default_is_ignored(): void
    {
        $this->make('pending-default', AiConnectionStatus::Pending, isDefault: true);

        $this->assertNull(app(AiConnectionResolver::class)->defaultSlug());
        $this->assertNull(app(AiConnectionResolver::class)->resolve());
    }

    #[Test]
    public function forget_drops_the_cached_default_slug(): void
    {
        $this->make('cached-default', AiConnectionStatus::Active, isDefault: true);

        $resolver = app(AiConnectionResolver::class);
        $this->assertSame('cached-default', $resolver->defaultSlug());

        AiConnection::query()->where('slug', 'cached-default')->update(['is_default' => false]);
        $this->assertSame('cached-default', $resolver->defaultSlug(), 'expected the cached value');

        $resolver->forget();
        $this->assertNull($resolver->defaultSlug());
    }

    private function make(
        string $slug,
        AiConnectionStatus $status,
        bool $isDefault = false,
        string $apiKey = 'sk-test-key',
    ): AiConnection {
        $connection = new AiConnection([
            'slug' => $slug,
            'label' => $slug,
            'endpoint' => 'https://ai.example.test/v1/chat/completions',
            'test_model' => 'probe-model',
            'timeout_seconds' => 45,
            'connect_timeout_seconds' => 5,
            'max_retries' => 2,
            'extra_headers' => ['X-Tenant' => 'acme'],
            'is_default' => $isDefault,
            'status' => $status,
        ]);
        $connection->secret_payload = ['api_key' => $apiKey];
        $connection->secret_hint = '…'.mb_substr($apiKey, -4);
        $connection->secret_fingerprint = str_repeat('b', 64);
        $connection->save();

        return $connection;
    }
}
