<x-adminlte4::layouts.app :title="__('web/inference.model_cards.create_title')">
  @php
      \Diglactic\Breadcrumbs\Breadcrumbs::setCurrentRoute('inference.model-cards.create');
  @endphp
    <div class="row">
        <div class="col-lg-8">
            <x-adminlte4::card :title="__('web/inference.model_cards.new_card_title')">
                <x-adminlte4::form.ajax :action="route('inference.model-cards.store')" method="POST">
                    @include(config('web.layout').'.inference.model-cards._form', ['isEdit' => false])
                    <x-adminlte4::form.submit :label="__('ui.actions.save')" />
                </x-adminlte4::form.ajax>
            </x-adminlte4::card>
        </div>
    </div>
</x-adminlte4::layouts.app>
