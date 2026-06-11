<x-layouts.auth :title="__('web/auth.login_title')" :heading="__('web/auth.login_heading')">
    <x-form.ajax :action="route('login.store')">
        <x-form.input name="email" type="email" :placeholder="__('web/auth.email_placeholder')" icon="bi-envelope" required autofocus autocomplete="email" />
        <x-form.input name="password" type="password" :placeholder="__('web/auth.password_placeholder')" icon="bi-lock-fill" required autocomplete="current-password" />

        <div class="row align-items-center">
            <div class="col-7">
                <x-form.checkbox name="remember_me" :label="__('web/auth.remember_me')" />
            </div>
            <div class="col-5">
                <div class="d-grid">
                    <x-form.submit :label="__('web/auth.sign_in')" class="btn btn-primary" />
                </div>
            </div>
        </div>
    </x-form.ajax>

    <p class="mt-3 mb-1">
        <a href="{{ route('password.request') }}">{{ __('web/auth.forgot_password_link') }}</a>
    </p>
    <p class="mb-0">
        {{ __('web/auth.no_account') }} <a href="{{ route('register') }}">{{ __('web/auth.register_now') }}</a>
    </p>
</x-layouts.auth>
