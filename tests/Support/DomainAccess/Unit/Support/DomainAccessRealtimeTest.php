<?php

namespace Tests\Support\DomainAccess\Unit\Support;

use App\Support\DomainAccess\Events\DomainVisibilityChanged;
use App\Support\DomainAccess\Support\DomainAccessRealtime;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class DomainAccessRealtimeTest extends TestCase
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
        $rt = app(DomainAccessRealtime::class);

        $this->disableBroadcast();
        $this->assertFalse($rt->enabled());

        $this->enableBroadcast();
        $this->assertTrue($rt->enabled());
    }

    public function test_noop_when_unconfigured(): void
    {
        Event::fake([DomainVisibilityChanged::class]);
        $this->disableBroadcast();

        app(DomainAccessRealtime::class)->visibilityChanged('archivd', 'private');

        Event::assertNotDispatched(DomainVisibilityChanged::class);
    }

    public function test_dispatches_when_configured(): void
    {
        Event::fake([DomainVisibilityChanged::class]);
        $this->enableBroadcast();

        app(DomainAccessRealtime::class)->visibilityChanged('archivd', 'private');

        Event::assertDispatched(DomainVisibilityChanged::class, function (DomainVisibilityChanged $e): bool {
            return $e->productKey === 'archivd'
                && $e->visibility === 'private';
        });
    }
}
