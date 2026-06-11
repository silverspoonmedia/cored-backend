<x-layouts.auth :title="__('web/auth.register_title')" :heading="__('web/auth.register_heading')">
    <x-form.ajax :action="route('register.store')">
        <x-form.input name="name" :placeholder="__('web/auth.name_placeholder')" icon="bi-person" required autofocus autocomplete="name" />
        <x-form.input name="email" type="email" :placeholder="__('web/auth.email_placeholder')" icon="bi-envelope" required autocomplete="email" />
        <x-form.input name="password" type="password" :placeholder="__('web/auth.password_placeholder')" icon="bi-lock-fill" required autocomplete="new-password" />
        <x-form.input name="password_confirmation" type="password" :placeholder="__('web/auth.password_confirm_placeholder')" icon="bi-lock-fill" required autocomplete="new-password" />

        <div class="d-grid">
            <x-form.submit :label="__('web/auth.sign_up')" class="btn btn-primary" />
        </div>
    </x-form.ajax>

    <p class="mt-3 mb-0">
        {{ __('web/auth.has_account') }} <a href="{{ route('login') }}">{{ __('web/auth.sign_in') }}</a>
    </p>
</x-layouts.auth>
