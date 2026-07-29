/**
 * Channel checkout for pending invoices (QRIS / VA / e-wallet).
 *
 * Soft-updates hero / steps / panel / sidebar on status change.
 * Prefer Echo private-payments.invoice.{id}; keep 15s sync-status as fallback.
 */
import { createNotificationEcho } from '../notifications/echo.js';
import { copyToClipboard, resolveQrImageSrc } from './instructionMedia.js';

const TERMINAL_SUCCESS = new Set(['paid']);
const TERMINAL_FAILURE = new Set(['expired', 'cancelled', 'failed']);
const VARIANT_CLASS = {
    warning: 'ois-status-warning',
    success: 'ois-status-success',
    danger: 'ois-status-danger',
    secondary: 'ois-status-secondary',
};

export function initPaymentCheckout(root) {
    if (!root || root.dataset.paymentCheckoutBound === '1') {
        return;
    }

    root.dataset.paymentCheckoutBound = '1';

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    const messages = JSON.parse(root.dataset.messages || '{}');
    const checkoutMode = root.dataset.checkoutMode || 'snap_fallback';
    const invoiceId = root.dataset.invoiceId || '';
    const broadcast = parseJson(root.dataset.broadcast);

    const ui = createPaymentUiController(root, messages);

    if (TERMINAL_SUCCESS.has(root.dataset.invoiceStatus) || TERMINAL_FAILURE.has(root.dataset.invoiceStatus)) {
        return;
    }

    if (checkoutMode === 'channel') {
        initChannelCheckout(root, csrfToken, messages, ui, invoiceId, broadcast);
    } else if (invoiceId) {
        // Non-channel pages still listen for status push when payable.
        subscribeInvoiceChannel(invoiceId, broadcast, csrfToken, (payload) => {
            ui.applyStatus(payload?.status ?? '', payload);
        });
    }
}

function initChannelCheckout(root, csrfToken, messages, ui, invoiceId, broadcast) {
    const channelCheckoutUrl = root.dataset.channelCheckoutUrl;
    const syncStatusUrl = root.dataset.syncStatusUrl;
    const channels = JSON.parse(root.dataset.channels || '[]');
    const initialCheckout = parseInitialCheckout(root.dataset.initialCheckout);
    const picker = root.querySelector('[data-payment-channel-picker]');
    const changeChannelButton = root.querySelector('[data-payment-change-channel]');
    const channelLabel = root.querySelector('[data-payment-channel-label]');
    const checkoutButton = root.querySelector('[data-payment-channel-checkout]');
    const syncButton = root.querySelector('[data-payment-sync-status]');
    const instructionsEl = root.querySelector('[data-payment-instructions]');

    let selectedChannel = initialCheckout?.channel_code ?? channels[0]?.code ?? '';
    let pickerCollapsed = false;
    let hasActiveInstructions = false;
    let terminal = false;

    function stopPolling() {
        if (window.__paymentCheckoutPollTimer) {
            clearInterval(window.__paymentCheckoutPollTimer);
            window.__paymentCheckoutPollTimer = null;
        }

        syncButton?.classList.add('d-none');
    }

    function clearInstructions() {
        hasActiveInstructions = false;

        if (instructionsEl) {
            instructionsEl.innerHTML = '';
            instructionsEl.classList.add('d-none');
        }

        checkoutButton?.classList.remove('d-none');
    }

    function showInstructions(data) {
        if (terminal) {
            return;
        }

        renderInstructions(instructionsEl, data, messages, showCheckoutToast);
        instructionsEl?.classList.remove('d-none');
        syncButton?.classList.remove('d-none');
        checkoutButton?.classList.add('d-none');
        hasActiveInstructions = true;
        collapsePicker();
        startPolling(syncStatusUrl, csrfToken, messages, handleStatus);
        ui.applyPendingWithSession();
        updateAmountCallout(root, data);
    }

    function handleStatus(status, meta = {}) {
        if (terminal) {
            return;
        }

        if (TERMINAL_SUCCESS.has(status) || TERMINAL_FAILURE.has(status)) {
            terminal = true;
            stopPolling();
            ui.applyStatus(status, meta);

            return true;
        }

        return false;
    }

    function setSelected(code, { collapse = false } = {}) {
        if (terminal) {
            return;
        }

        if (code !== selectedChannel) {
            stopPolling();
            clearInstructions();
        }

        selectedChannel = code;
        picker?.querySelectorAll('[data-payment-channel-option]').forEach((button) => {
            const isSelected = button.getAttribute('data-payment-channel-option') === code;
            button.classList.toggle('is-selected', isSelected);
            button.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
        });

        if (checkoutButton) {
            checkoutButton.disabled = selectedChannel === '';
        }

        if (collapse) {
            collapsePicker();
        }
    }

    function collapsePicker() {
        if (!picker || pickerCollapsed) {
            return;
        }

        pickerCollapsed = true;
        picker.classList.add('is-collapsed');
        changeChannelButton?.classList.remove('d-none');
        channelLabel?.classList.add('d-none');
    }

    function expandPicker() {
        if (!picker) {
            return;
        }

        pickerCollapsed = false;
        picker.classList.remove('is-collapsed');
        changeChannelButton?.classList.add('d-none');
        channelLabel?.classList.remove('d-none');
    }

    function showCheckoutToast(message, type) {
        const container = ensureToastContainer(root);
        const toast = document.createElement('div');
        toast.className = 'ois-toast is-' + (type || 'info');
        toast.setAttribute('role', 'status');
        toast.textContent = message;
        container.appendChild(toast);

        window.setTimeout(() => {
            toast.classList.add('is-leaving');
            window.setTimeout(() => toast.remove(), 300);
        }, 4000);
    }

    if (selectedChannel !== '') {
        setSelected(selectedChannel);
    }

    if (initialCheckout?.instructions) {
        showInstructions(initialCheckout);
    }

    subscribeInvoiceChannel(invoiceId, broadcast, csrfToken, (payload) => {
        handleStatus(payload?.status ?? '', payload);
    });

    picker?.querySelectorAll('[data-payment-channel-option]').forEach((button) => {
        button.addEventListener('click', () => {
            setSelected(button.getAttribute('data-payment-channel-option') ?? '', { collapse: true });
        });
    });

    changeChannelButton?.addEventListener('click', () => {
        if (terminal) {
            return;
        }

        expandPicker();

        if (hasActiveInstructions) {
            stopPolling();
            clearInstructions();
        }
    });

    checkoutButton?.addEventListener('click', async () => {
        if (terminal || !channelCheckoutUrl || !selectedChannel || checkoutButton.disabled) {
            return;
        }

        checkoutButton.disabled = true;
        const originalLabel = checkoutButton.textContent;
        checkoutButton.textContent = messages.channelLoading ?? 'Creating payment…';

        try {
            const response = await fetch(channelCheckoutUrl, {
                method: 'POST',
                headers: buildHeaders(csrfToken),
                credentials: 'same-origin',
                body: JSON.stringify({ channel_code: selectedChannel }),
            });

            const payload = await response.json();

            if (!response.ok || payload.status !== 'success') {
                throw new Error(payload.message || messages.channelFailed || 'Channel checkout failed.');
            }

            const data = payload.data ?? {};
            showInstructions(data);
            showCheckoutToast(messages.channelReady ?? 'Payment instructions ready.', 'success');
        } catch (error) {
            const message = error instanceof Error ? error.message : (messages.channelFailed ?? 'Channel checkout failed.');
            showCheckoutToast(message, 'error');
        } finally {
            if (!terminal) {
                checkoutButton.disabled = false;
                checkoutButton.textContent = originalLabel;
            }
        }
    });

    syncButton?.addEventListener('click', () => {
        void syncStatus(syncStatusUrl, csrfToken, messages, false, handleStatus, showCheckoutToast);
    });
}

/**
 * Soft-patch page chrome (hero, steps, sidebar, panel body).
 */
function createPaymentUiController(root, messages) {
    return {
        applyPendingWithSession() {
            setPaymentBadge(messages.paymentPending ?? 'Awaiting payment', 'warning');
            setSteps(2, false);
            setSidebarStatus(messages.paymentPending ?? 'Awaiting payment');
            root.dataset.invoiceStatus = 'pending';
        },

        applyStatus(status, meta = {}) {
            root.dataset.invoiceStatus = status;

            if (TERMINAL_SUCCESS.has(status)) {
                const label = messages.statusPaid ?? 'Paid';
                setPaymentBadge(label, 'success');
                setSteps(3, true);
                setSidebarStatus(label);
                setPurchaseStatus(meta.invoiceable_status ?? 'paid', 'success');
                setPaidAt(meta.paid_at);
                replaceCheckoutBody(root, messages.paid ?? 'This invoice is already paid.', 'success');

                return;
            }

            if (TERMINAL_FAILURE.has(status)) {
                const label = statusLabel(status, messages);
                setPaymentBadge(label, 'danger');
                setSteps(1, false);
                setSidebarStatus(label);
                if (meta.invoiceable_status) {
                    setPurchaseStatus(meta.invoiceable_status, 'danger');
                }
                setExpiredAt(meta.expired_at);
                replaceCheckoutBody(root, messages.notPayable ?? 'This invoice cannot be paid.', 'muted');
            }
        },
    };
}

function statusLabel(status, messages) {
    if (status === 'expired') {
        return messages.statusExpired ?? 'Expired';
    }
    if (status === 'cancelled') {
        return messages.statusCancelled ?? 'Cancelled';
    }
    if (status === 'failed') {
        return messages.statusFailed ?? 'Failed';
    }

    return status;
}

function setPaymentBadge(label, variant) {
    const badge = document.querySelector('[data-payment-badge="payment"]');
    if (!badge) {
        return;
    }

    badge.textContent = label;
    badge.className = 'badge ' + (VARIANT_CLASS[variant] ?? VARIANT_CLASS.secondary);
}

function setPurchaseStatus(status, variant) {
    const badge = document.querySelector('[data-payment-badge="purchase"]');
    if (badge) {
        badge.textContent = status;
        badge.className = 'badge ' + (VARIANT_CLASS[variant] ?? VARIANT_CLASS.secondary);
    }

    const sidebar = document.querySelector('[data-purchase-status]');
    if (sidebar) {
        sidebar.textContent = status;
    }
}

function setSidebarStatus(label) {
    document.querySelectorAll('[data-payment-sidebar-status]').forEach((el) => {
        el.textContent = label;
    });

    const inline = document.querySelector('[data-payment-inline-status]');
    if (inline) {
        inline.textContent = label;
    }
}

function setPaidAt(value) {
    const el = document.querySelector('[data-payment-paid-at]');
    if (el && value) {
        el.textContent = formatDate(value);
    }
}

function setExpiredAt(value) {
    const el = document.querySelector('[data-payment-expired-at]');
    if (el && value) {
        el.textContent = formatDate(value);
    }
}

function setSteps(currentStep, completed) {
    const stepsRoot = document.querySelector('[data-payment-steps]');
    if (!stepsRoot) {
        return;
    }

    stepsRoot.dataset.currentStep = String(currentStep);
    stepsRoot.dataset.completed = completed ? '1' : '0';

    stepsRoot.querySelectorAll('[data-payment-step]').forEach((stepEl) => {
        const number = Number(stepEl.getAttribute('data-payment-step'));
        stepEl.classList.remove('is-done', 'is-active');

        if (number < currentStep) {
            stepEl.classList.add('is-done');
        } else if (number === currentStep) {
            stepEl.classList.add(completed ? 'is-done' : 'is-active');
        }
    });
}

function replaceCheckoutBody(root, message, tone) {
    const body = root.querySelector('[data-payment-checkout-body]');
    if (!body) {
        return;
    }

    const className = tone === 'success' ? 'text-success mb-0' : 'text-muted mb-0';
    body.innerHTML = `<p class="${className}" data-payment-terminal-message>${escapeHtml(message)}</p>`;
}

function updateAmountCallout(root, data) {
    const amountEl = root.querySelector('[data-payment-amount-value]');
    if (!amountEl || data == null) {
        return;
    }

    if (typeof data.amount === 'number' || typeof data.amount === 'string') {
        const currency = data.currency ? String(data.currency) + ' ' : '';
        amountEl.textContent = currency + data.amount;
    }
}

function ensureToastContainer(root) {
    let container = root.querySelector('[data-payment-toast-container]');
    if (!container) {
        container = document.createElement('div');
        container.className = 'ois-toast-container';
        container.setAttribute('data-payment-toast-container', '');
        container.setAttribute('role', 'status');
        container.setAttribute('aria-live', 'polite');
        container.setAttribute('aria-atomic', 'false');
        root.appendChild(container);
    }

    return container;
}

function parseInitialCheckout(raw) {
    return parseJson(raw);
}

function parseJson(raw) {
    if (!raw || raw === 'null' || raw === 'undefined') {
        return null;
    }

    try {
        const parsed = JSON.parse(raw);

        return parsed && typeof parsed === 'object' ? parsed : null;
    } catch {
        return null;
    }
}

function renderInstructions(container, data, messages, showCheckoutToast) {
    if (!container) {
        return;
    }

    const instructions = data.instructions ?? {};
    const expiresAt = data.expires_at
        ? `<p class="text-muted small">${escapeHtml((messages.expiresAt ?? 'Pay before {time}').replace('{time}', formatDate(data.expires_at)))}</p>`
        : '';
    let body = '';

    if (instructions.type === 'qris') {
        const qrSrc = resolveQrImageSrc(instructions.qr_url, instructions.qr_string);

        body = `
            ${qrSrc ? `<div class="text-center mb-3"><img src="${qrSrc.replaceAll('"', '&quot;')}" alt="QRIS" class="img-fluid" style="max-width:240px"></div>` : ''}
            ${instructions.qr_string ? `<div class="mb-2"><label class="form-label small text-muted">${escapeHtml(messages.qrString ?? 'QR payload')}</label><div class="input-group"><input class="form-control form-control-sm font-monospace" readonly value="${escapeHtml(instructions.qr_string)}"><button type="button" class="btn btn-outline-secondary btn-sm" data-copy-value="${escapeHtml(instructions.qr_string)}">${escapeHtml(messages.copy ?? 'Copy')}</button></div></div>` : ''}
        `;
    } else if (instructions.type === 'va') {
        body = `
            <dl class="row mb-0">
                <dt class="col-sm-4">${escapeHtml(messages.bank ?? 'Bank')}</dt>
                <dd class="col-sm-8">${escapeHtml(instructions.bank_name || '—')}</dd>
                <dt class="col-sm-4">${escapeHtml(messages.vaNumber ?? 'Virtual account')}</dt>
                <dd class="col-sm-8"><span class="font-monospace">${escapeHtml(instructions.va_number || '—')}</span>${instructions.va_number ? ` <button type="button" class="btn btn-link btn-sm p-0 ms-2" data-copy-value="${escapeHtml(instructions.va_number)}">${escapeHtml(messages.copy ?? 'Copy')}</button>` : ''}</dd>
                ${instructions.payment_name ? `<dt class="col-sm-4">${escapeHtml(messages.accountName ?? 'Account name')}</dt><dd class="col-sm-8">${escapeHtml(instructions.payment_name)}</dd>` : ''}
            </dl>
        `;
    } else if (instructions.type === 'ewallet') {
        const qrSrc = resolveQrImageSrc(instructions.qr_url, instructions.qr_string);

        body = `
            ${instructions.deeplink_url ? `<div class="mb-3"><a href="${escapeHtml(instructions.deeplink_url)}" class="btn btn-primary" target="_blank" rel="noopener noreferrer">${escapeHtml(messages.openEwallet ?? 'Open e-wallet app')}</a></div>` : ''}
            ${qrSrc ? `<div class="text-center mb-3"><img src="${qrSrc.replaceAll('"', '&quot;')}" alt="QR" class="img-fluid" style="max-width:240px"></div>` : ''}
        `;
    }

    container.classList.add('pc-checkout-instructions');
    container.innerHTML = `
        <h6 class="mb-3">${escapeHtml(messages.instructionsTitle ?? 'Payment instructions')}</h6>
        ${expiresAt}
        ${body}
        <p class="text-muted small mb-0 mt-3">${escapeHtml(messages.pendingHint ?? '')}</p>
    `;

    container.querySelectorAll('[data-copy-value]').forEach((button) => {
        button.addEventListener('click', async () => {
            const value = button.getAttribute('data-copy-value') ?? '';
            const copied = await copyToClipboard(value);

            showCheckoutToast(
                copied ? (messages.copied ?? 'Copied') : (messages.copyFailed ?? 'Could not copy'),
                copied ? 'success' : 'error',
            );
        });
    });
}

function startPolling(syncStatusUrl, csrfToken, messages, onStatus) {
    if (!syncStatusUrl) {
        return;
    }

    if (window.__paymentCheckoutPollTimer) {
        clearInterval(window.__paymentCheckoutPollTimer);
    }

    window.__paymentCheckoutPollTimer = setInterval(() => {
        void syncStatus(syncStatusUrl, csrfToken, messages, true, onStatus, null);
    }, 15000);
}

async function syncStatus(syncStatusUrl, csrfToken, messages, silent, onStatus, showToast) {
    if (!syncStatusUrl) {
        return;
    }

    try {
        const response = await fetch(syncStatusUrl, {
            method: 'POST',
            headers: buildHeaders(csrfToken),
            credentials: 'same-origin',
            body: JSON.stringify({}),
        });

        const payload = await response.json();

        if (!response.ok || payload.status !== 'success') {
            throw new Error(payload.message || messages.syncFailed || 'Sync failed.');
        }

        const invoice = payload.data?.invoice ?? {};
        const invoiceStatus = invoice.status ?? '';
        const handled = onStatus?.(invoiceStatus, {
            paid_at: invoice.paid_at ?? null,
            expired_at: invoice.expired_at ?? null,
            invoiceable_status: null,
        });

        if (handled) {
            return;
        }

        if (!silent && showToast) {
            showToast(messages.syncPending ?? 'Payment is still pending.', 'info');
        }
    } catch (error) {
        if (!silent && showToast) {
            const message = error instanceof Error ? error.message : (messages.syncFailed ?? 'Sync failed.');
            showToast(message, 'error');
        }
    }
}

/**
 * Subscribe to private-payments.invoice.{id} when broadcast config present.
 * Reuses window.__monolithEcho with the notification bell / FH.
 */
function subscribeInvoiceChannel(invoiceId, broadcast, csrfToken, onPayload) {
    if (!invoiceId || !broadcast || typeof onPayload !== 'function') {
        return;
    }

    try {
        let echo = window.__monolithEcho;
        if (!echo) {
            echo = createNotificationEcho(broadcast, csrfToken);
            window.__monolithEcho = echo;
        }

        if (window.__paymentCheckoutEchoChannel === invoiceId) {
            return;
        }

        // Leave previous invoice channel if any (SPA soft-nav future-proofing).
        if (window.__paymentCheckoutEchoLeave) {
            try {
                window.__paymentCheckoutEchoLeave();
            } catch {
                // ignore
            }
            window.__paymentCheckoutEchoLeave = null;
        }

        const channelName = 'payments.invoice.' + invoiceId;
        const channel = echo.private(channelName);
        channel.listen('.invoice.status.changed', (payload) => {
            onPayload(payload ?? {});
        });

        window.__paymentCheckoutEchoChannel = invoiceId;
        window.__paymentCheckoutEchoLeave = () => {
            echo.leave(channelName);
            if (window.__paymentCheckoutEchoChannel === invoiceId) {
                window.__paymentCheckoutEchoChannel = null;
            }
        };
    } catch {
        // Polling remains the safety net.
    }
}

function buildHeaders(csrfToken) {
    return {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken,
        'X-Requested-With': 'XMLHttpRequest',
    };
}

function formatDate(value) {
    try {
        return new Date(value).toLocaleString();
    } catch {
        return String(value);
    }
}

function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;');
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-payment-checkout]').forEach(initPaymentCheckout);
});
