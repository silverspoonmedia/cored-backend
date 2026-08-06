/**
 * Shared API response handler — exposes window.MonolithApi.
 *
 * Handles AJAX response envelopes (422/419/401/403/5xx), field-error display,
 * and datatable AJAX error fallback. Translation strings are injected via a
 * <script type="application/json" data-monolith-api-messages> blob in the
 * layout so the module stays framework-agnostic.
 *
 * Contract: docs/web/blade-admin.md — "window.MonolithApi.handleResponse()".
 */
(function () {
    'use strict';

    const messagesEl = document.querySelector('[data-monolith-api-messages]');
    let messages = {
        requestFailed: 'Request failed.',
        serverUnreachable: 'Server unreachable.',
        csrfExpired: 'Session expired. Reloading…',
        datatableLoadFailed: 'Failed to load table data.',
    };

    if (messagesEl) {
        try {
            messages = JSON.parse(messagesEl.textContent || '{}');
        } catch (e) {
            console.error('MonolithApi: invalid messages JSON', e);
        }
    }

    function showMessage(alertEl, message, type) {
        if (!message) {
            return;
        }

        if (alertEl) {
            alertEl.className = 'alert alert-' + type + ' mb-3';
            alertEl.textContent = message;
            alertEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

            return;
        }

        if (window.MonolithSwal?.toastMessage) {
            window.MonolithSwal.toastMessage(message, type === 'danger' ? 'error' : type);
        } else {
            window.alert(message);
        }
    }

    function handleResponse(response, data, options) {
        const alertEl = options.alertEl ?? null;
        const form = options.form ?? null;
        const applyFieldErrors = options.applyFieldErrors ?? null;
        const onSuccess = options.onSuccess ?? null;

        if (response.ok) {
            if (data.message) {
                showMessage(alertEl, data.message, 'success');
            }

            if (data.redirect) {
                window.location.assign(data.redirect);

                return { handled: true, redirecting: true };
            }

            if (typeof onSuccess === 'function') {
                onSuccess(data);
            }

            document.dispatchEvent(new CustomEvent('ajax-form:success', {
                detail: { data: data, form: form },
            }));

            return { handled: true, success: true };
        }

        if (response.status === 422 && data.errors && form && typeof applyFieldErrors === 'function') {
            applyFieldErrors(form, data.errors);

            if (data.message) {
                showMessage(alertEl, data.message, 'danger');
            }

            document.dispatchEvent(new CustomEvent('ajax-form:invalid', {
                detail: { data: data, form: form, message: data.message },
            }));

            return { handled: true, invalid: true };
        }

        if (response.status === 422 && data.message) {
            showMessage(alertEl, data.message, 'danger');
            document.dispatchEvent(new CustomEvent('ajax-form:invalid', {
                detail: { data: data, form: form, message: data.message },
            }));

            return { handled: true, invalid: true };
        }

        if (response.status === 419 && data.reload) {
            showMessage(alertEl, data.message || messages.csrfExpired, 'danger');
            window.setTimeout(function () {
                window.location.reload();
            }, 2000);

            return { handled: true, reloading: true };
        }

        if (data.redirect) {
            if (data.message) {
                showMessage(alertEl, data.message, 'danger');
            }

            window.location.assign(data.redirect);

            return { handled: true, redirecting: true };
        }

        showMessage(alertEl, data.message || messages.requestFailed, 'danger');

        return { handled: true, error: true };
    }

    function handleNetworkError(alertEl) {
        showMessage(alertEl, messages.serverUnreachable, 'danger');
    }

    function datatableAjaxError(xhr) {
        const payload = xhr.responseJSON ?? {};
        const message = payload.error || payload.message || messages.datatableLoadFailed;

        if (window.MonolithSwal?.toastMessage) {
            window.MonolithSwal.toastMessage(message, 'error');
        } else {
            window.alert(message);
        }
    }

    window.MonolithApi = {
        messages: messages,
        showMessage: showMessage,
        handleResponse: handleResponse,
        handleNetworkError: handleNetworkError,
        datatableAjaxError: datatableAjaxError,
    };
})();
