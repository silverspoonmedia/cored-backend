<x-adminlte4::layouts.app :title="__('web/orders.show_title')">
    @php
        \Diglactic\Breadcrumbs\Breadcrumbs::setCurrentRoute('orders.show', data_get($order, 'order_number'));

        $statusStyles = ['pending' => 'secondary', 'processing' => 'info', 'completed' => 'success', 'cancelled' => 'danger'];
        $status = data_get($order, 'status', 'pending');
        $invoice = data_get($order, 'invoice');
        $invoiceStatus = (string) data_get($invoice, 'status', '');
        $hasChannel = (bool) data_get($invoice, 'payment_channel_code');
        $hasGateway = (bool) data_get($invoice, 'payment_gateway.id');
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

        $heroBadges = [
            ['label' => __('web/orders.status.'.$status), 'variant' => match ($status) {
                'completed' => 'success',
                'cancelled' => 'danger',
                'processing' => 'warning',
                default => 'secondary',
            }],
        ];

        if ($invoice) {
            $heroBadges[] = ['label' => $paymentUi['label'], 'variant' => $paymentUi['variant']];
        }
    @endphp

    @vite('resources/css/payments/order-invoice-show.css')

    <x-adminlte4::payments.payment-status-hero
        :eyebrow="__('web/orders.breadcrumb')"
        :title="data_get($order, 'order_number')"
        :amount="data_get($order, 'grand_total', 0)"
        currency="IDR"
        :badges="$heroBadges"
    />

    <div class="row g-3 mb-3">
        <div class="col-lg-8">
            @if ($invoice)
                <x-adminlte4::payments.payment-steps :current-step="$paymentUi['step']" />

                <x-adminlte4::payments.checkout-panel
                    :invoice="$invoice"
                    :order-id="data_get($order, 'id')"
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
                <div class="ois-sidebar-card__title">{{ __('web/orders.show.customer') }}</div>
                <dl class="row mb-0 small">
                    <dt class="col-5">{{ __('web/orders.show.customer') }}</dt>
                    <dd class="col-7">{{ data_get($order, 'customer_name') }}</dd>
                    <dt class="col-5">{{ __('web/orders.show.email') }}</dt>
                    <dd class="col-7">{{ data_get($order, 'customer_email') }}</dd>
                    <dt class="col-5">{{ __('web/orders.show.phone') }}</dt>
                    <dd class="col-7">{{ data_get($order, 'customer_phone') ?: '—' }}</dd>
                </dl>
            </div>

            @if ($invoice)
                <div class="ois-sidebar-card">
                    <div class="ois-sidebar-card__title">{{ __('web/orders.show.invoice') }}</div>
                    <dl class="row mb-0 small">
                        <dt class="col-5">{{ __('web/orders.show.invoice_number') }}</dt>
                        <dd class="col-7">
                            <a href="{{ route('invoices.show', data_get($invoice, 'id')) }}">
                                {{ data_get($invoice, 'invoice_number') }}
                            </a>
                        </dd>
                        <dt class="col-5">{{ __('web/orders.show.invoice_status') }}</dt>
                        <dd class="col-7">{{ $paymentUi['label'] }}</dd>
                        @if ($hasGateway)
                            <dt class="col-5">{{ __('web/orders.show.payment_gateway') }}</dt>
                            <dd class="col-7">
                                {{ data_get($invoice, 'payment_gateway.display_name', data_get($invoice, 'payment_gateway.name')) }}
                            </dd>
                        @endif
                        @if ($hasChannel)
                            <dt class="col-5">{{ __('web/payments.checkout.select_channel') }}</dt>
                            <dd class="col-7">{{ data_get($invoice, 'payment_channel_code') }}</dd>
                        @endif
                    </dl>
                </div>

                <div class="ois-sidebar-card">
                    <div class="ois-sidebar-card__title">{{ __('web/orders.show.grand_total') }}</div>
                    <dl class="row mb-0 small">
                        <dt class="col-5">{{ __('web/orders.show.subtotal') }}</dt>
                        <dd class="col-7">{{ number_format((float) data_get($order, 'subtotal', 0), 2) }}</dd>
                        <dt class="col-5">{{ __('web/orders.show.discount') }}</dt>
                        <dd class="col-7">{{ number_format((float) data_get($order, 'discount', 0), 2) }}</dd>
                        <dt class="col-5">{{ __('web/orders.show.tax') }}</dt>
                        <dd class="col-7">{{ number_format((float) data_get($order, 'tax', 0), 2) }}</dd>
                        <dt class="col-5 fw-bold">{{ __('web/orders.show.grand_total') }}</dt>
                        <dd class="col-7 fw-bold">{{ number_format((float) data_get($order, 'grand_total', 0), 2) }}</dd>
                    </dl>
                </div>
            @endif

            @if (data_get($order, 'notes'))
                <div class="ois-sidebar-card">
                    <div class="ois-sidebar-card__title">{{ __('web/orders.show.notes') }}</div>
                    <p class="mb-0 small">{{ data_get($order, 'notes') }}</p>
                </div>
            @endif
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <x-adminlte4::card :title="__('web/orders.show.items')">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('web/orders.show.product') }}</th>
                                <th class="text-center">{{ __('web/orders.show.quantity') }}</th>
                                <th class="text-end">{{ __('web/orders.show.unit_price') }}</th>
                                <th class="text-end">{{ __('web/orders.show.line_subtotal') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach (data_get($order, 'details', []) as $line)
                                <tr>
                                    <td>{{ data_get($line, 'product_name') }}</td>
                                    <td class="text-center">{{ data_get($line, 'quantity') }}</td>
                                    <td class="text-end">{{ number_format((float) data_get($line, 'unit_price', 0), 2) }}</td>
                                    <td class="text-end">{{ number_format((float) data_get($line, 'subtotal', 0), 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <a href="{{ route('orders.index') }}" class="btn btn-outline-secondary mt-3">
                    <i class="bi bi-arrow-left me-1"></i>{{ __('web/orders.show.back') }}
                </a>
            </x-adminlte4::card>
        </div>

        <div class="col-lg-4">
            <x-adminlte4::card :title="__('web/orders.show.attachments')">
                @php($files = data_get($order, 'attachment', []))
                @if (empty($files))
                    <p class="text-muted mb-0">{{ __('web/orders.show.no_attachments') }}</p>
                @else
                    <ul class="list-group list-group-flush">
                        @foreach ($files as $file)
                            <li class="list-group-item d-flex align-items-center gap-2 px-0">
                                <i class="bi bi-paperclip"></i>
                                <span class="flex-grow-1">
                                    @if (data_get($file, 'url'))
                                        <a href="{{ data_get($file, 'url') }}" target="_blank" rel="noopener">{{ data_get($file, 'name') }}</a>
                                    @else
                                        {{ data_get($file, 'name') }}
                                    @endif
                                </span>
                                <small class="text-muted">{{ number_format((int) data_get($file, 'size', 0) / 1024, 1) }} KB</small>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-adminlte4::card>
        </div>
    </div>
</x-adminlte4::layouts.app>
