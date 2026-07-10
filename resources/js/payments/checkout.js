/**
 * Hosted checkout for pending invoices (Midtrans Snap + iPaymu redirect).
 */
export function initPaymentCheckout(root) {
    if (!root || root.dataset.paymentCheckoutBound === '1') {
        return;
    }

    root.dataset.paymentCheckoutBound = '1';

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    const snapTokenUrl = root.dataset.snapTokenUrl;
    const returnUrl = root.dataset.returnUrl || '';
    const cancelUrl = root.dataset.cancelUrl || '';
    const messages = JSON.parse(root.dataset.messages || '{}');
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
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
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
