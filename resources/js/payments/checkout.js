/**
 * Channel checkout for pending invoices (QRIS / VA / e-wallet).
 *
 * Soft-updates hero / steps / panel / sidebar on status change.
 * Prefer Echo private-payments.invoice.{id}; keep 15s sync-status as fallback.
 */
import { createNotificationEcho } from '../notifications/echo.js';
import { copyToClipboard, resolveQrImageSrc } from './instructionMedia.js';
import { quoteChannelForSubtotal, readHubQuantity, formatMoney } from '../services/purchase-pricing-preview.js';

const TERMINAL_SUCCESS = new Set(['paid']);
const TERMINAL_FAILURE = new Set(['expired', 'cancelled', 'failed']);
const PAYABLE_STATUSES = new Set(['pending', 'challenge']);
const CHANNEL_KIND_ICONS = {
    qris: 'bi-qr-code',
    va: 'bi-bank',
    ewallet: 'bi-wallet2',
};
const ROUTING_POLL_MS = 60_000;
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
    const channelsUrl = root.dataset.channelsUrl || '';
    const syncStatusUrl = root.dataset.syncStatusUrl;
    let channels = JSON.parse(root.dataset.channels || '[]');
    const initialCheckout = parseInitialCheckout(root.dataset.initialCheckout);
    const picker = root.querySelector('[data-payment-channel-picker]');
    const channelListEl = root.querySelector('[data-payment-channel-list]');
    const changeChannelButton = root.querySelector('[data-payment-change-channel]');
    const channelLabel = root.querySelector('[data-payment-channel-label]');
    const checkoutButton = root.querySelector('[data-payment-channel-checkout]');
    const syncButton = root.querySelector('[data-payment-sync-status]');
    const instructionsEl = root.querySelector('[data-payment-instructions]');
    const cheapAltHintEl = root.querySelector('[data-payment-cheap-alt-hint]');

    let selectedChannel = initialCheckout?.channel_code ?? '';
    let pickerCollapsed = false;
    let hasActiveInstructions = false;
    let terminal = false;

    function updateUnselectedFeeBreakdown() {
        const currency = root.dataset.invoiceCurrency ?? 'IDR';
        const locale = root.dataset.appLocale ?? 'en';
        const subtotal = Number.parseFloat(root.dataset.invoiceSubtotal ?? '0');
        const subtotalFormatted = root.dataset.invoiceSubtotalFormatted
            || formatMoney(subtotal, currency, locale);

        updateFeeBreakdown(root, {
            subtotal,
            subtotal_formatted: subtotalFormatted,
            payment_channel_fee: 0,
            payment_channel_fee_formatted: formatMoney(0, currency, locale),
            payment_channel_fee_basis: null,
            customer_gross_total: subtotal,
            customer_gross_total_formatted: subtotalFormatted,
            currency,
        }, { confirmed: false, messages });
    }

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
            stopRoutingPoll();
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

        updateCheapAltHint(code);
        updateFeeBreakdownFromChannel(root, channels, code, messages);

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

    function updateCheapAltHint(code) {
        if (!cheapAltHintEl) {
            return;
        }

        const button = picker?.querySelector(`[data-payment-channel-option="${code}"]`);
        const altLabel = button?.getAttribute('data-cheap-alt-label') ?? '';

        if (altLabel !== '') {
            const template = messages.cheapAltHint ?? 'Pay with :channel to save on fees.';
            cheapAltHintEl.textContent = template.replace(':channel', altLabel);
            cheapAltHintEl.classList.remove('d-none');
        } else {
            cheapAltHintEl.textContent = '';
            cheapAltHintEl.classList.add('d-none');
        }
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

    function bindChannelPickerClicks() {
        picker?.querySelectorAll('[data-payment-channel-option]').forEach((button) => {
            button.addEventListener('click', () => {
                setSelected(button.getAttribute('data-payment-channel-option') ?? '', { collapse: true });
            });
        });
    }

    function formatPickerChannelFee(channel) {
        if (typeof channel?.estimated_channel_fee_formatted === 'string' && channel.estimated_channel_fee_formatted !== '') {
            return channel.estimated_channel_fee_formatted;
        }

        const currency = root.dataset.invoiceCurrency ?? 'IDR';
        const locale = root.dataset.appLocale ?? 'en';

        return formatMoney(channel?.estimated_channel_fee ?? 0, currency, locale);
    }

    function renderChannelPickerList(nextChannels, selectedCode) {
        if (!channelListEl) {
            return;
        }

        channelListEl.innerHTML = nextChannels.map((channel) => {
            const kind = channel?.kind || 'qris';
            const icon = CHANNEL_KIND_ICONS[kind] || 'bi-credit-card';
            const code = channel?.code || '';
            const isSelected = code !== '' && code === selectedCode;
            const cheapAlt = channel?.cheap_alt_code || '';
            const cheapAltLabel = cheapAlt !== ''
                ? (nextChannels.find((item) => item?.code === cheapAlt)?.display_name || cheapAlt)
                : '';
            const feeFormatted = formatPickerChannelFee(channel);
            const feeSuffix = channel?.fee_type === 'percent' && channel?.fee_label
                ? ` (${channel.fee_label})`
                : '';
            const feeHtml = feeFormatted
                ? `<span class="pc-checkout-channel__fee text-muted small">+ ${escapeHtml(feeFormatted)}${escapeHtml(feeSuffix)}</span>`
                : '';
            const cheapAltAttrs = cheapAlt !== ''
                ? ` data-cheap-alt-code="${escapeHtml(cheapAlt)}" data-cheap-alt-label="${escapeHtml(cheapAltLabel)}"`
                : '';

            return `<button type="button" class="pc-checkout-channel pc-kind-${escapeHtml(kind)}${isSelected ? ' is-selected' : ''}" data-payment-channel-option="${escapeHtml(code)}"${cheapAltAttrs} aria-pressed="${isSelected ? 'true' : 'false'}">`
                + `<span class="pc-checkout-channel__icon"><i class="bi ${escapeHtml(icon)}"></i></span>`
                + `<span class="pc-checkout-channel__label">${escapeHtml(channel?.display_name || code)}</span>`
                + feeHtml
                + '</button>';
        }).join('');

        bindChannelPickerClicks();
    }

    function applyMessageTemplate(template, replacements) {
        return Object.entries(replacements).reduce(
            (text, [key, value]) => text.replaceAll(`:${key}`, String(value ?? '')),
            template ?? '',
        );
    }

    function formatChannelFee(channel) {
        return formatPickerChannelFee(channel);
    }

    async function refreshCheckoutChannels({ silent = false } = {}) {
        if (terminal || channelsUrl === '') {
            return;
        }

        try {
            const response = await fetch(channelsUrl, {
                headers: buildHeaders(csrfToken),
                credentials: 'same-origin',
            });
            const payload = await response.json();

            if (!response.ok || payload.status !== 'success') {
                return;
            }

            const nextChannels = Array.isArray(payload.data) ? payload.data : [];
            const { removed, feeChanges } = diffCheckoutChannels(channels, nextChannels);

            if (!silent) {
                for (const channel of removed) {
                    const label = channel.display_name || channel.code || '';
                    showCheckoutToast(
                        applyMessageTemplate(messages.channelUnavailable, { channel: label }),
                        'warning',
                    );
                }

                for (const change of feeChanges) {
                    const label = change.new.display_name || change.code || '';
                    showCheckoutToast(
                        applyMessageTemplate(messages.feeUpdated, {
                            channel: label,
                            old: formatChannelFee(change.old),
                            new: formatChannelFee(change.new),
                        }),
                        'info',
                    );
                }

                if (removed.length === 0 && feeChanges.length === 0) {
                    const oldCodes = new Set(channels.map((item) => item?.code).filter(Boolean));
                    const newCodes = new Set(nextChannels.map((item) => item?.code).filter(Boolean));
                    const catalogChanged = oldCodes.size !== newCodes.size
                        || [...oldCodes].some((code) => !newCodes.has(code))
                        || [...newCodes].some((code) => !oldCodes.has(code));

                    if (catalogChanged) {
                        showCheckoutToast(messages.routingUpdated ?? 'Payment methods were updated.', 'info');
                    }
                }
            }

            const selectedStillAvailable = nextChannels.some((item) => item?.code === selectedChannel);

            if (!selectedStillAvailable && selectedChannel !== '') {
                stopPolling();
                clearInstructions();
                showCheckoutToast(
                    messages.selectedChannelUnavailable ?? 'Selected payment method is no longer available.',
                    'warning',
                );
                selectedChannel = '';
            } else if (nextChannels.length === 0 && selectedChannel !== '') {
                stopPolling();
                clearInstructions();
                selectedChannel = '';
            }

            channels = nextChannels;

            if (checkoutButton) {
                checkoutButton.disabled = selectedChannel === '';
            }

            if (channels.length === 0) {
                picker?.classList.add('d-none');
                updateFeeBreakdown(root, {
                    subtotal: Number.parseFloat(root.dataset.invoiceSubtotal ?? '0'),
                    payment_channel_fee: 0,
                    payment_channel_fee_basis: null,
                    customer_gross_total: Number.parseFloat(root.dataset.invoiceGross ?? '0'),
                    currency: root.dataset.invoiceCurrency ?? 'IDR',
                }, { confirmed: false, messages });
            } else {
                picker?.classList.remove('d-none');
                recalculateChannelQuotesForSubtotal(Number.parseFloat(root.dataset.invoiceSubtotal ?? '0'));

                if (selectedChannel !== '') {
                    updateCheapAltHint(selectedChannel);
                } else {
                    updateUnselectedFeeBreakdown();
                }
            }
        } catch {
            // Ignore transient network errors; poll or next WS event will retry.
        }
    }

    let routingPollTimer = null;

    function startRoutingPoll() {
        if (routingPollTimer !== null || channelsUrl === '') {
            return;
        }

        routingPollTimer = window.setInterval(() => {
            refreshCheckoutChannels({ silent: true });
        }, ROUTING_POLL_MS);
    }

    function stopRoutingPoll() {
        if (routingPollTimer !== null) {
            clearInterval(routingPollTimer);
            routingPollTimer = null;
        }
    }

    if (selectedChannel !== '') {
        setSelected(selectedChannel);
    } else if (!initialCheckout?.instructions) {
        updateUnselectedFeeBreakdown();
    }

    if (initialCheckout?.instructions) {
        showInstructions(initialCheckout);
    }

    subscribeInvoiceChannel(invoiceId, broadcast, csrfToken, (payload) => {
        handleStatus(payload?.status ?? '', payload);
    });

    const routingSubscribed = subscribeRoutingCatalog(broadcast, csrfToken, () => {
        if (terminal) {
            return;
        }

        refreshCheckoutChannels();
    });

    if (!routingSubscribed) {
        startRoutingPoll();
    }

    function recalculateChannelQuotesForSubtotal(subtotal) {
        const currency = root.dataset.invoiceCurrency ?? 'IDR';
        const locale = root.dataset.appLocale ?? 'en';
        channels = channels.map((channel) => quoteChannelForSubtotal(channel, subtotal, currency, locale));
        root.dataset.channels = JSON.stringify(channels);
        renderChannelPickerList(channels, selectedChannel);

        if (selectedChannel !== '') {
            updateFeeBreakdownFromChannel(root, channels, selectedChannel, messages);
        }
    }

    root.addEventListener('payment-checkout:preview-amount', (event) => {
        if (terminal) {
            return;
        }

        const detail = event.detail ?? {};

        if (detail.subtotal !== undefined) {
            root.dataset.invoiceSubtotal = String(detail.subtotal);
        }

        if (detail.subtotal_formatted) {
            root.dataset.invoiceSubtotalFormatted = detail.subtotal_formatted;
        }

        if (detail.customer_gross_total !== undefined) {
            root.dataset.invoiceGross = String(detail.customer_gross_total);
        }

        if (detail.currency) {
            root.dataset.invoiceCurrency = detail.currency;
        }

        stopPolling();
        clearInstructions();

        const subtotal = Number.parseFloat(root.dataset.invoiceSubtotal ?? '0');
        recalculateChannelQuotesForSubtotal(subtotal);

        if (selectedChannel === '' && detail.subtotal !== undefined) {
            updateUnselectedFeeBreakdown();
        }
    });

    bindChannelPickerClicks();

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
            const hubQuantity = readHubQuantity();
            const payload = { channel_code: selectedChannel };

            if (hubQuantity !== null) {
                payload.quantity = hubQuantity;
            }

            const response = await fetch(channelCheckoutUrl, {
                method: 'POST',
                headers: buildHeaders(csrfToken),
                credentials: 'same-origin',
                body: JSON.stringify(payload),
            });

            const payloadJson = await response.json();

            if (!response.ok || payloadJson.status !== 'success') {
                throw new Error(payloadJson.message || messages.channelFailed || 'Channel checkout failed.');
            }

            const data = payloadJson.data ?? {};
            updateAmountCallout(root, data);
            root.dataset.invoiceSubtotal = String(data.subtotal ?? root.dataset.invoiceSubtotal);
            root.dataset.invoiceGross = String(data.customer_gross_total ?? data.amount ?? root.dataset.invoiceGross);
            applyHubAmountsFromCheckout(data);
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
        if (syncButton.disabled) {
            return;
        }

        const originalLabel = syncButton.textContent;
        syncButton.disabled = true;
        syncButton.textContent = messages.syncing ?? 'Syncing…';

        syncStatus(syncStatusUrl, csrfToken, messages, false, handleStatus, showCheckoutToast)
            .finally(() => {
                if (!terminal) {
                    syncButton.disabled = false;
                    syncButton.textContent = originalLabel;
                }
            });
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
            } else if (TERMINAL_FAILURE.has(status)) {
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

            document.dispatchEvent(new CustomEvent('payment-checkout:status-changed', {
                detail: {
                    status,
                    meta,
                    terminal: TERMINAL_SUCCESS.has(status) || TERMINAL_FAILURE.has(status),
                },
            }));
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

function applyHubAmountsFromCheckout(data) {
    const settlement = data.subtotal_formatted ?? null;
    const gross = data.customer_gross_total_formatted ?? null;

    if (settlement) {
        document.querySelector('[data-purchase-settlement]')?.replaceChildren(document.createTextNode(settlement));
    }

    if (gross) {
        document.querySelector('[data-payment-hero-amount]')?.replaceChildren(document.createTextNode(gross));
    }
}

function updateAmountCallout(root, data) {
    updateFeeBreakdown(root, {
        subtotal: data.subtotal,
        subtotal_formatted: data.subtotal_formatted,
        payment_channel_fee: data.payment_channel_fee,
        payment_channel_fee_formatted: data.payment_channel_fee_formatted,
        payment_channel_fee_basis: data.payment_channel_fee_basis ?? null,
        customer_gross_total: data.customer_gross_total ?? data.amount,
        customer_gross_total_formatted: data.customer_gross_total_formatted,
        currency: data.currency,
    }, { confirmed: true });
}

function resolveCheckoutLocale(root) {
    return root.dataset.appLocale ?? document.documentElement.lang?.split('-')[0] ?? 'en';
}

function formatDisplayMoney(root, raw, formatted, currency) {
    if (typeof formatted === 'string' && formatted !== '') {
        return formatted;
    }

    if (raw === null || raw === undefined || raw === '') {
        return '—';
    }

    const code = currency ?? root.dataset.invoiceCurrency ?? 'IDR';

    return formatMoney(raw, code, resolveCheckoutLocale(root));
}

function updateFeeBreakdownFromChannel(root, channels, code, messages) {
    const channel = channels.find((item) => item?.code === code);

    if (!channel) {
        return;
    }

    const currency = root.dataset.invoiceCurrency ?? 'IDR';
    const locale = resolveCheckoutLocale(root);
    const subtotal = Number.parseFloat(root.dataset.invoiceSubtotal ?? '0');
    const fee = Number(channel.estimated_channel_fee ?? 0);
    const total = Number(channel.estimated_customer_total ?? subtotal + fee);
    const subtotalFormatted = root.dataset.invoiceSubtotalFormatted
        || formatMoney(subtotal, currency, locale);

    updateFeeBreakdown(root, {
        subtotal,
        subtotal_formatted: subtotalFormatted,
        payment_channel_fee: fee,
        payment_channel_fee_formatted: channel.estimated_channel_fee_formatted ?? null,
        payment_channel_fee_basis: channel.fee_basis ?? null,
        customer_gross_total: total,
        customer_gross_total_formatted: channel.estimated_customer_total_formatted ?? null,
        currency,
    }, { confirmed: false, messages });
}

function updateFeeBasisElement(element, basis) {
    if (!element) {
        return;
    }

    const text = typeof basis === 'string' && basis !== '' ? basis : '';

    element.textContent = text;
    element.classList.toggle('d-none', text === '');
}

function updateFeeBreakdown(root, data, { confirmed = false, messages = {} } = {}) {
    const currency = data.currency ?? root.dataset.invoiceCurrency ?? 'IDR';
    const formatDisplay = (raw, formatted) => formatDisplayMoney(root, raw, formatted, currency);

    const subtotalEl = root.querySelector('[data-payment-fee-subtotal]');
    const feeEl = root.querySelector('[data-payment-fee-surcharge]');
    const feeBasisEl = root.querySelector('[data-payment-fee-basis]');
    const totalEl = root.querySelector('[data-payment-fee-total]');
    const hintEl = root.querySelector('[data-payment-fee-estimate-hint]');

    if (subtotalEl) {
        subtotalEl.textContent = formatDisplay(
            data.subtotal ?? root.dataset.invoiceSubtotal,
            data.subtotal_formatted,
        );
    }

    if (feeEl) {
        feeEl.textContent = formatDisplay(data.payment_channel_fee, data.payment_channel_fee_formatted);
    }

    updateFeeBasisElement(feeBasisEl, data.payment_channel_fee_basis);

    if (totalEl) {
        totalEl.textContent = formatDisplay(
            data.customer_gross_total ?? data.amount,
            data.customer_gross_total_formatted,
        );
    }

    hintEl?.classList.toggle('d-none', confirmed);

    document.querySelector('[data-payment-sidebar-subtotal]')?.replaceChildren(
        document.createTextNode(formatDisplay(data.subtotal ?? root.dataset.invoiceSubtotal, data.subtotal_formatted)),
    );

    const sidebarFeeAmount = document.querySelector('[data-payment-sidebar-fee-amount]');
    if (sidebarFeeAmount) {
        sidebarFeeAmount.textContent = formatDisplay(data.payment_channel_fee, data.payment_channel_fee_formatted);
    }

    updateFeeBasisElement(document.querySelector('[data-payment-sidebar-fee-basis]'), data.payment_channel_fee_basis);

    document.querySelector('[data-payment-sidebar-total]')?.replaceChildren(
        document.createTextNode(formatDisplay(data.customer_gross_total ?? data.amount, data.customer_gross_total_formatted)),
    );
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
        ? `<p class="text-muted small">${escapeHtml((messages.expiresAt ?? 'Pay before :time').replace(':time', formatDate(data.expires_at)))}</p>`
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
            invoiceable_status: invoice.invoiceable_status ?? null,
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
 * Subscribe to private-payments.routing when broadcast config present.
 *
 * @returns {boolean} whether subscription was attempted successfully
 */
function subscribeRoutingCatalog(broadcast, csrfToken, onPayload) {
    if (!broadcast || typeof onPayload !== 'function') {
        return false;
    }

    try {
        let echo = window.__monolithEcho;
        if (!echo) {
            echo = createNotificationEcho(broadcast, csrfToken);
            window.__monolithEcho = echo;
        }

        if (window.__paymentRoutingEchoBound === '1') {
            return true;
        }

        const channelName = 'payments.routing';
        const channel = echo.private(channelName);
        channel.listen('.routing.catalog.changed', () => {
            onPayload();
        });

        window.__paymentRoutingEchoBound = '1';

        return true;
    } catch {
        return false;
    }
}

function diffCheckoutChannels(oldChannels, newChannels) {
    const oldMap = new Map(oldChannels.map((channel) => [channel?.code, channel]));
    const newMap = new Map(newChannels.map((channel) => [channel?.code, channel]));
    const removed = [];
    const feeChanges = [];

    for (const [code, oldChannel] of oldMap.entries()) {
        if (!code) {
            continue;
        }

        if (!newMap.has(code)) {
            removed.push(oldChannel);
            continue;
        }

        const newChannel = newMap.get(code);
        const oldFee = Number(oldChannel?.estimated_channel_fee ?? 0);
        const newFee = Number(newChannel?.estimated_channel_fee ?? 0);

        if (oldFee !== newFee) {
            feeChanges.push({ code, old: oldChannel, new: newChannel });
        }
    }

    return { removed, feeChanges };
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
