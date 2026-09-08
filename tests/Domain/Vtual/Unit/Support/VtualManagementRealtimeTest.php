<?php

namespace Tests\Domain\Vtual\Unit\Support;

use App\Domain\Vtual\Events\ManagementUpdated;
use App\Domain\Vtual\Support\VtualManagementRealtime;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class VtualManagementRealtimeTest extends TestCase
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
        $rt = app(VtualManagementRealtime::class);

        $this->disableBroadcast();
        $this->assertFalse($rt->enabled());

        $this->enableBroadcast();
        $this->assertTrue($rt->enabled());
    }

    public function test_changed_noop_when_unconfigured(): void
    {
        Event::fake([ManagementUpdated::class]);
        $this->disableBroadcast();

        app(VtualManagementRealtime::class)->changed('mgmt-1', 'invite', 'inv-1', 'created');

        Event::assertNotDispatched(ManagementUpdated::class);
    }

    public function test_changed_dispatches_event_when_configured(): void
    {
        Event::fake([ManagementUpdated::class]);
        $this->enableBroadcast();

        app(VtualManagementRealtime::class)->changed('mgmt-1', 'invite', 'inv-1', 'created');

        Event::assertDispatched(ManagementUpdated::class, function (ManagementUpdated $event): bool {
            return $event->managementId === 'mgmt-1'
                && $event->kind === 'invite'
                && $event->refId === 'inv-1'
                && $event->action === 'created'
                && $event->broadcastAs() === 'management.updated'
                && $event->broadcastWith() === [
                    'management_id' => 'mgmt-1',
                    'kind' => 'invite',
                    'ref_id' => 'inv-1',
                    'action' => 'created',
                ]
                && $event->broadcastOn()[0]->name === 'private-vtual.management.mgmt-1';
        });
    }
}
