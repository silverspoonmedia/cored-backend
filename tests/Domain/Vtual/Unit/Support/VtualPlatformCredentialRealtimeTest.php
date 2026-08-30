<?php

namespace Tests\Domain\Vtual\Unit\Support;

use App\Domain\Vtual\Enums\PlatformCredentialStatus;
use App\Domain\Vtual\Events\PlatformCredentialChanged;
use App\Domain\Vtual\Support\VtualPlatformCredentialRealtime;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class VtualPlatformCredentialRealtimeTest extends TestCase
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
        $rt = app(VtualPlatformCredentialRealtime::class);

        $this->disableBroadcast();
        $this->assertFalse($rt->enabled());

        $this->enableBroadcast();
        $this->assertTrue($rt->enabled());
    }

    public function test_changed_noop_when_unconfigured(): void
    {
        Event::fake([PlatformCredentialChanged::class]);
        $this->disableBroadcast();

        app(VtualPlatformCredentialRealtime::class)->changed(
            'plat-1',
            'cred-1',
            'tested',
            PlatformCredentialStatus::Active,
        );

        Event::assertNotDispatched(PlatformCredentialChanged::class);
    }

    public function test_changed_dispatches_when_configured(): void
    {
        Event::fake([PlatformCredentialChanged::class]);
        $this->enableBroadcast();

        app(VtualPlatformCredentialRealtime::class)->changed(
            'plat-1',
            'cred-1',
            'tested',
            PlatformCredentialStatus::Active,
        );

        Event::assertDispatched(PlatformCredentialChanged::class, function (PlatformCredentialChanged $e): bool {
            return $e->platformId === 'plat-1'
                && $e->credentialId === 'cred-1'
                && $e->action === 'tested'
                && $e->status === PlatformCredentialStatus::Active->value
                && $e->broadcastAs() === 'platform.credential.changed'
                && $e->broadcastOn()[0]->name === 'private-vtual.platform-credentials'
                && $e->broadcastWith() === [
                    'platform_id' => 'plat-1',
                    'credential_id' => 'cred-1',
                    'action' => 'tested',
                    'status' => 'active',
                ];
        });
    }

    public function test_deleted_payload_allows_null_status(): void
    {
        Event::fake([PlatformCredentialChanged::class]);
        $this->enableBroadcast();

        app(VtualPlatformCredentialRealtime::class)->changed('plat-1', 'cred-1', 'deleted', null);

        Event::assertDispatched(PlatformCredentialChanged::class, function (PlatformCredentialChanged $e): bool {
            return $e->action === 'deleted' && $e->status === null;
        });
    }
}
