# Blade partial manifest (archived)

> **Archived — nothing here exists.** The whole `resources/` tree was deleted with the Blade admin; see [blade-admin.md](blade-admin.md). This is a post-mortem inventory of what the Blade admin was made of, useful only for tracing where a behavior came from when porting or auditing the SPA. Do not treat any path, component tag, or composer binding below as live, and do not recreate them.

The Blade admin rendered from `resources/views/{layout}/` where `{layout}` came from `config('web.layout')` (default `adminlte4`); includes resolved it through `config('web.layout')` or `WebLayout::partial()` rather than hard-coding `adminlte4.`. Views under `resources/views/_archive/` were an earlier round of parked work — also deleted.

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

## Archivd

### Live partials (index/public)

`workspace-modals`, `microstock-review-panel`, `usage-offcanvas`, `file-folder-modals`, `team-offcanvas`, `move-modal`, `file-icon`, `visibility-badge`, `microstock-badge`, `quota-bar`, `workspace-switcher` — DOM `data-ar-*` / modal ids are JS API; do not rename without updating `resources/js/archivd/*` and Vue island.

### Archived (no active caller)

`toolbar-zone`, `browse-views`, `trash-show-panels` — superseded by Vue island first paint. Location: `resources/views/_archive/adminlte4/archivd/partials/`. See [archivd README](../domains/archivd/README.md).

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

- [blade-admin.md](blade-admin.md) — what remains on the `web` stack after removal
- [ui-patterns.md](ui-patterns.md) — **archived** UX patterns referencing these partials
- [domains/vtual/README.md](../domains/vtual/README.md) — live vTual SPA surfaces
- [domains/archivd/README.md](../domains/archivd/README.md) — removed Blade island history
