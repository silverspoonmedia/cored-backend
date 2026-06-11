<x-layouts.app :title="__('web/menus.create_title')">
    <x-slot:breadcrumb>
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('menu.dashboard') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('menus.index') }}">{{ __('web/menus.breadcrumb') }}</a></li>
        <li class="breadcrumb-item active" aria-current="page">{{ __('ui.breadcrumb.add') }}</li>
    </x-slot:breadcrumb>

    <x-card :title="__('web/menus.new_card_title')">
        @include('menus._form', [
            'action' => route('menus.store'),
            'method' => 'POST',
            'isEdit' => false,
            'menu' => null,
            'submitLabel' => __('ui.actions.save'),
        ])
    </x-card>
</x-layouts.app>
