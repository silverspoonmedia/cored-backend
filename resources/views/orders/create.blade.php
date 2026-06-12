<x-layouts.app :title="__('web/orders.create_title')">
    <x-slot:breadcrumb>
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('menu.dashboard') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('orders.index') }}">{{ __('web/orders.breadcrumb') }}</a></li>
        <li class="breadcrumb-item active" aria-current="page">{{ __('ui.breadcrumb.add') }}</li>
    </x-slot:breadcrumb>

    <x-card :title="__('web/orders.new_card_title')">
        @include('orders._form', [
            'action' => route('orders.store'),
            'method' => 'POST',
            'order' => null,
            'submitLabel' => __('ui.actions.save'),
        ])
    </x-card>
</x-layouts.app>
