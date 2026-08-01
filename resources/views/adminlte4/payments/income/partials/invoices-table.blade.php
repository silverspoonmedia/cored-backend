<table class="table table-sm table-hover mb-0">
    <thead>
        <tr>
            <th>{{ __('web/payments.invoices.fields.invoice_number') }}</th>
            <th>{{ __('web/payments.invoices.fields.status') }}</th>
            <th>{{ __('web/payments.invoices.fields.gateway') }}</th>
            <th class="text-end">{{ __('web/payments.invoices.fields.subtotal') }}</th>
            <th class="text-end">{{ __('web/payments.invoices.fields.fee') }}</th>
            <th class="text-end">{{ __('web/payments.invoices.fields.total') }}</th>
            <th>{{ __('web/payments.invoices.fields.paid_at') }}</th>
            <th></th>
        </tr>
    </thead>
    <tbody data-payment-income-invoices-body>
        @forelse ($items as $invoice)
            <tr>
                <td><code>{{ $invoice['invoice_number'] ?? '—' }}</code></td>
                <td>{{ $invoice['status'] ?? '—' }}</td>
                <td>{{ data_get($invoice, 'payment_gateway.display_name', data_get($invoice, 'payment_gateway.name', '—')) }}</td>
                <td class="text-end">{{ $invoice['subtotal_formatted'] ?? '—' }}</td>
                <td class="text-end">{{ $invoice['payment_channel_fee_formatted'] ?? '—' }}</td>
                <td class="text-end">{{ $invoice['customer_gross_total_formatted'] ?? '—' }}</td>
                <td>{{ $invoice['paid_at_formatted'] ?? $invoice['paid_at'] ?? '—' }}</td>
                <td class="text-end">
                    @if (! empty($invoice['show_url']))
                        <a href="{{ $invoice['show_url'] }}" class="btn btn-outline-info btn-sm">{{ __('ui.datatable.view') }}</a>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="8" class="text-muted">{{ __('web/payments.income.empty_invoices') }}</td></tr>
        @endforelse
    </tbody>
</table>
