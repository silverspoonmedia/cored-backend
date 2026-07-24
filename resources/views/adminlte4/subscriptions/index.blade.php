<x-adminlte4::layouts.app :title="__('web/payments.subscriptions.title')">
    <x-adminlte4::card :title="__('web/payments.subscriptions.list_title')">
        <x-slot:actions>
            <a href="{{ route('subscriptions.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg me-1"></i> {{ __('web/payments.subscriptions.add_button') }}
            </a>
        </x-slot:actions>

        <div class="table-responsive">
            <table id="subscriptions-table" class="table table-hover align-middle mb-0 w-100"></table>
        </div>
    </x-adminlte4::card>

    <x-slot:scripts>
        <x-adminlte4::partials.datatable-assets />
        <x-adminlte4::partials.datatable-row-actions />

        <script>
            $(function () {
                const table = $('#subscriptions-table').DataTable({
                    processing: true,
                    serverSide: true,
                    ajax: {
                        url: '{{ route('subscriptions.datatable') }}',
                        error: function (xhr) {
                            window.MonolithApi.datatableAjaxError(xhr);
                        },
                    },
                    order: [[0, 'desc']],
                    language: {
                        emptyTable: @json(__('web/payments.subscriptions.datatable.empty')),
                        zeroRecords: @json(__('web/payments.subscriptions.datatable.zero_records')),
                    },
                    columns: [
                        { data: 'subscription_number', title: @json(__('web/payments.subscriptions.datatable.columns.subscription_number')) },
                        { data: 'subscriber_name', title: @json(__('web/payments.subscriptions.datatable.columns.subscriber_name')) },
                        { data: 'plan_name', title: @json(__('web/payments.subscriptions.datatable.columns.plan_name')) },
                        { data: 'status', title: @json(__('web/payments.subscriptions.datatable.columns.status')) },
                        {
                            data: 'amount_formatted',
                            title: @json(__('web/payments.subscriptions.datatable.columns.amount')),
                            orderable: false,
                            render: function (value) {
                                return value != null && value !== '' ? value : '—';
                            },
                        },
                        {
                            data: 'actions',
                            title: @json(__('web/payments.subscriptions.datatable.columns.actions')),
                            orderable: false,
                            searchable: false,
                            className: 'text-end',
                            render: function (actions) {
                                return window.renderDatatableActions(actions);
                            },
                        },
                    ],
                });

                window.dataTables = window.dataTables || {};
                window.dataTables['subscriptions-table'] = table;
            });
        </script>
    </x-slot:scripts>
</x-adminlte4::layouts.app>
