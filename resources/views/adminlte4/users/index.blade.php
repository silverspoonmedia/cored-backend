<x-adminlte4::layouts.app :title="__('web/users.title')">
    <x-adminlte4::card :title="__('web/users.list_title')">
        @can('create user')
            <x-slot:actions>
                <a href="{{ route('users.create') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg me-1"></i> {{ __('web/users.add_button') }}
                </a>
            </x-slot:actions>
        @endcan

        <div class="table-responsive">
            <table id="users-table" class="table table-hover align-middle mb-0 w-100" aria-label="{{ __('ui.labels.users_table') }}">
                <caption class="visually-hidden">{{ __('web/users.list_title') }}</caption>
            </table>
        </div>
    </x-adminlte4::card>

    <x-slot:scripts>
        <x-adminlte4::partials.datatable-row-actions />
        @vite('resources/js/datatable-init.js')

        <script type="module">
            const initDataTable = window.initDataTable;

            initDataTable({
                tableId: 'users-table',
                ajaxUrl: '{{ route('users.datatable') }}',
                order: [[0, 'asc']],
                exposeForReload: true,
                language: {
                    emptyTable: @json(__('web/users.datatable.empty')),
                    zeroRecords: @json(__('web/users.datatable.zero_records')),
                },
                columns: [
                    { data: 'name', title: @json(__('web/users.datatable.columns.name')) },
                    { data: 'email', title: @json(__('web/users.datatable.columns.email')) },
                    {
                        data: 'roles',
                        title: @json(__('web/users.datatable.columns.roles')),
                        orderable: false,
                        render: function (roles) {
                            if (!Array.isArray(roles) || roles.length === 0) {
                                return '<span class="text-muted">&mdash;</span>';
                            }

                            return roles.map(function (role) {
                                return '<span class="badge text-bg-light border me-1">' + role.name + '</span>';
                            }).join('');
                        },
                    },
                    {
                        data: 'actions',
                        title: @json(__('web/users.datatable.columns.actions')),
                        orderable: false,
                        searchable: false,
                        className: 'text-end',
                        render: function (actions) {
                            return window.renderDatatableActions(actions);
                        },
                    },
                ],
                onReady: function (table) {
                    // WebSocket is the primary live-reload path (bell ping ->
                    // reloadDataTable). Polling is ONLY a fallback: it runs while the
                    // socket is degraded (down past the bell's grace window) and is torn
                    // down the moment it reconnects, so the two never overlap.
                    let lastReload = 0;
                    let tablePollTimer = null;

                    function refreshUsersTable() {
                        if (document.visibilityState !== 'visible') {
                            return;
                        }

                        const now = Date.now();

                        if (now - lastReload < 2000) {
                            return; // debounce
                        }

                        lastReload = now;
                        table.ajax.reload(null, false); // false = keep current paging
                    }

                    function startTablePolling() {
                        if (tablePollTimer) {
                            return;
                        }

                        refreshUsersTable();
                        tablePollTimer = setInterval(refreshUsersTable, 60000);
                    }

                    function stopTablePolling() {
                        if (tablePollTimer) {
                            clearInterval(tablePollTimer);
                            tablePollTimer = null;
                        }
                    }

                    // Follow the realtime health broadcast by bell.js: healthy socket =>
                    // no polling (pure WS); degraded => polling on; reconnect => polling
                    // off + one catch-up reload. Pure polling / failed config emits
                    // notification-realtime:polling (bell loads in assets-scripts before
                    // this $scripts slot, so the snapshot below usually sees health already).
                    window.addEventListener('notification-realtime:connected', function () {
                        stopTablePolling();
                        refreshUsersTable();
                    });
                    window.addEventListener('notification-realtime:degraded', startTablePolling);
                    window.addEventListener('notification-realtime:polling', startTablePolling);

                    const realtimeHealth = window.notificationRealtime;

                    if (realtimeHealth && (realtimeHealth.enabled === false || realtimeHealth.degraded === true)) {
                        startTablePolling();
                    }
                },
            });
        </script>
    </x-slot:scripts>
</x-adminlte4::layouts.app>
