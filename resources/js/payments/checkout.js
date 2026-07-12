/**
 * Channel checkout for pending invoices (QRIS / VA / e-wallet).
 */
import { copyToClipboard, resolveQrImageSrc } from './instructionMedia.js';

export function initPaymentCheckout(root) {
    if (!root || root.dataset.paymentCheckoutBound === '1') {
        return;
    }

    root.dataset.paymentCheckoutBound = '1';

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    const messages = JSON.parse(root.dataset.messages || '{}');
    const checkoutMode = root.dataset.checkoutMode || 'snap_fallback';

    if (checkoutMode === 'channel') {
        initChannelCheckout(root, csrfToken, messages);
    }
}

function initChannelCheckout(root, csrfToken, messages) {
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
        renderInstructions(instructionsEl, data, messages);
        instructionsEl?.classList.remove('d-none');
        syncButton?.classList.remove('d-none');
        checkoutButton?.classList.add('d-none');
        hasActiveInstructions = true;
        collapsePicker();
        startPolling(syncStatusUrl, csrfToken, messages);
    }

    function setSelected(code, { collapse = false } = {}) {
        if (code !== selectedChannel) {
            stopPolling();
            clearInstructions();
        }

        selectedChannel = code;
        picker?.querySelectorAll('[data-payment-channel-option]').forEach((button) => {
            button.classList.toggle('is-selected', button.getAttribute('data-payment-channel-option') === code);
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

    if (selectedChannel !== '') {
        setSelected(selectedChannel);
    }

    if (initialCheckout?.instructions) {
        showInstructions(initialCheckout);
    }

    picker?.querySelectorAll('[data-payment-channel-option]').forEach((button) => {
        button.addEventListener('click', () => {
            setSelected(button.getAttribute('data-payment-channel-option') ?? '', { collapse: true });
        });
    });

    changeChannelButton?.addEventListener('click', () => {
        expandPicker();

        if (hasActiveInstructions) {
            stopPolling();
            clearInstructions();
        }
    });

    checkoutButton?.addEventListener('click', async () => {
        if (!channelCheckoutUrl || !selectedChannel || checkoutButton.disabled) {
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

            window.location.reload();
        } catch (error) {
            const message = error instanceof Error ? error.message : (messages.channelFailed ?? 'Channel checkout failed.');
            window.alert(message);
        } finally {
            checkoutButton.disabled = false;
            checkoutButton.textContent = originalLabel;
        }
    });

    syncButton?.addEventListener('click', () => {
        void syncStatus(syncStatusUrl, csrfToken, messages, false);
    });
}

function parseInitialCheckout(raw) {
    if (!raw || raw === 'null') {
        return null;
    }

    try {
        const parsed = JSON.parse(raw);

        return parsed && typeof parsed === 'object' ? parsed : null;
    } catch {
        return null;
    }
}

function renderInstructions(container, data, messages) {
    if (!container) {
        return;
    }

    const instructions = data.instructions ?? {};
    const expiresAt = data.expires_at ? `<p class="text-muted small">${escapeHtml((messages.expiresAt ?? 'Pay before {time}').replace('{time}', formatDate(data.expires_at)))}</p>` : '';
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

            window.alert(copied ? (messages.copied ?? 'Copied') : (messages.copyFailed ?? 'Could not copy'));
        });
    });
}

function startPolling(syncStatusUrl, csrfToken, messages) {
    if (!syncStatusUrl) {
        return;
    }

    if (window.__paymentCheckoutPollTimer) {
        clearInterval(window.__paymentCheckoutPollTimer);
    }

    window.__paymentCheckoutPollTimer = setInterval(() => {
        void syncStatus(syncStatusUrl, csrfToken, messages, true);
    }, 15000);
}

async function syncStatus(syncStatusUrl, csrfToken, messages, silent) {
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

        const invoiceStatus = payload.data?.invoice?.status ?? '';

        if (invoiceStatus === 'paid') {
            window.location.reload();
            return;
        }

        if (!silent) {
            window.alert(messages.syncPending ?? 'Payment is still pending.');
        }
    } catch (error) {
        if (!silent) {
            const message = error instanceof Error ? error.message : (messages.syncFailed ?? 'Sync failed.');
            window.alert(message);
        }
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
