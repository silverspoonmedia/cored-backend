<x-adminlte4::layouts.app :title="__('web/vtual-orphans.edit_title')">
    <x-adminlte4::card :title="__('web/vtual-orphans.edit_title')">
        @include(config('web.layout').'.vtual.orphans._form', [
            'action' => route('vtual.orphans.update', $creator['id'] ?? ''),
            'method' => 'PATCH',
            'isEdit' => true,
            'creator' => $creator,
            'submitLabel' => __('web/vtual-orphans.form.submit_update'),
        ])

        @include(config('web.layout').'.vtual.orphans._channels', [
            'creator' => $creator,
            'platforms' => $platforms ?? collect(),
        ])
    </x-adminlte4::card>
</x-adminlte4::layouts.app>
