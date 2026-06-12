<x-layouts.app :title="__('web/users.edit_title')">
    <div class="row">
        <div class="col-lg-6">
            <x-card :title="__('web/users.edit_card_title', ['name' => $user->name])">
                @include('users._form', [
                    'action' => route('users.update', $user->id),
                    'method' => 'PUT',
                    'isEdit' => true,
                    'submitLabel' => __('web/users.update_submit'),
                ])
            </x-card>
        </div>
    </div>
</x-layouts.app>
