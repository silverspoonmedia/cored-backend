<?php

namespace App\Domain\Currency\Tests\Unit\Support;

use App\Domain\Currency\Events\CurrencyChanged;
use App\Domain\Currency\Services\CurrencyAdminService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\SeedsCurrencies;
use Tests\TestCase;

/**
 * Currency mutations broadcast on the settings channel only when a broadcaster
 * is configured, and the wire name stays `service.currency.changed` — the SPA
 * `useCurrenciesRealtime.ts` subscription predates this namespace.
 */
class CurrencyRealtimeTest extends TestCase
{
    use RefreshDatabase;
    use SeedsCurrencies;

    private function enableBroadcast(): void
    {
        config([
            'broadcasting.default' => 'pusher',
            'broadcasting.connections.pusher' => [
                'driver' => 'pusher',
                'key' => 'key',
                'secret' => 'secret',
                'app_id' => 'app',
                'options' => ['cluster' => 'ap1', 'port' => 443, 'scheme' => 'https'],
            ],
        ]);
    }

    public function test_currency_changed_noop_when_unconfigured(): void
    {
        $this->seedCurrencies();
        Event::fake([CurrencyChanged::class]);
        config(['broadcasting.default' => 'null']);

        app(CurrencyAdminService::class)->update('EUR', ['is_active' => false]);

        Event::assertNotDispatched(CurrencyChanged::class);
    }

    public function test_currency_changed_dispatches_when_configured(): void
    {
        $this->seedCurrencies();
        Event::fake([CurrencyChanged::class]);
        $this->enableBroadcast();

        app(CurrencyAdminService::class)->update('USD', ['is_active' => false]);

        Event::assertDispatched(CurrencyChanged::class, function (CurrencyChanged $e): bool {
            return $e->action === 'updated'
                && $e->currencyCode === 'USD'
                && $e->broadcastAs() === 'service.currency.changed'
                && $e->broadcastOn()[0]->name === 'private-settings.currencies';
        });
    }
}
