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
            'class' => 'btn-outline-secondary',
        ],
        [
            'label' => __('web/vtual.nav.profiles'),
            'route' => 'vtual.profiles.index',
            'active' => request()->routeIs('vtual.profiles.*', 'vtual.creators.*'),
            'show' => true,
            'class' => 'btn-outline-info',
        ],
        [
            'label' => __('web/vtual.nav.claim'),
            'route' => 'vtual.claims.index',
            'active' => request()->routeIs('vtual.claims.index', 'vtual.claims.show'),
            'show' => $canClaim,
            'class' => 'btn-outline-primary',
        ],
        [
            'label' => __('web/vtual.nav.registry'),
            'route' => 'vtual.claims.staff',
            'active' => request()->routeIs('vtual.claims.staff'),
            'show' => $canManageAny,
            'class' => 'btn-outline-warning',
        ],
        [
            'label' => __('web/vtual.nav.credentials'),
            'route' => 'vtual.admin.credentials.index',
            'active' => request()->routeIs('vtual.admin.credentials.*', 'vtual.admin.platform-api-probe*'),
            'show' => $canManageCredentials,
            'class' => 'btn-outline-dark',
        ],
    ];
@endphp
<p class="mb-3 d-flex flex-wrap gap-2">
    @foreach ($items as $item)
        @if ($item['show'])
            <a href="{{ route($item['route']) }}"
               class="btn btn-sm {{ $item['active'] ? str_replace('outline-', '', $item['class']) : $item['class'] }}">
                {{ $item['label'] }}
            </a>
        @endif
    @endforeach
</p>
