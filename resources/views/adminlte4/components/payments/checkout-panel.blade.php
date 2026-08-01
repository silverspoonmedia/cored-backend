@props([
    'invoice' => null,
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
    $formattedSubtotal = data_get($invoice, 'subtotal_formatted');
    $formattedFee = data_get($invoice, 'payment_channel_fee_formatted');
    $formattedTotal = data_get($invoice, 'customer_gross_total_formatted');
    $invoiceSubtotal = data_get($invoice, 'subtotal', 0);
    $invoiceFee = data_get($invoice, 'payment_channel_fee', 0);
    $invoiceGross = data_get($invoice, 'customer_gross_total', 0);
    $invoiceCurrency = data_get($invoice, 'currency', 'IDR');
    $useChannelCheckout = $checkoutMode === 'channel' && is_array($paymentChannels) && $paymentChannels !== [];
    $initialCheckout = data_get($invoice, 'checkout_session');
    if (is_array($initialCheckout) && $initialCheckout === []) {
        $initialCheckout = null;
    }
    $hasActiveCheckout = is_array($initialCheckout) && $initialCheckout !== null;
    $invoiceFeeBasis = data_get($invoice, 'payment_channel_fee_basis');

    $channelKindIcons = [
        'qris' => 'bi-qr-code',
        'va' => 'bi-bank',
        'ewallet' => 'bi-wallet2',
    ];
    $defaultChannelCode = $useChannelCheckout && $hasActiveCheckout
        ? (string) data_get($initialCheckout, 'channel_code', '')
        : '';
    $canInitiatePayment = auth()->check() && auth()->user()->can('initiate payment');
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

    @once
        <script>
            window.monolithQrBaseUrl = @json(route('qr.show'));
        </script>
    @endonce
    @vite('resources/js/payments/checkout.js')
@endif
