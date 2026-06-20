<x-adminlte4::layouts.app title="{{ __('web/auth.two_factor_title') }}">
    <x-adminlte4::card title="{{ __('web/auth.two_factor_card_title') }}">
        @if(!empty($profile['two_factor_enabled']))
            <p class="text-success"><i class="bi bi-shield-check me-1"></i> {{ __('web/auth.two_factor_is_enabled') }}</p>

            <form method="POST" action="{{ route('profile.two-factor.disable') }}" class="mt-3">
                @csrf
                <x-adminlte4::form.input name="code" type="text" :placeholder="__('web/auth.two_factor_code_placeholder')" required inputmode="numeric" pattern="[0-9]*" />
                <x-adminlte4::form.submit :label="__('web/auth.two_factor_disable_button')" class="btn btn-danger" />
            </form>
        @elseif(!empty($setup))
            <p class="text-muted small">{{ __('web/auth.two_factor_setup_qr_help') }}</p>
            @if(!empty($setup['qr_code_svg']))
                <div class="mb-3 text-center">
                    <img src="{{ $setup['qr_code_svg'] }}" alt="{{ __('web/auth.two_factor_qr_alt') }}" class="img-fluid border rounded p-2 bg-white" style="max-width: 220px;">
                </div>
            @endif
            <label class="form-label">{{ __('web/auth.two_factor_secret_label') }}</label>
            <p class="fw-bold font-monospace user-select-all bg-body-tertiary border rounded p-2">{{ $setup['secret'] }}</p>
            <p class="small text-muted">{{ __('web/auth.two_factor_qr_url_label') }}: <a href="{{ $setup['qr_code_url'] }}" target="_blank" rel="noopener noreferrer">{{ __('web/auth.two_factor_open_app') }}</a></p>

            <form method="POST" action="{{ route('profile.two-factor.confirm') }}" class="mt-3">
                @csrf
                <x-adminlte4::form.input name="code" type="text" :placeholder="__('web/auth.two_factor_code_placeholder')" required autofocus inputmode="numeric" pattern="[0-9]*" />
                <x-adminlte4::form.submit :label="__('web/auth.two_factor_confirm_button')" class="btn btn-primary" />
            </form>

            <form method="POST" action="{{ route('profile.two-factor.setup') }}" class="mt-2">
                @csrf
                <button type="submit" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-clockwise me-1"></i> {{ __('web/auth.two_factor_restart_setup') }}
                </button>
            </form>
        @else
            <p class="text-muted">{{ __('web/auth.two_factor_not_enabled') }}</p>
            <form method="POST" action="{{ route('profile.two-factor.setup') }}">
                @csrf
                <x-adminlte4::form.submit :label="__('web/auth.two_factor_setup_button')" class="btn btn-outline-primary" />
            </form>
        @endif
    </x-adminlte4::card>
</x-adminlte4::layouts.app>
