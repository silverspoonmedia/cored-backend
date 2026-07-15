# Domain modules (internal monorepo)

Logical monorepo: domain code under `app/Domain/{Domain}/` in one Laravel app and git repo. Gateway and Frontend are **separate repositories**; they depend on Monolith **HTTP contracts**, not PHP namespaces.

## Core, locked, removable

| Kind | Domains | `domain:remove` |
|------|---------|-----------------|
| **Core** (platform) | Identity, Navigation, Notification, ActivityLog, Approval, PaymentGateway + Kernel | **Always refused** — `DomainManager::PLATFORM_CORE`; flipping `core` in config does nothing |
| **Locked** (product) | Default for `domain:make` | Refused until `'locked' => false` in [`config/domains.php`](../../config/domains.php) |
| **Removable** | Playground, or `domain:make --removable` | Allowed (`--force`) |

There is **no** `domain:unlock` command — unlock is a deliberate config edit.

## Day-2 commands

```powershell
php artisan domain:list
php artisan domain:make Billing                 # locked by default
php artisan domain:make Scratch --removable
php artisan domain:remove Scratch --force
# Locked: edit config/domains.php → locked false, then domain:remove
```

| Command | Effect |
|---------|--------|
| `domain:list` | Name, kind, whether `app/Domain/{Name}` exists |
| `domain:make` | Scaffold folders, provider, route stubs, tests; register everywhere |
| `domain:remove` | Delete domain PHP/routes/tests/provider; **not** migrations |

Implementation: [`DomainManager`](../../app/Support/Domain/DomainManager.php), `app/Console/Commands/Domain/*`.

## Directory layout

```
app/
├── Domain/
│   ├── Identity/       # Auth, User, RBAC, lookups (core)
│   ├── Navigation/     # Menu tree (core)
│   ├── Notification/   # Activity notifications (core)
│   ├── ActivityLog/    # Spatie audit trail (core)
│   ├── Playground/     # Demo users, orders, attachments (locked)
│   ├── Product/        # Product catalog items (locked product domain)
│   ├── PaymentGateway/ # Midtrans, iPaymu, invoices (core)
│   └── Approval/       # Approval matrix (core)
├── Http/Middleware/    # Kernel
├── Repositories/Eloquent/EloquentCrudRepository.php
├── Support/            # Api, DataTable, Web, Auth, Domain (Kernel)
├── Exceptions/         # BusinessRuleException (Kernel)
└── Providers/
    ├── AppServiceProvider.php
    └── Domain/*ServiceProvider.php
```

Typical domain folders: `Http/Controllers/{API,Web}/`, `Http/Requests`, `Http/Resources`, `Services`, `Repositories`, `Models`, `Exceptions`, Events/Listeners as needed.

Tests: `tests/Domain/{Domain}/` (PHPUnit suite `Domain`).

## Routes

API loaders: `routes/api/v1.php` → `routes/api/v1/{domain}.php`. Web: `routes/web.php` → `routes/web/{domain}.php`.

| API file | Domain |
|----------|--------|
| `identity.php` | Auth, users, lookups, admin |
| `navigation.php` | Menus |
| `notification.php` | Notifications |
| `activity-log.php` | Activity logs |
| `approval.php` | Approvals + workflow admin |
| `payment-gateway.php` | Gateways, invoices, subscriptions |
| `product.php` | Product items catalog |

Global: `routes/api.php` (ping) + `routes/api/playground.php` (non-production).

**Public URLs stay stable** when route files split — e.g. `/api/v1/approvals/*`.

## Namespace convention

| Old | New |
|-----|-----|
| `App\Models\User` | `App\Domain\Identity\Models\User` |
| `App\Services\Navigation\MenuService` | `App\Domain\Navigation\Services\MenuService` |
| `App\Http\Controllers\API\V1\UserController` | `App\Domain\Identity\Http\Controllers\API\V1\UserController` |

Shared infra: `App\Support\*`, `App\Http\Middleware\*`, `EloquentCrudRepository`.

## Cross-domain dependencies

- **Identity** (`User`, `Role`, `Permission`) is the hub — other domains import those models.
- **RBAC catalog** lives in Identity only. Roles/permissions carry `domain_kind` (`core` | `product`) so platform and product catalogs share Spatie tables without colliding on hierarchy. This is **not** the same as the module registry in [`config/domains.php`](../../config/domains.php) (`core` / `locked` for `domain:remove`). Details: [identity README — Core vs product RBAC](../domains/identity/README.md#core-vs-product-rbac).
- **Product H-RBAC when core is not enough:** Core seeds a fixed platform ladder (Root → User) and platform permissions. If a **product** domain needs a richer hierarchy, more roles, or domain-specific permissions, **add new roles and permissions with `domain_kind = product`** (own seeder, own level ladder). Do **not** overload or rename core roles. Spatie names stay globally unique — use product-prefixed names (e.g. `Billing Admin`, not a second `Admin`). Product levels never drive platform user-management H-RBAC; wire product authorization in that domain’s routes/policies/services.
- **Approval** is cross-cutting (`document_type` such as orders).
- Do not import domain services across boundaries unless explicit and documented.

## Adding a new domain

1. `php artisan domain:make {Name}` (or `--removable`)
2. Implement under `app/Domain/{Name}/`
3. Fill `routes/api/v1/{slug}.php` and `routes/web/{slug}.php`
4. Bind repos/policies in the generated ServiceProvider
5. Tests under `tests/Domain/{Name}/`
6. If the product needs its own H-RBAC (beyond core roles/permissions): seed product roles/permissions (`domain_kind = product`), unique names, optional product level ladder — see [Core vs product RBAC](../domains/identity/README.md#core-vs-product-rbac)
7. New public URLs or JSON shapes → [changelog/pending/](../changelog/pending/)
8. Update this doc and [docs/README.md](../README.md) if the domain map changes

## Deliberately not done

- No Composer path packages (`packages/*`) until justified
- No `nwidart/laravel-modules`
- No merge of Gateway/Frontend into this repo
- No dynamic provider auto-discovery — `bootstrap/providers.php` stays explicit

Historical migration notes: [archive/internal-monorepo-assessment.md](../archive/internal-monorepo-assessment.md), [archive/internal-monorepo-porting.md](../archive/internal-monorepo-porting.md).
