<x-adminlte4::layouts.app :title="__('web/orders.create_title')">
    <x-adminlte4::card :title="__('web/orders.new_card_title')">
        @include(config('web.layout').'.orders._form', [
            'action' => route('orders.store'),
            'method' => 'POST',
            'order' => null,
            'submitLabel' => __('ui.actions.save'),
        ])
    </x-adminlte4::card>
</x-adminlte4::layouts.app>
