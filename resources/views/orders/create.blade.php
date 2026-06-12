<x-layouts.app :title="__('web/orders.create_title')">
    <x-card :title="__('web/orders.new_card_title')">
        @include('orders._form', [
            'action' => route('orders.store'),
            'method' => 'POST',
            'order' => null,
            'submitLabel' => __('ui.actions.save'),
        ])
    </x-card>
</x-layouts.app>
