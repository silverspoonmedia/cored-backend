{{--
    Horizontal navbar renderer for the dynamic navigation tree (navbar layout).

    $items — same shape as MenuService::navigationTree(). Visibility is already
    resolved by the engine. Headers (h) flatten into their children because
    section labels do not fit the top navbar row.
--}}
@props(['items' => [], 'nested' => false])

@php
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

    $href = fn (?string $name): string => $name && \Illuminate\Support\Facades\Route::has($name) ? route($name) : '#';
@endphp

@foreach ($items as $item)
    @php
        $children = $item['children'] ?? [];
        $type = $item['type'] ?? 'c';
        $active = $isActive($item);
        $icon = $item['icon'] ?? null;
    @endphp

    @if ($type === 'h')
        <x-adminlte4::nav.bar-tree :items="$children" :nested="$nested" />
    @elseif ($nested)
        @if (! empty($children))
            <li><h6 class="dropdown-header">{{ $item['name'] }}</h6></li>
            <x-adminlte4::nav.bar-tree :items="$children" nested />
        @else
            <li>
                <a href="{{ $href($item['route'] ?? null) }}" @class(['dropdown-item', 'active' => $active]) @if ($active) aria-current="page" @endif>
                    @if ($icon)<i class="{{ $icon }} me-1" aria-hidden="true"></i>@endif
                    {{ $item['name'] }}
                </a>
            </li>
        @endif
    @elseif (! empty($children))
        <li class="nav-item dropdown d-none d-md-block">
            <a href="{{ $href($item['route'] ?? null) }}" class="nav-link dropdown-toggle {{ $active ? 'active' : '' }}" data-bs-toggle="dropdown" role="button" aria-expanded="false" @if ($active) aria-current="page" @endif>
                @if ($icon)<i class="{{ $icon }} me-1" aria-hidden="true"></i>@endif
                {{ $item['name'] }}
            </a>
            <ul class="dropdown-menu">
                <x-adminlte4::nav.bar-tree :items="$children" nested />
            </ul>
        </li>
    @else
        <li class="nav-item d-none d-md-block">
            <a href="{{ $href($item['route'] ?? null) }}" class="nav-link {{ $active ? 'active' : '' }}" @if ($active) aria-current="page" @endif>
                @if ($icon)<i class="{{ $icon }} me-1" aria-hidden="true"></i>@endif
                {{ $item['name'] }}
            </a>
        </li>
    @endif
@endforeach
