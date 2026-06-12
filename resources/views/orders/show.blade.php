<x-layouts.app :title="__('web/orders.show_title')">
    <x-slot:breadcrumb>
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('menu.dashboard') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('orders.index') }}">{{ __('web/orders.breadcrumb') }}</a></li>
        <li class="breadcrumb-item active" aria-current="page">{{ data_get($order, 'order_number') }}</li>
    </x-slot:breadcrumb>

    @php($statusStyles = ['pending' => 'secondary', 'processing' => 'info', 'completed' => 'success', 'cancelled' => 'danger'])
    @php($status = data_get($order, 'status', 'pending'))

    <div class="row">
        <div class="col-lg-5">
            <x-card :title="data_get($order, 'order_number')">
                <dl class="row mb-0">
                    <dt class="col-5">{{ __('web/orders.show.customer') }}</dt>
                    <dd class="col-7">{{ data_get($order, 'customer_name') }}</dd>

                    <dt class="col-5">{{ __('web/orders.show.email') }}</dt>
                    <dd class="col-7">{{ data_get($order, 'customer_email') }}</dd>

                    <dt class="col-5">{{ __('web/orders.show.phone') }}</dt>
                    <dd class="col-7">{{ data_get($order, 'customer_phone') ?: '—' }}</dd>

                    <dt class="col-5">{{ __('web/orders.show.status') }}</dt>
                    <dd class="col-7">
                        <span class="badge text-bg-{{ $statusStyles[$status] ?? 'secondary' }}">
                            {{ __('web/orders.status.'.$status) }}
                        </span>
                    </dd>

                    <dt class="col-5">{{ __('web/orders.show.subtotal') }}</dt>
                    <dd class="col-7">{{ number_format((float) data_get($order, 'subtotal', 0), 2) }}</dd>

                    <dt class="col-5">{{ __('web/orders.show.discount') }}</dt>
                    <dd class="col-7">{{ number_format((float) data_get($order, 'discount', 0), 2) }}</dd>

                    <dt class="col-5">{{ __('web/orders.show.tax') }}</dt>
                    <dd class="col-7">{{ number_format((float) data_get($order, 'tax', 0), 2) }}</dd>

                    <dt class="col-5 fw-bold">{{ __('web/orders.show.grand_total') }}</dt>
                    <dd class="col-7 fw-bold">{{ number_format((float) data_get($order, 'grand_total', 0), 2) }}</dd>

                    @if (data_get($order, 'notes'))
                        <dt class="col-5">{{ __('web/orders.show.notes') }}</dt>
                        <dd class="col-7">{{ data_get($order, 'notes') }}</dd>
                    @endif
                </dl>
            </x-card>
        </div>

        <div class="col-lg-7">
            <x-card :title="__('web/orders.show.items')">
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
            </x-card>
        </div>
    </div>
</x-layouts.app>
