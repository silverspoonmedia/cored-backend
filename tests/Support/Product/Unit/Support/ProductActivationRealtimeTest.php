<?php

namespace Tests\Support\Product\Unit\Support;

use App\Support\Product\Events\ProductActivationChanged;
use App\Support\Product\Support\ProductActivationRealtime;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ProductActivationRealtimeTest extends TestCase
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
        $rt = app(ProductActivationRealtime::class);

        $this->disableBroadcast();
        $this->assertFalse($rt->enabled());

        $this->enableBroadcast();
        $this->assertTrue($rt->enabled());
    }

    public function test_noop_when_unconfigured(): void
    {
        Event::fake([ProductActivationChanged::class]);
        $this->disableBroadcast();

        app(ProductActivationRealtime::class)->activationChanged('file-hosting', 'active', 'user-1');

        Event::assertNotDispatched(ProductActivationChanged::class);
    }

    public function test_dispatches_when_configured(): void
    {
        Event::fake([ProductActivationChanged::class]);
        $this->enableBroadcast();

        app(ProductActivationRealtime::class)->activationChanged('file-hosting', 'active', 'user-1');

        Event::assertDispatched(ProductActivationChanged::class, function (ProductActivationChanged $e): bool {
            return $e->productKey === 'file-hosting'
                && $e->action === 'active'
                && $e->userId === 'user-1';
        });
    }
}
