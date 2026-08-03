{{--
    Real-time sidebar navigation filter. Filters the embedded tree in-memory
    (same rules as NavigationTreeFilter on the API) so keystrokes stay instant.
    GET /navigation/tree (?q=) is also available as a server-filtered proxy.
--}}
@php
    $navTree = $navigation ?? [];
    $navRouteMap = \App\Domain\Navigation\Support\NavigationRouteMap::fromTree($navTree);
    $activeRoute = request()->route()?->getName();
    $sidebarNavConfig = [
        'tree' => $navTree,
        'routeMap' => $navRouteMap,
        'activeRoute' => $activeRoute,
        'noResults' => __('web/sidebar.search_no_results'),
    ];
@endphp

<div class="sidebar-search px-3 pb-2">
    <input
        type="search"
        class="form-control form-control-sm border-0"
        placeholder="{{ __('web/sidebar.search_placeholder') }}"
        data-sidebar-nav-search
        autocomplete="off"
        spellcheck="false"
        aria-label="{{ __('web/sidebar.search_label') }}"
        aria-controls="sidebar-menu-root"
    >
</div>

<script type="application/json" data-sidebar-nav-config>@json($sidebarNavConfig)</script>
<script type="module" src="{{ Vite::asset('resources/js/navigation/sidebar-search.js') }}"></script>
