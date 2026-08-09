<x-adminlte4::layouts.app :title="__('web/services.create_title')">
    <x-adminlte4::card :title="__('web/services.new_card_title')">
        @include(config('web.layout').'.services.partials.form', [
            'action' => route('services.store'),
            'method' => 'POST',
            'isEdit' => false,
            'service' => null,
            'submitLabel' => __('ui.actions.save'),
        ])
    </x-adminlte4::card>
</x-adminlte4::layouts.app>
