<div
    data-payment-checkout
    data-checkout-mode="{{ $useChannelCheckout ? 'channel' : 'snap_fallback' }}"
    data-channel-checkout-url="{{ route('invoices.checkout.channel', data_get($invoice, 'id')) }}"
    data-sync-status-url="{{ route('invoices.checkout.sync-status', data_get($invoice, 'id')) }}"
    data-channels="{{ json_encode(array_values($paymentChannels)) }}"
    data-initial-checkout='@json($initialCheckout)'
    data-messages="{{ json_encode([
        'channelLoading' => __('web/payments.checkout.channel_loading'),
        'channelFailed' => __('web/payments.checkout.channel_failed'),
        'channelReady' => __('web/payments.checkout.channel_ready'),
        'synced' => __('web/payments.checkout.synced'),
        'syncPending' => __('web/payments.checkout.sync_pending'),
        'syncFailed' => __('web/payments.checkout.sync_failed'),
        'selectChannel' => __('web/payments.checkout.select_channel'),
        'payWithChannel' => __('web/payments.checkout.pay_with_channel'),
        'confirmPaid' => __('web/payments.checkout.confirm_paid'),
        'changeChannel' => __('web/payments.checkout.change_channel'),
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
    @unless ($hubMode)
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <span class="fw-semibold">
                {{ __('web/payments.checkout.invoice_summary', ['number' => data_get($invoice, 'invoice_number', '—')]) }}
            </span>
            <span class="badge text-bg-light border">{{ $status ?: '—' }}</span>
        </div>
    @endunless

    <div class="ois-amount-callout">
        <p class="ois-amount-callout__label mb-0">{{ __('web/payments.checkout.amount') }}</p>
        <p class="ois-amount-callout__value mb-0">{{ $currency }} {{ $amount }}</p>
    </div>

    @if ($showGateway && $gatewayName && ! $hubMode)
        <p class="text-muted small mb-3">
            {{ __('web/payments.invoices.fields.gateway') }}:
            <span class="fw-semibold text-body">{{ $gatewayName }}</span>
        </p>
    @endif

    @if ($isPaid)
        <p class="text-success mb-0">{{ __('web/payments.checkout.paid') }}</p>
    @elseif (! $isPayable)
        <p class="text-muted mb-0">{{ __('web/payments.checkout.not_payable') }}</p>
    @elseif ($canInitiatePayment)
        @if ($useChannelCheckout)
            <label class="form-label" data-payment-channel-label>{{ __('web/payments.checkout.select_channel') }}</label>
            <div class="pc-checkout-channels mb-2" data-payment-channel-picker data-payment-channel-list>
                @foreach ($paymentChannels as $channel)
                    @php
                        $kind = (string) data_get($channel, 'kind', 'qris');
                        $icon = $channelKindIcons[$kind] ?? 'bi-credit-card';
                        $code = (string) data_get($channel, 'code', '');
                        $isDefaultChannel = $code !== '' && $code === $defaultChannelCode;
                    @endphp
                    <button
                        type="button"
                        class="pc-checkout-channel pc-kind-{{ $kind }}{{ $isDefaultChannel ? ' is-selected' : '' }}"
                        data-payment-channel-option="{{ $code }}"
                    >
                        <span class="pc-checkout-channel__icon"><i class="bi {{ $icon }}"></i></span>
                        <span class="pc-checkout-channel__label">{{ data_get($channel, 'display_name', data_get($channel, 'code')) }}</span>
                    </button>
                @endforeach
            </div>

            <button
                type="button"
                class="btn btn-link btn-sm px-0 mb-3 d-none"
                data-payment-change-channel
            >
                <i class="bi bi-arrow-left-right me-1"></i>{{ __('web/payments.checkout.change_channel') }}
            </button>

            <div class="d-flex flex-wrap gap-2 mb-3">
                <button
                    type="button"
                    class="btn btn-primary"
                    data-payment-channel-checkout
                    @disabled($defaultChannelCode === '')
                >
                    {{ __('web/payments.checkout.pay_with_channel') }}
                </button>
                <button type="button" class="btn btn-outline-secondary d-none" data-payment-sync-status
                        title="{{ __('web/payments.checkout.confirm_paid_help') }}">
                    {{ __('web/payments.checkout.confirm_paid') }}
                </button>
                <small class="text-muted d-none" data-payment-sync-help>{{ __('web/payments.checkout.confirm_paid_help') }}</small>
            </div>

            <div class="d-none pc-checkout-instructions mb-0" data-payment-instructions></div>
        @else
            <p class="text-muted mb-0">{{ __('web/payments.checkout.no_channels') }}</p>
        @endif
    @else
        <p class="text-muted mb-0">{{ __('web/payments.checkout.permission_required') }}</p>
    @endif
</div>
