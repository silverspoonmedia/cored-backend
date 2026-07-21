<x-adminlte4::layouts.auth :title="__('web/auth.register_title')" :heading="__('web/auth.register_heading')">
    <x-adminlte4::form.ajax :action="route('register.store')">
        <x-adminlte4::form.input name="name" :label="__('ui.labels.name')" :placeholder="__('web/auth.name_placeholder')" icon="bi-person" required autofocus autocomplete="name" />
        <x-adminlte4::form.input name="email" type="email" :label="__('ui.labels.email')" :placeholder="__('web/auth.email_placeholder')" icon="bi-envelope" required autocomplete="email" />
        <x-adminlte4::form.input name="password" type="password" :label="__('ui.labels.password')" :placeholder="__('web/auth.password_placeholder')" icon="bi-lock-fill" required autocomplete="new-password" />
        <x-adminlte4::form.input name="password_confirmation" type="password" :label="__('web/auth.password_confirm_label')" :placeholder="__('web/auth.password_confirm_placeholder')" icon="bi-lock-fill" required autocomplete="new-password" />

        <x-adminlte4::form.auth-submit :label="__('web/auth.sign_up')" class="btn btn-primary" />
    </x-adminlte4::form.ajax>

    <p class="mt-3 mb-0">
        {{ __('web/auth.has_account') }} <a href="{{ route('login') }}">{{ __('web/auth.sign_in') }}</a>
    </p>
</x-adminlte4::layouts.auth>
