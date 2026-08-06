<x-adminlte4::layouts.app :title="__('web/vtual-orphans.title')">
    <x-adminlte4::card :title="__('web/vtual-orphans.list_title')">
        <x-slot:actions>
            <a href="{{ route('vtual.orphans.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ __('web/vtual-orphans.add_button') }}
            </a>
        </x-slot:actions>

        <p class="text-muted small mb-3">{{ __('web/vtual-orphans.subtitle') }}</p>

        <div class="table-responsive">
            <table id="vtual-orphans-table" class="table table-hover align-middle mb-0 w-100" aria-label="{{ __('web/vtual-orphans.list_title') }}">
                <caption class="visually-hidden">{{ __('web/vtual-orphans.list_title') }}</caption>
            </table>
        </div>
    </x-adminlte4::card>

    <x-slot:scripts>
        <x-adminlte4::partials.datatable-row-actions />
        @vite('resources/js/datatable-init.js')

        <script type="module">
            const initDataTable = window.initDataTable;

            initDataTable({
                tableId: 'vtual-orphans-table',
                ajaxUrl: '{{ route('vtual.orphans.datatable') }}',
                order: [[0, 'asc']],
                exposeForReload: true,
                language: {
                    emptyTable: @json(__('web/vtual-orphans.datatable.empty')),
                    zeroRecords: @json(__('web/vtual-orphans.datatable.zero_records')),
                },
                columns: [
                    { data: 'display_name', title: @json(__('web/vtual-orphans.datatable.columns.display_name')) },
                    { data: 'slug', title: @json(__('web/vtual-orphans.datatable.columns.slug')) },
                    { data: 'channels_count', title: @json(__('web/vtual-orphans.datatable.columns.channels')) },
                    { data: 'created_at', title: @json(__('web/vtual-orphans.datatable.columns.created_at')) },
                    {
                        data: 'actions',
                        title: @json(__('web/vtual-orphans.datatable.columns.actions')),
                        orderable: false,
                        searchable: false,
                        className: 'text-end',
                        render: function (actions) {
                            return window.renderDatatableActions(actions);
                        },
                    },
                ],
            });
        </script>
    </x-slot:scripts>
</x-adminlte4::layouts.app>
