# UI patterns — selects & list modes

## Form selects (Select2 + lookups)

Searchable Select2 (Bootstrap 5 theme). Large sets via paginated lookups, not bulk dumps.

### Lookup API (`/api/v1/lookups/*`)

| Route | Permission | Params |
|-------|------------|--------|
| `GET …/users` | User `viewAny` (H-RBAC) | `q`, `page`, `per_page`, `ids[]` |
| `GET …/menus` | `view menu` | + `layout`, `exclude` |
| `GET …/roles` | `view menu` | `q`, `page`, `per_page`, `ids[]` |

Response `data`: `{ results: [{id,text}], pagination: { more } }`.  
`ids[]` without `q` hydrates edit labels. Empty `q` → first page only (`config/lookup.php`).

Implementation: Identity `LookupService` / `LookupController`. Blade proxies (auth shell): `GET /apps/lookups/{users,menus,roles}` (route names `lookups.*`).

Components: `form.select` / `multiselect` (`ajaxUrl`, `select2`). Assets: jQuery **3.7.1** + Select2 **4.0.13** (exact pin) bundled in `app.js` (Vite, npm). Do **not** bump to Select2 4.1 under Vite ESM — 4.1 maps AJAX results through unbound `AjaxAdapter.prototype._normalizeItem`, and strict-mode `this` is `undefined` → `Cannot read properties of undefined (reading 'container')`. `select2-init.js` owns the Select2 factory (`select2($)`) then defers `initAll` via `queueMicrotask` so attach never races. AJAX fields use Select2’s default **jQuery.ajax** transport (`url` + `data` + `processResults`); `processLookupResults` in `select2-lookup.js` unwraps the Monolith `{ data: { results, pagination } }` envelope. Attribute: `data-monolith-select2` (**not** `data-select2` — jQuery collision).

### Troubleshooting

- Native select still showing → `npm run build` / `dev`; ensure `app.js` loaded via `@vite` in `assets-scripts.blade.php`
- "The results could not be loaded" / Deferred `container` on `_normalizeItem` → confirm Select2 is **4.0.13** (not 4.1); hard-refresh after `npm run build`. Lookup AJAX 4xx/5xx/non-JSON also surfaces errorLoading. Do **not** reintroduce a custom `fetch` transport / abortable controller — Select2’s default `$.ajax` aborts prior requests with `status === 0` (silent). Chrome extension noise (`message channel closed before a response was received`) is unrelated.
- Page jumps up on Select2 open/close → `select2-init.js` locks `window.scrollY` around `select2:opening|closing` / `open|close` and refocuses the search field with `{ preventScroll: true }` (Select2 focuses without preventScroll and the browser scrolls the clipped original `<select>` into view).
- Dark-mode styles ignored → Select2 dark overrides in `resources/css/monolith-overrides.css` MUST stay **unlayered**. Match select2-bootstrap-5-theme specificity (e.g. `[data-bs-theme="dark"] .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered`) — a short `.select2-selection__rendered` rule loses to the theme’s hardcoded `color: #212529`. Do NOT wrap these rules in `@layer`.

## List display modes (query & DataTables)

Tabular index endpoints support:

| Signal | Response |
|--------|----------|
| default / `display_mode=query` (no paging params) | `{ status, message, data }` where `data` is a flat `T[]` |
| `display_mode=query` **and** `page` / `per_page` / `search` | `{ status, message, data: { items, pagination } }` — `pagination` is `{ current_page, last_page, per_page, total, has_more }`. Default `per_page` 10, max 100. In-memory search + slice (`ListQueryPaginator`); SQL `LIMIT` per domain is a later wave. |
| `display_mode=datatable` or `draw` present | Yajra JSON + per-row `actions[]` (unchanged) |

Covered query-paging lists: users, invoices, payment-gateways, subscriptions, services, inference providers, inference model-cards, vtual orphans, activity logs, error logs. Navigation **tree** / menus list excluded (still a full dump). Helper: `RespondsWithListDisplayMode::respondToQueryList`.

### DataTable init

Shared factory: `initDataTable({ tableId, ajaxUrl, columns, order, language, exposeForReload, onReady })` in `resources/js/datatable-init.js` (Vite entry). Six index views (users, invoices, subscriptions, payment-gateways, inference providers, inference model-cards) load it via `@vite` inside `x-slot:scripts` (after `app.js`) and call `window.initDataTable(...)` — no ESM import (Vite 8/Rolldown tree-shakes named exports from entry modules). jQuery + DataTables core binding (`window.jQuery`, `DataTable.use(jQuery)`) lives in `resources/js/infra/jquery-init.js` and runs before any consumer — DataTables 3 does not auto-detect `window.jQuery` for `.fn.DataTable` attachment. **Always set `exposeForReload: true`** on these admin lists so `bell.js` can soft-reload via `window.dataTables[tableId]` without a hard page refresh.

### Actions contract

Each action: `key`, `label`, `method`, `url`, `style`, `icon`, optional `confirm`. RBAC filters which keys appear; Blade datatable proxies **rewrite URLs** to web routes only.

### Web datatable routes

e.g. `GET /apps/users/datatable` (route `users.datatable`) → API `?display_mode=datatable` + Bearer from cookie. No list logic in web layer. Authenticated Blade lives under `/apps/*` — see [blade-admin — Routes](blade-admin.md#routes).

Implementation helpers under `App\Support\DataTable\*`; domain row action resolvers per list.

## Accessibility (ARIA)

The Blade admin follows WCAG 2.1 AA. Guards live in `tests/Feature/Http/Controllers/Web/AccessibilityBladeTest.php`.

### Required invariants

| Area | Rule | Where |
|------|------|-------|
| Skip link | `<a href="#main-content" class="visually-hidden-focusable">` first in `<body>` (**before** `.app-wrapper` — unnamed grid children break AdminLTE 4 footer/sidebar layout); `<main id="main-content">` | `layouts/app.blade.php`, `layouts/auth.blade.php` |
| Auth inputs | Visible `<label>` (not placeholder-only) | `auth/*.blade.php` via `form.input :label` |
| Icon-only buttons | `aria-label` + `title` | `menus/index.blade.php`, navbar toggles |
| Navbar dropdowns | `aria-expanded="false"` initial (Bootstrap updates dynamically) | `navbar.blade.php` |
| Active nav | `aria-current="page"` | `nav/tree.blade.php`, `nav/bar-tree.blade.php`, `sidebar-nav-search.blade.php` JS |
| Breadcrumbs | `<nav aria-label="breadcrumb">`; every parent must expose a clickable `route()` URL (no plain-text parents) | `partials/breadcrumbs.blade.php`, `routes/breadcrumbs.php` |
| DataTables | `aria-label` on `<table>`; `<caption>` where helpful | `users/index.blade.php`, `invoices/index.blade.php`, `payment-gateways/index.blade.php` |
| Status regions | `role="status"` + `aria-live="polite"` | `payment-status-hero.blade.php`, bell badge (`bell.js`), toasts (checkout / FH island) |
| Toggle buttons | `aria-pressed` | `checkout.js` channel buttons |
| Interactive divs | `role="button"` + `tabindex="0"` + Enter/Space handler | file-hosting dropzone (`FhDropzone.vue`), move-tree items |

File Hosting authenticated browse is a **domain-local Vue island SPA** (soft-nav, listing, bulk, upload dropzone, Microstock chrome) — not a global AdminLTE SPA. Public `/s/*` stays vanilla. Full behavior and asset map: [domains/file-hosting/README.md — UI architecture / SPA shell](../domains/file-hosting/README.md#ui-architecture--spa-shell).

## Vue islands

When a page needs richer client UI than vanilla ESM + jQuery plugins, mount a **Vite Vue 3 island** inside Blade — only after the dual-path checklist passes. Conventions, identification rules, mount contract, `useApi` / `MonolithApi` alignment, and anti-Inertia/CDN rules: [blade-admin — Dual-path / Vue islands](blade-admin.md#dual-path--when-to-use-which).

| Do | Don't |
|----|--------|
| SFC under `resources/js/vue/islands/{name}/` + Vite entry | Inline CDN Vue or runtime `template:` strings as house style |
| Bootstrap URLs via `route()` in Blade JSON | Hard-code `/apps/…` in SFCs |
| Fetch via `apiFetch` → web JSON proxy | Re-implement 401/419/422 toast logic outside `MonolithApi` |
| Keep DataTables/Select2 pages on existing stack until they truly hurt | Big-bang rewrite of all Blade CRUD to Vue |

Reference islands: dashboard live-session; File Hosting auth browse (XOR — no `file-manager.js`).

### Lang keys

Added under `lang/{en,id,ja}/ui.php`: `skip_to_content`, `labels.actions`, `labels.main_navigation`, `labels.breadcrumb`, `labels.users_table`, `labels.user_menu`, `actions.reset`.

## Responsive

Mobile-first adjustments live in CSS (not inline Blade styles), guarded by `ThemeOverrideCssTest`.

### Reduced motion

`monolith-overrides.css`, `file-hosting.css`, `payments/channels-admin.css`, and `payments/order-invoice-show.css` all ship `@media (prefers-reduced-motion: reduce)` blocks that shorten animations/transitions to `0.01ms` (or disable them) and disable card hover lift. Required for WCAG 2.3.3.

### Mobile navigation fallback

`bar-tree.blade.php` items are `d-none d-md-block` on the navbar row. On mobile (`< md`), the navbar renders a "More" dropdown (`d-md-none`) that exposes the same tree as nested `dropdown-item`s, so navbar-layout menu items stay reachable without the sidebar.

### Modals

File-hosting modals (`show.blade.php` rename/share, `file-hosting/partials/move-modal.blade.php`) use `modal-fullscreen-sm-down` so they fill the viewport on phones.

### Sidebar-first column ordering

Detail pages with a main content column + sidebar (invoice show, service-purchase hub) put the **sidebar first on mobile** so the user sees summary/amounts before the long main panel. Source order stays main-first (DOM/SEO/screen-reader friendly); Bootstrap `order-*` utilities reflow visually:

```blade
<div class="col-lg-8 order-2 order-lg-1">  {{-- main: first in DOM, second on mobile, first on ≥lg --}}
<div class="col-lg-4 order-1 order-lg-2">  {{-- sidebar: second in DOM, first on mobile, second on ≥lg --}}
```

Applied to `invoices/show.blade.php` and `service-purchases/show.blade.php`. Reuse this pair for any new main+sidebar detail page.

### Inline styles

Inline `style="max-width: …"` / `padding-left: …px` were removed. Use utility classes (`ps-3`, `ps-4`) or the `.monolith-truncate-md` helper (28rem, full-width under 576px).

### Radius consistency

`payments/channels-admin.css` and `payments/order-invoice-show.css` use `var(--monolith-radius)` / `var(--monolith-radius-sm)` for every border-radius (previously mixed legacy `--pc-radius` / `--ois-radius` tokens and hardcoded `0.5` / `0.65` / `0.75rem`). Tokens consolidated to `--monolith-*` namespace; legacy `--pc-*` / `--ois-*` radius/border/muted aliases removed in favor of `--monolith-border-subtle` / `--monolith-text-muted`.

## Empty states

Use `<x-adminlte4::empty-state :icon="…" :title="…" :message="…" />` for all "no data" rows/panels. It renders `role="status"`, a muted icon, title, and optional message.

Convention for table empty rows:

```blade
@empty
    <tr>
        <td colspan="N">
            <x-adminlte4::empty-state icon="bi-inbox" :title="__('web/domain.empty')" />
        </td>
    </tr>
@endforelse
```

Adopted in: `notifications/index`, `services/index`, `error-logs/index`, `activity-logs/index`, `menus/index`. File-hosting trash keeps its own `.fh-empty` block (richer icon layout).

## Toasts (replacing window.alert)

Toast routing goes through `window.MonolithSwal.toastMessage(message, type)` — a SweetAlert2 toast mixin (position `top-end`, 4s timer, progress bar, pause on hover). The legacy `.monolith-toast` CSS component (defined in `monolith-overrides.css`) is retained for domain consumers that build toast elements manually (checkout, channels-admin, domain-access, inference rate-plans) via local `showToast(message, type)` helpers; File Hosting Vue island uses `MonolithSwal` via `useFhApi`. Those containers expose `role="status"` + `aria-live="polite"`. `window.alert` is forbidden — use `MonolithSwal.alert` (modal) or `MonolithSwal.toastMessage` (toast). Status transitions (paid / expired / cancelled / failed) soft-patch the same page chrome without hard reload — see [payment-gateway — Realtime invoice status](../domains/payment-gateway/README.md#realtime-invoice-status-no-hard-reload).

## SweetAlert2 / confirmations

SweetAlert2 (`sweetalert2` npm) replaces every `window.alert` / `window.confirm` in the admin. The wrapper at `resources/js/infra/sweetalert.js` (bundled in `app.js`) exposes `window.MonolithSwal`:

| Method | Signature | Replaces |
|--------|-----------|----------|
| `alert(message, title?)` | `Promise<void>` — themed modal, single OK | `window.alert` |
| `confirm(message, title?, opts?)` | `Promise<boolean>` — `true` if confirmed; `opts.dangerMode` shows warning icon + reverse buttons | `window.confirm` |
| `toastMessage(message, type?)` | `void` — `type`: `success` \| `error` \| `info` \| `warning` | inline status toasts |
| `Swal` | raw SweetAlert2 instance for advanced use | — |

### Form confirm delegation (`data-swal-confirm`)

Blade forms marked with `data-swal-confirm="{{ __('...') }}"` trigger a SweetAlert2 confirmation before submit. `app.js` binds a global `submit` listener: it calls `MonolithSwal.confirm(message, '', { dangerMode: true })`, and only submits if confirmed. This replaces inline `onsubmit="return confirm(...)"`.

```blade
<form method="POST" action="{{ route('services.destroy', $service) }}"
      data-swal-confirm="{{ __('ui.swal.confirm') }}">
```

### i18n label blob

Button labels are injected via a JSON blob in `assets-scripts.blade.php`:

```blade
<script type="application/json" data-monolith-swal-labels>
    @json(['ok' => __('ui.swal.ok'), 'confirm' => __('ui.swal.confirm'), 'cancel' => __('ui.swal.cancel')])
</script>
```

Keys live under `swal.*` in `lang/{en,id,ja}/ui.php`. The wrapper reads them at boot; missing keys fall back to English literals.

### Adoption

- **`data-swal-confirm` forms**: `file-hosting/blocklist.blade.php`, `product-activations/show.blade.php`, `services/index.blade.php`, `file-hosting/partials/team-offcanvas.blade.php`.
- **`MonolithSwal.alert` / `confirm` helpers**: `file-hosting/direct-upload.js` (`monolithAlert`), Vue FH `useFhApi` (`fhConfirm` / `toast`). Each helper feature-detects `window.MonolithSwal` and falls back to `window.alert` / `window.confirm` if absent.

## Theme-color meta

`assets-head.blade.php` pins `<meta name="theme-color">` to `#6366f1` (indigo accent) for light and `#1e293b` (slate) for dark — matches the `--monolith-accent` token, not Bootstrap blue.

## Action buttons (list rows)

Row action groups use a single `btn-group` with direct-children buttons only. Nested `<form>` inside `btn-group` breaks alignment.

### Row action groups (shared renderer + manual Blade)

The shared DataTable renderer `window.renderDatatableActions` (in `components/partials/datatable-row-actions.blade.php`) wraps every row's action set in `<div class="btn-group btn-group-sm" role="group" aria-label="…">`. Each icon-only button carries both `aria-label` and `title` (set from `action.label`); icons use `aria-hidden="true"`. No `ms-*` margin utilities — spacing comes from `.btn-group`.

Manual Blade row-action clusters (e.g. `services/index`, `product-activations/show`) follow the same contract: wrap the per-row actions in `<div class="btn-group btn-group-sm" role="group">` (Bootstrap rows). File Hosting auth browse row actions live in the Vue island (`App.vue` / `.fh-action-group` in `file-hosting.css`) — not archived `resources/views/_archive/adminlte4/file-hosting/partials/browse-views`. Every icon-only button must carry `aria-label` + `title`.

Forms carrying destructive actions use `class="d-inline-block"` (not `d-inline`) so they sit cleanly as a direct child of the `btn-group`; the submit button inside carries `[data-form-submit-spinner]` (see [blade-admin — UX patterns](blade-admin.md#ux-patterns)).

### Destructive cascade pattern

When a delete action has a cascade variant (e.g. menus with descendants), render **one** delete trigger per row that opens a shared confirmation modal. The modal carries a cascade checkbox; JS swaps the form `action` between the default and cascade URLs before submit. Do not stack two sibling delete buttons inline.

Reference: `menus/index.blade.php` (`#menuDeleteConfirmModal` + `data-menu-cascade-url` + `data-menu-confirm-template`).

## Card alignment (paired columns)

When two cards sit in sibling columns (e.g. `users/edit.blade.php` edit form + recent activity), both must align flush: no `mt-3` on either card. If one column may be empty at runtime, render an `<x-adminlte4::empty-state>` inside the card so the column does not collapse and break the grid symmetry.

Reference: `users/partials/activity-history.blade.php`.

## File-hosting toolbar

`.fh-toolbar` declares a full `border: 1px solid var(--fh-border)` and uniform `border-radius: var(--fh-radius)` so the search box reads as a symmetric shell (not a half-open bar). Auth browse listing/toolbar/empty-state colspan are painted by the Vue island (`App.vue`); Blade index only mounts `#vue-file-hosting` + modal/offcanvas shells. Archived `toolbar-zone` / `browse-views` under `resources/views/_archive/adminlte4/file-hosting/partials/` are not the live paint path.

## Related

- [blade-admin.md](blade-admin.md)
- [domains/identity](../domains/identity/README.md)
- [domains/navigation](../domains/navigation/README.md)

## Known bridge gaps (realtime)

- **`__monolithEcho`**: Pusher/Echo instance exposed on `window.__monolithEcho` by `bell.js` (notifications; loaded from `assets-scripts` after `app.js`, before page `$scripts`). Other domain modules (file-hosting realtime) attach to the same instance if present, but there is no shared "echo registry" — each consumer must feature-detect.
- **Bell → DataTable reload**: `bell.js` reloads DataTables via `window.dataTables[tableId].ajax.reload()` (set by `initDataTable({ exposeForReload: true })`). This bridge is string-keyed by table ID — no type safety, no event contract. A missing table ID silently skips reload.
- **Polling fallback**: `users/index.blade.php` inline `onReady` polls via `setInterval(60s)` when realtime is degraded (`notification-realtime:degraded`), in pure polling / failed-config mode (`notification-realtime:polling`), or already disabled. The poll is torn down on reconnect (`notification-realtime:connected`). No overlap with WS pushes.
