<x-adminlte4::layouts.app :title="__('web/profile.password_title')">
    <div class="row">
        <div class="col-lg-6">
            <x-adminlte4::card :title="__('web/profile.password_card')">
                <x-adminlte4::form.ajax :action="route('profile.password.update')" method="PUT">
                    <x-adminlte4::form.input name="current_password" type="password" :label="__('web/profile.current_password')" required autocomplete="current-password" />
                    <x-adminlte4::form.input name="password" type="password" :label="__('web/profile.new_password')" required autocomplete="new-password" />
                    <x-adminlte4::form.input name="password_confirmation" type="password" :label="__('web/profile.confirm_password')" required autocomplete="new-password" />

                    <x-adminlte4::form.submit :label="__('ui.actions.save')" class="btn btn-primary" />
                </x-adminlte4::form.ajax>
            </x-adminlte4::card>
        </div>
    </div>
</x-adminlte4::layouts.app>
