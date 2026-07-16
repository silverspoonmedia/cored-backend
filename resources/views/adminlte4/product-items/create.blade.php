<x-adminlte4::layouts.app :title="__('web/product-items.create_title')">
    <div class="row">
        <div class="col-lg-6">
            <x-adminlte4::card :title="__('web/product-items.new_card_title')">
                @include(config('web.layout').'.product-items._form', [
                    'action' => route('product-items.store'),
                    'method' => 'POST',
                    'isEdit' => false,
                    'item' => null,
                    'submitLabel' => __('ui.actions.save'),
                ])
            </x-adminlte4::card>
        </div>
    </div>
</x-adminlte4::layouts.app>
