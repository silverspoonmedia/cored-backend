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
            [
                'key' => 'purchase',
                'label' => data_get($purchase, 'status', 'pending'),
                'variant' => match (data_get($purchase, 'status')) {
                    'paid' => 'success',
                    'cancelled', 'failed' => 'danger',
                    default => 'secondary',
                },
            ],
        ];
        if ($invoice) {
            $heroBadges[] = [
                'key' => 'payment',
                'label' => $paymentUi['label'],
                'variant' => $paymentUi['variant'],
            ];
        }
    @endphp

    @vite('resources/css/payments/order-invoice-show.css')

    <x-adminlte4::payments.payment-status-hero
        :eyebrow="__('web/services.breadcrumb')"
        :title="$serviceName"
        :amount="data_get($purchase, 'settlement_amount_formatted')"
        :currency="''"
        :badges="$heroBadges"
    />

    @if ($invoice && in_array($invoiceStatus, ['pending', 'challenge'], true) && (float) data_get($invoice, 'payment_channel_fee', 0) <= 0)
        <p class="text-muted small mb-3">{{ __('web/payments.checkout.fee_at_checkout_hint') }}</p>
    @endif

    @php
        $entitlement = data_get($purchase, 'entitlement');
        $entitlementStatus = data_get($entitlement, 'status');
        $service = data_get($purchase, 'service', []);
        $purchaseStatus = (string) data_get($purchase, 'status', '');
        $canEditQuantity = $purchaseStatus === 'pending'
            && in_array($invoiceStatus, ['pending', 'challenge'], true)
            && data_get($service, 'plan_kind') === 'addon';
        $purchaseQuantity = max(1, (int) data_get($purchase, 'quantity', 1));
        $maxPurchaseQuantity = max(1, (int) setting_config('service.purchase.max_quantity', 99));
        $currentStackQuantity = max(0, (int) data_get($entitlement, 'quantity', 0));
        $projectedStackQuantity = $currentStackQuantity > 0
            ? $currentStackQuantity + $purchaseQuantity
            : null;
        $pricingSnapshot = [
            'locale' => app()->getLocale(),
            'unit_list_amount' => data_get($purchase, 'unit_list_amount'),
            'unit_charge_amount' => data_get($purchase, 'unit_charge_amount', data_get($purchase, 'unit_list_amount')),
            'fx_rate' => data_get($purchase, 'fx_rate'),
            'display_currency' => data_get($purchase, 'display_currency'),
            'settlement_currency' => data_get($purchase, 'settlement_currency', 'IDR'),
            'settlement_decimals' => strtoupper((string) data_get($purchase, 'settlement_currency', 'IDR')) === 'IDR' ? 0 : 2,
            'storage_bytes_per_unit' => (int) data_get($service, 'storage_bytes', 0),
            'committed_quantity' => $purchaseQuantity,
            'current_stack_quantity' => $currentStackQuantity,
            'stack_equation_template' => __('web/services.show.stack_quantity_equation'),
            'storage_gain_template' => __('web/services.show.storage_gain_preview'),
        ];
    @endphp

    @if ($entitlementStatus === 'grace')
        <div class="alert alert-warning mb-3" role="alert">
            {{ __('web/services.entitlement.grace_banner') }}
            @if (data_get($entitlement, 'grace_ends_at'))
                <strong>{{ __('web/services.show.entitlement_grace') }}:</strong>
                {{ data_get($entitlement, 'grace_ends_at_formatted') ?: data_get($entitlement, 'grace_ends_at') }}
            @endif
        </div>
    @endif

    <div class="row g-3 mb-3">
        <div class="col-lg-8 order-2 order-lg-1">
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

        <div class="col-lg-4 order-1 order-lg-2">
            <div class="ois-sidebar-card">
                <div class="ois-sidebar-card__title">{{ __('web/services.purchase_title') }}</div>
                <dl class="row mb-0 small">
                    @if ($canEditQuantity)
                        <dt class="col-5">{{ __('web/services.show.purchase_quantity') }}</dt>
                        <dd class="col-7">
                            <div
                                class="d-flex flex-wrap align-items-center gap-2"
                                data-purchase-quantity
                                data-pricing='@json($pricingSnapshot)'
                                data-max-quantity="{{ $maxPurchaseQuantity }}"
                                data-messages="{{ json_encode([
                                    'storageGain' => __('web/services.show.storage_gain_preview'),
                                ]) }}"
                            >
                                <div class="input-group input-group-sm" style="max-width: 9rem;">
                                    <button type="button" class="btn btn-outline-secondary" data-purchase-qty-minus aria-label="-">−</button>
                                    <input
                                        type="number"
                                        class="form-control text-center"
                                        data-purchase-qty-input
                                        min="1"
                                        max="{{ $maxPurchaseQuantity }}"
                                        value="{{ $purchaseQuantity }}"
                                    >
                                    <button type="button" class="btn btn-outline-secondary" data-purchase-qty-plus aria-label="+">+</button>
                                </div>
                                @if (data_get($purchase, 'storage_gain_human'))
                                    <span class="text-muted" data-purchase-storage-gain>
                                        {{ __('web/services.show.storage_gain_preview', ['amount' => data_get($purchase, 'storage_gain_human')]) }}
                                    </span>
                                @endif
                            </div>
                            <p class="text-muted small mb-0 mt-1">{{ __('web/services.show.quantity_estimate_hint') }}</p>
                        </dd>
                    @elseif ($purchaseQuantity > 1)
                        <dt class="col-5">{{ __('web/services.show.purchase_quantity') }}</dt>
                        <dd class="col-7" data-purchase-qty-display>{{ $purchaseQuantity }}</dd>
                    @endif
                    @if (data_get($purchase, 'change_type'))
                        <dt class="col-5">{{ __('web/services.show.change_type') }}</dt>
                        <dd class="col-7">{{ __('web/services.change_type.'.data_get($purchase, 'change_type')) }}</dd>
                    @endif
                    @if (data_get($purchase, 'list_amount') !== null)
                        <dt class="col-5">{{ __('web/services.show.list_amount') }}</dt>
                        <dd class="col-7" data-purchase-list-amount>{{ data_get($purchase, 'list_amount_formatted') ?: '—' }}</dd>
                    @endif
                    @if ((float) data_get($purchase, 'credit_amount', 0) > 0)
                        <dt class="col-5">{{ __('web/services.show.credit_amount') }}</dt>
                        <dd class="col-7">{{ data_get($purchase, 'credit_amount_formatted') ?: '—' }}</dd>
                    @endif
                    <dt class="col-5">{{ __('web/services.show.display_amount') }}</dt>
                    <dd class="col-7" data-purchase-display-amount>
                        {{ data_get($purchase, 'charge_amount_formatted')
                            ?: data_get($purchase, 'display_amount_formatted')
                            ?: '—' }}
                    </dd>
                    @if ((int) data_get($purchase, 'surplus_days', 0) > 0)
                        <dt class="col-5">{{ __('web/services.show.surplus_days') }}</dt>
                        <dd class="col-7">{{ data_get($purchase, 'surplus_days') }}</dd>
                    @endif
                    <dt class="col-5">{{ __('web/services.show.settlement_amount') }}</dt>
                    <dd class="col-7" data-purchase-settlement>{{ data_get($purchase, 'settlement_amount_formatted') ?: '—' }}</dd>
                    <dt class="col-5">{{ __('web/services.show.fx_rate') }}</dt>
                    <dd class="col-7" data-purchase-fx-rate>{{ data_get($purchase, 'fx_rate_label') ?: data_get($purchase, 'fx_rate_formatted') ?: data_get($purchase, 'fx_rate') ?: '—' }}</dd>
                    <dt class="col-5">{{ __('web/services.show.status') }}</dt>
                    <dd class="col-7" data-purchase-status>{{ data_get($purchase, 'status') }}</dd>
                    @if (data_get($purchase, 'period_start') || data_get($purchase, 'period_end'))
                        <dt class="col-5">{{ __('web/services.show.period') }}</dt>
                        <dd class="col-7">
                            {{ data_get($purchase, 'period_start_formatted') ?: data_get($purchase, 'period_start') ?: '—' }}
                            →
                            {{ data_get($purchase, 'period_end_formatted') ?: data_get($purchase, 'period_end') ?: '—' }}
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
                        @if (data_get($service, 'plan_kind') === 'addon' && $currentStackQuantity > 0 && $projectedStackQuantity !== null)
                            <dt class="col-5">{{ __('web/services.show.stack_quantity') }}</dt>
                            <dd class="col-7" data-stack-quantity-equation>
                                {{ __('web/services.show.stack_quantity_equation', [
                                    'current' => $currentStackQuantity,
                                    'purchase' => $purchaseQuantity,
                                    'total' => $projectedStackQuantity,
                                ]) }}
                            </dd>
                        @endif
                        @if (data_get($entitlement, 'ends_at'))
                            <dt class="col-5">{{ __('web/services.show.entitlement_ends') }}</dt>
                            <dd class="col-7">{{ data_get($entitlement, 'ends_at_formatted') ?: data_get($entitlement, 'ends_at') }}</dd>
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
                            {{ data_get($invoice, 'invoice_number') }}
                        </dd>
                        <dt class="col-5">{{ __('web/payments.invoices.fields.status') }}</dt>
                        <dd class="col-7" data-payment-sidebar-status>{{ $paymentUi['label'] }}</dd>
                    </dl>
                </div>
            @endif
        </div>
    </div>

    <x-slot:scripts>
        @vite('resources/js/services/purchase-quantity.js')
    </x-slot:scripts>
</x-adminlte4::layouts.app>
