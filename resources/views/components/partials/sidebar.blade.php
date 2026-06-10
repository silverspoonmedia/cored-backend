{{--
    Admin sidebar — fully data-driven by the dynamic navigation engine.
    $navigation is the per-viewer filtered tree shared by NavigationComposer
    (MenuService::navigationTree). No hardcoded items, no @can gating: the
    engine already resolved exactly what this signed-in user may see.
--}}
<aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
    <div class="sidebar-brand">
        <a href="{{ route('dashboard') }}" class="brand-link">
            <span class="brand-text fw-light"><b>Mono</b>lith</span>
        </a>
    </div>

    <div class="sidebar-wrapper">
        <nav class="mt-2">
            <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="navigation" aria-label="Main navigation" data-accordion="false">
                <x-nav.tree :items="$navigation ?? []" />
            </ul>
        </nav>
    </div>
</aside>
