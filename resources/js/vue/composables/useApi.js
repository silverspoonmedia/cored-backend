/**
 * Thin fetch wrapper for Vue islands.
 *
 * Same-origin web proxies (or future `/api/v1`) with CSRF + credentials.
 * Response envelopes go through `window.MonolithApi.handleResponse` when present
 * so 401/419/422 UX stays aligned with Blade ajax-form.
 *
 * @see docs/web/blade-admin.md — Vue islands / MonolithApi contract
 */

/**
 * @returns {string}
 */
export function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

/**
 * @param {string} url
 * @param {RequestInit & { alertEl?: Element|null, onSuccess?: Function }} [options]
 * @returns {Promise<{ ok: boolean, status: number, data: Record<string, unknown>, result?: object }>}
 */
export async function apiFetch(url, options = {}) {
    const {
        alertEl = null,
        onSuccess = null,
        headers: extraHeaders = {},
        ...init
    } = options;

    const method = (init.method ?? 'GET').toUpperCase();
    const headers = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...extraHeaders,
    };

    if (method !== 'GET' && method !== 'HEAD' && method !== 'OPTIONS') {
        headers['X-CSRF-TOKEN'] = csrfToken();
    }

    let response;

    try {
        response = await fetch(url, {
            credentials: 'same-origin',
            ...init,
            method,
            headers,
        });
    } catch (e) {
        if (window.MonolithApi?.handleNetworkError) {
            window.MonolithApi.handleNetworkError(alertEl);
        }

        return { ok: false, status: 0, data: {}, networkError: true };
    }

    let data = {};

    try {
        data = await response.json();
    } catch (e) {
        data = {};
    }

    let result;

    if (window.MonolithApi?.handleResponse) {
        result = window.MonolithApi.handleResponse(response, data, {
            alertEl,
            onSuccess,
        });
    } else if (response.ok && typeof onSuccess === 'function') {
        onSuccess(data);
        result = { handled: true, success: true };
    }

    return {
        ok: response.ok,
        status: response.status,
        data,
        result,
    };
}

/**
 * Unwrap Monolith `{ data }` / success envelopes for island consumers.
 *
 * @param {unknown} payload
 * @returns {unknown}
 */
export function unwrapData(payload) {
    if (payload && typeof payload === 'object' && 'data' in payload) {
        return payload.data;
    }

    return payload;
}
