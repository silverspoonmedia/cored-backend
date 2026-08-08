import { ref, onUnmounted } from 'vue';
import { createNotificationEcho } from '../../../../notifications/echo';

const REALTIME_GRACE_MS = 30_000;

/**
 * Echo/Pusher connection for claim progress events.
 *
 * Reuses window.__monolithEcho when the navbar bell already opened Pusher.
 * Manages connection state, private channel subscription, and claim.progress
 * event forwarding back to the consuming component via a callback.
 *
 * @param {import('vue').Ref<object>} realtime - reactive realtime config blob
 * @param {import('vue').Ref<string|null>} claimId - reactive claim ID
 * @param {(phase: string, claim: object) => void} onProgress - callback on claim.progress event
 * @returns {{ connected: import('vue').Ref<boolean>, enabled: import('vue').Ref<boolean> }}
 */
export function useClaimRealtime(realtime, claimId, onProgress) {
    const connected = ref(false);
    const enabled = ref(false);
    let echo = null;
    let ownsEcho = false;
    let channel = null;
    let graceTimer = null;
    let subscribedClaimId = null;

    function isSocketHealthy() {
        if (window.notificationRealtime?.connected === true) {
            return true;
        }

        const connection = echo?.connector?.pusher?.connection;
        return connection?.state === 'connected';
    }

    function clearGraceTimer() {
        if (graceTimer) {
            clearTimeout(graceTimer);
            graceTimer = null;
        }
    }

    function onRealtimeConnected() {
        clearGraceTimer();
        connected.value = true;
    }

    function onRealtimeNotConnected() {
        connected.value = false;

        if (graceTimer) {
            return;
        }

        graceTimer = setTimeout(() => {
            graceTimer = null;
            if (!isSocketHealthy()) {
                connected.value = false;
            }
        }, REALTIME_GRACE_MS);
    }

    function bindConnectionHandlers() {
        const connection = echo?.connector?.pusher?.connection;

        if (!connection) {
            return;
        }

        connection.bind('connected', onRealtimeConnected);
        connection.bind('disconnected', onRealtimeNotConnected);

        if (connection.state === 'connected') {
            onRealtimeConnected();
        } else {
            onRealtimeNotConnected();
        }
    }

    function connect() {
        const broadcast = realtime.value?.broadcast;

        if (!broadcast?.authEndpoint || !realtime.value?.userId || !(broadcast.key || import.meta.env.VITE_PUSHER_APP_KEY)) {
            enabled.value = false;
            return;
        }

        enabled.value = true;

        try {
            if (window.__monolithEcho) {
                echo = window.__monolithEcho;
            } else {
                echo = createNotificationEcho(broadcast, csrfToken());
                window.__monolithEcho = echo;
                ownsEcho = true;
            }
        } catch (err) {
            console.error('vtual claim realtime: echo init failed', err);
            onRealtimeNotConnected();
            return;
        }

        bindConnectionHandlers();

        if (claimId.value) {
            subscribe(claimId.value);
        }
    }

    function subscribe(id) {
        if (!echo || !id) {
            return;
        }
        if (channel) {
            echo.leave(`private-vtual.claim.${subscribedClaimId}`);
        }
        subscribedClaimId = id;
        channel = echo.private(`vtual.claim.${id}`);
        channel.listen('.claim.progress', (payload) => {
            onProgress(payload?.phase ?? '', payload?.claim ?? {});
        });
    }

    function resubscribeTo(id) {
        if (!id || id === subscribedClaimId) {
            return;
        }
        subscribe(id);
    }

    function disconnect() {
        clearGraceTimer();
        try {
            if (echo && subscribedClaimId) {
                echo.leave(`private-vtual.claim.${subscribedClaimId}`);
            }
        } catch {
            // ignore leave errors
        }
        if (ownsEcho && !document.getElementById('notification-bell')) {
            echo?.disconnect();
            window.__monolithEcho = null;
        }
    }

    // Initialise
    connect();

    onUnmounted(() => {
        disconnect();
    });

    return {
        connected,
        enabled,
        resubscribeTo,
    };
}

/**
 * Read CSRF token from meta tag.
 * @returns {string}
 */
function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}
