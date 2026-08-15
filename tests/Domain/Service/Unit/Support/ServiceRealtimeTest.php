<?php

namespace Tests\Domain\Service\Unit\Support;

use App\Domain\Service\Events\ServiceCatalogChanged;
use App\Domain\Service\Events\ServicePurchaseChanged;
use App\Domain\Service\Support\ServiceRealtime;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ServiceRealtimeTest extends TestCase
{
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

    private function disableBroadcast(): void
    {
        config(['broadcasting.default' => 'null']);
    }

    public function test_enabled_follows_broadcast_config(): void
    {
        $rt = app(ServiceRealtime::class);

        $this->disableBroadcast();
        $this->assertFalse($rt->enabled());

        $this->enableBroadcast();
        $this->assertTrue($rt->enabled());
    }

    public function test_catalog_changed_noop_when_unconfigured(): void
    {
        Event::fake([ServiceCatalogChanged::class]);
        $this->disableBroadcast();

        app(ServiceRealtime::class)->catalogChanged('created', 'svc-1');

        Event::assertNotDispatched(ServiceCatalogChanged::class);
    }

    public function test_catalog_changed_dispatches_when_configured(): void
    {
        Event::fake([ServiceCatalogChanged::class]);
        $this->enableBroadcast();

        app(ServiceRealtime::class)->catalogChanged('updated', 'svc-2');

        Event::assertDispatched(ServiceCatalogChanged::class, function (ServiceCatalogChanged $e): bool {
            return $e->action === 'updated'
                && $e->serviceId === 'svc-2';
        });
    }

    public function test_purchase_changed_dispatches_when_configured(): void
    {
        Event::fake([ServicePurchaseChanged::class]);
        $this->enableBroadcast();

        app(ServiceRealtime::class)->purchaseChanged('pur-1', 'paid');

        Event::assertDispatched(ServicePurchaseChanged::class, function (ServicePurchaseChanged $e): bool {
            return $e->purchaseId === 'pur-1'
                && $e->action === 'paid';
        });
    }

    public function test_purchase_changed_noop_when_unconfigured(): void
    {
        Event::fake([ServicePurchaseChanged::class]);
        $this->disableBroadcast();

        app(ServiceRealtime::class)->purchaseChanged('pur-1', 'paid');

        Event::assertNotDispatched(ServicePurchaseChanged::class);
    }
}
