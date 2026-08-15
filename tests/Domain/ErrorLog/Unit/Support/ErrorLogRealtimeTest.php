<?php

namespace Tests\Domain\ErrorLog\Unit\Support;

use App\Domain\ErrorLog\Events\ErrorLogRecorded;
use App\Domain\ErrorLog\Support\ErrorLogRealtime;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ErrorLogRealtimeTest extends TestCase
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
        $rt = app(ErrorLogRealtime::class);

        $this->disableBroadcast();
        $this->assertFalse($rt->enabled());

        $this->enableBroadcast();
        $this->assertTrue($rt->enabled());
    }

    public function test_recorded_noop_when_unconfigured(): void
    {
        Event::fake([ErrorLogRecorded::class]);
        $this->disableBroadcast();

        app(ErrorLogRealtime::class)->recorded('error', 'exception');

        Event::assertNotDispatched(ErrorLogRecorded::class);
    }

    public function test_recorded_dispatches_when_configured(): void
    {
        Event::fake([ErrorLogRecorded::class]);
        $this->enableBroadcast();

        app(ErrorLogRealtime::class)->recorded('critical', 'log');

        Event::assertDispatched(ErrorLogRecorded::class, function (ErrorLogRecorded $e): bool {
            return $e->level === 'critical'
                && $e->type === 'log';
        });
    }
}
