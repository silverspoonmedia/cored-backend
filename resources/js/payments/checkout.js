/**
 * Hosted checkout for pending invoices (channel direct + Midtrans Snap + iPaymu redirect).
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

    initSnapCheckout(root, csrfToken, messages);
}

function initSnapCheckout(root, csrfToken, messages) {
    const snapTokenUrl = root.dataset.snapTokenUrl;
    const returnUrl = root.dataset.returnUrl || '';
    const cancelUrl = root.dataset.cancelUrl || '';
    const payButton = root.querySelector('[data-payment-checkout-pay]');

    payButton?.addEventListener('click', async function () {
        if (!snapTokenUrl || payButton.disabled) {
            return;
        }

        payButton.disabled = true;
        const originalLabel = payButton.textContent;
        payButton.textContent = messages.loading ?? 'Opening payment…';

        try {
            const body = {};

            if (returnUrl) {
                body.return_url = returnUrl;
            }

            if (cancelUrl) {
                body.cancel_url = cancelUrl;
            }

            const response = await fetch(snapTokenUrl, {
                method: 'POST',
                headers: buildHeaders(csrfToken),
                credentials: 'same-origin',
                body: JSON.stringify(body),
            });

            const payload = await response.json();

            if (!response.ok || payload.status !== 'success') {
                throw new Error(payload.message || messages.failed || 'Checkout failed.');
            }

            const data = payload.data ?? {};
            const provider = payload.provider ?? '';

            if (provider === 'ipaymu' && data.redirect_url) {
                window.location.assign(String(data.redirect_url));
                return;
            }

            if (data.snap_token && data.client_key) {
                await loadMidtransSnap(String(data.client_key));
                window.snap?.pay(String(data.snap_token), {
                    onSuccess: () => window.location.reload(),
                    onPending: () => window.location.reload(),
                    onError: () => {
                        window.alert(messages.failed ?? 'Checkout failed.');
                    },
                    onClose: () => {
                        window.location.reload();
                    },
                });
                return;
            }

            if (data.redirect_url) {
                window.location.assign(String(data.redirect_url));
                return;
            }

            throw new Error('missing checkout payload');
        } catch (error) {
            const message = error instanceof Error ? error.message : (messages.failed ?? 'Checkout failed.');
            window.alert(message);
        } finally {
            payButton.disabled = false;
            payButton.textContent = originalLabel;
        }
    });
}

function initChannelCheckout(root, csrfToken, messages) {
    const channelCheckoutUrl = root.dataset.channelCheckoutUrl;
    const syncStatusUrl = root.dataset.syncStatusUrl;
    const channels = JSON.parse(root.dataset.channels || '[]');
    const picker = root.querySelector('[data-payment-channel-picker]');
    const checkoutButton = root.querySelector('[data-payment-channel-checkout]');
    const syncButton = root.querySelector('[data-payment-sync-status]');
    const instructionsEl = root.querySelector('[data-payment-instructions]');

    let selectedChannel = channels[0]?.code ?? '';
    let pollTimer = null;

    function setSelected(code) {
        selectedChannel = code;
        picker?.querySelectorAll('[data-payment-channel-option]').forEach((button) => {
            const isActive = button.getAttribute('data-payment-channel-option') === code;
            button.classList.toggle('btn-primary', isActive);
            button.classList.toggle('btn-outline-primary', !isActive);
        });
        if (checkoutButton) {
            checkoutButton.disabled = selectedChannel === '';
        }
    }

    if (selectedChannel !== '') {
        setSelected(selectedChannel);
    }

    picker?.querySelectorAll('[data-payment-channel-option]').forEach((button) => {
        button.addEventListener('click', () => {
            setSelected(button.getAttribute('data-payment-channel-option') ?? '');
        });
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
                if (payload.data?.snap_fallback_available) {
                    revealSnapFallback(root, messages);
                }

                throw new Error(payload.message || messages.channelFailed || 'Channel checkout failed.');
            }

            renderInstructions(instructionsEl, payload.data ?? {}, messages);
            instructionsEl?.classList.remove('d-none');
            syncButton?.classList.remove('d-none');
            startPolling(syncStatusUrl, csrfToken, messages);
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

function revealSnapFallback(root, messages) {
    const snapSection = root.querySelector('[data-payment-snap-fallback]');

    if (!snapSection) {
        return;
    }

    snapSection.classList.remove('d-none');

    const hint = snapSection.querySelector('.text-muted');

    if (hint && messages.channelFailed) {
        hint.textContent = messages.channelFailed;
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

function loadMidtransSnap(clientKey) {
    if (window.snap) {
        return Promise.resolve();
    }

    const isProduction = !clientKey.startsWith('SB-');

    return new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = isProduction
            ? 'https://app.midtrans.com/snap/snap.js'
            : 'https://app.sandbox.midtrans.com/snap/snap.js';
        script.setAttribute('data-client-key', clientKey);
        script.onload = () => resolve();
        script.onerror = () => reject(new Error('snap script failed'));
        document.body.appendChild(script);
    });
}

document.querySelectorAll('[data-payment-checkout]').forEach(initPaymentCheckout);
