/**
 * Resolve a displayable QR image src from normalized payment instructions.
 */
export function resolveQrImageSrc(qrUrl, qrString) {
    const url = String(qrUrl ?? '').trim();

    if (url !== '') {
        if (url.startsWith('http://') || url.startsWith('https://') || url.startsWith('data:')) {
            return url;
        }

        if (looksLikeBase64(url)) {
            return `data:image/png;base64,${url}`;
        }

        return url;
    }

    const payload = String(qrString ?? '').trim();

    if (payload === '') {
        return '';
    }

    // Local endpoint (chillerlan/php-qrcode) — no third-party leak.
    return `${window.monolithQrBaseUrl ?? '/qr'}?data=${encodeURIComponent(payload)}`;
}

/**
 * Copy text with clipboard API fallback for non-secure contexts (HTTP).
 */
export async function copyToClipboard(text) {
    const value = String(text ?? '');

    if (value === '') {
        return false;
    }

    if (navigator.clipboard?.writeText) {
        try {
            await navigator.clipboard.writeText(value);

            return true;
        } catch {
            // fall through to legacy copy
        }
    }

    const textarea = document.createElement('textarea');
    textarea.value = value;
    textarea.setAttribute('readonly', '');
    textarea.style.position = 'fixed';
    textarea.style.left = '-9999px';
    document.body.appendChild(textarea);
    textarea.select();

    try {
        return document.execCommand('copy');
    } finally {
        document.body.removeChild(textarea);
    }
}

function looksLikeBase64(value) {
    if (value.length < 32) {
        return false;
    }

    return /^[A-Za-z0-9+/]+=*$/.test(value);
}
