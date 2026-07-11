@props([
    'invoice' => null,
    'orderId' => null,
    'subscriptionId' => null,
    'paymentChannels' => [],
    'checkoutMode' => 'snap_fallback',
])

@php
    $invoice = is_array($invoice) ? $invoice : [];
    $status = (string) data_get($invoice, 'status', '');
    $isPaid = $status === 'paid';
    $isPayable = in_array($status, ['pending', 'challenge'], true);
    $gatewayName = data_get($invoice, 'payment_gateway.display_name', data_get($invoice, 'payment_gateway.name'));
    $currency = data_get($invoice, 'currency', 'IDR');
    $amount = number_format((float) data_get($invoice, 'customer_gross_total', 0), 2);
    $useChannelCheckout = $checkoutMode === 'channel' && is_array($paymentChannels) && $paymentChannels !== [];
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
            data-checkout-mode="{{ $useChannelCheckout ? 'channel' : 'snap_fallback' }}"
            data-snap-token-url="{{ route('invoices.checkout.snap-token', data_get($invoice, 'id')) }}"
            data-channel-checkout-url="{{ route('invoices.checkout.channel', data_get($invoice, 'id')) }}"
            data-sync-status-url="{{ route('invoices.checkout.sync-status', data_get($invoice, 'id')) }}"
            data-return-url="{{ $ipaymuReturnUrl }}"
            data-cancel-url="{{ $ipaymuCancelUrl }}"
            data-channels="{{ json_encode(array_values($paymentChannels)) }}"
            data-messages="{{ json_encode([
                'loading' => __('web/payments.checkout.snap_loading'),
                'channelLoading' => __('web/payments.checkout.channel_loading'),
                'failed' => __('web/payments.checkout.snap_failed'),
                'channelFailed' => __('web/payments.checkout.channel_failed'),
                'channelReady' => __('web/payments.checkout.channel_ready'),
                'synced' => __('web/payments.checkout.synced'),
                'syncPending' => __('web/payments.checkout.sync_pending'),
                'syncFailed' => __('web/payments.checkout.sync_failed'),
                'selectChannel' => __('web/payments.checkout.select_channel'),
                'payWithChannel' => __('web/payments.checkout.pay_with_channel'),
                'confirmPaid' => __('web/payments.checkout.confirm_paid'),
                'snapFallbackHint' => __('web/payments.checkout.snap_fallback_hint'),
                'instructionsTitle' => __('web/payments.instructions.title'),
                'expiresAt' => __('web/payments.instructions.expires_at'),
                'qrString' => __('web/payments.instructions.qr_string'),
                'bank' => __('web/payments.instructions.bank'),
                'vaNumber' => __('web/payments.instructions.va_number'),
                'accountName' => __('web/payments.instructions.account_name'),
                'openEwallet' => __('web/payments.instructions.open_ewallet'),
                'copy' => __('web/payments.instructions.copy'),
                'copied' => __('web/payments.instructions.copied'),
                'copyFailed' => __('web/payments.instructions.copy_failed'),
                'pendingHint' => __('web/payments.instructions.pending_hint'),
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
                    @if ($useChannelCheckout)
                        <label class="form-label">{{ __('web/payments.checkout.select_channel') }}</label>
                        <div class="d-flex flex-wrap gap-2 mb-3" data-payment-channel-picker>
                            @foreach ($paymentChannels as $channel)
                                <button
                                    type="button"
                                    class="btn btn-outline-primary"
                                    data-payment-channel-option="{{ data_get($channel, 'code') }}"
                                >
                                    {{ data_get($channel, 'display_name', data_get($channel, 'code')) }}
                                </button>
                            @endforeach
                        </div>

                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <button type="button" class="btn btn-primary" data-payment-channel-checkout disabled>
                                {{ __('web/payments.checkout.pay_with_channel') }}
                            </button>
                            <button type="button" class="btn btn-outline-secondary d-none" data-payment-sync-status>
                                {{ __('web/payments.checkout.confirm_paid') }}
                            </button>
                        </div>

                        <div class="d-none border rounded p-3 bg-light mb-0" data-payment-instructions></div>

                        <div class="d-none mt-3" data-payment-snap-fallback>
                            <p class="text-muted small">{{ __('web/payments.checkout.snap_fallback_hint') }}</p>
                            <button type="button" class="btn btn-primary" data-payment-checkout-pay>
                                {{ __('web/payments.checkout.pay_now') }}
                            </button>
                        </div>
                    @else
                        <p class="text-muted small">{{ __('web/payments.checkout.snap_fallback_hint') }}</p>
                        <button type="button" class="btn btn-primary" data-payment-checkout-pay>
                            {{ __('web/payments.checkout.pay_now') }}
                        </button>
                    @endif
                @endcan
            @endif
        </div>
    </x-adminlte4::card>

    @vite('resources/js/payments/checkout.js')
@endif
