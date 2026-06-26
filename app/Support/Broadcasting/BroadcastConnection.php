<?php

namespace App\Support\Broadcasting;

/**
 * Decides whether the web notification bell uses WebSocket push or HTTP polling.
 *
 * Realtime delivery requires APP_NOTIFICATION_TYPE=realtime AND a usable broadcast
 * driver (Pusher / Socket-compatible). Polling mode and misconfigured realtime always
 * fall back to HTTP polling so the bell keeps working without a websocket server.
 */
class BroadcastConnection
{
    /**
     * Whether the default broadcast connection has the credentials it needs.
     */
    public static function isConfigured(): bool
    {
        $connection = (string) config('broadcasting.default');

        if ($connection === '' || in_array($connection, ['null', 'log'], true)) {
            return false;
        }

        $config = config("broadcasting.connections.{$connection}");

        if (! is_array($config)) {
            return false;
        }

        return match ($config['driver'] ?? null) {
            'pusher', 'reverb' => filled($config['key'] ?? null)
                && filled($config['secret'] ?? null)
                && filled($config['app_id'] ?? null),
            default => false,
        };
    }

    /**
     * Realtime websocket delivery for the navbar bell.
     */
    public static function usesRealtimeNotifications(): bool
    {
        return config('notifications.delivery') === 'realtime' && self::isConfigured();
    }

    /**
     * Client-safe Pusher / Echo options for the Blade bell (no secrets).
     *
     * @return array<string, mixed>|null
     */
    public static function clientConfig(): ?array
    {
        if (! self::usesRealtimeNotifications()) {
            return null;
        }

        $connection = (string) config('broadcasting.default');
        $config = config("broadcasting.connections.{$connection}");

        if (! is_array($config)) {
            return null;
        }

        $options = is_array($config['options'] ?? null) ? $config['options'] : [];

        return [
            'key' => $config['key'],
            'cluster' => $options['cluster'] ?? null,
            'host' => $options['host'] ?? null,
            'port' => (int) ($options['port'] ?? 443),
            'scheme' => $options['scheme'] ?? 'https',
            'authEndpoint' => url('/broadcasting/auth'),
        ];
    }
}
