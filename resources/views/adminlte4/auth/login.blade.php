<x-adminlte4::layouts.auth :title="__('web/auth.login_title')" :heading="__('web/auth.login_heading')">
    <x-adminlte4::form.ajax :action="route('login.store')">
        <x-adminlte4::form.input name="email" type="email" :placeholder="__('web/auth.email_placeholder')" icon="bi-envelope" required autofocus autocomplete="email" />
        <x-adminlte4::form.input name="password" type="password" :placeholder="__('web/auth.password_placeholder')" icon="bi-lock-fill" required autocomplete="current-password" />

        <x-adminlte4::form.hcaptcha />

        <div class="row align-items-center g-2">
            <div class="col-sm-7">
                <x-adminlte4::form.checkbox name="remember_me" :label="__('web/auth.remember_me')" />
            </div>
            <div class="col-sm-5">
                <div class="d-grid">
                    <x-adminlte4::form.submit :label="__('web/auth.sign_in')" class="btn btn-primary" />
                </div>
            </div>
        </div>
    </x-adminlte4::form.ajax>

    <p class="mt-3 mb-1">
        <a href="{{ route('password.request') }}">{{ __('web/auth.forgot_password_link') }}</a>
    </p>
    <p class="mb-0">
        {{ __('web/auth.no_account') }} <a href="{{ route('register') }}">{{ __('web/auth.register_now') }}</a>
    </p>
</x-adminlte4::layouts.auth>
