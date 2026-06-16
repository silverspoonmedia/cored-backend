@php($user = auth('api')->user())
<nav class="app-header navbar navbar-expand bg-body">
    <div class="container-fluid">
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button" aria-label="{{ __('web/navbar.toggle_sidebar') }}">
                    <i class="bi bi-list"></i>
                </a>
            </li>
            <x-adminlte4::nav.bar-tree :items="$navbarNavigation ?? []" />
        </ul>

        <ul class="navbar-nav ms-auto">
            {{-- Color mode toggle --}}
            <li class="nav-item dropdown">
                <a class="nav-link" href="#" id="bd-theme" aria-label="{{ __('web/navbar.toggle_theme') }}" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-sun-fill" data-lte-theme-icon="light"></i>
                    <i class="bi bi-moon-fill d-none" data-lte-theme-icon="dark"></i>
                    <i class="bi bi-circle-half d-none" data-lte-theme-icon="auto"></i>
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
                <a class="nav-link" href="#" data-bs-toggle="dropdown" aria-label="{{ __('ui.labels.language') }}">
                    <i class="bi bi-translate"></i>
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
                <li class="nav-item dropdown" id="notification-bell">
                    <a class="nav-link" href="#" data-bs-toggle="dropdown" aria-label="{{ __('web/notifications.bell_label') }}">
                        <i class="bi bi-bell-fill"></i>
                        <span class="navbar-badge badge text-bg-danger d-none" data-notification-badge>0</span>
                    </a>
                    <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
                        <span class="dropdown-item dropdown-header d-flex justify-content-between align-items-center">
                            <span data-notification-header>{{ __('web/notifications.title') }}</span>
                            <x-adminlte4::form.ajax :action="route('notifications.read-all')" :show-alert="false" class="m-0">
                                <button type="submit" class="btn btn-link btn-sm p-0 text-decoration-none" data-notification-read-all>
                                    {{ __('web/notifications.mark_all_read') }}
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
                <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="bi bi-person-circle fs-5"></i>
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
                    <li class="user-footer">
                        <a href="{{ route('profile.password.edit') }}" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-key"></i> {{ __('menu.password') }}
                        </a>
                        <x-adminlte4::form.ajax :action="route('logout')" :show-alert="false" class="float-end">
                            <button type="submit" class="btn btn-outline-danger btn-sm">
                                <i class="bi bi-box-arrow-right"></i> {{ __('ui.actions.logout') }}
                            </button>
                        </x-adminlte4::form.ajax>
                    </li>
                </ul>
            </li>
        </ul>
    </div>
</nav>

@can('view notification')
    <script>
        (function () {
            'use strict';

            const bell = document.getElementById('notification-bell');
            if (!bell) return;

            const badge = bell.querySelector('[data-notification-badge]');
            const list = bell.querySelector('[data-notification-list]');
            const header = bell.querySelector('[data-notification-header]');
            const headerDefault = header ? header.textContent : '';
            const urls = {
                unread: @json(route('notifications.unread-count')),
                recent: @json(route('notifications.recent')),
                read: @json(url('notifications')) + '/',
            };
            const labels = @json(['empty' => __('web/notifications.empty'), 'header' => __('web/notifications.header_count')]);
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

            function escapeHtml(value) {
                const div = document.createElement('div');
                div.textContent = value ?? '';
                return div.innerHTML;
            }

            // Compact relative time (e.g. "3m", "4h", "2d"), matching the dense navbar look.
            function relativeTime(iso) {
                if (!iso) return '';
                const then = new Date(iso).getTime();
                if (Number.isNaN(then)) return '';
                const secs = Math.max(1, Math.round((Date.now() - then) / 1000));
                if (secs < 60) return secs + 's';
                const mins = Math.round(secs / 60);
                if (mins < 60) return mins + 'm';
                const hours = Math.round(mins / 60);
                if (hours < 24) return hours + 'h';
                return Math.round(hours / 24) + 'd';
            }

            function setBadge(count) {
                if (count > 0) {
                    badge.textContent = count > 99 ? '99+' : count;
                    badge.classList.remove('d-none');
                    if (header) header.textContent = labels.header.replace(':count', count);
                } else {
                    badge.classList.add('d-none');
                    if (header) header.textContent = headerDefault;
                }
            }

            async function refreshBadge() {
                try {
                    const res = await fetch(urls.unread, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
                    if (!res.ok) return;
                    const data = await res.json();
                    setBadge(Number(data.unread || 0));
                } catch (e) { /* silent: bell is non-critical */ }
            }

            async function refreshList() {
                try {
                    const res = await fetch(urls.recent, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
                    if (!res.ok) return;
                    const { data = [] } = await res.json();

                    if (!data.length) {
                        list.innerHTML = '<span class="dropdown-item text-secondary fs-7">' + escapeHtml(labels.empty) + '</span>';
                        return;
                    }

                    list.innerHTML = data.map(function (n, index) {
                        const divider = index > 0 ? '<div class="dropdown-divider"></div>' : '';
                        const icon = '<i class="' + escapeHtml(n.icon || 'bi bi-bell') + ' me-2"></i>';
                        const time = relativeTime(n.created_at);
                        const timeTag = time ? '<span class="float-end text-secondary fs-7">' + escapeHtml(time) + '</span>' : '';
                        const weight = n.is_read ? '' : ' fw-semibold';
                        const href = n.url ? n.url : '#';
                        return divider +
                            '<a class="dropdown-item text-truncate' + weight + '" href="' + href + '" ' +
                            'title="' + escapeHtml(n.message) + '" data-notification-id="' + escapeHtml(n.id) + '">' +
                            icon + escapeHtml(n.message) + timeTag +
                            '</a>';
                    }).join('');

                    list.querySelectorAll('[data-notification-id]').forEach(function (anchor) {
                        anchor.addEventListener('click', function () {
                            const id = anchor.getAttribute('data-notification-id');
                            // Fire-and-forget mark-as-read; navigation continues regardless.
                            fetch(urls.read + id + '/read', {
                                method: 'POST',
                                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf },
                                credentials: 'same-origin',
                            }).then(refreshBadge);
                        });
                    });
                } catch (e) { /* silent */ }
            }

            // Refresh the dropdown contents whenever it is opened.
            bell.addEventListener('show.bs.dropdown', refreshList);

            // Keep the read-all button in sync after submit (ajax-form handles the POST).
            const readAll = bell.querySelector('[data-notification-read-all]');
            if (readAll) {
                readAll.closest('form')?.addEventListener('submit', function () {
                    setTimeout(function () { refreshBadge(); refreshList(); }, 400);
                });
            }

            refreshBadge();
            setInterval(refreshBadge, 60000);
        })();
    </script>
@endcan
