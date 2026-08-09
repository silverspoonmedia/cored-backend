<x-adminlte4::layouts.app :title="__('web/menus.create_title')">
    <x-adminlte4::card :title="__('web/menus.new_card_title')">
        @include(config('web.layout').'.menus.partials.form', [
            'action' => route('menus.store'),
            'method' => 'POST',
            'isEdit' => false,
            'menu' => null,
            'submitLabel' => __('ui.actions.save'),
        ])
    </x-adminlte4::card>
</x-adminlte4::layouts.app>
