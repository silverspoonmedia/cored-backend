@php
    $paymentRealtime = app(\App\Domain\PaymentGateway\Support\PaymentRealtime::class);
    $broadcastConfig = $paymentRealtime->clientConfig();
@endphp
<div
    data-payment-checkout
    data-app-locale="{{ app()->getLocale() }}"
    data-checkout-mode="{{ $useChannelCheckout ? 'channel' : 'snap_fallback' }}"
    data-invoice-id="{{ data_get($invoice, 'id') }}"
    data-invoice-status="{{ $status }}"
    data-channel-checkout-url="{{ route('invoices.checkout.channel', data_get($invoice, 'id')) }}"
    data-channels-url="{{ route('invoices.checkout.channels', data_get($invoice, 'id')) }}"
    data-sync-status-url="{{ route('invoices.checkout.sync-status', data_get($invoice, 'id')) }}"
    data-invoice-subtotal="{{ $invoiceSubtotal }}"
    data-invoice-subtotal-formatted="{{ $formattedSubtotal }}"
    data-invoice-fee="{{ $invoiceFee }}"
    data-invoice-gross="{{ $invoiceGross }}"
    data-invoice-currency="{{ $invoiceCurrency }}"
    data-initial-checkout='@json($initialCheckout)'
    @if ($useChannelCheckout)
        data-channels='@json($paymentChannels)'
    @endif
    @if ($broadcastConfig !== null)
        data-broadcast='@json($broadcastConfig)'
    @endif
    data-messages="{{ json_encode([
        'channelLoading' => __('web/payments.checkout.channel_loading'),
        'channelFailed' => __('web/payments.checkout.channel_failed'),
        'channelReady' => __('web/payments.checkout.channel_ready'),
        'synced' => __('web/payments.checkout.synced'),
        'syncPending' => __('web/payments.checkout.sync_pending'),
        'syncFailed' => __('web/payments.checkout.sync_failed'),
        'syncing' => __('web/payments.checkout.syncing'),
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
        'paid' => __('web/payments.checkout.paid'),
        'notPayable' => __('web/payments.checkout.not_payable'),
        'statusPaid' => __('web/payments.show.status.paid'),
        'statusExpired' => __('web/payments.show.status.expired'),
        'statusCancelled' => __('web/payments.show.status.cancelled'),
        'statusFailed' => __('web/payments.show.status.failed'),
        'paymentPending' => __('web/payments.show.payment_pending'),
        'paymentNotStarted' => __('web/payments.show.payment_not_started'),
        'cheapAltHint' => __('web/payments.checkout.cheap_alt_hint'),
        'subtotal' => __('web/payments.checkout.subtotal'),
        'paymentFee' => __('web/payments.checkout.payment_fee'),
        'paymentFeeNote' => __('web/payments.checkout.payment_fee_note'),
        'totalDue' => __('web/payments.checkout.total_due'),
        'feeEstimateHint' => __('web/payments.checkout.fee_estimate_hint'),
        'routingUpdated' => __('web/payments.checkout.routing_updated'),
        'channelUnavailable' => __('web/payments.checkout.channel_unavailable'),
        'feeUpdated' => __('web/payments.checkout.fee_updated'),
        'selectedChannelUnavailable' => __('web/payments.checkout.selected_channel_unavailable'),
    ]) }}"
>
    @unless ($hubMode)
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <span class="fw-semibold">
                {{ __('web/payments.checkout.invoice_summary', ['number' => data_get($invoice, 'invoice_number', '—')]) }}
            </span>
            <span class="badge text-bg-light border" data-payment-inline-status>{{ $status ?: '—' }}</span>
        </div>
    @endunless

    <div class="ois-fee-breakdown mb-3" data-payment-fee-breakdown>
        <div class="ois-fee-breakdown__row">
            <span class="text-muted">{{ __('web/payments.checkout.subtotal') }}</span>
            <span data-payment-fee-subtotal>{{ $formattedSubtotal ?: ($invoiceCurrency.' '.$invoiceSubtotal) }}</span>
        </div>
        <div class="ois-fee-breakdown__row">
            <span class="text-muted">
                {{ __('web/payments.checkout.payment_fee') }}
                <span class="d-block small">{{ __('web/payments.checkout.payment_fee_note') }}</span>
            </span>
            <span class="text-end">
                <span data-payment-fee-surcharge>{{ $formattedFee ?: ($invoiceCurrency.' '.$invoiceFee) }}</span>
                @if (! empty($invoiceFeeBasis))
                    <span class="d-block small text-muted" data-payment-fee-basis>{{ $invoiceFeeBasis }}</span>
                @else
                    <span class="d-block small text-muted d-none" data-payment-fee-basis></span>
                @endif
            </span>
        </div>
        <div class="ois-fee-breakdown__row ois-fee-breakdown__row--total">
            <span class="fw-semibold">{{ __('web/payments.checkout.total_due') }}</span>
            <span class="fw-semibold ois-amount-callout__value" data-payment-fee-total data-payment-amount-value>
                {{ $formattedTotal ?: ($invoiceCurrency.' '.$invoiceGross) }}
            </span>
        </div>
        <p class="text-muted small mb-0 mt-2 d-none" data-payment-fee-estimate-hint>{{ __('web/payments.checkout.fee_estimate_hint') }}</p>
    </div>

    @if ($showGateway && $gatewayName && ! $hubMode)
        <p class="text-muted small mb-3">
            {{ __('web/payments.invoices.fields.gateway') }}:
            <span class="fw-semibold text-body">{{ $gatewayName }}</span>
        </p>
    @endif

    <div data-payment-checkout-body>
        @if ($isPaid)
            <p class="text-success mb-0" data-payment-terminal-message>{{ __('web/payments.checkout.paid') }}</p>
        @elseif (! $isPayable)
            <p class="text-muted mb-0" data-payment-terminal-message>{{ __('web/payments.checkout.not_payable') }}</p>
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
                            $cheapAlt = (string) data_get($channel, 'cheap_alt_code', '');
                            $cheapAltLabel = $cheapAlt !== ''
                                ? collect($paymentChannels)->firstWhere('code', $cheapAlt)['display_name'] ?? $cheapAlt
                                : '';
                        @endphp
                        <button
                            type="button"
                            class="pc-checkout-channel pc-kind-{{ $kind }}{{ $isDefaultChannel ? ' is-selected' : '' }}"
                            data-payment-channel-option="{{ $code }}"
                            aria-pressed="{{ $isDefaultChannel ? 'true' : 'false' }}"
                            @if ($cheapAlt !== '')
                                data-cheap-alt-code="{{ $cheapAlt }}"
                                data-cheap-alt-label="{{ $cheapAltLabel }}"
                            @endif
                        >
                            <span class="pc-checkout-channel__icon"><i class="bi {{ $icon }}"></i></span>
                            <span class="pc-checkout-channel__label">{{ data_get($channel, 'display_name', data_get($channel, 'code')) }}</span>
                            @if (data_get($channel, 'estimated_channel_fee_formatted'))
                                <span class="pc-checkout-channel__fee text-muted small">
                                    + {{ data_get($channel, 'estimated_channel_fee_formatted') }}@if (data_get($channel, 'fee_type') === 'percent' && data_get($channel, 'fee_label')) ({{ data_get($channel, 'fee_label') }})@endif
                                </span>
                            @endif
                        </button>
                    @endforeach
                </div>

                <p class="text-muted small mb-2 d-none" data-payment-cheap-alt-hint></p>

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
                </div>

                <div class="d-none pc-checkout-instructions mb-0" data-payment-instructions></div>
            @else
                <p class="text-muted mb-0">{{ __('web/payments.checkout.no_channels') }}</p>
            @endif
        @else
            <p class="text-muted mb-0">{{ __('web/payments.checkout.permission_required') }}</p>
        @endif
    </div>
</div>
