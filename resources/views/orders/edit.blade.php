<x-layouts.app :title="__('web/orders.edit_title')">
    <x-slot:breadcrumb>
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('menu.dashboard') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('orders.index') }}">{{ __('web/orders.breadcrumb') }}</a></li>
        <li class="breadcrumb-item active" aria-current="page">{{ __('ui.breadcrumb.edit') }}</li>
    </x-slot:breadcrumb>

    <x-card :title="__('web/orders.edit_card_title', ['number' => data_get($order, 'order_number')])">
        @include('orders._form', [
            'action' => route('orders.update', data_get($order, 'id')),
            'method' => 'PUT',
            'order' => $order,
            'submitLabel' => __('web/orders.update_submit'),
        ])
    </x-card>
</x-layouts.app>
