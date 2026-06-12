<x-layouts.app :title="__('web/menus.edit_title')">
    <x-card :title="__('web/menus.edit_card_title', ['name' => $menu['name']])">
        @include('menus._form', [
            'action' => route('menus.update', $menu['id']),
            'method' => 'PUT',
            'isEdit' => true,
            'submitLabel' => __('web/users.update_submit'),
        ])
    </x-card>
</x-layouts.app>
