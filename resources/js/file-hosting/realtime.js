/**
 * File Hosting live listing — dedicated private channels (not notification bell).
 *
 * Soft-refetch is the correctness floor; single-entity DOM patch is polish.
 * Gate: broadcast credentials only (independent of APP_NOTIFICATION_TYPE).
 */
import { createNotificationEcho } from '../notifications/echo';

const GRACE_MS = 30000;
const POLL_MS = 60000;

/**
 * @param {object} options
 * @param {HTMLElement} options.root
 * @param {object} options.config  data-config from Blade
 * @param {string} options.csrf
 * @param {() => void} options.onListingRefresh  soft-refetch current folder listing
 * @param {() => void} options.onQuotaRefresh
 * @param {() => void} [options.onTrashRefresh]
 * @param {() => void} [options.onMembersRefresh]
 * @param {() => void} [options.onActivityRefresh]
 * @param {(payload: object) => void} [options.onListingPatch]
 * @param {(payload: object) => void} [options.onFileStats]
 * @param {(payload: object) => void} [options.onMembersChanged]
 * @param {(payload: object) => void} [options.onCollectionProgress]
 * @param {(payload: object) => void} [options.onAssetMetadataProgress]
 */
export function initFileHostingRealtime(options) {
    const {
        root,
        config,
        csrf,
        onListingRefresh,
        onQuotaRefresh,
        onTrashRefresh,
        onMembersRefresh,
        onActivityRefresh,
        onListingPatch,
        onFileStats,
        onMembersChanged,
        onCollectionProgress,
        onAssetMetadataProgress,
    } = options;

    const realtime = config.realtime || {};
    const broadcast = realtime.broadcast;
    const userId = realtime.userId;
    // page / workspace / fileId read live from config so SPA soft-nav rebinds without resubscribe when only page changes.
    const workspaceId = () => config.workspaceId || null;
    const ownerUserId = () => config.ownerUserId || userId;
    const page = () => (config.realtime?.page || realtime.page || 'index');
    const fileId = () => config.realtime?.fileId || realtime.fileId || null;

    if (!broadcast?.authEndpoint || !userId || !(broadcast.key || import.meta.env.VITE_PUSHER_APP_KEY)) {
        window.fileHostingRealtime = { enabled: false, connected: false, degraded: false };
        startFallbackPoll();
        return { softRefresh: softRefreshAll, disconnect() { stopPoll(); } };
    }

    let connected = false;
    let degraded = false;
    let graceTimer = null;
    let pollTimer = null;
    let echo = null;
    let ownsEcho = false;

    function setHealth(nextConnected, nextDegraded) {
        connected = nextConnected;
        degraded = nextDegraded;
        window.fileHostingRealtime = { enabled: true, connected, degraded };
        window.dispatchEvent(new CustomEvent(
            nextConnected ? 'file-hosting-realtime:connected' : 'file-hosting-realtime:degraded',
        ));
    }

    function softRefreshAll() {
        const p = page();
        if (p === 'trash') {
            onTrashRefresh?.();
        } else if (p === 'show') {
            // stats only — no listing
        } else {
            onListingRefresh?.();
        }
        onQuotaRefresh?.();
    }

    function stopPoll() {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    function startFallbackPoll() {
        stopPoll();
        pollTimer = setInterval(() => {
            if (document.visibilityState !== 'visible') {
                return;
            }
            softRefreshAll();
        }, POLL_MS);
    }

    function onConnected() {
        if (graceTimer) {
            clearTimeout(graceTimer);
            graceTimer = null;
        }
        stopPoll();
        setHealth(true, false);
        softRefreshAll();
    }

    function onNotConnected() {
        setHealth(false, false);
        if (graceTimer) {
            return;
        }
        graceTimer = setTimeout(() => {
            graceTimer = null;
            setHealth(false, true);
            startFallbackPoll();
        }, GRACE_MS);
    }

    function channelName() {
        const ws = workspaceId();
        if (ws) {
            return `file-hosting.workspace.${ws}`;
        }
        return `file-hosting.user.${ownerUserId() || userId}`;
    }

    function handleListing(payload) {
        if (typeof onListingPatch === 'function') {
            const handled = onListingPatch(payload);
            if (handled === true) {
                return;
            }
        }
        if (page() === 'trash') {
            onTrashRefresh?.();
        } else if (page() === 'show') {
            // show listens via patch path
        } else {
            onListingRefresh?.();
        }
    }

    try {
        if (window.__monolithEcho) {
            echo = window.__monolithEcho;
        } else {
            echo = createNotificationEcho(broadcast, csrf);
            window.__monolithEcho = echo;
            ownsEcho = true;
        }

        const connection = echo?.connector?.pusher?.connection;
        if (connection) {
            connection.bind('state_change', ({ current }) => {
                if (current === 'connected') {
                    onConnected();
                } else {
                    onNotConnected();
                }
            });
            if (connection.state === 'connected') {
                onConnected();
            } else {
                onNotConnected();
            }
        }

        const ch = echo.private(channelName());
        ch.listen('.listing.changed', (e) => handleListing(e || {}));
        ch.listen('.quota.changed', () => onQuotaRefresh?.());
        ch.listen('.members.changed', (e) => {
            onMembersChanged?.(e || {});
            onMembersRefresh?.();
            onActivityRefresh?.();
        });
        ch.listen('.activity.appended', () => onActivityRefresh?.());
        ch.listen('.file.stats.changed', (e) => {
            const fid = fileId();
            if (fid && e?.file_id && e.file_id !== fid) {
                return;
            }
            onFileStats?.(e || {});
        });
        ch.listen('.collection.analysis.progress', (e) => onCollectionProgress?.(e || {}));
        ch.listen('.asset.metadata.progress', (e) => onAssetMetadataProgress?.(e || {}));

        // Storage-owner quota also lands on personal user channel when browsing a team.
        const ws = workspaceId();
        const owner = ownerUserId();
        if (ws && owner && owner !== userId) {
            // team member viewing someone else's pool — quota bar is personal; still listen own channel
            echo.private(`file-hosting.user.${userId}`)
                .listen('.quota.changed', () => onQuotaRefresh?.());
        }

        setHealth(false, false);
    } catch {
        window.fileHostingRealtime = { enabled: false, connected: false, degraded: true };
        startFallbackPoll();
    }

    root.dataset.fhRealtimeBound = '1';
    const subscribedChannel = channelName();

    return {
        softRefresh: softRefreshAll,
        channel: subscribedChannel,
        disconnect() {
            stopPoll();
            if (graceTimer) {
                clearTimeout(graceTimer);
            }
            try {
                if (echo && subscribedChannel) {
                    echo.leave(subscribedChannel);
                }
            } catch {
                // ignore leave errors
            }
            root.dataset.fhRealtimeBound = '0';
            // Do not disconnect shared Echo — bell may still need it.
            if (ownsEcho && !document.getElementById('notification-bell')) {
                echo?.disconnect();
                window.__monolithEcho = null;
            }
        },
    };
}

/**
 * Fetch JSON from a same-origin web route.
 * Retries once after 500ms on failure so transient errors don't leave the DOM stale.
 * @param {string} url
 * @param {string} csrf
 * @returns {Promise<object|null>}
 */
export async function fetchJson(url, csrf) {
    const attempt = () => fetch(url, {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrf,
        },
        credentials: 'same-origin',
    }).then((res) => (res.ok ? res.json() : null));

    try {
        let json = await attempt();
        if (json) {
            return json;
        }
        await new Promise((r) => setTimeout(r, 500));
        return await attempt();
    } catch {
        return null;
    }
}

/**
 * Build browse URL with current folder/workspace query params.
 * @param {string} browseUrl
 * @param {object} config
 */
export function browseUrlWithContext(browseUrl, config) {
    const u = new URL(browseUrl, window.location.origin);
    if (config.currentFolderId) {
        u.searchParams.set('folder_id', config.currentFolderId);
    } else {
        u.searchParams.delete('folder_id');
    }
    if (config.ownerUserId) {
        u.searchParams.set('user_id', config.ownerUserId);
    }
    if (config.workspaceId) {
        u.searchParams.set('workspace_id', config.workspaceId);
    }
    return u.toString();
}
