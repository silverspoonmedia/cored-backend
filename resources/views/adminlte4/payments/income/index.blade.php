@php
    $overview = is_array($overview ?? null) ? $overview : [];
    $summary = data_get($overview, 'summary', []);
    $pipeline = data_get($overview, 'pipeline', []);
    $filters = is_array($filters ?? null) ? $filters : [];
    $invoiceItems = data_get($invoices, 'items', []);
    $exportQuery = http_build_query(array_filter([
        'paid_from' => $filters['from'] ?? null,
        'paid_to' => $filters['to'] ?? null,
        'status' => 'paid',
    ]));
@endphp

<x-adminlte4::layouts.app :title="__('web/payments.income.title')">
    @vite('resources/css/payments/order-invoice-show.css')

    <div
        class="pg-income"
        data-payment-income-page
        data-overview-url="{{ route('payments.income.overview') }}"
        data-invoices-url="{{ route('payments.income.invoices') }}"
        data-export-url="{{ route('invoices.export') }}{{ $exportQuery !== '' ? '?' . $exportQuery : '' }}"
        data-messages="{{ json_encode([
            'loading' => __('web/payments.income.loading'),
            'loadFailed' => __('web/payments.income.load_failed'),
            'emptyInvoices' => __('web/payments.income.empty_invoices'),
            'emptyBreakdown' => __('web/payments.income.empty_breakdown'),
        ]) }}"
    >
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
            <div>
                <h1 class="h3 mb-1">{{ __('web/payments.income.title') }}</h1>
                <p class="text-muted mb-0">{{ __('web/payments.income.subtitle') }}</p>
            </div>
            <a href="{{ route('invoices.export') }}{{ $exportQuery !== '' ? '?' . $exportQuery : '' }}" class="btn btn-outline-secondary btn-sm" data-payment-income-export>
                <i class="bi bi-download me-1"></i>{{ __('web/payments.invoices.export_csv') }}
            </a>
        </div>

        @if ($loadError)
            <div class="alert alert-danger">{{ $loadError }}</div>
        @endif

        <form class="card mb-4" method="get" action="{{ route('payments.income.index') }}" data-payment-income-filters>
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label" for="income-from">{{ __('web/payments.income.filters.from') }}</label>
                        <input type="date" class="form-control" id="income-from" name="from" value="{{ $filters['from'] ?? '' }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="income-to">{{ __('web/payments.income.filters.to') }}</label>
                        <input type="date" class="form-control" id="income-to" name="to" value="{{ $filters['to'] ?? '' }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="income-basis">{{ __('web/payments.income.filters.basis') }}</label>
                        <select class="form-select" id="income-basis" name="basis">
                            <option value="paid_at" @selected(($filters['basis'] ?? 'paid_at') === 'paid_at')>{{ __('web/payments.income.filters.basis_paid_at') }}</option>
                            <option value="withdrawable_at" @selected(($filters['basis'] ?? '') === 'withdrawable_at')>{{ __('web/payments.income.filters.basis_withdrawable_at') }}</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="income-gateway">{{ __('web/payments.income.filters.gateway') }}</label>
                        <select class="form-select" id="income-gateway" name="gateway">
                            <option value="">{{ __('web/payments.income.filters.all_gateways') }}</option>
                            <option value="midtrans" @selected(($filters['gateway'] ?? '') === 'midtrans')">Midtrans</option>
                            <option value="ipaymu" @selected(($filters['gateway'] ?? '') === 'ipaymu')">iPaymu</option>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <button type="submit" class="btn btn-primary w-100">{{ __('web/payments.income.filters.apply') }}</button>
                    </div>
                </div>
            </div>
        </form>

        <div class="row g-3 mb-4" data-payment-income-summary>
            @foreach ([
                ['key' => 'service_revenue', 'label' => __('web/payments.income.summary.service_revenue'), 'hint' => __('web/payments.income.summary.service_revenue_hint')],
                ['key' => 'buyer_fees', 'label' => __('web/payments.income.summary.buyer_fees'), 'hint' => __('web/payments.income.summary.buyer_fees_hint')],
                ['key' => 'cash_collected', 'label' => __('web/payments.income.summary.cash_collected'), 'hint' => __('web/payments.income.summary.cash_collected_hint')],
                ['key' => 'invoice_count', 'label' => __('web/payments.income.summary.invoice_count'), 'hint' => null, 'raw' => true],
                ['key' => 'avg_order_value', 'label' => __('web/payments.income.summary.avg_order_value'), 'hint' => null],
            ] as $card)
                <div class="col-md-4 col-lg">
                    <div class="ois-sidebar-card h-100">
                        <div class="text-muted small">{{ $card['label'] }}</div>
                        @if (! empty($card['hint']))
                            <div class="text-muted small mb-1">{{ $card['hint'] }}</div>
                        @endif
                        <div class="fs-5 fw-semibold" data-payment-income-summary-{{ $card['key'] }}>
                            @if (! empty($card['raw']))
                                {{ data_get($summary, $card['key'], 0) }}
                            @else
                                {{ data_get($summary, $card['key'] . '_formatted', '—') }}
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <h2 class="h5 mb-3">{{ __('web/payments.income.pipeline.title') }}</h2>
        <div class="row g-3 mb-4" data-payment-income-pipeline>
            @foreach ([
                'awaiting_settlement' => __('web/payments.income.pipeline.awaiting_settlement'),
                'ready_to_disburse' => __('web/payments.income.pipeline.ready_to_disburse'),
                'in_payout' => __('web/payments.income.pipeline.in_payout'),
                'disbursed' => __('web/payments.income.pipeline.disbursed'),
                'failed_payout' => __('web/payments.income.pipeline.failed_payout'),
            ] as $bucket => $label)
                @php $bucketData = data_get($pipeline, $bucket, []); @endphp
                <div class="col-md-4 col-lg">
                    <div class="ois-sidebar-card h-100">
                        <div class="text-muted small">{{ $label }}</div>
                        <div class="fw-semibold" data-payment-income-pipeline-{{ $bucket }}-count>{{ data_get($bucketData, 'invoice_count', 0) }} {{ __('web/payments.income.pipeline.invoices') }}</div>
                        <div class="small text-body" data-payment-income-pipeline-{{ $bucket }}-amount>{{ data_get($bucketData, 'service_revenue_formatted', '—') }}</div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="row g-3 mb-4">
            <div class="col-lg-4">
                <x-adminlte4::card :title="__('web/payments.income.breakdown.gateway')">
                    <div class="table-responsive" data-payment-income-by-gateway>
                        @include(config('web.layout').'.payments.income.partials.breakdown-table', ['rows' => data_get($overview, 'by_gateway', [])])
                    </div>
                </x-adminlte4::card>
            </div>
            <div class="col-lg-4">
                <x-adminlte4::card :title="__('web/payments.income.breakdown.channel')">
                    <div class="table-responsive" data-payment-income-by-channel>
                        @include(config('web.layout').'.payments.income.partials.breakdown-table', ['rows' => data_get($overview, 'by_channel', [])])
                    </div>
                </x-adminlte4::card>
            </div>
            <div class="col-lg-4">
                <x-adminlte4::card :title="__('web/payments.income.breakdown.product')">
                    <div class="table-responsive" data-payment-income-by-product>
                        @include(config('web.layout').'.payments.income.partials.breakdown-table', ['rows' => data_get($overview, 'by_invoiceable_type', [])])
                    </div>
                </x-adminlte4::card>
            </div>
        </div>

        <x-adminlte4::card :title="__('web/payments.income.daily.title')" class="mb-4">
            <div class="table-responsive" data-payment-income-daily>
                <table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('web/payments.income.daily.date') }}</th>
                            <th class="text-end">{{ __('web/payments.income.daily.invoices') }}</th>
                            <th class="text-end">{{ __('web/payments.income.daily.service_revenue') }}</th>
                            <th class="text-end">{{ __('web/payments.income.daily.cash_collected') }}</th>
                        </tr>
                    </thead>
                    <tbody data-payment-income-daily-body>
                        @forelse (data_get($overview, 'daily', []) as $day)
                            <tr>
                                <td>{{ $day['day'] ?? '—' }}</td>
                                <td class="text-end">{{ $day['invoice_count'] ?? 0 }}</td>
                                <td class="text-end">{{ $day['service_revenue_formatted'] ?? '—' }}</td>
                                <td class="text-end">{{ $day['cash_collected_formatted'] ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-muted">{{ __('web/payments.income.empty_breakdown') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-adminlte4::card>

        <x-adminlte4::card :title="__('web/payments.income.invoices_title')">
            <div class="table-responsive" data-payment-income-invoices>
                @include(config('web.layout').'.payments.income.partials.invoices-table', ['items' => $invoiceItems])
            </div>
            <div class="d-flex justify-content-end mt-3" data-payment-income-pagination>
                @php $pagination = data_get($invoices, 'pagination', []); @endphp
                @if ((int) data_get($pagination, 'last_page', 1) > 1)
                    <span class="text-muted small">
                        {{ __('web/payments.income.pagination', [
                            'page' => data_get($pagination, 'current_page', 1),
                            'total' => data_get($pagination, 'last_page', 1),
                        ]) }}
                    </span>
                @endif
            </div>
        </x-adminlte4::card>
    </div>

    <x-slot:scripts>
        @vite('resources/js/payments/income.js')
    </x-slot:scripts>
</x-adminlte4::layouts.app>
