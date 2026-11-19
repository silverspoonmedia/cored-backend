<?php

namespace App\Domain\Inference\Tests\Unit\Support;

use App\Domain\Inference\Events\InferenceCatalogChanged;
use App\Domain\Inference\Support\InferenceRealtime;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class InferenceRealtimeTest extends TestCase
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
        $rt = app(InferenceRealtime::class);

        $this->disableBroadcast();
        $this->assertFalse($rt->enabled());

        $this->enableBroadcast();
        $this->assertTrue($rt->enabled());
    }

    public function test_catalog_changed_noop_when_unconfigured(): void
    {
        Event::fake([InferenceCatalogChanged::class]);
        $this->disableBroadcast();

        app(InferenceRealtime::class)->catalogChanged('provider', 'created', 'prov-1');

        Event::assertNotDispatched(InferenceCatalogChanged::class);
    }

    public function test_catalog_changed_dispatches_when_configured(): void
    {
        Event::fake([InferenceCatalogChanged::class]);
        $this->enableBroadcast();

        app(InferenceRealtime::class)->catalogChanged('model_card', 'updated', 'card-1');

        Event::assertDispatched(InferenceCatalogChanged::class, function (InferenceCatalogChanged $e): bool {
            return $e->entity === 'model_card'
                && $e->action === 'updated'
                && $e->entityId === 'card-1';
        });
    }
}
