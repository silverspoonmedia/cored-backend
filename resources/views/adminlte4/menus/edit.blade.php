<x-adminlte4::layouts.app :title="__('web/menus.edit_title')">
    <x-adminlte4::card :title="__('web/menus.edit_card_title', ['name' => $menu['name']])">
        @include(config('web.layout').'.menus._form', [
            'action' => route('menus.update', $menu['id']),
            'method' => 'PUT',
            'isEdit' => true,
            'submitLabel' => __('web/users.update_submit'),
        ])
    </x-adminlte4::card>

    <x-slot:scripts>
        <x-adminlte4::partials.select2-scripts />
    </x-slot:scripts>
</x-adminlte4::layouts.app>
