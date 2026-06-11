<x-layouts.app :title="__('web/users.title')">
    <x-slot:breadcrumb>
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('menu.dashboard') }}</a></li>
        <li class="breadcrumb-item active" aria-current="page">{{ __('web/users.breadcrumb') }}</li>
    </x-slot:breadcrumb>

    <x-card :title="__('web/users.list_title')">
        @can('create user')
            <x-slot:actions>
                <a href="{{ route('users.create') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg me-1"></i> {{ __('web/users.add_button') }}
                </a>
            </x-slot:actions>
        @endcan

        <div class="table-responsive">
            <table id="users-table" class="table table-hover align-middle mb-0 w-100"></table>
        </div>
    </x-card>

    <x-slot:scripts>
        <x-partials.datatable-assets />
        <x-partials.datatable-row-actions />

        <script>
            $(function () {
                $('#users-table').DataTable({
                    processing: true,
                    serverSide: true,
                    ajax: '{{ route('users.datatable') }}',
                    order: [[0, 'asc']],
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
                });
            });
        </script>
    </x-slot:scripts>
</x-layouts.app>
