<?php

namespace Tests\Domain\Service\Unit\Services;

use App\Domain\Service\Exceptions\ExchangeRateException;
use App\Domain\Service\Models\ExchangeRateSnapshot;
use App\Domain\Service\Services\ExchangeRateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\SeedsCurrencies;
use Tests\TestCase;

class ExchangeRateServiceTest extends TestCase
{
    use RefreshDatabase;
    use SeedsCurrencies;

    private ExchangeRateService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCurrencies();
        Cache::flush();
        $this->service = app(ExchangeRateService::class);
    }

    public function test_convert_usd_to_idr_uses_frankfurter_rate(): void
    {
        Http::fake([
            'api.frankfurter.app/*' => Http::response([
                'amount' => 1,
                'base' => 'USD',
                'date' => '2026-07-15',
                'rates' => ['IDR' => 18000],
            ], 200),
        ]);

        $result = $this->service->convert(10.0, 'USD', 'IDR');

        $this->assertSame(18000.0, $result['rate']);
        $this->assertSame(180000.0, $result['amount']);
        $this->assertSame('USD', $result['from']);
        $this->assertSame('IDR', $result['to']);
        $this->assertSame(ExchangeRateService::PROVIDER, $result['provider']);
        $this->assertNotNull($result['snapshot_id']);
    }

    public function test_convert_persists_exchange_rate_snapshot(): void
    {
        Http::fake([
            'api.frankfurter.app/*' => Http::response([
                'amount' => 1,
                'base' => 'USD',
                'date' => '2026-07-15',
                'rates' => ['IDR' => 18000],
            ], 200),
        ]);

        $result = $this->service->convert(10.0, 'USD', 'IDR');

        $this->assertDatabaseHas('exchange_rate_snapshots', [
            'id' => $result['snapshot_id'],
            'base_currency' => 'USD',
            'quote_currency' => 'IDR',
            'provider' => 'frankfurter',
        ]);

        $this->assertSame(1, ExchangeRateSnapshot::query()->count());
    }

    public function test_convert_to_idr_rounds_to_whole_rupiah(): void
    {
        Http::fake([
            'api.frankfurter.app/*' => Http::response([
                'rates' => ['IDR' => 18197.55],
            ], 200),
        ]);

        $result = $this->service->convert(10.0, 'USD', 'IDR');

        $this->assertSame(18197.55, $result['rate']);
        $this->assertSame(181976.0, $result['amount']);
        $this->assertSame(0.0, fmod($result['amount'], 1.0));
    }

    public function test_same_currency_returns_rate_one_without_http(): void
    {
        Http::fake();

        $result = $this->service->convert(50.25, 'IDR', 'IDR');

        $this->assertSame(1.0, $result['rate']);
        $this->assertSame(50.0, $result['amount']);
        $this->assertNull($result['snapshot_id']);
        Http::assertNothingSent();
    }

    public function test_rate_is_cached_and_skips_second_http(): void
    {
        Http::fake([
            'api.frankfurter.app/*' => Http::response([
                'rates' => ['IDR' => 16000],
            ], 200),
        ]);

        $this->assertSame(16000.0, $this->service->rate('USD', 'IDR'));
        $this->assertSame(16000.0, $this->service->rate('USD', 'IDR'));

        Http::assertSentCount(1);
        $this->assertSame(1, ExchangeRateSnapshot::query()->count());
        $this->assertTrue(Cache::has('fx.frankfurter.USD.IDR'));
    }

    public function test_aged_l1_cache_forces_rebuild_from_fresh_db_or_http(): void
    {
        Http::fake([
            'api.frankfurter.app/*' => Http::sequence()
                ->push(['rates' => ['IDR' => 16000]], 200)
                ->push(['rates' => ['IDR' => 17000]], 200),
        ]);

        \Illuminate\Support\Carbon::setTestNow('2026-01-01 00:00:00');
        $this->assertSame(16000.0, $this->service->rate('USD', 'IDR'));
        Http::assertSentCount(1);

        // Within freshness (default 3600s): forever L1 hit, no new HTTP.
        \Illuminate\Support\Carbon::setTestNow('2026-01-01 00:30:00');
        $this->assertSame(16000.0, $this->service->rate('USD', 'IDR'));
        Http::assertSentCount(1);

        // Past freshness: L1 ignored; DB snapshot also stale → new Frankfurter fetch.
        \Illuminate\Support\Carbon::setTestNow('2026-01-01 02:00:00');
        $this->assertSame(17000.0, $this->service->rate('USD', 'IDR'));
        Http::assertSentCount(2);

        \Illuminate\Support\Carbon::setTestNow();
    }

    public function test_db_snapshot_within_ttl_avoids_http_after_cache_flush(): void
    {
        Http::fake([
            'api.frankfurter.app/*' => Http::response([
                'rates' => ['IDR' => 17000],
            ], 200),
        ]);

        $this->service->rate('USD', 'IDR');
        Http::assertSentCount(1);

        Cache::flush();

        $this->assertSame(17000.0, $this->service->rate('USD', 'IDR'));
        Http::assertSentCount(1);
    }

    public function test_http_failure_throws_exchange_rate_exception(): void
    {
        Http::fake([
            'api.frankfurter.app/*' => Http::response(['error' => 'down'], 503),
        ]);

        $this->expectException(ExchangeRateException::class);

        $this->service->convert(10.0, 'USD', 'IDR');
    }

    public function test_missing_rate_in_payload_throws(): void
    {
        Http::fake([
            'api.frankfurter.app/*' => Http::response([
                'rates' => ['EUR' => 0.9],
            ], 200),
        ]);

        $this->expectException(ExchangeRateException::class);

        $this->service->rate('USD', 'IDR');
    }
}
