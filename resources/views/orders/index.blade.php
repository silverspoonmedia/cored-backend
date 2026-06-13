<x-layouts.app :title="__('web/orders.title')">
    <x-card :title="__('web/orders.list_title')">
        <x-slot:actions>
            <button type="button" id="orders-bulk-delete" class="btn btn-danger btn-sm d-none">
                <i class="bi bi-trash me-1"></i>
                {{ __('web/orders.bulk.delete_button') }}
                <span class="badge text-bg-light ms-1" data-bulk-count>0</span>
            </button>
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

                const bulk = {
                    url: @json(route('orders.bulk-destroy')),
                    csrf: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
                    confirm: @json(__('web/orders.bulk.confirm')),
                    none: @json(__('web/orders.bulk.none_selected')),
                    failed: @json(__('ui.ajax.request_failed')),
                    unreachable: @json(__('ui.ajax.server_unreachable')),
                };

                // Selected ids survive paging/search (server-side mode only ever
                // renders the current page, so we track the set ourselves).
                const selected = new Set();

                const $table = $('#orders-table');
                const $bulkButton = $('#orders-bulk-delete');
                const $bulkCount = $bulkButton.find('[data-bulk-count]');

                function checkboxCell(row) {
                    // Completed orders are frozen (API rejects deleting them), so
                    // they are not selectable — render a disabled placeholder.
                    if (row.status === 'completed') {
                        return '<input type="checkbox" class="form-check-input" disabled title="' + statusLabels.completed + '">';
                    }

                    const checked = selected.has(row.id) ? ' checked' : '';

                    return '<input type="checkbox" class="form-check-input" data-row-select value="'
                        + row.id + '"' + checked + '>';
                }

                function refreshToolbar() {
                    const count = selected.size;
                    $bulkCount.text(count);
                    $bulkButton.toggleClass('d-none', count === 0);
                }

                function syncSelectAll() {
                    const boxes = $table.find('tbody [data-row-select]');
                    const allChecked = boxes.length > 0 && boxes.filter(':checked').length === boxes.length;
                    $table.find('thead [data-select-all]').prop('checked', allChecked);
                }

                const dt = $table.DataTable({
                    processing: true,
                    serverSide: true,
                    ajax: '{{ route('orders.datatable') }}',
                    order: [[1, 'asc']],
                    language: {
                        emptyTable: @json(__('web/orders.datatable.empty')),
                        zeroRecords: @json(__('web/orders.datatable.zero_records')),
                    },
                    columns: [
                        {
                            data: 'id',
                            title: '<input type="checkbox" class="form-check-input" data-select-all title="'
                                + @json(__('web/orders.bulk.select_all')) + '">',
                            orderable: false,
                            searchable: false,
                            className: 'text-center',
                            render: function (id, type, row) {
                                return checkboxCell(row);
                            },
                        },
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

                // Re-sync the header checkbox after every redraw (paging/search).
                dt.on('draw', syncSelectAll);

                // Per-row toggle.
                $table.on('change', '[data-row-select]', function () {
                    if (this.checked) {
                        selected.add(this.value);
                    } else {
                        selected.delete(this.value);
                    }
                    refreshToolbar();
                    syncSelectAll();
                });

                // Header select-all toggles every selectable row on the page.
                $table.on('change', '[data-select-all]', function () {
                    const check = this.checked;
                    $table.find('tbody [data-row-select]').each(function () {
                        this.checked = check;
                        if (check) {
                            selected.add(this.value);
                        } else {
                            selected.delete(this.value);
                        }
                    });
                    refreshToolbar();
                });

                $bulkButton.on('click', async function () {
                    const ids = Array.from(selected);

                    if (ids.length === 0) {
                        window.alert(bulk.none);
                        return;
                    }

                    if (!window.confirm(bulk.confirm.replace(':count', ids.length))) {
                        return;
                    }

                    const formData = new FormData();
                    formData.append('_token', bulk.csrf);
                    formData.append('_method', 'DELETE');
                    ids.forEach((id) => formData.append('ids[]', id));

                    try {
                        const response = await fetch(bulk.url, {
                            method: 'POST',
                            body: formData,
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': bulk.csrf,
                            },
                            credentials: 'same-origin',
                        });

                        let data = {};
                        try {
                            data = await response.json();
                        } catch (parseError) {
                            data = {};
                        }

                        if (response.ok) {
                            selected.clear();
                            refreshToolbar();
                            dt.ajax.reload(null, false);
                            return;
                        }

                        window.alert(data.message || bulk.failed);
                    } catch (error) {
                        window.alert(bulk.unreachable);
                        console.error('Bulk delete failed', error);
                    }
                });
            });
        </script>
    </x-slot:scripts>
</x-layouts.app>
