{{--
    Admin sidebar — fully data-driven by the dynamic navigation engine.
    $navigation is the sidebar-layout tree from NavigationComposer
    (MenuService::navigationTree, layout=sidebar). No hardcoded items.
--}}
<aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
    <div class="sidebar-brand">
        <a href="{{ route('dashboard') }}" class="brand-link">
            <span class="brand-text fw-light"><b>Mono</b>lith</span>
        </a>
    </div>

    <div class="sidebar-wrapper">
        <x-adminlte4::partials.sidebar-nav-search :navigation="$navigation ?? []" />

        <nav class="mt-2">
            <ul
                id="sidebar-menu-root"
                class="nav sidebar-menu flex-column"
                data-lte-toggle="treeview"
                role="navigation"
                aria-label="Main navigation"
                data-accordion="false"
            >
                <x-adminlte4::nav.tree :items="$navigation ?? []" />
            </ul>
        </nav>
    </div>
</aside>
