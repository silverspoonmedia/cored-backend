import { createNotificationEcho } from './echo';

/**
 * Navbar notification bell — polling (local / invalid broadcast) or Echo push.
 *
 * Config is injected on `#notification-bell` via `data-notification-config`
 * (routes, labels, realtime flag, viewer id, broadcast auth endpoint).
 */

function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = value ?? '';
    return div.innerHTML;
}

function relativeTime(iso) {
    if (!iso) {
        return '';
    }

    const then = new Date(iso).getTime();

    if (Number.isNaN(then)) {
        return '';
    }

    const secs = Math.max(1, Math.round((Date.now() - then) / 1000));

    if (secs < 60) {
        return `${secs}s`;
    }

    const mins = Math.round(secs / 60);

    if (mins < 60) {
        return `${mins}m`;
    }

    const hours = Math.round(mins / 60);

    if (hours < 24) {
        return `${hours}h`;
    }

    return `${Math.round(hours / 24)}d`;
}

/**
 * Pending table reloads (tableId set). Used when reload event arrives before
 * DataTable initialization completes (e.g., during navigation).
 */
const pendingReloads = new Set();

/**
 * Reload a DataTable if it exists on the current page. If the table doesn't
 * exist yet, queue it for retry (handles timing issues during navigation).
 */
function reloadDataTable(tableId) {
    if (!tableId) {
        return;
    }

    // Immediate reload if table exists
    if (typeof window.dataTables === 'object' && window.dataTables[tableId]) {
        const table = window.dataTables[tableId];

        if (table && typeof table.ajax === 'object' && typeof table.ajax.reload === 'function') {
            table.ajax.reload(null, false); // false = stay on current page
            pendingReloads.delete(tableId); // Clear if it was pending
            return;
        }
    }

    // Table doesn't exist yet — queue for retry
    pendingReloads.add(tableId);
    retryPendingReloads();
}

/**
 * Check for flash session flag (set by controller on redirect) and trigger
 * reload once DataTable is ready. Centralized handling for active-tab reloads.
 */
function handleFlashReload() {
    const meta = document.querySelector('meta[name="datatable-reload"]');
    const tableId = meta?.getAttribute('content');
    
    if (tableId) {
        reloadDataTable(tableId);
    }
}

/**
 * Retry pending table reloads with exponential backoff (max 3 attempts over 2s).
 * Handles case where reload event arrives during page navigation.
 */
let retryAttempt = 0;
function retryPendingReloads() {
    if (pendingReloads.size === 0 || retryAttempt >= 3) {
        retryAttempt = 0;
        return;
    }

    const delay = [500, 1000, 2000][retryAttempt];
    retryAttempt++;

    setTimeout(() => {
        for (const tableId of pendingReloads) {
            if (typeof window.dataTables === 'object' && window.dataTables[tableId]) {
                const table = window.dataTables[tableId];

                if (table && typeof table.ajax === 'object' && typeof table.ajax.reload === 'function') {
                    table.ajax.reload(null, false);
                    pendingReloads.delete(tableId);
                }
            }
        }

        // Retry remaining pending reloads
        if (pendingReloads.size > 0) {
            retryPendingReloads();
        } else {
            retryAttempt = 0;
        }
    }, delay);
}

function initNotificationBell(config) {
    const bell = document.getElementById('notification-bell');

    if (!bell) {
        return;
    }

    // Destructure config BEFORE any use of its fields. `const` bindings live in a
    // temporal dead zone until this line, so touching `labels` earlier throws a
    // ReferenceError that the caller's try/catch swallows — killing the whole bell.
    const {
        urls,
        labels,
        realtime,
        pollingIntervalSeconds = 60,
        initialUnreadCount = 0,
        userId,
        broadcast,
    } = config;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

    const badge = bell.querySelector('[data-notification-badge]');
    const list = bell.querySelector('[data-notification-list]');
    const header = bell.querySelector('[data-notification-header]');

    if (badge) {
        badge.setAttribute('role', 'status');
        badge.setAttribute('aria-live', 'polite');
        badge.setAttribute('aria-label', badge.getAttribute('aria-label') || labels?.badge || 'Unread notifications');
    }

    if (list) {
        list.setAttribute('aria-live', 'polite');
        list.setAttribute('aria-atomic', 'false');
    }

    const headerDefault = header ? header.textContent : '';

    function setBadge(count) {
        const readAllButton = bell.querySelector('[data-notification-read-all]');

        if (readAllButton) {
            readAllButton.disabled = count <= 0;
        }

        document.dispatchEvent(new CustomEvent('notification-unread-count:changed', {
            detail: { unread: count },
        }));

        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : String(count);
            badge.classList.remove('d-none');

            if (header) {
                header.textContent = labels.header.replace(':count', String(count));
            }
        } else {
            badge.classList.add('d-none');

            if (header) {
                header.textContent = headerDefault;
            }
        }
    }

    // Server-rendered bootstrap avoids an initial unread-count HTTP request.
    setBadge(Number(initialUnreadCount) || 0);

    async function refreshBadge() {
        try {
            const res = await fetch(urls.unread, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            if (!res.ok) {
                return;
            }

            const data = await res.json();
            setBadge(Number(data.unread || 0));
        } catch {
            // Bell is non-critical — fail silently.
        }
    }

    async function refreshList() {
        try {
            const res = await fetch(urls.recent, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            if (!res.ok) {
                return;
            }

            const { data = [] } = await res.json();

            if (!data.length) {
                list.innerHTML = `<span class="dropdown-item text-secondary fs-7">${escapeHtml(labels.empty)}</span>`;

                return;
            }

            list.innerHTML = data
                .map((n, index) => {
                    const divider = index > 0 ? '<div class="dropdown-divider"></div>' : '';
                    const icon = `<i class="${escapeHtml(n.icon || 'bi bi-bell')} me-2"></i>`;
                    const time = relativeTime(n.created_at);
                    const timeTag = time
                        ? `<span class="float-end text-secondary fs-7">${escapeHtml(time)}</span>`
                        : '';
                    const weight = n.is_read ? '' : ' fw-semibold';
                    const href = n.url ? n.url : '#';

                    return (
                        divider +
                        `<a class="dropdown-item text-truncate${weight}" href="${href}" ` +
                        `title="${escapeHtml(n.message)}" data-notification-id="${escapeHtml(n.id)}">` +
                        icon +
                        escapeHtml(n.message) +
                        timeTag +
                        '</a>'
                    );
                })
                .join('');

            list.querySelectorAll('[data-notification-id]').forEach((anchor) => {
                anchor.addEventListener('click', () => {
                    const id = anchor.getAttribute('data-notification-id');

                    fetch(`${urls.read}${id}/read`, {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': csrf,
                        },
                        credentials: 'same-origin',
                    }).then(refreshBadge);
                });
            });
        } catch {
            // Bell is non-critical — fail silently.
        }
    }

    function isDropdownOpen() {
        const toggle = bell.querySelector('[data-bs-toggle="dropdown"]');

        return toggle?.getAttribute('aria-expanded') === 'true';
    }

    function onRealtimePing(eventData = null, fromTabSync = false) {
        // Realtime payload carries the recipient's absolute unread count. Set the
        // badge directly; do not turn a WS event into an HTTP unread-count request.
        if (Number.isFinite(Number(eventData?.unread_count))) {
            setBadge(Number(eventData.unread_count));
        }

        if (isDropdownOpen()) {
            refreshList();
        }

        // Reload DataTable if notification has a tableId
        if (eventData?.table_id) {
            reloadDataTable(eventData.table_id);
        }

        if (!fromTabSync && eventData) {
            tabSync?.postMessage({ type: 'ping', at: Date.now(), eventData });
        }
    }

    /** @type {BroadcastChannel | null} */
    let tabSync = null;

    if (typeof BroadcastChannel !== 'undefined' && userId) {
        try {
            tabSync = new BroadcastChannel(`activity-notifications.${userId}`);
            tabSync.addEventListener('message', (event) => {
                if (event.data?.type === 'ping') {
                    onRealtimePing(event.data.eventData || null, true);
                } else if (event.data?.type === 'read-all') {
                    applyReadAllState(false);
                }
            });
        } catch {
            tabSync = null;
        }
    }

    function onTabVisible() {
        // Healthy WS keeps count current. HTTP reconciliation belongs only to
        // degraded/polling mode; focus itself must not become hidden polling.
        if (window.notificationRealtime?.enabled === false
            || window.notificationRealtime?.degraded === true) {
            refreshBadge();
        }

        if (isDropdownOpen()) {
            refreshList();
        }
    }

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState !== 'visible') {
            return;
        }

        onTabVisible();

        const connection = echoInstance?.connector?.pusher?.connection;

        if (connection && connection.state !== 'connected' && connection.state !== 'connecting') {
            connection.connect();
        }
    });

    window.addEventListener('focus', onTabVisible);

    /** @type {import('laravel-echo').default | null} */
    let echoInstance = null;

    // Guards against starting the polling interval twice (e.g. realtime setup
    // succeeds, then the websocket later drops and we fall back to polling).
    let pollingStarted = false;
    let badgePollTimer = null;

    // Realtime health tracking. WebSocket is the primary path; polling only kicks
    // in after the socket stays non-connected past a grace window, and stops again
    // on reconnect — so the two never overlap while the socket is healthy.
    const REALTIME_GRACE_MS = 30_000;
    let graceTimer = null;
    let hasConnectedOnce = false;

    function setRealtimeHealth(connected, degraded = false) {
        window.notificationRealtime = { enabled: true, connected, degraded };

        // `connected: false` alone means connecting / inside grace — NOT degraded.
        // Only emit the fallback event once the 30s grace actually expires.
        if (connected) {
            window.dispatchEvent(new CustomEvent('notification-realtime:connected'));
        } else if (degraded) {
            window.dispatchEvent(new CustomEvent('notification-realtime:degraded'));
        }
    }

    function stopPolling() {
        if (badgePollTimer) {
            clearInterval(badgePollTimer);
            badgePollTimer = null;
        }

        pollingStarted = false;
    }

    function startPolling() {
        if (pollingStarted) {
            return;
        }

        pollingStarted = true;

        const intervalMs = Math.max(1000, Number(pollingIntervalSeconds) * 1000 || 60_000);

        refreshBadge();
        badgePollTimer = setInterval(refreshBadge, intervalMs);
    }

    // WS recovered (or connected for the first time): cancel any pending grace,
    // kill fallback polling, mark healthy, and do one catch-up fetch.
    function onRealtimeConnected() {
        if (graceTimer) {
            clearTimeout(graceTimer);
            graceTimer = null;
        }

        stopPolling();
        setRealtimeHealth(true);

        // First connect uses the server-rendered initial count. Later reconnects
        // reconcile once over HTTP in case events were missed while disconnected.
        if (hasConnectedOnce) {
            refreshBadge();
        }

        hasConnectedOnce = true;
    }

    // WS is not connected. Start a grace timer once; if still down when it fires,
    // enter degraded mode (turn on polling). A later reconnect cancels it.
    function onRealtimeNotConnected() {
        setRealtimeHealth(false);

        if (graceTimer || badgePollTimer) {
            return;
        }

        graceTimer = setTimeout(() => {
            graceTimer = null;
            setRealtimeHealth(false, true);
            startPolling();
        }, REALTIME_GRACE_MS);
    }

    function startRealtime() {
        const pusherKey = broadcast?.key ?? import.meta.env.VITE_PUSHER_APP_KEY;

        if (!broadcast?.authEndpoint || !userId || !pusherKey) {
            window.notificationRealtime = { enabled: false, connected: false };
            startPolling();

            return;
        }

        try {
            echoInstance = createNotificationEcho(broadcast, csrf);

            // Single source of truth for realtime health. Polling is gated behind a
            // grace window so a healthy socket never runs polling in parallel; a
            // reconnect tears the fallback back down.
            const connection = echoInstance?.connector?.pusher?.connection;

            if (connection) {
                connection.bind('state_change', ({ current }) => {
                    if (current === 'connected') {
                        onRealtimeConnected();
                    } else {
                        onRealtimeNotConnected();
                    }
                });

                // Echo may already be `connecting` before this listener binds.
                // Seed health from the current state so fallback grace cannot be
                // skipped when no subsequent failure transition arrives.
                if (connection.state === 'connected') {
                    onRealtimeConnected();
                } else {
                    onRealtimeNotConnected();
                }
            }

            echoInstance
                .private(`App.Models.User.${userId}`)
                .listen('.activity-notification.received', (e) => onRealtimePing(e, false));
        } catch {
            window.notificationRealtime = { enabled: false, connected: false };
            startPolling();
        }
    }

    window.addEventListener('beforeunload', () => {
        tabSync?.close();
        echoInstance?.disconnect();
    });

    bell.addEventListener('show.bs.dropdown', refreshList);

    function applyReadAllState(syncTabs = true) {
        setBadge(0);
        list?.querySelectorAll('[data-notification-id]').forEach((item) => {
            item.classList.remove('fw-semibold');
        });
        document.dispatchEvent(new CustomEvent('notification-read-all:applied'));

        if (syncTabs) {
            tabSync?.postMessage({ type: 'read-all', at: Date.now() });
        }
    }

    // Generic AJAX infrastructure emits success with the submitted form. Apply
    // mark-all state immediately; no redirect, hard reload, timer, or count fetch.
    document.addEventListener('ajax-form:success', (event) => {
        if (event.detail?.form?.matches('[data-notification-read-all-form]')) {
            applyReadAllState(true);
        }
    });

    if (realtime) {
        startRealtime();
    } else {
        window.notificationRealtime = { enabled: false, connected: false };
        startPolling();
    }
}

const bellRoot = document.getElementById('notification-bell');

if (bellRoot?.dataset.notificationConfig) {
    try {
        initNotificationBell(JSON.parse(bellRoot.dataset.notificationConfig));
    } catch (error) {
        // Misconfigured JSON or init failure — bell stays inert. Surface it once
        // so silent init failures (e.g. a TDZ regression) don't go unnoticed.
        console.warn('Notification bell failed to initialize:', error);
    }
}

// Handle flash-based DataTable reload (active tab after create/delete redirect)
handleFlashReload();
