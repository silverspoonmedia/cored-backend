<x-adminlte4::layouts.app :title="__('web/vtual-orphans.create_title')">
    <x-adminlte4::card :title="__('web/vtual-orphans.create_title')">
        @include(config('web.layout').'.vtual.orphans.partials.form', [
            'action' => route('vtual.orphans.store'),
            'method' => 'POST',
            'isEdit' => false,
            'creator' => null,
            'submitLabel' => __('web/vtual-orphans.form.submit_create'),
        ])
    </x-adminlte4::card>
</x-adminlte4::layouts.app>
