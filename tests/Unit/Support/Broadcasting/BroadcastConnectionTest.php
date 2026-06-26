<?php

namespace Tests\Unit\Support\Broadcasting;

use App\Support\Broadcasting\BroadcastConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BroadcastConnectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_configured_requires_pusher_credentials(): void
    {
        config([
            'broadcasting.default' => 'pusher',
            'broadcasting.connections.pusher' => [
                'driver' => 'pusher',
                'key' => '',
                'secret' => 'secret',
                'app_id' => 'app',
                'options' => [],
            ],
        ]);

        $this->assertFalse(BroadcastConnection::isConfigured());

        config([
            'broadcasting.connections.pusher.key' => 'key',
            'broadcasting.connections.pusher.secret' => 'secret',
            'broadcasting.connections.pusher.app_id' => 'app',
        ]);

        $this->assertTrue(BroadcastConnection::isConfigured());
    }

    public function test_realtime_requires_notification_type_and_valid_broadcast_config(): void
    {
        config([
            'notifications.delivery' => 'polling',
            'broadcasting.default' => 'pusher',
            'broadcasting.connections.pusher' => [
                'driver' => 'pusher',
                'key' => 'key',
                'secret' => 'secret',
                'app_id' => 'app',
                'options' => ['cluster' => 'ap1', 'port' => 443, 'scheme' => 'https'],
            ],
        ]);

        $this->assertFalse(BroadcastConnection::usesRealtimeNotifications());

        config(['notifications.delivery' => 'realtime']);
        $this->assertTrue(BroadcastConnection::usesRealtimeNotifications());

        config(['broadcasting.connections.pusher.key' => '']);
        $this->assertFalse(BroadcastConnection::usesRealtimeNotifications());
    }

    public function test_client_config_is_null_outside_realtime_mode(): void
    {
        config(['notifications.delivery' => 'polling']);

        $this->assertNull(BroadcastConnection::clientConfig());
    }

    public function test_client_config_exposes_public_pusher_fields_in_realtime_mode(): void
    {
        config([
            'notifications.delivery' => 'realtime',
            'broadcasting.default' => 'pusher',
            'broadcasting.connections.pusher' => [
                'driver' => 'pusher',
                'key' => 'public-key',
                'secret' => 'secret',
                'app_id' => 'app',
                'options' => [
                    'cluster' => 'ap1',
                    'host' => 'ws.example.test',
                    'port' => 6001,
                    'scheme' => 'https',
                ],
            ],
        ]);

        $this->assertSame([
            'key' => 'public-key',
            'cluster' => 'ap1',
            'host' => 'ws.example.test',
            'port' => 6001,
            'scheme' => 'https',
            'authEndpoint' => url('/broadcasting/auth'),
        ], BroadcastConnection::clientConfig());
    }
}
