{{--
    Real-time sidebar navigation filter. Filters the embedded tree in-memory
    (same rules as NavigationTreeFilter on the API) so keystrokes stay instant.
    GET /navigation/tree (?q=) is also available as a server-filtered proxy.
--}}
@php
    $navTree = $navigation ?? [];
    $navRouteMap = \App\Domain\Navigation\Support\NavigationRouteMap::fromTree($navTree);
    $activeRoute = request()->route()?->getName();
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

<script>
    (() => {
        'use strict';

        const config = {
            tree: @json($navTree),
            routeMap: @json($navRouteMap),
            activeRoute: @json($activeRoute),
            noResults: @json(__('web/sidebar.search_no_results')),
        };

        const normalize = (value) => String(value ?? '').trim().toLowerCase();

        const matchesNode = (node, needle) => {
            for (const field of ['name', 'description', 'route']) {
                const value = node[field];
                if (typeof value === 'string' && value !== '' && normalize(value).includes(needle)) {
                    return true;
                }
            }
            return false;
        };

        const filterNode = (node, needle) => {
            const filteredChildren = (node.children ?? [])
                .map((child) => filterNode(child, needle))
                .filter((child) => child !== null);

            if (!matchesNode(node, needle) && filteredChildren.length === 0) {
                return null;
            }

            return { ...node, children: filteredChildren };
        };

        const filterTree = (tree, query) => {
            const needle = normalize(query);
            if (needle === '') {
                return tree;
            }

            return tree
                .map((node) => filterNode(node, needle))
                .filter((node) => node !== null);
        };

        const isActive = (node, activeRoute) => {
            if (activeRoute && node.route === activeRoute) {
                return true;
            }

            return (node.children ?? []).some((child) => isActive(child, activeRoute));
        };

        const escapeHtml = (value) => String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;');

        const hrefFor = (routeName) => {
            if (routeName && config.routeMap[routeName]) {
                return config.routeMap[routeName];
            }
            return '#';
        };

        const renderTree = (items, isSearching) => {
            let html = '';

            for (const item of items) {
                const children = item.children ?? [];
                const type = item.type ?? 'c';
                const active = isActive(item, config.activeRoute);
                const icon = item.icon || 'bi bi-circle';
                const href = hrefFor(item.route ?? null);

                if (type === 'h') {
                    html += `<li class="nav-header">${escapeHtml(String(item.name).toUpperCase())}</li>`;
                    html += renderTree(children, isSearching);
                    continue;
                }

                if (children.length > 0) {
                    const openClass = isSearching || active ? 'menu-open' : '';
                    html += `<li class="nav-item ${openClass}">`;
                    html += `<a href="${escapeHtml(href)}" class="nav-link ${active ? 'active' : ''}">`;
                    html += `<i class="nav-icon ${escapeHtml(icon)}"></i>`;
                    html += `<p>${escapeHtml(item.name)}<i class="nav-arrow bi bi-chevron-right"></i></p>`;
                    html += '</a>';
                    html += `<ul class="nav nav-treeview">${renderTree(children, isSearching)}</ul>`;
                    html += '</li>';
                    continue;
                }

                html += `<li class="nav-item">`;
                html += `<a href="${escapeHtml(href)}" class="nav-link ${active ? 'active' : ''}">`;
                html += `<i class="nav-icon ${escapeHtml(icon)}"></i>`;
                html += `<p>${escapeHtml(item.name)}</p>`;
                html += '</a></li>';
            }

            return html;
        };

        document.addEventListener('DOMContentLoaded', () => {
            const input = document.querySelector('[data-sidebar-nav-search]');
            const root = document.getElementById('sidebar-menu-root');

            if (!input || !root) {
                return;
            }

            const originalHtml = root.innerHTML;

            const applyFilter = () => {
                const query = input.value;
                const needle = normalize(query);

                if (needle === '') {
                    root.innerHTML = originalHtml;
                    return;
                }

                const filtered = filterTree(config.tree, query);

                if (filtered.length === 0) {
                    root.innerHTML = `<li class="nav-item px-3 py-2 text-muted small">${escapeHtml(config.noResults)}</li>`;
                    return;
                }

                root.innerHTML = renderTree(filtered, true);
            };

            input.addEventListener('input', applyFilter);
            input.addEventListener('search', applyFilter);
        });
    })();
</script>
