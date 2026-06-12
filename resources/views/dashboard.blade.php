<x-layouts.app :title="__('web/dashboard.title')">
    <div class="row">
        <div class="col-lg-8">
            <x-card :title="__('web/dashboard.welcome', ['name' => $profile['name']])">
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
                            <x-form.ajax :action="route('verification.send')">
                                <button type="submit" class="btn btn-sm btn-outline-secondary">{{ __('web/dashboard.resend_verification') }}</button>
                            </x-form.ajax>
                        </span>
                    @endif
                </div>

                <div>
                    <strong>{{ __('ui.labels.permissions') }}:</strong>
                    @forelse ($profile['permissions'] as $permission)
                        <span class="badge text-bg-secondary">{{ $permission }}</span>
                    @empty
                        <span class="text-muted">{{ __('web/dashboard.no_permissions') }}</span>
                    @endforelse
                </div>
            </x-card>
        </div>

        <div class="col-lg-4">
            <x-card :title="__('web/dashboard.quick_access')">
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
                </div>
            </x-card>
        </div>
    </div>
</x-layouts.app>
