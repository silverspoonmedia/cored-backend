@props([
    'invoice' => null,
    'orderId' => null,
    'subscriptionId' => null,
])

@php
    $invoice = is_array($invoice) ? $invoice : [];
    $status = (string) data_get($invoice, 'status', '');
    $isPaid = $status === 'paid';
    $isPayable = in_array($status, ['pending', 'challenge'], true);
    $gatewayName = data_get($invoice, 'payment_gateway.display_name', data_get($invoice, 'payment_gateway.name'));
    $currency = data_get($invoice, 'currency', 'IDR');
    $amount = number_format((float) data_get($invoice, 'customer_gross_total', 0), 2);
    $ipaymuReturnParams = array_filter([
        'invoice_id' => data_get($invoice, 'id'),
        'redirect' => $orderId ? 'order' : ($subscriptionId ? 'subscription' : null),
        'order' => $orderId,
        'subscription' => $subscriptionId,
    ]);
    $ipaymuReturnUrl = $ipaymuReturnParams !== []
        ? route('payment.ipaymu.return', $ipaymuReturnParams)
        : route('payment.ipaymu.return');
    $ipaymuCancelUrl = $ipaymuReturnParams !== []
        ? route('payment.ipaymu.cancel', $ipaymuReturnParams)
        : route('payment.ipaymu.cancel');
@endphp

@if (! empty($invoice))
    <x-adminlte4::card :title="__('web/payments.checkout.title')">
        <div
            data-payment-checkout
            data-snap-token-url="{{ route('invoices.checkout.snap-token', data_get($invoice, 'id')) }}"
            data-return-url="{{ $ipaymuReturnUrl }}"
            data-cancel-url="{{ $ipaymuCancelUrl }}"
            data-messages="{{ json_encode([
                'loading' => __('web/payments.checkout.snap_loading'),
                'failed' => __('web/payments.checkout.snap_failed'),
            ]) }}"
        >
            <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                <span class="fw-semibold">
                    {{ __('web/payments.checkout.invoice_summary', ['number' => data_get($invoice, 'invoice_number', '—')]) }}
                </span>
                <span class="badge text-bg-light border">{{ $status ?: '—' }}</span>
            </div>

            <dl class="row mb-3">
                <dt class="col-sm-4">{{ __('web/payments.checkout.amount') }}</dt>
                <dd class="col-sm-8">{{ $currency }} {{ $amount }}</dd>

                @if ($gatewayName)
                    <dt class="col-sm-4">{{ __('web/payments.invoices.fields.gateway') }}</dt>
                    <dd class="col-sm-8">{{ $gatewayName }}</dd>
                @endif
            </dl>

            @if ($isPaid)
                <p class="text-success mb-0">{{ __('web/payments.checkout.paid') }}</p>
            @elseif (! $isPayable)
                <p class="text-muted mb-0">{{ __('web/payments.checkout.not_payable') }}</p>
            @else
                @can('initiate payment')
                    <button type="button" class="btn btn-primary" data-payment-checkout-pay>
                        {{ __('web/payments.checkout.pay_now') }}
                    </button>
                @endcan
            @endif
        </div>
    </x-adminlte4::card>

    @vite('resources/js/payments/checkout.js')
@endif
