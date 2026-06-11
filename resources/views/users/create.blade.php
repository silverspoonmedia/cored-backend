<x-layouts.app :title="__('web/users.create_title')">
    <x-slot:breadcrumb>
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('menu.dashboard') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('users.index') }}">{{ __('web/users.breadcrumb') }}</a></li>
        <li class="breadcrumb-item active" aria-current="page">{{ __('ui.breadcrumb.add') }}</li>
    </x-slot:breadcrumb>

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
