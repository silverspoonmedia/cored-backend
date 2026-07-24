<script>
    (function () {
        'use strict';

        @php
            $monolithApiMessages = [
                'requestFailed' => __('ui.ajax.request_failed'),
                'serverUnreachable' => __('ui.ajax.server_unreachable'),
                'csrfExpired' => __('web/session.csrf_expired'),
                'datatableLoadFailed' => __('ui.ajax.datatable_load_failed'),
            ];
        @endphp
        const messages = @json($monolithApiMessages);

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

            window.alert(message);
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

                // Let feature UIs update in-place after a successful generic AJAX
                // form without coupling shared infrastructure to domain markup.
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

                document.dispatchEvent(new CustomEvent('ajax-form:invalid'));

                return { handled: true, invalid: true };
            }

            if (response.status === 422 && data.message) {
                showMessage(alertEl, data.message, 'danger');
                document.dispatchEvent(new CustomEvent('ajax-form:invalid'));

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

            window.alert(message);
        }

        window.MonolithApi = {
            messages: messages,
            showMessage: showMessage,
            handleResponse: handleResponse,
            handleNetworkError: handleNetworkError,
            datatableAjaxError: datatableAjaxError,
        };
    })();
</script>
