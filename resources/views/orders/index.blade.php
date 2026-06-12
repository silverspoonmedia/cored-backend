<x-layouts.app :title="__('web/orders.title')">
    <x-card :title="__('web/orders.list_title')">
        <x-slot:actions>
            <a href="{{ route('orders.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg me-1"></i> {{ __('web/orders.add_button') }}
            </a>
        </x-slot:actions>

        <div class="table-responsive">
            <table id="orders-table" class="table table-hover align-middle mb-0 w-100"></table>
        </div>
    </x-card>

    @php
        $statusLabels = [
            'pending' => __('web/orders.status.pending'),
            'processing' => __('web/orders.status.processing'),
            'completed' => __('web/orders.status.completed'),
            'cancelled' => __('web/orders.status.cancelled'),
        ];
    @endphp

    <x-slot:scripts>
        <x-partials.datatable-assets />
        <x-partials.datatable-row-actions />

        <script>
            $(function () {
                const statusLabels = @json($statusLabels);
                const statusStyles = {
                    pending: 'secondary',
                    processing: 'info',
                    completed: 'success',
                    cancelled: 'danger',
                };

                $('#orders-table').DataTable({
                    processing: true,
                    serverSide: true,
                    ajax: '{{ route('orders.datatable') }}',
                    order: [[0, 'asc']],
                    language: {
                        emptyTable: @json(__('web/orders.datatable.empty')),
                        zeroRecords: @json(__('web/orders.datatable.zero_records')),
                    },
                    columns: [
                        { data: 'order_number', title: @json(__('web/orders.datatable.columns.order_number')) },
                        { data: 'customer_name', title: @json(__('web/orders.datatable.columns.customer_name')) },
                        {
                            data: 'status',
                            title: @json(__('web/orders.datatable.columns.status')),
                            render: function (status) {
                                const style = statusStyles[status] || 'secondary';
                                const label = statusLabels[status] || status;
                                return '<span class="badge text-bg-' + style + '">' + label + '</span>';
                            },
                        },
                        {
                            data: 'details_count',
                            title: @json(__('web/orders.datatable.columns.details_count')),
                            className: 'text-center',
                            orderable: false,
                        },
                        {
                            data: 'grand_total',
                            title: @json(__('web/orders.datatable.columns.grand_total')),
                            className: 'text-end',
                            render: function (value) {
                                return Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                            },
                        },
                        {
                            data: 'actions',
                            title: @json(__('web/orders.datatable.columns.actions')),
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
