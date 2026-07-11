<x-adminlte4::layouts.app :title="__('web/payments.invoices.show_title')">
    <x-adminlte4::card :title="__('web/payments.invoices.show_card_title', ['number' => $invoice['invoice_number'] ?? $invoice['id'] ?? ''])">
        <dl class="row mb-0">
            <dt class="col-sm-3">{{ __('web/payments.invoices.fields.invoice_number') }}</dt>
            <dd class="col-sm-9">{{ $invoice['invoice_number'] ?? '—' }}</dd>

            <dt class="col-sm-3">{{ __('web/payments.invoices.fields.status') }}</dt>
            <dd class="col-sm-9"><span class="badge text-bg-light border">{{ $invoice['status'] ?? '—' }}</span></dd>

            <dt class="col-sm-3">{{ __('web/payments.invoices.fields.gateway') }}</dt>
            <dd class="col-sm-9">{{ data_get($invoice, 'payment_gateway.display_name', data_get($invoice, 'payment_gateway.name', '—')) }}</dd>

            <dt class="col-sm-3">{{ __('web/payments.invoices.fields.payment_method') }}</dt>
            <dd class="col-sm-9">{{ $invoice['payment_method'] ?? '—' }}</dd>

            <dt class="col-sm-3">{{ __('web/payments.invoices.fields.subtotal') }}</dt>
            <dd class="col-sm-9">{{ ($invoice['currency'] ?? '') }} {{ $invoice['subtotal'] ?? '—' }}</dd>

            <dt class="col-sm-3">{{ __('web/payments.invoices.fields.fee') }}</dt>
            <dd class="col-sm-9">{{ ($invoice['currency'] ?? '') }} {{ $invoice['payment_channel_fee'] ?? '—' }}</dd>

            <dt class="col-sm-3">{{ __('web/payments.invoices.fields.total') }}</dt>
            <dd class="col-sm-9">{{ ($invoice['currency'] ?? '') }} {{ $invoice['customer_gross_total'] ?? '—' }}</dd>

            <dt class="col-sm-3">{{ __('web/payments.invoices.fields.paid_at') }}</dt>
            <dd class="col-sm-9">{{ $invoice['paid_at'] ?? '—' }}</dd>

            <dt class="col-sm-3">{{ __('web/payments.invoices.fields.expired_at') }}</dt>
            <dd class="col-sm-9">{{ $invoice['expired_at'] ?? '—' }}</dd>

            <dt class="col-sm-3">{{ __('web/payments.invoices.fields.created_at') }}</dt>
            <dd class="col-sm-9">{{ $invoice['created_at'] ?? '—' }}</dd>
        </dl>

        <x-slot:actions>
            <a href="{{ route('invoices.index') }}" class="btn btn-outline-secondary btn-sm">
                {{ __('web/payments.invoices.back_to_list') }}
            </a>
        </x-slot:actions>
    </x-adminlte4::card>

    <x-adminlte4::payments.checkout-panel
        :invoice="$invoice"
        :payment-channels="$paymentChannels ?? []"
        :checkout-mode="$checkoutMode ?? 'snap_fallback'"
    />
</x-adminlte4::layouts.app>
