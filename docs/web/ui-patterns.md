# Blade UI patterns (archived)

> **Archived — nothing here is live.** The in-repo Blade + AdminLTE 4 admin was removed; see [blade-admin.md](blade-admin.md). Every file path, asset, component tag, and JS module below is deleted. This document is kept as the record of what the Blade admin did and which conventions the SPA inherited or deliberately dropped. Do **not** follow it as a spec, and do **not** recreate these patterns in Monolith — UI belongs in `monolith-vue-workers`.
>
> **The live parts moved out.** The `display_mode` list contract, the row-action payload, and the `/api/v1/lookups/*` API are all API-layer concerns and now live in **[platform/list-display-modes.md](../platform/list-display-modes.md)**. Cite that file, not this one.

## Form selects (Select2 + lookups)

Searchable Select2 (Bootstrap 5 theme), large sets via paginated lookups. The lookup API survived and is specified in [list-display-modes.md](../platform/list-display-modes.md); the Blade side did not.

Blade components were `form.select` / `multiselect` (`ajaxUrl`, `select2`), with jQuery **3.7.1** + Select2 **4.0.13** pinned exactly in `app.js`. The pin mattered: Select2 4.1 under Vite ESM maps AJAX results through unbound `AjaxAdapter.prototype._normalizeItem`, and strict-mode `this` is `undefined` → `Cannot read properties of undefined (reading 'container')`. `select2-init.js` owned the factory and deferred `initAll` via `queueMicrotask` so attach never raced. AJAX fields used Select2's default jQuery.ajax transport, never a custom `fetch` transport — Select2's `$.ajax` aborts prior requests with `status === 0` silently, which a hand-rolled abortable controller turns into visible errors. The attribute was `data-monolith-select2`, not `data-select2` (jQuery collision).

**Inherited by the SPA:** the exact-pin rule and the no-custom-transport rule still apply to `FormSelect2.vue`, which wraps the same jQuery Select2 build. The dark-mode caveat also carried over — Select2 dark overrides must stay **unlayered** and match `select2-bootstrap-5-theme` specificity, because a short `.select2-selection__rendered` rule loses to the theme's hardcoded `color: #212529`.

## List display modes

Moved to [platform/list-display-modes.md](../platform/list-display-modes.md), including the note that `display_mode=datatable` now has no consumer.

The Blade side of it — `initDataTable({ tableId, ajaxUrl, columns, order, language, exposeForReload, onReady })` in `resources/js/datatable-init.js`, the `resources/js/infra/jquery-init.js` core binding, the `exposeForReload: true` convention that let `bell.js` soft-reload a table, and the `GET /apps/{resource}/datatable` web proxies that rewrote action URLs onto web routes — is all gone.

## Accessibility (ARIA)

The Blade admin targeted WCAG 2.1 AA, guarded by a Blade-specific test that no longer exists. The invariants below are worth honoring in the SPA even though the file paths are dead.

### Required invariants

| Area | Rule | Where |
|------|------|-------|
| Skip link | `<a href="#main-content" class="visually-hidden-focusable">` first in `<body>` (**before** `.app-wrapper` — unnamed grid children break AdminLTE 4 footer/sidebar layout); `<main id="main-content">` | `layouts/app.blade.php`, `layouts/auth.blade.php` |
| Auth inputs | Visible `<label>` (not placeholder-only) | `auth/*.blade.php` via `form.input :label` |
| Icon-only buttons | `aria-label` + `title` | `menus/index.blade.php`, navbar toggles |
| Navbar dropdowns | `aria-expanded="false"` initial (Bootstrap updates dynamically) | `navbar.blade.php` |
| Active nav | `aria-current="page"` | `nav/tree.blade.php`, `nav/bar-tree.blade.php`, `sidebar-nav-search.blade.php` JS |
| Breadcrumbs | `<nav aria-label="breadcrumb">`; every parent must expose a clickable URL (no plain-text parents) | `partials/breadcrumbs.blade.php` + the former `routes/breadcrumbs.php` registry |
| DataTables | `aria-label` on `<table>`; `<caption>` where helpful | `users/index.blade.php`, `invoices/index.blade.php`, `payment-gateways/index.blade.php` |
| Status regions | `role="status"` + `aria-live="polite"` | `payment-status-hero.blade.php`, bell badge (`bell.js`), toasts (checkout / Archivd island) |
| Toggle buttons | `aria-pressed` | `checkout.js` channel buttons |
| Interactive divs | `role="button"` + `tabindex="0"` + Enter/Space handler | archivd dropzone (`ArDropzone.vue`), move-tree items |

Archivd authenticated browse was a **domain-local Vue island** (soft-nav, listing, bulk, upload dropzone, Microstock chrome) rather than a global AdminLTE SPA. Historical asset map: [domains/archivd/README.md — UI architecture / SPA shell](../domains/archivd/README.md#ui-architecture--spa-shell).

## Vue islands (obsolete concept)

The "Vue island inside Blade" pattern existed because Blade owned first paint. With Blade gone the whole dual-path question is moot — the SPA is one Vue application. The old house rules (SFC under `resources/js/vue/islands/{name}/` with a Vite entry, bootstrap URLs minted by `route()` into a Blade JSON block, fetch through `apiFetch` → web JSON proxy, no CDN Vue, no runtime `template:` strings) applied to a build that no longer exists.

The two islands that shipped were the dashboard live-session widget and Archivd auth browse.

### Lang keys

Added under `lang/{en,id,ja,ko,zh_CN,ar,ru}/ui.php`: `skip_to_content`, `labels.actions`, `labels.main_navigation`, `labels.breadcrumb`, `labels.users_table`, `labels.user_menu`, `actions.reset`.

## Responsive

Mobile-first adjustments live in CSS (not inline Blade styles), guarded by `ThemeOverrideCssTest`.

### Reduced motion

`monolith-overrides.css`, `archivd.css`, `payments/channels-admin.css`, and `payments/order-invoice-show.css` all ship `@media (prefers-reduced-motion: reduce)` blocks that shorten animations/transitions to `0.01ms` (or disable them) and disable card hover lift. Required for WCAG 2.3.3.

### Mobile navigation fallback

`bar-tree.blade.php` items are `d-none d-md-block` on the navbar row. On mobile (`< md`), the navbar renders a "More" dropdown (`d-md-none`) that exposes the same tree as nested `dropdown-item`s, so navbar-layout menu items stay reachable without the sidebar.

### Modals

archivd modals (`show.blade.php` rename/share, `archivd/partials/move-modal.blade.php`) use `modal-fullscreen-sm-down` so they fill the viewport on phones.

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

Adopted in: `notifications/index`, `services/index`, `error-logs/index`, `activity-logs/index`, `menus/index`. archivd trash keeps its own `.ar-empty` block (richer icon layout).

## Toasts (replacing window.alert)

Toast routing goes through `window.MonolithSwal.toastMessage(message, type)` — a SweetAlert2 toast mixin (position `top-end`, 4s timer, progress bar, pause on hover). The legacy `.monolith-toast` CSS component (defined in `monolith-overrides.css`) is retained for domain consumers that build toast elements manually (checkout, channels-admin, domain-access, inference rate-plans) via local `showToast(message, type)` helpers; Archivd Vue island uses `MonolithSwal` via `useArchivd`. Those containers expose `role="status"` + `aria-live="polite"`. `window.alert` is forbidden — use `MonolithSwal.alert` (modal) or `MonolithSwal.toastMessage` (toast). Status transitions (paid / expired / cancelled / failed) soft-patch the same page chrome without hard reload — see [payment-gateway — Realtime invoice status](../domains/payment-gateway/README.md#realtime-invoice-status-no-hard-reload).

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

Keys live under `swal.*` in `lang/{en,id,ja,ko,zh_CN,ar,ru}/ui.php`. The SPA reads equivalent `ui.swal.*` keys from all seven JSON locales; missing keys fail parity checks rather than silently falling back to English literals.

### Adoption

- **`data-swal-confirm` forms**: `archivd/blocklist.blade.php`, `product-activations/show.blade.php`, `services/index.blade.php`, `archivd/partials/team-offcanvas.blade.php`.
- **`MonolithSwal.alert` / `confirm` helpers**: `archivd/direct-upload.js` (`monolithAlert`), Vue Archivd `useArchivd` (`arConfirm` / `toast`). Each helper feature-detects `window.MonolithSwal` and falls back to `window.alert` / `window.confirm` if absent.

## Theme-color meta

`assets-head.blade.php` pins `<meta name="theme-color">` to `#6366f1` (indigo accent) for light and `#1e293b` (slate) for dark — matches the `--monolith-accent` token, not Bootstrap blue.

## Action buttons (list rows)

Row action groups use a single `btn-group` with direct-children buttons only. Nested `<form>` inside `btn-group` breaks alignment.

### Row action groups (shared renderer + manual Blade)

The shared DataTable renderer `window.renderDatatableActions` (in `components/partials/datatable-row-actions.blade.php`) wraps every row's action set in `<div class="btn-group btn-group-sm" role="group" aria-label="…">`. Each icon-only button carries both `aria-label` and `title` (set from `action.label`); icons use `aria-hidden="true"`. No `ms-*` margin utilities — spacing comes from `.btn-group`.

Manual Blade row-action clusters (e.g. `services/index`, `product-activations/show`) follow the same contract: wrap the per-row actions in `<div class="btn-group btn-group-sm" role="group">` (Bootstrap rows). Archivd auth browse row actions live in the Vue island (`App.vue` / `.ar-action-group` in `archivd.css`) — not archived `resources/views/_archive/adminlte4/archivd/partials/browse-views`. Every icon-only button must carry `aria-label` + `title`.

Forms carrying destructive actions used `class="d-inline-block"` (not `d-inline`) so they sat cleanly as a direct child of the `btn-group`; the submit button inside carried `[data-form-submit-spinner]`.

### Destructive cascade pattern

When a delete action has a cascade variant (e.g. menus with descendants), render **one** delete trigger per row that opens a shared confirmation modal. The modal carries a cascade checkbox; JS swaps the form `action` between the default and cascade URLs before submit. Do not stack two sibling delete buttons inline.

Reference: `menus/index.blade.php` (`#menuDeleteConfirmModal` + `data-menu-cascade-url` + `data-menu-confirm-template`).

## Card alignment (paired columns)

When two cards sit in sibling columns (e.g. `users/edit.blade.php` edit form + recent activity), both must align flush: no `mt-3` on either card. If one column may be empty at runtime, render an `<x-adminlte4::empty-state>` inside the card so the column does not collapse and break the grid symmetry.

Reference: `users/partials/activity-history.blade.php`.

## archivd toolbar

`.ar-toolbar` declares a full `border: 1px solid var(--ar-border)` and uniform `border-radius: var(--ar-radius)` so the search box reads as a symmetric shell (not a half-open bar). Auth browse listing/toolbar/empty-state colspan are painted by the Vue island (`App.vue`); Blade index only mounts `#vue-archivd` + modal/offcanvas shells. Archived `toolbar-zone` / `browse-views` under `resources/views/_archive/adminlte4/archivd/partials/` are not the live paint path.

## Related

- [platform/list-display-modes.md](../platform/list-display-modes.md) — the live contract extracted from this file
- [blade-admin.md](blade-admin.md) — what remains on the `web` stack
- [integration/vue-workers-spa.md](../integration/vue-workers-spa.md) — the SPA that replaced all of the above

## Known bridge gaps (realtime)

- **`__monolithEcho`**: Pusher/Echo instance exposed on `window.__monolithEcho` by `bell.js` (notifications; loaded from `assets-scripts` after `app.js`, before page `$scripts`). Other domain modules (archivd realtime) attach to the same instance if present, but there is no shared "echo registry" — each consumer must feature-detect.
- **Bell → DataTable reload**: `bell.js` reloads DataTables via `window.dataTables[tableId].ajax.reload()` (set by `initDataTable({ exposeForReload: true })`). This bridge is string-keyed by table ID — no type safety, no event contract. A missing table ID silently skips reload.
- **Polling fallback**: `users/index.blade.php` inline `onReady` polls via `setInterval(60s)` when realtime is degraded (`notification-realtime:degraded`), in pure polling / failed-config mode (`notification-realtime:polling`), or already disabled. The poll is torn down on reconnect (`notification-realtime:connected`). No overlap with WS pushes.
