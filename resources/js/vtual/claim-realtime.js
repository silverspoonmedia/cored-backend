/**
 * vTual claim page — WebSocket progress (no polling).
 *
 * When broadcast is unconfigured: AJAX queue ack only; pool results need manual reload.
 * Reuses window.__monolithEcho when the navbar bell (or another module) already opened Pusher.
 */
import { createNotificationEcho } from '../notifications/echo';

const REALTIME_GRACE_MS = 30_000;

/**
 * @param {object} options
 * @param {HTMLElement} options.root
 * @param {object} options.config
 * @param {string} options.csrf
 */
export function initClaimRealtime(options) {
    const { root, config, csrf } = options;
    const realtime = config.realtime || {};
    const broadcast = realtime.broadcast;
    const messages = config.messages || {};
    let claimId = realtime.claimId || null;
    let echo = null;
    let ownsEcho = false;
    let channel = null;
    let connected = false;
    let graceTimer = null;

    const state = {
        enabled: Boolean(broadcast?.authEndpoint && realtime.userId && (broadcast.key || import.meta.env.VITE_PUSHER_APP_KEY)),
        connected: false,
    };

    window.vtualClaimRealtime = state;

    const degradedEl = root.querySelector('[data-claim-degraded]');
    const statusEl = root.querySelector('[data-claim-realtime-status]');
    const realtimeBar = root.querySelector('[data-claim-realtime-bar]');
    const realtimeIcon = root.querySelector('[data-claim-realtime-icon]');

    if (!state.enabled) {
        showDegradedBanner();
        syncQueueNotice();
    } else {
        syncQueueNotice();
        connectEcho();
    }

    document.addEventListener('ajax-form:success', onAjaxSuccess);

    function clearGraceTimer() {
        if (graceTimer) {
            clearTimeout(graceTimer);
            graceTimer = null;
        }
    }

    function hideDegradedBanner() {
        if (degradedEl) {
            degradedEl.classList.add('d-none');
        }
    }

    function showDegradedBanner() {
        if (isSocketHealthy()) {
            return;
        }
        if (degradedEl) {
            degradedEl.classList.remove('d-none');
        }
    }

    function isSocketHealthy() {
        if (window.notificationRealtime?.connected === true) {
            return true;
        }

        const connection = echo?.connector?.pusher?.connection;

        return connection?.state === 'connected';
    }

    function onRealtimeConnected() {
        clearGraceTimer();
        connected = true;
        state.connected = true;
        hideDegradedBanner();
        updateConnectionBadge();
    }

    function onRealtimeNotConnected() {
        connected = false;
        state.connected = false;
        updateConnectionBadge();

        if (graceTimer) {
            return;
        }

        graceTimer = setTimeout(() => {
            graceTimer = null;
            if (!isSocketHealthy()) {
                showDegradedBanner();
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

    function connectEcho() {
        try {
            if (window.__monolithEcho) {
                echo = window.__monolithEcho;
            } else {
                echo = createNotificationEcho(broadcast, csrf);
                window.__monolithEcho = echo;
                ownsEcho = true;
            }
        } catch (err) {
            console.error('vtual claim realtime: echo init failed', err);
            onRealtimeNotConnected();

            return;
        }

        bindConnectionHandlers();

        if (claimId) {
            subscribeClaim(claimId);
        }
    }

    function subscribeClaim(id) {
        if (!echo || !id) {
            return;
        }
        if (channel) {
            echo.leave(`private-vtual.claim.${claimId}`);
        }
        claimId = id;
        channel = echo.private(`vtual.claim.${id}`);
        channel.listen('.claim.progress', (payload) => {
            handleProgress(payload || {});
        });
    }

    function updateConnectionBadge() {
        if (!statusEl) {
            return;
        }
        statusEl.textContent = connected
            ? (messages.connected || 'Live')
            : (messages.connecting || 'Connecting…');
        if (realtimeBar) {
            realtimeBar.classList.toggle('text-success', connected);
            realtimeBar.classList.toggle('text-secondary', !connected);
        }
        if (realtimeIcon) {
            realtimeIcon.classList.toggle('text-success', connected);
        }
    }

    function syncQueueNotice() {
        const noticeEl = root.querySelector('[data-claim-panel] [data-claim-queue-notice]');
        if (!noticeEl) {
            return;
        }
        if (state.enabled && messages.realtimeNotice) {
            noticeEl.textContent = messages.realtimeNotice;
        } else if (messages.degradedNotice) {
            noticeEl.textContent = messages.degradedNotice;
        }
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function updateProofChannel(panel, channel) {
        const rows = panel.querySelectorAll('[data-claim-proof-row]');
        const cell = panel.querySelector('[data-claim-proof-channel]');
        if (!cell) {
            return;
        }
        const hasChannel = channel && typeof channel === 'object';
        rows.forEach((row) => {
            row.classList.toggle('d-none', !hasChannel);
        });
        cell.classList.toggle('d-none', !hasChannel);
        if (!hasChannel) {
            cell.textContent = '';

            return;
        }
        const platformName = channel.platform?.name || channel.platform?.key || '—';
        const label = channel.handle || channel.display_name || channel.external_id || '';
        let html = `<span class="badge text-bg-light border">${escapeHtml(platformName)}</span>`;
        html += `<span class="ms-1">${escapeHtml(label)}</span>`;
        if (channel.url) {
            html += `<a href="${escapeHtml(channel.url)}" class="btn btn-outline-secondary btn-sm ms-2" target="_blank" rel="noopener noreferrer" data-claim-proof-open>${escapeHtml(messages.openLink || 'Open')}</a>`;
        }
        cell.innerHTML = html;
    }

    function populatePanelFromClaim(panel, claim) {
        const tokenEl = panel.querySelector('[data-claim-token]');
        if (tokenEl && claim.token) {
            tokenEl.textContent = claim.token;
        }

        const attemptsEl = panel.querySelector('[data-claim-attempts]');
        if (attemptsEl) {
            attemptsEl.textContent = String(claim.attempts_remaining ?? '—');
        }

        updateProofChannel(panel, claim.channel);
        updateQueueBadge(panel, claim.verify_queue_status);

        const verifyWrap = panel.querySelector('[data-claim-verify-wrap]');
        if (verifyWrap) {
            verifyWrap.classList.toggle('d-none', !claim.can_verify);
        }

        panel.querySelectorAll('[data-claim-verify-form], [data-claim-cancel-form]').forEach((form) => {
            if (!claim.id) {
                return;
            }
            const hidden = form.querySelector('input[name="claim_id"]');
            if (hidden) {
                hidden.value = claim.id;
            }
        });

        syncQueueNotice();
    }

    function mountActiveClaimPanel(claim) {
        let panel = root.querySelector('[data-claim-panel]');
        if (!panel) {
            const tpl = root.querySelector('[data-claim-panel-template]');
            const fragment = tpl?.content?.firstElementChild;
            if (!fragment) {
                return;
            }
            panel = fragment.cloneNode(true);
            panel.id = 'vtual-active-claim-panel';
            const channelsSection = root.querySelector('[data-claim-channels-section]');
            if (channelsSection?.parentNode) {
                channelsSection.parentNode.insertBefore(panel, channelsSection);
            } else {
                root.querySelector('.card-body')?.appendChild(panel);
            }
        }
        panel.classList.remove('d-none');
        populatePanelFromClaim(panel, claim);
        hideChannelsSection();
        if (claim.id) {
            subscribeClaim(claim.id);
        }
    }

    function hideFailedBanner() {
        const banner = root.querySelector('[data-claim-panel] [data-claim-failed-banner]')
            || root.querySelector('[data-claim-failed-banner]');
        if (banner) {
            banner.classList.add('d-none');
            banner.textContent = '';
        }
    }

    function showFailedBanner(message) {
        const banner = root.querySelector('[data-claim-panel] [data-claim-failed-banner]')
            || root.querySelector('[data-claim-failed-banner]');
        if (!banner || !message) {
            return;
        }
        banner.textContent = message;
        banner.classList.remove('d-none');
    }

    function applyVerifyFailedState(panel, claim) {
        updateQueueBadge(panel, null);
        showFailedBanner(messages.failed || 'Token not found in channel bio.');
        const verifyWrap = panel.querySelector('[data-claim-verify-wrap]');
        if (verifyWrap) {
            const showVerify = claim.can_verify === true
                || (claim.status === 'pending' && (claim.attempts_remaining ?? 0) > 0);
            verifyWrap.classList.toggle('d-none', !showVerify);
        }
    }

    function hideChannelsSection() {
        const section = root.querySelector('[data-claim-channels-section]');
        if (section) {
            section.classList.add('d-none');
        }
    }

    function toast(message, type = 'success') {
        if (window.MonolithSwal?.toastMessage) {
            window.MonolithSwal.toastMessage(message, type === 'danger' ? 'error' : type);
        }
    }

    function handleProgress(payload) {
        const phase = payload.phase || '';
        const claim = payload.claim || {};

        if (claim.id && claim.id !== claimId) {
            subscribeClaim(claim.id);
        }

        applyClaimSnapshot(claim, phase);
    }

    function applyClaimSnapshot(claim, phase = '') {
        if (!claim || typeof claim !== 'object') {
            return;
        }

        const panel = root.querySelector('[data-claim-panel]');

        if (phase === 'started') {
            hideFailedBanner();
            mountActiveClaimPanel(claim);
            toast(messages.started || 'Claim started.', 'success');

            return;
        }

        if (phase === 'cancelled' || phase === 'max_attempts_blocked') {
            hideFailedBanner();
            if (panel) {
                panel.remove();
            }
            if (phase === 'max_attempts_blocked') {
                showBlockedBanner(claim.blocked_until_formatted || claim.blocked_until);
                toast(messages.maxAttemptsBlocked || 'Max attempts reached.', 'warning');
            }
            showChannelsSection();

            return;
        }

        if (phase === 'verified') {
            hideFailedBanner();
            toast(messages.verified || 'Claim verified.', 'success');
            const target = claim.profile_url || config.profileUrl;
            if (target) {
                window.setTimeout(() => window.location.assign(target), 800);
            }

            return;
        }

        if (!panel) {
            return;
        }

        if (phase === 'verify_failed') {
            populatePanelFromClaim(panel, claim);
            applyVerifyFailedState(panel, claim);
            toast(messages.failed || 'Token not found in channel bio.', 'warning');

            return;
        }

        if (phase === 'verify_queued' || phase === 'verify_processing') {
            hideFailedBanner();
        }

        populatePanelFromClaim(panel, claim);

        if (phase === 'verify_queued') {
            toast(messages.queued || 'Verification queued.', 'info');
        } else if (phase === 'verify_processing') {
            toast(messages.processing || 'Processing verification…', 'info');
        }
    }

    function updateQueueBadge(panel, status) {
        const rows = panel.querySelectorAll('[data-claim-queue-row]');
        const badge = panel.querySelector('[data-claim-queue-badge]');
        if (!badge || rows.length === 0) {
            return;
        }
        rows.forEach((row) => {
            row.classList.toggle('d-none', !status);
        });
        if (!status) {
            badge.textContent = '';

            return;
        }
        const key = `queue_${status}`;
        badge.textContent = messages[key] || status;
    }

    function showBlockedBanner(until) {
        const existing = root.querySelector('[data-claim-blocked-banner]');
        if (existing || !until) {
            return;
        }
        const alert = document.createElement('div');
        alert.className = 'alert alert-warning small mb-3';
        alert.setAttribute('data-claim-blocked-banner', '');
        alert.setAttribute('role', 'alert');
        alert.textContent = (messages.blockedUntil || 'Blocked until :until.').replace(':until', until);
        const cardBody = root.querySelector('.card-body') || root;
        cardBody.insertBefore(alert, cardBody.firstChild);
    }

    function showChannelsSection() {
        const section = root.querySelector('[data-claim-channels-section]');
        if (section) {
            section.classList.remove('d-none');
        }
    }

    function onAjaxSuccess(event) {
        const form = event.detail?.form;
        const data = event.detail?.data;
        if (!form || !root.contains(form)) {
            return;
        }
        const claim = data?.data;
        if (!claim || typeof claim !== 'object') {
            return;
        }

        if (claim.id) {
            subscribeClaim(claim.id);
        }

        let phase = '';
        if (form.matches('[data-claim-start-form]')) {
            phase = 'started';
        } else if (form.matches('[data-claim-verify-form]')) {
            phase = 'verify_queued';
        } else if (form.matches('[data-claim-cancel-form]')) {
            phase = 'cancelled';
        }

        applyClaimSnapshot(claim, phase);
    }

    updateConnectionBadge();

    return {
        disconnect() {
            clearGraceTimer();
            document.removeEventListener('ajax-form:success', onAjaxSuccess);
            try {
                if (echo && claimId) {
                    echo.leave(`private-vtual.claim.${claimId}`);
                }
            } catch {
                // ignore leave errors
            }
            if (ownsEcho && !document.getElementById('notification-bell')) {
                echo?.disconnect();
                window.__monolithEcho = null;
            }
        },
    };
}

document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('vtual-claim-root');
    if (!root) {
        return;
    }
    const configEl = root.querySelector('[data-claim-config]');
    if (!configEl) {
        return;
    }
    let config = {};
    try {
        config = JSON.parse(configEl.textContent || '{}');
    } catch (e) {
        console.error('vtual claim: invalid config JSON', e);
    }
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    initClaimRealtime({ root, config, csrf });
});
