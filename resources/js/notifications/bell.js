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

    const badge = bell.querySelector('[data-notification-badge]');
    const list = bell.querySelector('[data-notification-list]');
    const header = bell.querySelector('[data-notification-header]');
    const headerDefault = header ? header.textContent : '';
    const { urls, labels, realtime, userId, broadcast } = config;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

    function setBadge(count) {
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
        refreshBadge();

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
                }
            });
        } catch {
            tabSync = null;
        }
    }

    function onTabVisible() {
        refreshBadge();

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

    function startPolling() {
        refreshBadge();
        setInterval(refreshBadge, 60_000);
    }

    function startRealtime() {
        const pusherKey = broadcast?.key ?? import.meta.env.VITE_PUSHER_APP_KEY;

        if (!broadcast?.authEndpoint || !userId || !pusherKey) {
            startPolling();

            return;
        }

        try {
            echoInstance = createNotificationEcho(broadcast, csrf);

            echoInstance
                .private(`App.Models.User.${userId}`)
                .listen('.activity-notification.received', (e) => onRealtimePing(e, false));

            refreshBadge();
        } catch {
            startPolling();
        }
    }

    window.addEventListener('beforeunload', () => {
        tabSync?.close();
        echoInstance?.disconnect();
    });

    bell.addEventListener('show.bs.dropdown', refreshList);

    const readAll = bell.querySelector('[data-notification-read-all]');

    if (readAll) {
        readAll.closest('form')?.addEventListener('submit', () => {
            setTimeout(() => {
                refreshBadge();
                refreshList();
            }, 400);
        });
    }

    if (realtime) {
        startRealtime();
    } else {
        startPolling();
    }
}

const bellRoot = document.getElementById('notification-bell');

if (bellRoot?.dataset.notificationConfig) {
    try {
        initNotificationBell(JSON.parse(bellRoot.dataset.notificationConfig));
    } catch {
        // Misconfigured JSON — bell stays inert.
    }
}
