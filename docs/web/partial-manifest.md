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

## Payments

| Partial | Callers |
|---------|---------|
| `payments/income/partials/breakdown-table` | income/index (×3) |
| `payments/income/partials/invoices-table` | income/index |
| `components/payments/checkout-panel-body` | checkout-panel |

## Related

- [blade-admin.md](blade-admin.md) — what remains on the `web` stack after removal
- [archive/ui-patterns-blade.md](../archive/ui-patterns-blade.md) — **archived** UX patterns referencing these partials