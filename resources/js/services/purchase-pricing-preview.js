/**
 * Local purchase pricing preview — mirrors backend rounding (Service + FX + channel fees).
 * Display-only until channel checkout commits quantity on the server.
 */

export function roundMoney(amount, currency) {
    const code = String(currency ?? 'IDR').toUpperCase();

    if (code === 'IDR') {
        return Math.round(amount);
    }

    return Math.round(amount * 100) / 100;
}

const MONEY_DECIMALS = 2;

function formatNumber(amount, locale) {
    const value = Number(amount ?? 0);
    const loc = String(locale ?? 'en').split(/[-_]/)[0] || 'en';

    try {
        const formatted = new Intl.NumberFormat(loc, {
            minimumFractionDigits: MONEY_DECIMALS,
            maximumFractionDigits: MONEY_DECIMALS,
        }).format(value);

        if (formatted) {
            return formatted;
        }
    } catch {
        // fall through to PHP-aligned separators
    }

    const fixed = value.toFixed(MONEY_DECIMALS);
    const [intPart, decPart] = fixed.split('.');
    const withThousands = (separator) => intPart.replace(/\B(?=(\d{3})+(?!\d))/g, separator);

    if (loc === 'id') {
        return `${withThousands('.')},${decPart}`;
    }

    if (loc === 'ja') {
        return `${withThousands(',')}.${decPart}`;
    }

    return `${withThousands(',')}.${decPart}`;
}

/**
 * Mirror MoneyPresenter::formatted — locale-aware display string.
 */
export function formatMoney(amount, currency, locale) {
    const code = String(currency ?? 'IDR').toUpperCase().trim();
    const number = formatNumber(amount, locale);

    if (code === '') {
        return number;
    }

    return `${code} ${number}`;
}

const FX_MAX_DECIMALS = 8;

function trimTrailingZeros(formatted) {
    const text = String(formatted ?? '');

    if (!text.includes(',') && !text.includes('.')) {
        return text;
    }

    const decimalSeparator = text.includes(',') ? ',' : '.';
    const [whole, fraction = ''] = text.split(decimalSeparator);
    const trimmed = fraction.replace(/0+$/, '');

    if (trimmed === '') {
        return whole;
    }

    return `${whole}${decimalSeparator}${trimmed}`;
}

function formatFxNumber(amount, locale) {
    const value = Number(amount ?? 0);
    const loc = String(locale ?? 'en').split(/[-_]/)[0] || 'en';

    try {
        const formatted = new Intl.NumberFormat(loc, {
            minimumFractionDigits: 0,
            maximumFractionDigits: FX_MAX_DECIMALS,
        }).format(value);

        if (formatted) {
            return trimTrailingZeros(formatted);
        }
    } catch {
        // fall through to PHP-aligned separators
    }

    const fixed = value.toFixed(FX_MAX_DECIMALS);
    const [intPart, decPart] = fixed.split('.');
    const withThousands = (separator) => intPart.replace(/\B(?=(\d{3})+(?!\d))/g, separator);

    let formatted;

    if (loc === 'id') {
        formatted = `${withThousands('.')},${decPart}`;
    } else if (loc === 'ja') {
        formatted = `${withThousands(',')}.${decPart}`;
    } else {
        formatted = `${withThousands(',')}.${decPart}`;
    }

    return trimTrailingZeros(formatted);
}

/**
 * Mirror FxPresenter::formattedRate / label.
 */
export function formatFxRate(rate, locale) {
    if (rate === null || rate === undefined || rate === '' || Number.isNaN(Number(rate))) {
        return null;
    }

    return formatFxNumber(rate, locale);
}

export function formatFxLabel(rate, fromCurrency, toCurrency, locale) {
    const formatted = formatFxRate(rate, locale);
    const fromCode = String(fromCurrency ?? '').toUpperCase().trim();
    const toCode = String(toCurrency ?? '').toUpperCase().trim();

    if (formatted === null || formatted === '') {
        return null;
    }

    if (fromCode === '' || toCode === '') {
        return formatted;
    }

    if (fromCode === toCode) {
        return `1 ${fromCode} = 1 ${toCode}`;
    }

    return `1 ${fromCode} = ${formatted} ${toCode}`;
}

export function formatStackEquation(current, purchase, template) {
    const currentQty = Math.max(0, Number(current ?? 0));
    const purchaseQty = Math.max(1, Number(purchase ?? 1));
    const total = currentQty + purchaseQty;
    const tpl = template || ':current + :purchase = :total';

    return tpl
        .replace(':current', String(currentQty))
        .replace(':purchase', String(purchaseQty))
        .replace(':total', String(total));
}

export function formatBytesHuman(bytes) {
    const value = Math.max(0, Number(bytes ?? 0));

    if (value === 0) {
        return '0 B';
    }

    if (value < 1024) {
        return `${value} B`;
    }

    if (value < 1024 * 1024) {
        return `${(value / 1024).toFixed(1)} KB`;
    }

    if (value < 1024 * 1024 * 1024) {
        return `${(value / (1024 * 1024)).toFixed(1)} MB`;
    }

    return `${(value / (1024 * 1024 * 1024)).toFixed(1)} GB`;
}

/**
 * @param {Record<string, unknown>} pricing
 * @param {number} quantity
 */
export function computePurchasePreview(pricing, quantity) {
    const qty = Math.max(1, Number.parseInt(String(quantity), 10) || 1);
    const locale = String(pricing.locale ?? 'en');
    const unitList = Number(pricing.unit_list_amount ?? 0);
    const unitCharge = Number(pricing.unit_charge_amount ?? unitList);
    const fxRate = Number(pricing.fx_rate ?? 1);
    const displayCurrency = String(pricing.display_currency ?? 'USD');
    const settlementCurrency = String(pricing.settlement_currency ?? 'IDR');
    const settlementDecimals = Number(pricing.settlement_decimals ?? 0);
    const storageBytesPerUnit = Number(pricing.storage_bytes_per_unit ?? 0);
    const currentStack = Number(pricing.current_stack_quantity ?? 0);

    const listAmount = roundMoney(unitList * qty, displayCurrency);
    const displayCharge = roundMoney(unitCharge * qty, displayCurrency);
    const rawSettlement = displayCharge * fxRate;
    const settlement = settlementDecimals === 0
        ? Math.round(rawSettlement)
        : roundMoney(rawSettlement, settlementCurrency);

    return {
        quantity: qty,
        list_amount: listAmount,
        list_amount_formatted: formatMoney(listAmount, displayCurrency, locale),
        charge_amount: displayCharge,
        charge_amount_formatted: formatMoney(displayCharge, displayCurrency, locale),
        display_amount: displayCharge,
        display_amount_formatted: formatMoney(displayCharge, displayCurrency, locale),
        settlement_amount: settlement,
        settlement_amount_formatted: formatMoney(settlement, settlementCurrency, locale),
        fx_rate: fxRate,
        fx_rate_formatted: formatFxRate(fxRate, locale),
        fx_rate_label: formatFxLabel(fxRate, displayCurrency, settlementCurrency, locale),
        storage_gain_human: storageBytesPerUnit > 0
            ? formatBytesHuman(storageBytesPerUnit * qty)
            : null,
        stack_equation: currentStack > 0
            ? formatStackEquation(currentStack, qty, pricing.stack_equation_template)
            : null,
        projected_stack_quantity: currentStack > 0 ? currentStack + qty : null,
        subtotal: settlement,
        subtotal_formatted: formatMoney(settlement, settlementCurrency, locale),
        currency: settlementCurrency,
    };
}

/**
 * Mirror PaymentChannelFeeCalculator::estimateFromRoute.
 *
 * @param {Record<string, unknown>} channel
 * @param {number} subtotal
 * @param {string} currency
 */
export function estimateChannelFee(channel, subtotal, currency = 'IDR') {
    const flat = channel.fee_flat;

    if (flat !== null && flat !== undefined && flat !== '') {
        return roundMoney(Number(flat), currency);
    }

    const percent = channel.fee_percent;

    if (percent !== null && percent !== undefined && percent !== '') {
        return roundMoney(subtotal * (Number(percent) / 100), currency);
    }

    return 0;
}

/**
 * Rebuild channel quote fields for a preview subtotal.
 *
 * @param {Record<string, unknown>} channel
 * @param {number} subtotal
 * @param {string} currency
 */
export function quoteChannelForSubtotal(channel, subtotal, currency = 'IDR', locale = 'en') {
    const fee = estimateChannelFee(channel, subtotal, currency);
    const total = subtotal + fee;

    return {
        ...channel,
        estimated_channel_fee: fee,
        estimated_channel_fee_formatted: formatMoney(fee, currency, locale),
        estimated_customer_total: total,
        estimated_customer_total_formatted: formatMoney(total, currency, locale),
    };
}

export function readHubQuantity() {
    const input = document.querySelector('[data-purchase-qty-input]');

    if (!input) {
        return null;
    }

    const value = Number.parseInt(input.value || '1', 10);

    return Number.isNaN(value) ? 1 : Math.max(1, value);
}
