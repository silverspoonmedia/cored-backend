<script>
    (function () {
        'use strict';

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

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

        window.handleDatatableAction = function (action) {
            if (action.confirm && !window.confirm(action.confirm)) {
                return;
            }

            if (action.method === 'GET') {
                window.location.href = action.url;

                return;
            }

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = action.url;
            form.style.display = 'none';

            const appendHidden = function (name, value) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                input.value = value;
                form.appendChild(input);
            };

            appendHidden('_token', csrfToken);

            if (action.method !== 'POST') {
                appendHidden('_method', action.method);
            }

            document.body.appendChild(form);
            form.submit();
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
