<script>
    (function () {
        'use strict';

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
        const messages = @json([
            'requestFailed' => __('ui.ajax.request_failed'),
            'serverUnreachable' => __('ui.ajax.server_unreachable'),
        ]);

        window.renderDatatableActions = function (actions) {
            if (!Array.isArray(actions) || actions.length === 0) {
                return '';
            }

            return actions.map(function (action) {
                const icon = action.icon
                    ? '<i class="' + action.icon + '"></i>'
                    : action.label;
                const payload = JSON.stringify(action).replace(/'/g, '&#39;');

                return '<button type="button" class="btn btn-sm btn-' + action.style + ' ms-1" '
                    + 'data-datatable-action=\'' + payload + '\' title="' + action.label + '">'
                    + icon + '</button>';
            }).join('');
        };

        window.handleDatatableAction = async function (action) {
            if (action.confirm && !window.confirm(action.confirm)) {
                return;
            }

            if (action.method === 'GET') {
                window.location.href = action.url;

                return;
            }

            const formData = new FormData();
            formData.append('_token', csrfToken);

            if (action.method !== 'POST') {
                formData.append('_method', action.method);
            }

            try {
                const response = await fetch(action.url, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    credentials: 'same-origin',
                });

                let data = {};
                try {
                    data = await response.json();
                } catch (parseError) {
                    data = {};
                }

                if (response.ok && data.redirect) {
                    window.location.assign(data.redirect);
                    return;
                }

                if (response.ok) {
                    window.location.reload();
                    return;
                }

                window.alert(data.message || messages.requestFailed);
            } catch (error) {
                window.alert(messages.serverUnreachable);
                console.error('Datatable action failed', error);
            }
        };

        document.addEventListener('click', function (event) {
            const button = event.target.closest('[data-datatable-action]');

            if (!button) {
                return;
            }

            event.preventDefault();

            try {
                handleDatatableAction(JSON.parse(button.getAttribute('data-datatable-action')));
            } catch (error) {
                console.error('Invalid datatable action payload', error);
            }
        });
    })();
</script>
