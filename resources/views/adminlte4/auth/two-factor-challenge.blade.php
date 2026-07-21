<x-adminlte4::layouts.auth :title="__('web/auth.two_factor_challenge_title')" :heading="__('web/auth.two_factor_challenge_heading')">
    <p class="text-muted small mb-3">{{ __('web/auth.two_factor_challenge_help') }}</p>

    <x-adminlte4::form.ajax :action="route('two-factor.challenge.store')">
        <x-adminlte4::form.input name="code" type="text" :label="__('web/auth.two_factor_code_label')" :placeholder="__('web/auth.two_factor_code_placeholder')" icon="bi-shield-lock" required autofocus autocomplete="one-time-code" inputmode="numeric" pattern="[0-9]*" />

        <x-adminlte4::form.auth-submit :label="__('web/auth.verify_two_factor')" class="btn btn-primary" />
    </x-adminlte4::form.ajax>

    <p class="mt-3 mb-0">
        <a href="{{ route('login') }}">{{ __('web/auth.back_to_login') }}</a>
    </p>
</x-adminlte4::layouts.auth>
