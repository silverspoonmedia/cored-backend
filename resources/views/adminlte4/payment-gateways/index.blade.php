<x-adminlte4::layouts.app :title="__('web/payments.gateways.title')">
    <x-adminlte4::card :title="__('web/payments.gateways.list_title')">
        @can('manage payment gateway')
            <x-slot:actions>
                <a href="{{ route('payment-gateways.create') }}" class="btn btn-primary btn-sm">
                    {{ __('web/payments.gateways.create') }}
                </a>
            </x-slot:actions>
        @endcan
        <div class="table-responsive">
            <table id="payment-gateways-table" class="table table-hover align-middle mb-0 w-100" aria-label="{{ __('web/payments.gateways.list_title') }}"></table>
        </div>
    </x-adminlte4::card>

    <x-slot:scripts>
        <x-adminlte4::partials.datatable-assets />

        <script>
            $(function () {
                $('#payment-gateways-table').DataTable({
                    processing: true,
                    serverSide: true,
                    ajax: {
                        url: '{{ route('payment-gateways.datatable') }}',
                        error: function (xhr) {
                            window.MonolithApi.datatableAjaxError(xhr);
                        },
                    },
                    order: [[3, 'asc']],
                    language: {
                        emptyTable: @json(__('web/payments.gateways.datatable.empty')),
                        zeroRecords: @json(__('web/payments.gateways.datatable.zero_records')),
                    },
                    columns: [
                        { data: 'name', title: @json(__('web/payments.gateways.datatable.columns.name')) },
                        { data: 'display_name', title: @json(__('web/payments.gateways.datatable.columns.display_name')) },
                        {
                            data: 'is_active',
                            title: @json(__('web/payments.gateways.datatable.columns.is_active')),
                            render: function (value) {
                                return value
                                    ? '<span class="badge text-bg-success">Yes</span>'
                                    : '<span class="badge text-bg-secondary">No</span>';
                            },
                        },
                        { data: 'priority', title: @json(__('web/payments.gateways.datatable.columns.priority')) },
                    ],
                });
            });
        </script>
    </x-slot:scripts>
</x-adminlte4::layouts.app>
