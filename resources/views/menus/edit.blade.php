<x-layouts.app :title="__('web/menus.edit_title')">
    <x-slot:breadcrumb>
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('menu.dashboard') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('menus.index') }}">{{ __('web/menus.breadcrumb') }}</a></li>
        <li class="breadcrumb-item active" aria-current="page">{{ __('ui.breadcrumb.edit') }}</li>
    </x-slot:breadcrumb>

    <x-card :title="__('web/menus.edit_card_title', ['name' => $menu['name']])">
        @include('menus._form', [
            'action' => route('menus.update', $menu['id']),
            'method' => 'PUT',
            'isEdit' => true,
            'submitLabel' => __('web/users.update_submit'),
        ])
    </x-card>
</x-layouts.app>
