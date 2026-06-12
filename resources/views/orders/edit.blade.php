<x-layouts.app :title="__('web/orders.edit_title')">
    <x-card :title="__('web/orders.edit_card_title', ['number' => data_get($order, 'order_number')])">
        @include('orders._form', [
            'action' => route('orders.update', data_get($order, 'id')),
            'method' => 'PUT',
            'order' => $order,
            'submitLabel' => __('web/orders.update_submit'),
        ])
    </x-card>
</x-layouts.app>
