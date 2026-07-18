<x-adminlte4::layouts.app :title="__('web/services.purchase_title')">
    @php
        \Diglactic\Breadcrumbs\Breadcrumbs::setCurrentRoute('service-purchases.show', data_get($purchase, 'id'));

        $invoice = data_get($purchase, 'invoice');
        $invoiceStatus = (string) data_get($invoice, 'status', '');
        $hasChannel = (bool) data_get($invoice, 'payment_channel_code');
        $hasCheckoutSession = $hasChannel
            || (bool) data_get($invoice, 'external_transaction_id')
            || ! empty(data_get($invoice, 'gateway_response_payload'));

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

        $serviceName = data_get($purchase, 'service.name', __('web/services.purchase_title'));
        $heroBadges = [
            ['label' => data_get($purchase, 'status', 'pending'), 'variant' => match (data_get($purchase, 'status')) {
                'paid' => 'success',
                'cancelled', 'failed' => 'danger',
                default => 'secondary',
            }],
        ];
        if ($invoice) {
            $heroBadges[] = ['label' => $paymentUi['label'], 'variant' => $paymentUi['variant']];
        }
    @endphp

    @vite('resources/css/payments/order-invoice-show.css')

    <x-adminlte4::payments.payment-status-hero
        :eyebrow="__('web/services.breadcrumb')"
        :title="$serviceName"
        :amount="data_get($purchase, 'settlement_amount', 0)"
        :currency="data_get($purchase, 'settlement_currency', 'IDR')"
        :badges="$heroBadges"
    />

    @php
        $entitlement = data_get($purchase, 'entitlement');
        $entitlementStatus = data_get($entitlement, 'status');
    @endphp

    @if ($entitlementStatus === 'grace')
        <div class="alert alert-warning mb-3" role="alert">
            {{ __('web/services.entitlement.grace_banner') }}
            @if (data_get($entitlement, 'grace_ends_at'))
                <strong>{{ __('web/services.show.entitlement_grace') }}:</strong>
                {{ data_get($entitlement, 'grace_ends_at') }}
            @endif
        </div>
    @endif

    <div class="row g-3 mb-3">
        <div class="col-lg-8">
            @if ($invoice)
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
            @else
                <x-adminlte4::card :title="__('web/payments.checkout.title')">
                    <p class="text-muted mb-0">{{ __('web/payments.checkout.not_payable') }}</p>
                </x-adminlte4::card>
            @endif
        </div>

        <div class="col-lg-4">
            <div class="ois-sidebar-card">
                <div class="ois-sidebar-card__title">{{ __('web/services.purchase_title') }}</div>
                <dl class="row mb-0 small">
                    @if (data_get($purchase, 'change_type'))
                        <dt class="col-5">{{ __('web/services.show.change_type') }}</dt>
                        <dd class="col-7">{{ __('web/services.change_type.'.data_get($purchase, 'change_type')) }}</dd>
                    @endif
                    @if (data_get($purchase, 'list_amount') !== null)
                        <dt class="col-5">{{ __('web/services.show.list_amount') }}</dt>
                        <dd class="col-7">
                            {{ data_get($purchase, 'display_currency') }}
                            {{ number_format((float) data_get($purchase, 'list_amount', 0), 2) }}
                        </dd>
                    @endif
                    @if ((float) data_get($purchase, 'credit_amount', 0) > 0)
                        <dt class="col-5">{{ __('web/services.show.credit_amount') }}</dt>
                        <dd class="col-7">
                            {{ data_get($purchase, 'display_currency') }}
                            {{ number_format((float) data_get($purchase, 'credit_amount', 0), 2) }}
                        </dd>
                    @endif
                    <dt class="col-5">{{ __('web/services.show.display_amount') }}</dt>
                    <dd class="col-7">
                        {{ data_get($purchase, 'display_currency') }}
                        {{ number_format((float) data_get($purchase, 'charge_amount', data_get($purchase, 'display_amount', 0)), 2) }}
                    </dd>
                    @if ((int) data_get($purchase, 'surplus_days', 0) > 0)
                        <dt class="col-5">{{ __('web/services.show.surplus_days') }}</dt>
                        <dd class="col-7">{{ data_get($purchase, 'surplus_days') }}</dd>
                    @endif
                    <dt class="col-5">{{ __('web/services.show.settlement_amount') }}</dt>
                    <dd class="col-7">
                        {{ data_get($purchase, 'settlement_currency', 'IDR') }}
                        {{ number_format((float) data_get($purchase, 'settlement_amount', 0), 2) }}
                    </dd>
                    <dt class="col-5">{{ __('web/services.show.fx_rate') }}</dt>
                    <dd class="col-7">{{ data_get($purchase, 'fx_rate') ?: '—' }}</dd>
                    <dt class="col-5">{{ __('web/services.show.status') }}</dt>
                    <dd class="col-7">{{ data_get($purchase, 'status') }}</dd>
                    @if (data_get($purchase, 'period_start') || data_get($purchase, 'period_end'))
                        <dt class="col-5">{{ __('web/services.show.period') }}</dt>
                        <dd class="col-7">
                            {{ data_get($purchase, 'period_start') ?: '—' }}
                            →
                            {{ data_get($purchase, 'period_end') ?: '—' }}
                        </dd>
                    @endif
                    @if ($entitlement)
                        <dt class="col-5">{{ __('web/services.show.entitlement_status') }}</dt>
                        <dd class="col-7">
                            @if (data_get($entitlement, 'is_permanent'))
                                {{ __('web/services.entitlement.permanent') }}
                            @else
                                {{ __('web/services.entitlement.'.$entitlementStatus) }}
                            @endif
                        </dd>
                        @if (data_get($entitlement, 'quantity'))
                            <dt class="col-5">{{ __('web/services.show.entitlement_quantity') }}</dt>
                            <dd class="col-7">{{ data_get($entitlement, 'quantity') }}</dd>
                        @endif
                        @if (data_get($entitlement, 'ends_at'))
                            <dt class="col-5">{{ __('web/services.show.entitlement_ends') }}</dt>
                            <dd class="col-7">{{ data_get($entitlement, 'ends_at') }}</dd>
                        @endif
                    @endif
                </dl>
            </div>

            @if ($invoice)
                <div class="ois-sidebar-card">
                    <div class="ois-sidebar-card__title">{{ __('web/payments.invoices.show_title') }}</div>
                    <dl class="row mb-0 small">
                        <dt class="col-5">{{ __('web/payments.invoices.fields.invoice_number') }}</dt>
                        <dd class="col-7">
                            <a href="{{ route('invoices.show', data_get($invoice, 'id')) }}">
                                {{ data_get($invoice, 'invoice_number') }}
                            </a>
                        </dd>
                        <dt class="col-5">{{ __('web/payments.invoices.fields.status') }}</dt>
                        <dd class="col-7">{{ $paymentUi['label'] }}</dd>
                    </dl>
                </div>
            @endif
        </div>
    </div>
</x-adminlte4::layouts.app>
