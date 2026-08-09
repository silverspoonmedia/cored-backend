<x-adminlte4::layouts.app :title="__('web/inference.providers.create_title')">
  @php
      \Diglactic\Breadcrumbs\Breadcrumbs::setCurrentRoute('inference.providers.create');
  @endphp
    <div class="row">
        <div class="col-lg-8">
            <x-adminlte4::card :title="__('web/inference.providers.new_card_title')">
                <x-adminlte4::form.ajax :action="route('inference.providers.store')" method="POST">
                    @include(config('web.layout').'.inference.providers.partials.form', ['isEdit' => false, 'provider' => []])
                    <x-adminlte4::form.submit :label="__('ui.actions.save')" />
                </x-adminlte4::form.ajax>
            </x-adminlte4::card>
        </div>
    </div>
</x-adminlte4::layouts.app>
