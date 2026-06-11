<x-layouts.auth :title="__('web/auth.forgot_password_title')" :heading="__('web/auth.forgot_password_heading')">
    <x-form.ajax :action="route('password.email')">
        <x-form.input name="email" type="email" :placeholder="__('web/auth.email_placeholder')" icon="bi-envelope" required autofocus autocomplete="email" />

        <div class="d-grid">
            <x-form.submit :label="__('web/auth.send_reset_link')" class="btn btn-primary" />
        </div>
    </x-form.ajax>

    <p class="mt-3 mb-0">
        <a href="{{ route('login') }}">{{ __('web/auth.back_to_login') }}</a>
    </p>
</x-layouts.auth>
