# Blade partial manifest (active surface)

Inventory for active admin Blade under `resources/views/{layout}/` where `{layout}` is `config('web.layout')` (default `adminlte4`). Includes use `config('web.layout')` or `WebLayout::partial()` — never hard-code `adminlte4.` in domain views.

**Archive vs active:** views under `resources/views/_archive/` keep historical paths and includes for reference only — not relocated with active code. Active surface follows [blade-admin — Partial layout](blade-admin.md#partial-and-component-layout).

## Layout shell (composer-bound — do not move)

| Partial | Callers | Composer vars |
|---------|---------|---------------|
| `components/partials/sidebar.blade.php` | `layouts/app` | `navigation` |
| `components/partials/navbar.blade.php` | `layouts/app` | `navigation`, `navbarNavigation`, `notification*` |
| `components/partials/assets-head.blade.php` | layouts | — |
| `components/partials/assets-scripts.blade.php` | layouts | — |
| `partials/breadcrumbs.blade.php` | `layouts/app` | breadcrumb trail |

## CRUD forms (→ `{domain}/partials/form.blade.php`)

| Domain | Variables | Callers |
|--------|-----------|---------|
| menus | `$action`, `$method`, `$isEdit`, `$menu`, `$hydratedLookups`, `$listLayout` | create, edit |
| users | `$action`, `$method`, `$isEdit`, `$user`, roles | create, edit |
| users/partials/activity-history | `$activityLogs` | users/edit |
| services | `$action`, `$method`, `$isEdit`, `$service`, `$submitLabel` | create, edit |
| settings | `$group`, `$settings`, field meta | platform |
| payment-gateways | `$action`, `$method`, `$isEdit`, gateway | create, edit |
| subscriptions | `$action`, `$method`, `$isEdit`, subscription | create, edit |
| inference/providers | `$isEdit`, `$provider` | create, edit |
| inference/model-cards | `$isEdit`, `$card` | create, edit |
| vtual/orphans | `$action`, `$method`, `$isEdit`, creator | create, edit |
| vtual/platform-credentials | `$credential` | create, edit |

## File Hosting

### Live partials (index/public)

`workspace-modals`, `microstock-review-panel`, `usage-offcanvas`, `file-folder-modals`, `team-offcanvas`, `move-modal`, `file-icon`, `visibility-badge`, `microstock-badge`, `quota-bar`, `workspace-switcher` — DOM `data-fh-*` / modal ids are JS API; do not rename without updating `resources/js/file-hosting/*` and Vue island.

### Archived (no active caller)

`toolbar-zone`, `browse-views`, `trash-show-panels` — superseded by Vue island first paint. Location: `resources/views/_archive/adminlte4/file-hosting/partials/`. See [file-hosting README](../domains/file-hosting/README.md).

## vTual

| Partial | Callers | Notes |
|---------|---------|-------|
| `vtual/partials/confirm-modal` | profiles/show, platform-credentials/platform | singleton `#vtualConfirmModal` |
| `vtual/partials/channel-add-form` | orphans/partials/channels, profiles/partials/channels | shared add form |
| `vtual/orphans/partials/channels` | orphans/edit | staff pending table |
| `vtual/orphans/partials/channel-row` | orphans/partials/channels | nested row fragment |
| `vtual/profiles/partials/meta-form` | profiles/show | profile metadata |
| `vtual/profiles/partials/merge-form` | profiles/show | absorb merge |
| `vtual/profiles/partials/links` | profiles/show | external links |
| `vtual/profiles/partials/channels` | profiles/show | claims + active/archived — **do not merge with orphans** |
| `vtual/profiles/partials/content-stub` | profiles/show | content placeholder |
| `vtual/platform-credentials/partials/credential-actions` | platform-credentials/platform | row action bar |
| `vtual/vtuber/partials/sidebar` | vtuber/show | identity + stats |
| `vtual/vtuber/partials/content-panel` | vtuber/show | main column shell |
| `vtual/vtuber/partials/channels` | vtuber/partials/sidebar | channel ID cards |
| `vtual/vtuber/partials/contents` | vtuber/partials/content-panel | content grid |
| `vtual/vtuber/partials/claim-active-panel` | vtuber/claim (Vue island bootstrap) | claim state mount |

## Payments

| Partial | Callers |
|---------|---------|
| `payments/income/partials/breakdown-table` | income/index (×3) |
| `payments/income/partials/invoices-table` | income/index |
| `components/payments/checkout-panel-body` | checkout-panel |

## Related

- [blade-admin.md](blade-admin.md) — include contract, `WebLayout::partial()`
- [ui-patterns.md](ui-patterns.md) — shared UX patterns referencing partials
- [domains/vtual/README.md](../domains/vtual/README.md) — vTual web surfaces
- [domains/file-hosting/README.md](../domains/file-hosting/README.md) — Vue island vs archived Blade
