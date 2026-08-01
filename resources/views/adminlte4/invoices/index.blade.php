<x-adminlte4::layouts.app :title="__('web/payments.invoices.title')">
    <x-adminlte4::card :title="__('web/payments.invoices.list_title')">
        <div class="d-flex justify-content-end mb-3">
            @can('view invoice')
                <a href="{{ route('invoices.export') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-download me-1"></i>{{ __('web/payments.invoices.export_csv') }}
                </a>
            @endcan
        </div>

        <div class="table-responsive">
            <table id="invoices-table" class="table table-hover align-middle mb-0 w-100" aria-label="{{ __('web/payments.invoices.list_title') }}"></table>
        </div>
    </x-adminlte4::card>

    <x-slot:scripts>
        <x-adminlte4::partials.datatable-assets />
        <x-adminlte4::partials.datatable-row-actions />

        <script>
            $(function () {
                $('#invoices-table').DataTable({
                    processing: true,
                    serverSide: true,
                    ajax: {
                        url: '{{ route('invoices.datatable') }}',
                        error: function (xhr) {
                            window.MonolithApi.datatableAjaxError(xhr);
                        },
                    },
                    order: [[5, 'desc']],
                    language: {
                        emptyTable: @json(__('web/payments.invoices.datatable.empty')),
                        zeroRecords: @json(__('web/payments.invoices.datatable.zero_records')),
                    },
                    columns: [
                        { data: 'invoice_number', title: @json(__('web/payments.invoices.datatable.columns.invoice_number')) },
                        { data: 'status', title: @json(__('web/payments.invoices.datatable.columns.status')) },
                        {
                            data: 'subtotal_formatted',
                            title: @json(__('web/payments.invoices.datatable.columns.subtotal')),
                            orderable: false,
                            render: function (value) {
                                return value != null && value !== '' ? value : '—';
                            },
                        },
                        {
                            data: 'payment_channel_fee_formatted',
                            title: @json(__('web/payments.invoices.datatable.columns.fee')),
                            orderable: false,
                            render: function (value) {
                                return value != null && value !== '' ? value : '—';
                            },
                        },
                        {
                            data: 'customer_gross_total_formatted',
                            title: @json(__('web/payments.invoices.datatable.columns.total')),
                            orderable: false,
                            render: function (value) {
                                return value != null && value !== '' ? value : '—';
                            },
                        },
                        {
                            data: 'created_at_formatted',
                            title: @json(__('web/payments.invoices.datatable.columns.created_at')),
                            orderable: false,
                            render: function (value, type, row) {
                                return value || row.created_at || '—';
                            },
                        },
                        {
                            data: 'actions',
                            title: @json(__('web/payments.invoices.datatable.columns.actions')),
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
</x-adminlte4::layouts.app>
