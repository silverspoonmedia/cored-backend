<x-adminlte4::layouts.app :title="__('web/inference.model_cards.edit_title')">
  @php
      \Diglactic\Breadcrumbs\Breadcrumbs::setCurrentRoute('inference.model-cards.edit', $card['id']);
  @endphp
    <div class="row">
        <div class="col-lg-8">
            <x-adminlte4::card :title="__('web/inference.model_cards.edit_title')">
                <x-adminlte4::form.ajax :action="route('inference.model-cards.update', $card['id'])" method="PUT">
                    @include(config('web.layout').'.inference.model-cards._form', ['isEdit' => true, 'card' => $card])
                    <x-adminlte4::form.submit :label="__('ui.actions.save')" />
                </x-adminlte4::form.ajax>
            </x-adminlte4::card>
        </div>
    </div>
</x-adminlte4::layouts.app>
