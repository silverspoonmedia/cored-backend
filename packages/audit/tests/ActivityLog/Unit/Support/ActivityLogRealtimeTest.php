<?php

namespace App\Domain\ActivityLog\Tests\Unit\Support;

use App\Domain\ActivityLog\Events\ActivityLogAppended;
use App\Domain\ActivityLog\Support\ActivityLogRealtime;
use App\Domain\ActivityLog\Tests\TestCase;
use Illuminate\Support\Facades\Event;

class ActivityLogRealtimeTest extends TestCase
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
        $rt = app(ActivityLogRealtime::class);

        $this->disableBroadcast();
        $this->assertFalse($rt->enabled());

        $this->enableBroadcast();
        $this->assertTrue($rt->enabled());
    }

    public function test_appended_noop_when_unconfigured(): void
    {
        Event::fake([ActivityLogAppended::class]);
        $this->disableBroadcast();

        app(ActivityLogRealtime::class)->appended('auth', 'login');

        Event::assertNotDispatched(ActivityLogAppended::class);
    }

    public function test_appended_dispatches_when_configured(): void
    {
        Event::fake([ActivityLogAppended::class]);
        $this->enableBroadcast();

        app(ActivityLogRealtime::class)->appended('crypto', 'keystore_rotated');

        Event::assertDispatched(ActivityLogAppended::class, function (ActivityLogAppended $e): bool {
            return $e->logName === 'crypto'
                && $e->event === 'keystore_rotated'
                && $e->broadcastAs() === 'log.activity.appended'
                && $e->broadcastOn()[0]->name === 'private-logs.activity';
        });
    }
}
