<x-layouts.app :title="__('web/users.edit_title')">
    <x-slot:breadcrumb>
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('menu.dashboard') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('users.index') }}">{{ __('web/users.breadcrumb') }}</a></li>
        <li class="breadcrumb-item active" aria-current="page">{{ __('ui.breadcrumb.edit') }}</li>
    </x-slot:breadcrumb>

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
