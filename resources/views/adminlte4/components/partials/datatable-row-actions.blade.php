<script>
    (function () {
        'use strict';

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

        window.renderDatatableActions = function (actions) {
            if (!Array.isArray(actions) || actions.length === 0) {
                return '';
            }

            const groupLabel = @json(__('ui.labels.actions'));

            const buttons = actions.map(function (action) {
                const icon = action.icon
                    ? '<i class="' + action.icon + '" aria-hidden="true"></i>'
                    : action.label;
                const payload = JSON.stringify(action).replace(/'/g, '&#39;');

                return '<button type="button" class="btn btn-sm btn-' + action.style + '" '
                    + 'data-datatable-action=\'' + payload + '\' '
                    + 'aria-label="' + action.label + '" title="' + action.label + '">'
                    + icon + '</button>';
            }).join('');

            return '<div class="btn-group btn-group-sm" role="group" aria-label="' + groupLabel + '">'
                + buttons
                + '</div>';
        };

        window.handleDatatableAction = async function (action) {
            if (action.confirm) {
                const confirmed = window.MonolithSwal
                    ? await window.MonolithSwal.confirm(action.confirm, '', { dangerMode: true })
                    : window.confirm(action.confirm);
                if (!confirmed) {
                    return;
                }
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

                window.MonolithApi.handleResponse(response, data, {
                    onSuccess: function () {
                        window.location.reload();
                    },
                });
            } catch (error) {
                window.MonolithApi.handleNetworkError(null);
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
