@props([
    'invoice' => null,
    'orderId' => null,
    'subscriptionId' => null,
    'paymentChannels' => [],
    'checkoutMode' => 'snap_fallback',
    'hubMode' => false,
])

@php
    $invoice = is_array($invoice) ? $invoice : [];
    $status = (string) data_get($invoice, 'status', '');
    $isPaid = $status === 'paid';
    $isPayable = in_array($status, ['pending', 'challenge'], true);
    $gatewayName = data_get($invoice, 'payment_gateway.display_name', data_get($invoice, 'payment_gateway.name'));
    $showGateway = data_get($invoice, 'payment_gateway.id') || data_get($invoice, 'payment_channel_code');
    // Prefer Resource companion (includes currency). Raw fallback keeps code + number.
    $formattedTotal = data_get($invoice, 'customer_gross_total_formatted');
    if (is_string($formattedTotal) && $formattedTotal !== '') {
        $currency = '';
        $amount = $formattedTotal;
    } else {
        $currency = data_get($invoice, 'currency', 'IDR');
        $amount = data_get($invoice, 'customer_gross_total', '0');
    }
    $useChannelCheckout = $checkoutMode === 'channel' && is_array($paymentChannels) && $paymentChannels !== [];

    $channelKindIcons = [
        'qris' => 'bi-qr-code',
        'va' => 'bi-bank',
        'ewallet' => 'bi-wallet2',
    ];
    $defaultChannelCode = $useChannelCheckout
        ? (string) data_get($paymentChannels[0] ?? null, 'code', '')
        : '';
    $canInitiatePayment = auth()->check() && auth()->user()->can('initiate payment');
    $initialCheckout = data_get($invoice, 'checkout_session');
    if (is_array($initialCheckout) && $initialCheckout === []) {
        $initialCheckout = null;
    }
@endphp

@if (! empty($invoice))
    @if ($hubMode)
        <div class="ois-payment-card">
            <div class="ois-payment-card__head">
                <h2 class="h5 mb-0">{{ __('web/payments.checkout.title') }}</h2>
            </div>
            <div class="ois-payment-card__body">
                @include('adminlte4.components.payments.checkout-panel-body')
            </div>
        </div>
    @else
        <x-adminlte4::card :title="__('web/payments.checkout.title')">
            @include('adminlte4.components.payments.checkout-panel-body')
        </x-adminlte4::card>
    @endif

    @vite('resources/js/payments/checkout.js')
@endif
