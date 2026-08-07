@php($user = auth('api')->user())
<nav class="app-header navbar navbar-expand bg-body">
    <div class="container-fluid">
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button" aria-label="{{ __('web/navbar.toggle_sidebar') }}" aria-expanded="false">
                    <i class="bi bi-list" aria-hidden="true"></i>
                </a>
            </li>
            <x-adminlte4::nav.bar-tree :items="$navbarNavigation ?? []" />
            @if (! empty($navbarNavigation))
                <li class="nav-item dropdown d-md-none">
                    <a class="nav-link" href="#" data-bs-toggle="dropdown" role="button" aria-expanded="false" aria-label="{{ __('ui.labels.main_navigation') }}">
                        <i class="bi bi-grid-3x3-gap" aria-hidden="true"></i>
                    </a>
                    <ul class="dropdown-menu">
                        <x-adminlte4::nav.bar-tree :items="$navbarNavigation" nested />
                    </ul>
                </li>
            @endif
        </ul>

        <ul class="navbar-nav ms-auto">
            {{-- Color mode toggle --}}
            <li class="nav-item dropdown">
                <a class="nav-link" href="#" id="bd-theme" aria-label="{{ __('web/navbar.toggle_theme') }}" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-sun-fill" data-lte-theme-icon="light" aria-hidden="true"></i>
                    <i class="bi bi-moon-fill d-none" data-lte-theme-icon="dark" aria-hidden="true"></i>
                    <i class="bi bi-circle-half d-none" data-lte-theme-icon="auto" aria-hidden="true"></i>
                </a>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="bd-theme" style="--bs-dropdown-min-width: 8rem">
                    <li>
                        <button type="button" class="dropdown-item d-flex align-items-center" data-bs-theme-value="light" aria-pressed="false">
                            <i class="bi bi-sun-fill me-2"></i> {{ __('web/navbar.theme_light') }} <i class="bi bi-check-lg ms-auto d-none"></i>
                        </button>
                    </li>
                    <li>
                        <button type="button" class="dropdown-item d-flex align-items-center" data-bs-theme-value="dark" aria-pressed="false">
                            <i class="bi bi-moon-fill me-2"></i> {{ __('web/navbar.theme_dark') }} <i class="bi bi-check-lg ms-auto d-none"></i>
                        </button>
                    </li>
                    <li>
                        <button type="button" class="dropdown-item d-flex align-items-center active" data-bs-theme-value="auto" aria-pressed="true">
                            <i class="bi bi-circle-half me-2"></i> {{ __('web/navbar.theme_auto') }} <i class="bi bi-check-lg ms-auto d-none"></i>
                        </button>
                    </li>
                </ul>
            </li>

            {{-- Locale switcher --}}
            <li class="nav-item dropdown">
                <a class="nav-link" href="#" data-bs-toggle="dropdown" aria-label="{{ __('ui.labels.language') }}" aria-expanded="false">
                    <i class="bi bi-translate" aria-hidden="true"></i>
                    <span class="d-none d-md-inline ms-1 text-uppercase">{{ app()->getLocale() }}</span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    @foreach (config('localization.supported_locales', ['en', 'id']) as $supportedLocale)
                        <li>
                            <a class="dropdown-item @if(app()->getLocale() === $supportedLocale) active @endif"
                               href="{{ route('locale.switch', $supportedLocale) }}">
                                {{ strtoupper($supportedLocale) }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </li>

            {{-- Notifications bell (visible only to roles with `view notification`) --}}
            @can('view notification')
                <li
                    class="nav-item dropdown"
                    id="notification-bell"
                    data-notification-config='@json($notificationBellConfig ?? null)'
                >
                    <a class="nav-link" href="#" data-bs-toggle="dropdown" aria-label="{{ __('web/notifications.bell_label') }}" aria-expanded="false">
                        <i class="bi bi-bell-fill" aria-hidden="true"></i>
                        <span class="navbar-badge badge text-bg-danger d-none" data-notification-badge>0</span>
                    </a>
                    <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
                        <span class="dropdown-item dropdown-header d-flex justify-content-between align-items-center">
                            <span data-notification-header>{{ __('web/notifications.title') }}</span>
                            <x-adminlte4::form.ajax :action="route('notifications.read-all')" :show-alert="false" class="m-0" data-notification-read-all-form>
                                <button type="submit" class="btn btn-link btn-sm p-0 text-decoration-none" data-notification-read-all>
                                    {{ __('web/notifications.mark_all_read') }}
                                    <span class="spinner-border spinner-border-sm d-none ms-1" data-form-submit-spinner role="status" aria-hidden="true"></span>
                                </button>
                            </x-adminlte4::form.ajax>
                        </span>
                        <div class="dropdown-divider"></div>
                        <div data-notification-list>
                            <span class="dropdown-item text-secondary fs-7">{{ __('web/notifications.empty') }}</span>
                        </div>
                        <div class="dropdown-divider"></div>
                        <a href="{{ route('notifications.index') }}" class="dropdown-item dropdown-footer">
                            {{ __('web/notifications.view_all') }}
                        </a>
                    </div>
                </li>
            @endcan

            {{-- User menu --}}
            <li class="nav-item dropdown user-menu">
                <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" aria-label="{{ __('ui.labels.user_menu') }}">
                    <i class="bi bi-person-circle fs-5" aria-hidden="true"></i>
                    <span class="d-none d-md-inline ms-1">{{ $user?->name }}</span>
                </a>
                <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
                    <li class="user-header text-bg-primary">
                        <i class="bi bi-person-circle" style="font-size: 3.5rem;"></i>
                        <p>
                            {{ $user?->name }}
                            <small>{{ $user?->email }}</small>
                        </p>
                    </li>
                    <li class="px-3 py-2">
                        <div class="d-grid gap-2">
                            <a href="{{ route('profile.password.edit') }}" class="btn btn-outline-secondary btn-sm text-start">
                                <i class="bi bi-key me-1"></i> {{ __('menu.password') }}
                            </a>
                            <a href="{{ route('profile.two-factor.edit') }}" class="btn btn-outline-secondary btn-sm text-start">
                                <i class="bi bi-shield-lock me-1"></i> {{ __('menu.two_factor') }}
                            </a>
                            <a href="{{ route('profile.connections.edit') }}" class="btn btn-outline-secondary btn-sm text-start">
                                <i class="bi bi-link-45deg me-1"></i> {{ __('menu.connected_accounts') }}
                            </a>
                        </div>
                    </li>
                    <li class="user-footer">
                        <x-adminlte4::form.ajax :action="route('logout')" :show-alert="false" class="float-end">
                            <button type="submit" class="btn btn-outline-danger btn-sm">
                                <i class="bi bi-box-arrow-right" aria-hidden="true"></i> {{ __('ui.actions.logout') }}
                                <span class="spinner-border spinner-border-sm d-none ms-1" data-form-submit-spinner role="status" aria-hidden="true"></span>
                            </button>
                        </x-adminlte4::form.ajax>
                    </li>
                </ul>
            </li>
        </ul>
    </div>
</nav>
