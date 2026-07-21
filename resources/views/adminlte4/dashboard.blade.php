<x-adminlte4::layouts.app :title="__('web/dashboard.title')">
    <div class="row">
        <div class="col-lg-8">
            <x-adminlte4::card :title="__('web/dashboard.welcome', ['name' => $profile['name']])">
                <p class="text-muted mb-3">{{ $profile['email'] }}</p>

                <div class="mb-3">
                    <strong>{{ __('ui.labels.role') }}:</strong>
                    @forelse ($profile['roles'] as $role)
                        <span class="badge text-bg-primary">{{ $role }}</span>
                    @empty
                        <span class="text-muted">{{ __('web/dashboard.no_roles') }}</span>
                    @endforelse
                </div>

                <div class="mb-3">
                    <strong>{{ __('ui.labels.status') }}:</strong>
                    @if ($profile['email_verified_at'])
                        <span class="badge text-bg-success">{{ __('ui.status.verified') }}</span>
                    @else
                        <span class="badge text-bg-warning">{{ __('ui.status.unverified') }}</span>
                        <span class="d-inline-block align-top ms-2">
                            <x-adminlte4::form.ajax :action="route('verification.send')">
                                <button type="submit" class="btn btn-sm btn-outline-secondary">{{ __('web/dashboard.resend_verification') }}</button>
                            </x-adminlte4::form.ajax>
                        </span>
                    @endif
                </div>

                <div class="mb-3">
                    <strong>{{ __('menu.two_factor') }}:</strong>
                    @if (!empty($profile['two_factor_enabled']))
                        <span class="badge text-bg-success">{{ __('web/auth.two_factor_enabled_short') }}</span>
                    @else
                        <span class="badge text-bg-warning">{{ __('web/auth.two_factor_disabled_short') }}</span>
                        <a href="{{ route('profile.two-factor.edit') }}" class="btn btn-sm btn-outline-primary ms-2">
                            {{ __('web/auth.two_factor_setup_button') }}
                        </a>
                    @endif
                </div>

                <div class="mb-3">
                    <strong>{{ __('ui.labels.permissions') }}:</strong>
                    @forelse ($profile['permissions'] as $permission)
                        <span class="badge text-bg-secondary">{{ $permission }}</span>
                    @empty
                        <span class="text-muted">{{ __('web/dashboard.no_permissions') }}</span>
                    @endforelse
                </div>
            </x-adminlte4::card>
        </div>

        <div class="col-lg-4">
            <x-adminlte4::card :title="__('web/dashboard.quick_access')">
                <div class="d-grid gap-2">
                    @can('view user')
                        <a href="{{ route('users.index') }}" class="btn btn-outline-primary">
                            <i class="bi bi-people me-1"></i> {{ __('menu.users') }}
                        </a>
                    @endcan
                    @can('view menu')
                        <a href="{{ route('menus.index') }}" class="btn btn-outline-primary">
                            <i class="bi bi-list-nested me-1"></i> {{ __('menu.navigation') }}
                        </a>
                    @endcan
                    <a href="{{ route('profile.password.edit') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-key me-1"></i> {{ __('menu.password') }}
                    </a>
                    <a href="{{ route('profile.two-factor.edit') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-shield-lock me-1"></i> {{ __('menu.two_factor') }}
                    </a>
                </div>
            </x-adminlte4::card>
        </div>
    </div>
</x-adminlte4::layouts.app>
