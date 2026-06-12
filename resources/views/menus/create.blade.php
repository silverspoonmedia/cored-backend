<x-layouts.app :title="__('web/menus.create_title')">
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
