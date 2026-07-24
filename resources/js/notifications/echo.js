import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

/**
 * Build a Laravel Echo client for Pusher / Socket-compatible hosts.
 *
 * Public credentials come from VITE_PUSHER_* (.env). Server-injected
 * `broadcast` may override host/port/scheme when runtime config differs
 * from the Vite build (e.g. same artefact, multiple deploy targets).
 *
 * @param {{ authEndpoint: string, key?: string, cluster?: string, host?: string, port?: number, scheme?: string }} broadcast
 * @param {string} csrf
 */
export function createNotificationEcho(broadcast, csrf) {
    window.Pusher = Pusher;

    const key = broadcast.key ?? import.meta.env.VITE_PUSHER_APP_KEY;
    const cluster = broadcast.cluster ?? import.meta.env.VITE_PUSHER_APP_CLUSTER ?? 'mt1';
    // Only honour an explicit non-empty host (self-hosted / Reverb). Empty VITE
    // vars and Laravel's default `api-{cluster}.pusher.com` REST host must not
    // become wsHost — that kills the Pusher-hosted WebSocket connection.
    const rawHost = broadcast.host ?? import.meta.env.VITE_PUSHER_HOST ?? '';
    const host = typeof rawHost === 'string' && rawHost.trim() !== ''
        && !/^api-[a-z0-9-]+\.pusher\.com$/i.test(rawHost.trim())
        ? rawHost.trim()
        : undefined;
    const port = Number(broadcast.port ?? import.meta.env.VITE_PUSHER_PORT ?? 443);
    const scheme = broadcast.scheme ?? import.meta.env.VITE_PUSHER_SCHEME ?? 'https';

    /** @type {import('laravel-echo').EchoOptions<'pusher'>} */
    const options = {
        broadcaster: 'pusher',
        key,
        cluster,
        forceTLS: scheme === 'https',
        encrypted: true,
        authorizer: (channel) => ({
            authorize: (socketId, callback) => {
                fetch(broadcast.authEndpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        socket_id: socketId,
                        channel_name: channel.name,
                    }),
                })
                    .then((res) => res.json())
                    .then((data) => callback(null, data))
                    .catch((err) => callback(err, null));
            },
        }),
    };

    if (host) {
        options.wsHost = host;
        options.wsPort = port;
        options.wssPort = port;
        options.enabledTransports = ['ws', 'wss'];
    }

    return new Echo(options);
}
