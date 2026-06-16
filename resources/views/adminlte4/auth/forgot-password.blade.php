<x-adminlte4::layouts.auth :title="__('web/auth.forgot_password_title')" :heading="__('web/auth.forgot_password_heading')">
    <x-adminlte4::form.ajax :action="route('password.email')">
        <x-adminlte4::form.input name="email" type="email" :placeholder="__('web/auth.email_placeholder')" icon="bi-envelope" required autofocus autocomplete="email" />

        <div class="d-grid">
            <x-adminlte4::form.submit :label="__('web/auth.send_reset_link')" class="btn btn-primary" />
        </div>
    </x-adminlte4::form.ajax>

    <p class="mt-3 mb-0">
        <a href="{{ route('login') }}">{{ __('web/auth.back_to_login') }}</a>
    </p>
</x-adminlte4::layouts.auth>
