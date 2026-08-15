<?php

namespace Tests\Support\Settings\Unit\Support;

use App\Support\Settings\Events\SettingsGroupChanged;
use App\Support\Settings\Support\SettingsRealtime;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class SettingsRealtimeTest extends TestCase
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
        $rt = app(SettingsRealtime::class);

        $this->disableBroadcast();
        $this->assertFalse($rt->enabled());

        $this->enableBroadcast();
        $this->assertTrue($rt->enabled());
    }

    public function test_group_changed_noop_when_unconfigured(): void
    {
        Event::fake([SettingsGroupChanged::class]);
        $this->disableBroadcast();

        app(SettingsRealtime::class)->groupChanged('platform', 'updated');

        Event::assertNotDispatched(SettingsGroupChanged::class);
    }

    public function test_group_changed_dispatches_when_configured(): void
    {
        Event::fake([SettingsGroupChanged::class]);
        $this->enableBroadcast();

        app(SettingsRealtime::class)->groupChanged('platform', 'updated');

        Event::assertDispatched(SettingsGroupChanged::class, function (SettingsGroupChanged $e): bool {
            return $e->group === 'platform'
                && $e->action === 'updated';
        });
    }
}
