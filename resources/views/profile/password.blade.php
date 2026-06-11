<x-layouts.app :title="__('web/profile.password_title')">
    <x-slot:breadcrumb>
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('menu.dashboard') }}</a></li>
        <li class="breadcrumb-item active" aria-current="page">{{ __('web/profile.password_title') }}</li>
    </x-slot:breadcrumb>

    <div class="row">
        <div class="col-lg-6">
            <x-card :title="__('web/profile.password_card')">
                <x-form.ajax :action="route('profile.password.update')" method="PUT">
                    <x-form.input name="current_password" type="password" :label="__('web/profile.current_password')" required autocomplete="current-password" />
                    <x-form.input name="password" type="password" :label="__('web/profile.new_password')" required autocomplete="new-password" />
                    <x-form.input name="password_confirmation" type="password" :label="__('web/profile.confirm_password')" required autocomplete="new-password" />

                    <x-form.submit :label="__('ui.actions.save')" class="btn btn-primary" />
                </x-form.ajax>
            </x-card>
        </div>
    </div>
</x-layouts.app>
