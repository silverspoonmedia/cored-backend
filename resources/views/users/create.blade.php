<x-layouts.app :title="__('web/users.create_title')">
    <div class="row">
        <div class="col-lg-6">
            <x-card :title="__('web/users.new_card_title')">
                @include('users._form', [
                    'action' => route('users.store'),
                    'method' => 'POST',
                    'isEdit' => false,
                    'user' => null,
                    'currentRole' => null,
                    'submitLabel' => __('ui.actions.save'),
                ])
            </x-card>
        </div>
    </div>
</x-layouts.app>
