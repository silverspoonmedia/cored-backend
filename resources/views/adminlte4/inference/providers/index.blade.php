<x-adminlte4::layouts.app :title="__('web/inference.providers.title')">
    <x-adminlte4::card :title="__('web/inference.providers.list_title')">
        @can('manage inference catalog')
            <x-slot:actions>
                <a href="{{ route('inference.providers.create') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg me-1"></i>{{ __('web/inference.providers.add_button') }}
                </a>
            </x-slot:actions>
        @endcan

        <div class="table-responsive">
            <table id="inference-providers-table" class="table table-hover align-middle mb-0 w-100" aria-label="{{ __('web/inference.providers.list_title') }}"></table>
        </div>
    </x-adminlte4::card>

    <x-slot:scripts>
        <x-adminlte4::partials.datatable-row-actions />
        @vite('resources/js/datatable-init.js')

        <script type="module">
            const initDataTable = window.initDataTable;

            initDataTable({
                tableId: 'inference-providers-table',
                ajaxUrl: '{{ route('inference.providers.datatable') }}',
                order: [[0, 'asc']],
                language: {
                    emptyTable: @json(__('web/inference.providers.datatable.empty')),
                    zeroRecords: @json(__('web/inference.providers.datatable.zero_records')),
                },
                columns: [
                    { data: 'slug', title: @json(__('web/inference.providers.datatable.columns.slug')) },
                    { data: 'name', title: @json(__('web/inference.providers.datatable.columns.name')) },
                    {
                        data: 'model_rates_count',
                        title: @json(__('web/inference.providers.datatable.columns.rates_count')),
                        defaultContent: '0',
                    },
                    {
                        data: 'actions',
                        title: @json(__('ui.labels.actions')),
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
