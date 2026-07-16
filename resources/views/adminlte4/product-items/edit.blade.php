<x-adminlte4::layouts.app :title="__('web/product-items.edit_title')">
    <div class="row">
        <div class="col-lg-6">
            <x-adminlte4::card :title="__('web/product-items.edit_card_title', ['name' => $item->name ?? ''])">
                @include(config('web.layout').'.product-items._form', [
                    'action' => route('product-items.update', $item->id),
                    'method' => 'PUT',
                    'isEdit' => true,
                    'item' => $item,
                    'submitLabel' => __('ui.actions.save'),
                ])
            </x-adminlte4::card>
        </div>
    </div>
</x-adminlte4::layouts.app>
