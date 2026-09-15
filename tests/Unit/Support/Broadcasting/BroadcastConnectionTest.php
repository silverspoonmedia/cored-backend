<?php

namespace Tests\Unit\Support\Broadcasting;

use App\Support\Broadcasting\BroadcastAuthEndpoint;
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

    public function test_socket_client_config_works_without_notification_realtime(): void
    {
        config([
            'notifications.delivery' => 'polling',
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

        $this->assertNull(BroadcastConnection::clientConfig());
        $this->assertSame([
            'key' => 'public-key',
            'cluster' => 'ap1',
            'host' => 'ws.example.test',
            'port' => 6001,
            'scheme' => 'https',
            'authEndpoint' => BroadcastAuthEndpoint::url(),
        ], BroadcastConnection::socketClientConfig());
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
            'authEndpoint' => BroadcastAuthEndpoint::url(),
        ], BroadcastConnection::clientConfig());
    }

    public function test_client_config_auth_endpoint_targets_the_versioned_api(): void
    {
        // The framework registers a web-group twin of /broadcasting/auth. That
        // route is CSRF-guarded and not CORS-covered, so a client sent there
        // fails as an opaque browser CORS error. Server-issued config must always
        // point at the API endpoint.
        config([
            'notifications.delivery' => 'realtime',
            'broadcasting.default' => 'pusher',
            'broadcasting.connections.pusher' => [
                'driver' => 'pusher',
                'key' => 'public-key',
                'secret' => 'secret',
                'app_id' => 'app',
                'options' => ['cluster' => 'eu', 'port' => 443, 'scheme' => 'https'],
            ],
        ]);

        $config = BroadcastConnection::clientConfig();

        $this->assertNotNull($config);
        $this->assertSame(BroadcastAuthEndpoint::url(), $config['authEndpoint']);
        $this->assertStringEndsWith('/'.BroadcastAuthEndpoint::PATH, $config['authEndpoint']);
        $this->assertStringContainsString('/api/v1/broadcasting/auth', $config['authEndpoint']);
    }

    public function test_client_config_strips_default_pusher_rest_api_host(): void
    {
        // Laravel's pusher options default host to api-{cluster}.pusher.com for
        // the server-side REST client. That must not reach Echo as wsHost.
        config([
            'notifications.delivery' => 'realtime',
            'broadcasting.default' => 'pusher',
            'broadcasting.connections.pusher' => [
                'driver' => 'pusher',
                'key' => 'public-key',
                'secret' => 'secret',
                'app_id' => 'app',
                'options' => [
                    'cluster' => 'eu',
                    'host' => 'api-eu.pusher.com',
                    'port' => 443,
                    'scheme' => 'https',
                ],
            ],
        ]);

        $config = BroadcastConnection::clientConfig();

        $this->assertNotNull($config);
        $this->assertNull($config['host']);
        $this->assertSame('eu', $config['cluster']);
        $this->assertSame('public-key', $config['key']);
    }
}
