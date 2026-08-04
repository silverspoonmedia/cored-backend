/**
 * FH SPA fetch — same-origin web BFF. Does NOT auto-follow mutation redirects
 * (SPA soft-refetches instead). Uses MonolithApi only for 401/419 UX when useful.
 */
import { csrfToken, unwrapData } from '../../../composables/useApi.js';

/**
 * @param {string} url
 * @param {RequestInit & { json?: unknown }} [options]
 */
export async function fhFetch(url, options = {}) {
    const { json, headers: extraHeaders = {}, ...init } = options;
    const method = (init.method ?? 'GET').toUpperCase();
    const headers = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrfToken(),
        ...extraHeaders,
    };

    let body = init.body;
    if (json !== undefined) {
        headers['Content-Type'] = 'application/json';
        body = JSON.stringify(json);
    }

    let response;
    try {
        response = await fetch(url, {
            credentials: 'same-origin',
            ...init,
            method,
            headers,
            body,
        });
    } catch {
        return { ok: false, status: 0, data: {}, networkError: true };
    }

    let data = {};
    try {
        data = await response.json();
    } catch {
        data = {};
    }

    // Auth death — reuse Monolith redirect contract without toast spam on soft GETs.
    if (response.status === 401 && data.redirect) {
        if (data.message && window.MonolithApi?.showMessage) {
            window.MonolithApi.showMessage(null, data.message, 'danger');
        }
        window.location.assign(data.redirect);
        return { ok: false, status: 401, data, redirecting: true };
    }

    if (response.status === 419 && data.reload) {
        if (window.MonolithApi?.showMessage) {
            window.MonolithApi.showMessage(null, data.message || 'Session expired. Reloading…', 'danger');
        }
        window.setTimeout(() => window.location.reload(), 2000);
        return { ok: false, status: 419, data, reloading: true };
    }

    return {
        ok: response.ok,
        status: response.status,
        data,
        payload: unwrapData(data),
    };
}

export function toast(message, type = 'success') {
    if (!message) {
        return;
    }
    if (window.MonolithSwal?.toastMessage) {
        window.MonolithSwal.toastMessage(message, type === 'danger' ? 'error' : type);
        return;
    }
    if (window.MonolithApi?.showMessage) {
        window.MonolithApi.showMessage(null, message, type);
        return;
    }
    // eslint-disable-next-line no-alert
    window.alert(message);
}

export async function fhConfirm(message) {
    if (window.MonolithSwal?.confirm) {
        return window.MonolithSwal.confirm(message, '', { dangerMode: true });
    }
    // eslint-disable-next-line no-alert
    return window.confirm(message);
}

export { unwrapData, csrfToken };
