@php
    $user = auth()->user();
    $canManageCredentials = $user?->can('manage vtual platform credentials') ?? false;

    $items = [
        [
            'label' => __('web/vtual.nav.registry'),
            'route' => 'vtual.admin.registry',
            'active' => request()->routeIs('vtual.admin.registry', 'vtual.admin.claims.*'),
            'show' => true,
        ],
        [
            'label' => __('web/vtual.nav.credentials'),
            'route' => 'vtual.admin.credentials.index',
            'active' => request()->routeIs('vtual.admin.credentials.*', 'vtual.admin.platform-api-probe*'),
            'show' => $canManageCredentials,
        ],
    ];
@endphp
<nav aria-label="{{ __('web/vtual.nav.admin_title') }}">
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
