<x-adminlte4::layouts.app :title="__('web/payments.invoices.show_title')">
    @php
        $invoiceStatus = (string) ($invoice['status'] ?? '');
        $hasChannel = (bool) data_get($invoice, 'payment_channel_code');
        $hasGateway = (bool) data_get($invoice, 'payment_gateway.id');
        $hasCheckoutSession = $hasChannel
            || (bool) data_get($invoice, 'external_transaction_id')
            || ! empty(data_get($invoice, 'gateway_response_payload'));
        $invoiceableType = (string) ($invoice['invoiceable_type'] ?? '');
        $invoiceableId = $invoice['invoiceable_id'] ?? null;
        $purchaseId = $invoiceableType === 'service_purchase' ? $invoiceableId : null;

        if ($invoiceStatus === 'paid') {
            $paymentUi = ['label' => __('web/payments.show.status.paid'), 'variant' => 'success', 'step' => 3];
        } elseif (in_array($invoiceStatus, ['expired', 'cancelled', 'failed'], true)) {
            $paymentUi = ['label' => __('web/payments.show.status.'.$invoiceStatus), 'variant' => 'danger', 'step' => 1];
        } elseif (in_array($invoiceStatus, ['pending', 'challenge'], true)) {
            $paymentUi = $hasCheckoutSession
                ? ['label' => __('web/payments.show.payment_pending'), 'variant' => 'warning', 'step' => 2]
                : ['label' => __('web/payments.show.payment_not_started'), 'variant' => 'secondary', 'step' => 1];
        } else {
            $paymentUi = ['label' => $invoiceStatus ?: '—', 'variant' => 'secondary', 'step' => 1];
        }

        $heroBadges = [
            ['label' => $paymentUi['label'], 'variant' => $paymentUi['variant']],
        ];

        if ($hasGateway) {
            $heroBadges[] = [
                'label' => data_get($invoice, 'payment_gateway.display_name', data_get($invoice, 'payment_gateway.name')),
                'variant' => 'secondary',
            ];
        }

        if ($hasChannel) {
            $heroBadges[] = ['label' => data_get($invoice, 'payment_channel_code'), 'variant' => 'warning'];
        }
    @endphp

    @vite('resources/css/payments/order-invoice-show.css')

    <x-adminlte4::payments.payment-status-hero
        :eyebrow="__('web/payments.invoices.title')"
        :title="$invoice['invoice_number'] ?? $invoice['id'] ?? '—'"
        :amount="$invoice['customer_gross_total_formatted'] ?? null"
        :currency="''"
        :badges="$heroBadges"
    />

    <div class="row g-3 mb-3">
        <div class="col-lg-8 order-2 order-lg-1">
            <x-adminlte4::payments.payment-steps
                :current-step="$paymentUi['step']"
                :completed="$invoiceStatus === 'paid'"
            />

            <x-adminlte4::payments.checkout-panel
                :invoice="$invoice"
                :payment-channels="$paymentChannels ?? []"
                :checkout-mode="$checkoutMode ?? 'snap_fallback'"
                hub-mode
            />
        </div>

        <div class="col-lg-4 order-1 order-lg-2">
            <div class="ois-sidebar-card">
                <div class="ois-sidebar-card__title">{{ __('web/payments.invoices.fields.total') }}</div>
                <dl class="row mb-0 small">
                    <dt class="col-5">{{ __('web/payments.invoices.fields.subtotal') }}</dt>
                    <dd class="col-7">{{ $invoice['subtotal_formatted'] ?? '—' }}</dd>
                    <dt class="col-5">{{ __('web/payments.invoices.fields.fee') }}</dt>
                    <dd class="col-7">{{ $invoice['payment_channel_fee_formatted'] ?? '—' }}</dd>
                    <dt class="col-5 fw-bold">{{ __('web/payments.invoices.fields.total') }}</dt>
                    <dd class="col-7 fw-bold">{{ $invoice['customer_gross_total_formatted'] ?? '—' }}</dd>
                </dl>
            </div>

            <div class="ois-sidebar-card">
                <div class="ois-sidebar-card__title">{{ __('web/payments.invoices.fields.status') }}</div>
                <dl class="row mb-0 small">
                    <dt class="col-5">{{ __('web/payments.invoices.fields.payment_method') }}</dt>
                    <dd class="col-7">
                        @if ($hasCheckoutSession || $invoiceStatus === 'paid')
                            {{ $hasChannel ? data_get($invoice, 'payment_channel_code') : ($invoice['payment_method'] ?? '—') }}
                        @else
                            {{ __('web/payments.show.payment_not_started') }}
                        @endif
                    </dd>
                    <dt class="col-5">{{ __('web/payments.invoices.fields.paid_at') }}</dt>
                    <dd class="col-7">{{ $invoice['paid_at_formatted'] ?? $invoice['paid_at'] ?? '—' }}</dd>
                    <dt class="col-5">{{ __('web/payments.invoices.fields.expired_at') }}</dt>
                    <dd class="col-7">{{ $invoice['expired_at_formatted'] ?? $invoice['expired_at'] ?? '—' }}</dd>
                    <dt class="col-5">{{ __('web/payments.invoices.fields.created_at') }}</dt>
                    <dd class="col-7">{{ $invoice['created_at_formatted'] ?? $invoice['created_at'] ?? '—' }}</dd>
                </dl>
            </div>

            @if ($purchaseId)
                <div class="ois-sidebar-card">
                    <div class="ois-sidebar-card__title">{{ __('web/payments.invoices.view_purchase') }}</div>
                    <a href="{{ route('service-purchases.show', $purchaseId) }}" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-box-arrow-up-right me-1"></i>{{ __('web/payments.invoices.view_purchase') }}
                    </a>
                </div>
            @endif

            <a href="{{ route('invoices.index') }}" class="btn btn-outline-secondary btn-sm">
                {{ __('web/payments.invoices.back_to_list') }}
            </a>
        </div>
    </div>
</x-adminlte4::layouts.app>
