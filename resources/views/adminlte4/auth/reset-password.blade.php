<x-adminlte4::layouts.auth :title="__('web/auth.reset_password_title')" :heading="__('web/auth.reset_password_heading')">
    <x-adminlte4::form.ajax :action="route('password.update')">
        <input type="hidden" name="token" value="{{ $token }}" />

        <x-adminlte4::form.input name="email" type="email" :label="__('ui.labels.email')" :placeholder="__('web/auth.email_placeholder')" icon="bi-envelope" :value="$email" required autocomplete="email" />
        <x-adminlte4::form.input name="password" type="password" :label="__('ui.labels.password')" :placeholder="__('web/auth.new_password_placeholder')" icon="bi-lock-fill" required autocomplete="new-password" />
        <x-adminlte4::form.input name="password_confirmation" type="password" :label="__('web/auth.password_confirm_label')" :placeholder="__('web/auth.password_confirm_placeholder')" icon="bi-lock-fill" required autocomplete="new-password" />

        <div class="d-grid">
            <x-adminlte4::form.submit :label="__('web/auth.reset_password_submit')" class="btn btn-primary" />
        </div>
    </x-adminlte4::form.ajax>

    <p class="mt-3 mb-0">
        <a href="{{ route('login') }}">{{ __('web/auth.back_to_login') }}</a>
    </p>
</x-adminlte4::layouts.auth>
