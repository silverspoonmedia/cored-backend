<x-adminlte4::layouts.app :title="__('web/orders.edit_title')">
    <x-adminlte4::card :title="__('web/orders.edit_card_title', ['number' => data_get($order, 'order_number')])">
        @include(config('web.layout').'.orders._form', [
            'action' => route('orders.update', data_get($order, 'id')),
            'method' => 'PUT',
            'order' => $order,
            'submitLabel' => __('web/orders.update_submit'),
        ])
    </x-adminlte4::card>

    <x-slot:scripts>
        <x-adminlte4::partials.select2-scripts />
    </x-slot:scripts>
</x-adminlte4::layouts.app>
