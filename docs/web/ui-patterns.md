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

Components: `form.select` / `multiselect` (`ajaxUrl`, `select2`). Assets: `select2-init.js` (Vite). Attribute: `data-monolith-select2` (**not** `data-select2` — jQuery collision).

### Troubleshooting

- Native select still showing → `npm run build` / `dev`; ensure select2 scripts after jQuery  

## List display modes (query & DataTables)

Tabular index endpoints support:

| Signal | Response |
|--------|----------|
| default / `display_mode=query` | `{ status, message, data }` |
| `display_mode=datatable` or `draw` present | Yajra JSON + per-row `actions[]` |

Covered lists: v1 users, menus list/trashed, file-hosting lists where applicable. Navigation **tree** excluded.

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
| Skip link | `<a href="#main-content" class="visually-hidden-focusable">` first in `<body>`; `<main id="main-content">` | `layouts/app.blade.php`, `layouts/auth.blade.php` |
| Auth inputs | Visible `<label>` (not placeholder-only) | `auth/*.blade.php` via `form.input :label` |
| Icon-only buttons | `aria-label` + `title` | `menus/index.blade.php`, navbar toggles |
| Navbar dropdowns | `aria-expanded="false"` initial (Bootstrap updates dynamically) | `navbar.blade.php` |
| Active nav | `aria-current="page"` | `nav/tree.blade.php`, `nav/bar-tree.blade.php`, `sidebar-nav-search.blade.php` JS |
| Breadcrumbs | `<nav aria-label="breadcrumb">`; every parent must expose a clickable `route()` URL (no plain-text parents) | `partials/breadcrumbs.blade.php`, `routes/breadcrumbs.php` |
| DataTables | `aria-label` on `<table>`; `<caption>` where helpful | `users/index.blade.php`, `invoices/index.blade.php`, `payment-gateways/index.blade.php` |
| Status regions | `role="status"` + `aria-live="polite"` | `payment-status-hero.blade.php`, bell badge (`bell.js`), toasts (`file-manager.js`) |
| Toggle buttons | `aria-pressed` | `checkout.js` channel buttons |
| Interactive divs | `role="button"` + `tabindex="0"` + Enter/Space handler | file-hosting dropzone, move-tree items (`file-manager.js`) |

File Hosting browse is a **domain-local SPA shell** (select, context menu, drag-move, bulk bar, soft-nav, live listing) — not a global AdminLTE SPA. Full behavior and asset map: [domains/file-hosting/README.md — UI architecture / SPA shell](../domains/file-hosting/README.md#ui-architecture--spa-shell).

### Lang keys

Added under `lang/{en,id,ja}/ui.php`: `skip_to_content`, `labels.actions`, `labels.main_navigation`, `labels.breadcrumb`, `labels.users_table`, `labels.user_menu`, `actions.reset`.

## Responsive

Mobile-first adjustments live in CSS (not inline Blade styles), guarded by `ThemeOverrideCssTest`.

### Reduced motion

Both `monolith-overrides.css` and `file-hosting.css` ship `@media (prefers-reduced-motion: reduce)` blocks that shorten animations/transitions to `0.01ms` and disable card hover lift. Required for WCAG 2.3.3.

### Mobile navigation fallback

`bar-tree.blade.php` items are `d-none d-md-block` on the navbar row. On mobile (`< md`), the navbar renders a "More" dropdown (`d-md-none`) that exposes the same tree as nested `dropdown-item`s, so navbar-layout menu items stay reachable without the sidebar.

### Modals

File-hosting modals (`show.blade.php` rename/share, `_move-modal.blade.php`) use `modal-fullscreen-sm-down` so they fill the viewport on phones.

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

`payments/channels-admin.css` uses `var(--pc-radius)` for every border-radius (previously mixed 0.5 / 0.65 / 0.75rem).

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

Checkout errors/confirmations use `showCheckoutToast(message, type)` (`checkout.js`) with `.ois-toast` styles in `order-invoice-show.css`. The toast container exposes `role="status"` + `aria-live="polite"`. `window.alert` is forbidden in checkout. Status transitions (paid / expired / cancelled / failed) soft-patch the same page chrome without hard reload — see [payment-gateway — Realtime invoice status](../domains/payment-gateway/README.md#realtime-invoice-status-no-hard-reload).

## Theme-color meta

`assets-head.blade.php` pins `<meta name="theme-color">` to `#6366f1` (indigo accent) for light and `#1e293b` (slate) for dark — matches the `--monolith-accent` token, not Bootstrap blue.

## Action buttons (list rows)

Row action groups use a single `btn-group` with direct-children buttons only. Nested `<form>` inside `btn-group` breaks alignment.

### Destructive cascade pattern

When a delete action has a cascade variant (e.g. menus with descendants), render **one** delete trigger per row that opens a shared confirmation modal. The modal carries a cascade checkbox; JS swaps the form `action` between the default and cascade URLs before submit. Do not stack two sibling delete buttons inline.

Reference: `menus/index.blade.php` (`#menuDeleteConfirmModal` + `data-menu-cascade-url` + `data-menu-confirm-template`).

## Card alignment (paired columns)

When two cards sit in sibling columns (e.g. `users/edit.blade.php` edit form + recent activity), both must align flush: no `mt-3` on either card. If one column may be empty at runtime, render an `<x-adminlte4::empty-state>` inside the card so the column does not collapse and break the grid symmetry.

Reference: `users/_activity_history.blade.php`.

## File-hosting toolbar

`.fh-toolbar` declares a full `border: 1px solid var(--fh-border)` and uniform `border-radius: var(--fh-radius)` so the search box reads as a symmetric shell (not a half-open bar). The list-view empty row spans all columns (`colspan="8"`).

## Related

- [blade-admin.md](blade-admin.md)
- [domains/identity](../domains/identity/README.md)
- [domains/navigation](../domains/navigation/README.md)
