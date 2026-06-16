<x-adminlte4::layouts.app :title="__('web/users.create_title')">
    <div class="row">
        <div class="col-lg-6">
            <x-adminlte4::card :title="__('web/users.new_card_title')">
                @include('users._form', [
                    'action' => route('users.store'),
                    'method' => 'POST',
                    'isEdit' => false,
                    'user' => null,
                    'currentRole' => null,
                    'submitLabel' => __('ui.actions.save'),
                ])
            </x-adminlte4::card>
        </div>
    </div>
</x-adminlte4::layouts.app>
