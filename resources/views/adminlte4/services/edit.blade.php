<x-adminlte4::layouts.app :title="__('web/services.edit_title')">
    <x-adminlte4::card :title="__('web/services.edit_card_title', ['name' => $service->name ?? ''])">
        @include(config('web.layout').'.services._form', [
            'action' => route('services.update', $service->id),
            'method' => 'PUT',
            'isEdit' => true,
            'service' => $service,
            'submitLabel' => __('ui.actions.save'),
        ])
    </x-adminlte4::card>
</x-adminlte4::layouts.app>
