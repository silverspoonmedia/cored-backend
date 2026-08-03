<x-adminlte4::layouts.app :title="__('web/inference.model_cards.title')">
    <x-adminlte4::card :title="__('web/inference.model_cards.list_title')">
        @can('manage inference catalog')
            <x-slot:actions>
                <a href="{{ route('inference.model-cards.create') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg me-1"></i>{{ __('web/inference.model_cards.add_button') }}
                </a>
            </x-slot:actions>
        @endcan

        <div class="table-responsive">
            <table id="inference-model-cards-table" class="table table-hover align-middle mb-0 w-100" aria-label="{{ __('web/inference.model_cards.list_title') }}"></table>
        </div>
    </x-adminlte4::card>

    <x-slot:scripts>
        <x-adminlte4::partials.datatable-row-actions />
        @vite('resources/js/datatable-init.js')

        <script type="module">
            const initDataTable = window.initDataTable;

            initDataTable({
                tableId: 'inference-model-cards-table',
                ajaxUrl: '{{ route('inference.model-cards.datatable') }}',
                order: [[0, 'asc']],
                language: {
                    emptyTable: @json(__('web/inference.model_cards.datatable.empty')),
                    zeroRecords: @json(__('web/inference.model_cards.datatable.zero_records')),
                },
                columns: [
                    { data: 'slug', title: @json(__('web/inference.model_cards.datatable.columns.slug')) },
                    { data: 'display_name', title: @json(__('web/inference.model_cards.datatable.columns.display_name')) },
                    {
                        data: 'is_active',
                        title: @json(__('web/inference.model_cards.datatable.columns.is_active')),
                        render: function (value) {
                            return value
                                ? '<span class="badge text-bg-success">Yes</span>'
                                : '<span class="badge text-bg-secondary">No</span>';
                        },
                    },
                    {
                        data: 'needs_rate_classification',
                        title: @json(__('web/inference.model_cards.datatable.columns.needs_rate_classification')),
                        render: function (value) {
                            return value
                                ? '<span class="badge text-bg-warning text-dark">' + @json(__('web/inference.model_cards.datatable.needs_classification_badge')) + '</span>'
                                : '';
                        },
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
