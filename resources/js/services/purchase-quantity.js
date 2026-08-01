/**
 * Pending addon purchase quantity — local preview only until channel checkout commits.
 */
import { computePurchasePreview } from './purchase-pricing-preview.js';

function parseJson(raw) {
    try {
        return JSON.parse(raw || '{}');
    } catch {
        return {};
    }
}

function updateText(selector, value) {
    const el = document.querySelector(selector);

    if (el && value !== undefined && value !== null) {
        el.textContent = value;
    }
}

function applyPreviewToPage(preview, pricing) {
    updateText('[data-purchase-list-amount]', preview.list_amount_formatted);
    updateText('[data-purchase-display-amount]', preview.display_amount_formatted);
    updateText('[data-purchase-settlement]', preview.settlement_amount_formatted);
    updateText('[data-purchase-fx-rate]', preview.fx_rate_label ?? preview.fx_rate_formatted);
    updateText('[data-payment-hero-amount]', preview.settlement_amount_formatted);

    updateText('[data-stack-quantity-equation]', preview.stack_equation);

    const storageGain = document.querySelector('[data-purchase-storage-gain]');

    if (storageGain && preview.storage_gain_human) {
        const template = pricing.storage_gain_template || storageGain.dataset.template || '+:amount';
        storageGain.textContent = template.replace(':amount', preview.storage_gain_human);
    }

    document.querySelector('[data-payment-checkout]')?.dispatchEvent(new CustomEvent('payment-checkout:preview-amount', {
        bubbles: false,
        detail: {
            subtotal: preview.subtotal,
            subtotal_formatted: preview.subtotal_formatted,
            customer_gross_total: preview.subtotal,
            customer_gross_total_formatted: preview.subtotal_formatted,
            currency: preview.currency,
        },
    }));
}

export function lockPurchaseQuantityEditor() {
    const root = document.querySelector('[data-purchase-quantity]');

    if (!root || root.dataset.locked === '1') {
        return;
    }

    root.dataset.locked = '1';

    const input = root.querySelector('[data-purchase-qty-input]');
    const qty = Math.max(1, Number.parseInt(input?.value || '1', 10) || 1);
    const dd = root.closest('dd');
    const dt = dd?.previousElementSibling?.tagName === 'DT' ? dd.previousElementSibling : null;

    dd?.querySelector('.text-muted.small')?.remove();

    if (qty <= 1) {
        dt?.remove();
        dd?.remove();

        return;
    }

    const display = document.createElement('span');
    display.setAttribute('data-purchase-qty-display', '');
    display.textContent = String(qty);
    root.replaceWith(display);
}

function initPurchaseQuantity(root) {
    if (!root || root.dataset.purchaseQuantityBound === '1') {
        return;
    }

    root.dataset.purchaseQuantityBound = '1';

    const pricing = parseJson(root.dataset.pricing);
    const maxQuantity = Number.parseInt(root.dataset.maxQuantity || '99', 10);
    const input = root.querySelector('[data-purchase-qty-input]');
    const minus = root.querySelector('[data-purchase-qty-minus]');
    const plus = root.querySelector('[data-purchase-qty-plus]');

    if (!input) {
        return;
    }

    const storageGain = root.querySelector('[data-purchase-storage-gain]');

    if (storageGain) {
        storageGain.dataset.template = pricing.storage_gain_template || storageGain.textContent;
    }

    const clamp = (value) => Math.min(maxQuantity, Math.max(1, value));

    const render = (value) => {
        if (root.dataset.locked === '1') {
            return;
        }

        const next = clamp(value);
        input.value = String(next);
        applyPreviewToPage(computePurchasePreview(pricing, next), pricing);
    };

    minus?.addEventListener('click', () => {
        render(Number.parseInt(input.value || '1', 10) - 1);
    });

    plus?.addEventListener('click', () => {
        render(Number.parseInt(input.value || '1', 10) + 1);
    });

    input.addEventListener('change', () => {
        render(Number.parseInt(input.value || '1', 10));
    });
}

document.addEventListener('payment-checkout:status-changed', (event) => {
    if (event.detail?.status === 'paid') {
        lockPurchaseQuantityEditor();
    }
});

document.querySelectorAll('[data-purchase-quantity]').forEach((root) => {
    initPurchaseQuantity(root);
});
