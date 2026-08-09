<x-adminlte4::layouts.app :title="__('web/inference.providers.edit_title')">
  @php
      \Diglactic\Breadcrumbs\Breadcrumbs::setCurrentRoute('inference.providers.edit', $provider['id']);
  @endphp
    <div class="row">
        <div class="col-lg-8">
            <x-adminlte4::card :title="__('web/inference.providers.edit_title')">
                <x-adminlte4::form.ajax :action="route('inference.providers.update', $provider['id'])" method="PUT">
                    @include(config('web.layout').'.inference.providers.partials.form', ['isEdit' => true, 'provider' => $provider])
                    <x-adminlte4::form.submit :label="__('ui.actions.save')" />
                </x-adminlte4::form.ajax>
            </x-adminlte4::card>
        </div>
    </div>
</x-adminlte4::layouts.app>
