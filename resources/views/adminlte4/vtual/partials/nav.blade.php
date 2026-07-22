@php
    $user = auth()->user();
    $canClaim = $user?->can('claim vtual creator') ?? false;
    $canManageAny = $user?->can('manage any vtual resource') ?? false;
    $canManageCredentials = $user?->can('manage vtual platform credentials') ?? false;

    $items = [
        [
            'label' => __('web/vtual.nav.overview'),
            'route' => 'vtual.index',
            'active' => request()->routeIs('vtual.index'),
            'show' => true,
        ],
        [
            'label' => __('web/vtual.nav.profiles'),
            'route' => 'vtual.profiles.index',
            'active' => request()->routeIs('vtual.profiles.*', 'vtual.creators.*'),
            'show' => true,
        ],
        [
            'label' => __('web/vtual.nav.claim'),
            'route' => 'vtual.claims.index',
            'active' => request()->routeIs('vtual.claims.index', 'vtual.claims.show'),
            'show' => $canClaim,
        ],
        [
            'label' => __('web/vtual.nav.registry'),
            'route' => 'vtual.claims.staff',
            'active' => request()->routeIs('vtual.claims.staff'),
            'show' => $canManageAny,
        ],
        [
            'label' => __('web/vtual.nav.credentials'),
            'route' => 'vtual.admin.credentials.index',
            'active' => request()->routeIs('vtual.admin.credentials.*', 'vtual.admin.platform-api-probe*'),
            'show' => $canManageCredentials,
        ],
    ];
@endphp
<nav aria-label="{{ __('web/vtual.title') }}">
    <ul class="nav nav-pills mb-3 flex-wrap gap-1">
        @foreach ($items as $item)
            @if ($item['show'])
                <li class="nav-item">
                    <a href="{{ route($item['route']) }}"
                       class="nav-link py-1 px-2 {{ $item['active'] ? 'active' : '' }}"
                       @if ($item['active']) aria-current="page" @endif>
                        {{ $item['label'] }}
                    </a>
                </li>
            @endif
        @endforeach
    </ul>
</nav>
