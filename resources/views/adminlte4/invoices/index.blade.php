<x-adminlte4::layouts.app :title="__('web/payments.invoices.title')">
    <x-adminlte4::card :title="__('web/payments.invoices.list_title')">
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
                    order: [[3, 'desc']],
                    language: {
                        emptyTable: @json(__('web/payments.invoices.datatable.empty')),
                        zeroRecords: @json(__('web/payments.invoices.datatable.zero_records')),
                    },
                    columns: [
                        { data: 'invoice_number', title: @json(__('web/payments.invoices.datatable.columns.invoice_number')) },
                        { data: 'status', title: @json(__('web/payments.invoices.datatable.columns.status')) },
                        {
                            data: 'customer_gross_total',
                            title: @json(__('web/payments.invoices.datatable.columns.total')),
                            render: function (value, type, row) {
                                const currency = row.currency || '';
                                return value != null ? currency + ' ' + value : '—';
                            },
                        },
                        { data: 'created_at', title: @json(__('web/payments.invoices.datatable.columns.created_at')) },
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
