<?php

namespace Tests\Domain\Vtual\Unit\Integrations\Support;

use App\Domain\ErrorLog\Models\ErrorLog;
use App\Domain\Vtual\Exceptions\PlatformApiException;
use App\Domain\Vtual\Integrations\Support\PlatformHttp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Retry matrix for the shared platform HTTP helper.
 *
 * 429 / 5xx are transient and get exponential backoff with full jitter;
 * 401/403 belong to the credential failover path and every other 4xx is a hard
 * client error. Retrying either would multiply load for no benefit.
 */
class PlatformHttpRetryTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://api.example.test/v1/thing';

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureRetry(times: 3, baseMs: 1, maxMs: 1);
    }

    #[Test]
    public function retries_429_then_returns_the_successful_response(): void
    {
        Http::fake([
            'api.example.test/*' => Http::sequence()
                ->pushStatus(429)
                ->push(['ok' => true], 200),
        ]);

        $response = PlatformHttp::get(self::URL, ['q' => '1'], null, 'youtube');

        $this->assertTrue($response->successful());
        Http::assertSentCount(2);

        $retry = ErrorLog::query()->where('context->event', 'platform_http_retry_scheduled')->firstOrFail();
        $this->assertSame(429, ($retry->context ?? [])['upstream_status'] ?? null);
        $this->assertSame(1, (int) ($retry->context ?? [])['attempt']);
    }

    #[Test]
    public function retries_5xx_until_the_budget_is_spent_then_throws(): void
    {
        $this->configureRetry(times: 2, baseMs: 1, maxMs: 1);

        Http::fake(['api.example.test/*' => Http::response('upstream exploded', 503)]);

        try {
            PlatformHttp::get(self::URL, [], null, 'youtube');
            $this->fail('Expected PlatformApiException');
        } catch (PlatformApiException $e) {
            $this->assertSame(503, $e->upstreamStatus);
            $this->assertSame(502, $e->statusCode());
        }

        Http::assertSentCount(2);

        $failed = ErrorLog::query()->where('context->event', 'platform_http_failed')->firstOrFail();
        $this->assertSame(2, (int) ($failed->context ?? [])['attempt']);
        $this->assertSame(2, (int) ($failed->context ?? [])['max_attempts']);
    }

    #[Test]
    public function exhausted_429_is_reported_as_quota_exhaustion_not_a_generic_failure(): void
    {
        $this->configureRetry(times: 2, baseMs: 1, maxMs: 1);

        Http::fake(['api.example.test/*' => Http::response('quota', 429)]);

        try {
            PlatformHttp::get(self::URL, [], null, 'youtube');
            $this->fail('Expected PlatformApiException');
        } catch (PlatformApiException $e) {
            $this->assertSame(429, $e->upstreamStatus);
            $this->assertSame(502, $e->statusCode());
            $this->assertSame(
                __('api/vtual.integrations.upstream_quota_exhausted', ['platform' => 'youtube']),
                $e->getMessage(),
            );
        }

        Http::assertSentCount(2);
        $this->assertNotNull(
            ErrorLog::query()->where('context->event', 'platform_http_quota_exhausted')->first(),
        );
    }

    #[Test]
    public function numeric_retry_after_header_wins_over_backoff(): void
    {
        $this->configureRetry(times: 2, baseMs: 1, maxMs: 8000);

        Http::fake([
            'api.example.test/*' => Http::sequence()
                ->push('slow down', 429, ['Retry-After' => '1'])
                ->push(['ok' => true], 200),
        ]);

        PlatformHttp::get(self::URL, [], null, 'youtube');

        $retry = ErrorLog::query()->where('context->event', 'platform_http_retry_scheduled')->firstOrFail();
        $this->assertSame(1000, (int) ($retry->context ?? [])['delay_ms']);
        Http::assertSentCount(2);
    }

    #[Test]
    public function absurd_retry_after_is_ignored_in_favour_of_backoff(): void
    {
        $this->configureRetry(times: 2, baseMs: 250, maxMs: 8000);

        Http::fake([
            'api.example.test/*' => Http::sequence()
                ->push('come back tomorrow', 429, ['Retry-After' => '3600'])
                ->push(['ok' => true], 200),
        ]);

        PlatformHttp::get(self::URL, [], null, 'youtube');

        $retry = ErrorLog::query()->where('context->event', 'platform_http_retry_scheduled')->firstOrFail();
        // jitter off in this suite: first backoff = base_ms * 2^0.
        $this->assertSame(250, (int) ($retry->context ?? [])['delay_ms']);
        Http::assertSentCount(2);
    }

    #[Test]
    public function backoff_grows_exponentially_and_stops_at_the_cap(): void
    {
        $this->configureRetry(times: 4, baseMs: 50, maxMs: 100);

        Http::fake(['api.example.test/*' => Http::response('nope', 503)]);

        $startedAt = microtime(true);

        try {
            PlatformHttp::get(self::URL, [], null, 'youtube');
            $this->fail('Expected PlatformApiException');
        } catch (PlatformApiException) {
            // expected
        }

        $elapsedMs = (microtime(true) - $startedAt) * 1000;

        Http::assertSentCount(4);
        // Three waits: 50, 100, then capped at 100 = 250ms of real backoff.
        $this->assertGreaterThanOrEqual(230, $elapsedMs);

        // ErrorLog fingerprints normalise digits, so the three retry notices
        // collapse into one row; the count is the observable signal.
        $retry = ErrorLog::query()->where('context->event', 'platform_http_retry_scheduled')->firstOrFail();
        $this->assertSame(3, (int) $retry->occurrence_count);
    }

    #[Test]
    public function single_attempt_budget_never_retries(): void
    {
        $this->configureRetry(times: 1, baseMs: 1, maxMs: 1);

        Http::fake(['api.example.test/*' => Http::response('quota', 429)]);

        try {
            PlatformHttp::get(self::URL, [], null, 'youtube');
            $this->fail('Expected PlatformApiException');
        } catch (PlatformApiException) {
            // expected
        }

        Http::assertSentCount(1);
        $this->assertNull(
            ErrorLog::query()->where('context->event', 'platform_http_retry_scheduled')->first(),
        );
    }

    #[Test]
    public function auth_failures_are_not_retried(): void
    {
        Http::fake([
            'api.example.test/*' => Http::sequence()
                ->pushStatus(401)
                ->pushStatus(403)
                ->pushStatus(401),
        ]);

        foreach ([401, 403] as $status) {
            try {
                PlatformHttp::get(self::URL, [], null, 'youtube');
                $this->fail('Expected PlatformApiException for '.$status);
            } catch (PlatformApiException $e) {
                $this->assertSame($status, $e->upstreamStatus);
                // Credential-owned status: surfaced to the caller as a 422.
                $this->assertSame(422, $e->statusCode());
            }
        }

        Http::assertSentCount(2);
    }

    #[Test]
    public function other_client_errors_are_not_retried(): void
    {
        Http::fake(['api.example.test/*' => Http::response('bad request', 400)]);

        try {
            PlatformHttp::get(self::URL, [], null, 'youtube');
            $this->fail('Expected PlatformApiException');
        } catch (PlatformApiException $e) {
            $this->assertSame(400, $e->upstreamStatus);
            $this->assertSame(502, $e->statusCode());
        }

        Http::assertSentCount(1);
    }

    #[Test]
    public function connection_failure_is_a_single_502_without_retry(): void
    {
        $attempts = 0;
        Http::fake(function () use (&$attempts): void {
            $attempts++;

            throw new ConnectionException('timed out');
        });

        try {
            PlatformHttp::get(self::URL, [], null, 'youtube');
            $this->fail('Expected PlatformApiException');
        } catch (PlatformApiException $e) {
            $this->assertSame(502, $e->statusCode());
            $this->assertNull($e->upstreamStatus);
        }

        $this->assertSame(1, $attempts);
        $this->assertNotNull(
            ErrorLog::query()->where('context->event', 'platform_http_connection_failed')->first(),
        );
    }

    private function configureRetry(int $times, int $baseMs, int $maxMs): void
    {
        config([
            'vtual.http.retry.times' => $times,
            'vtual.http.retry.base_ms' => $baseMs,
            'vtual.http.retry.max_ms' => $maxMs,
            'vtual.http.retry.jitter' => false,
        ]);
    }
}
