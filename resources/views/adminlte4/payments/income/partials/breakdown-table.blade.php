<table class="table table-sm table-hover mb-0">
    <thead>
        <tr>
            <th>{{ __('web/payments.income.breakdown.label') }}</th>
            <th class="text-end">{{ __('web/payments.income.breakdown.invoices') }}</th>
            <th class="text-end">{{ __('web/payments.income.breakdown.service_revenue') }}</th>
            <th class="text-end">{{ __('web/payments.income.breakdown.cash_collected') }}</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $row)
            <tr>
                <td><code>{{ $row['label'] ?? '—' }}</code></td>
                <td class="text-end">{{ $row['invoice_count'] ?? 0 }}</td>
                <td class="text-end">{{ $row['service_revenue_formatted'] ?? '—' }}</td>
                <td class="text-end">{{ $row['cash_collected_formatted'] ?? '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="4" class="text-muted">{{ __('web/payments.income.empty_breakdown') }}</td></tr>
        @endforelse
    </tbody>
</table>
