{{--
    Recursive AdminLTE sidebar renderer for the dynamic navigation tree.

    $items is the array produced by MenuService::navigationTree() — each node is
    { id, name, icon, route, type, children[] }. Visibility (roles, auth/guest
    flags, user include/exclude) is ALREADY resolved by the engine, so there is
    no @can gating here: a node only reaches this view if the viewer may see it.

    Node types:
      h → section label (children render as siblings, AdminLTE-style)
      p → collapsible branch (children nested in .nav-treeview)
      c → leaf link
--}}
@props(['items' => []])

@php
    // A branch is "active/open" when it (or any descendant) targets the current route.
    $isActive = function (array $node) use (&$isActive): bool {
        $route = $node['route'] ?? null;
        if ($route && \Illuminate\Support\Facades\Route::has($route) && request()->routeIs($route)) {
            return true;
        }
        foreach ($node['children'] ?? [] as $child) {
            if ($isActive($child)) {
                return true;
            }
        }
        return false;
    };

    // Route names are stored, not URLs. Unregistered names degrade to "#".
    $href = fn (?string $name): string => $name && \Illuminate\Support\Facades\Route::has($name) ? route($name) : '#';
@endphp

@foreach ($items as $item)
    @php
        $children = $item['children'] ?? [];
        $type = $item['type'] ?? 'c';
        $active = $isActive($item);
    @endphp

    @if ($type === 'h')
        <li class="nav-header">{{ Str::upper($item['name']) }}</li>
        <x-nav.tree :items="$children" />
    @elseif (! empty($children))
        <li class="nav-item {{ $active ? 'menu-open' : '' }}">
            <a href="{{ $href($item['route'] ?? null) }}" class="nav-link {{ $active ? 'active' : '' }}">
                <i class="nav-icon {{ $item['icon'] ?: 'bi bi-circle' }}"></i>
                <p>
                    {{ $item['name'] }}
                    <i class="nav-arrow bi bi-chevron-right"></i>
                </p>
            </a>
            <ul class="nav nav-treeview">
                <x-nav.tree :items="$children" />
            </ul>
        </li>
    @else
        <li class="nav-item">
            <a href="{{ $href($item['route'] ?? null) }}" class="nav-link {{ $active ? 'active' : '' }}">
                <i class="nav-icon {{ $item['icon'] ?: 'bi bi-circle' }}"></i>
                <p>{{ $item['name'] }}</p>
            </a>
        </li>
    @endif
@endforeach
