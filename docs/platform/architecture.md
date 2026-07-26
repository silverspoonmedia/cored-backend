# Domain modules (internal monorepo)

Logical monorepo: domain code under `app/Domain/{Domain}/` in one Laravel app and git repo.

## Glossary (two axes)

Do **not** collapse taxonomy and lifecycle into one label.

| Term | Axis | Meaning |
|------|------|---------|
| **Core domain** | Taxonomy (`kind`) | Foundation. Without it the app does not run well (Identity, Navigation, Notification, ActivityLog, ErrorLog, PaymentGateway, Service). |
| **Product domain** | Taxonomy (`kind`) | SaaS / feature surface sold by the project (FileHosting, Vtual, Microstock). Extends core (Identity, H-RBAC, payments, …). |
| **`locked`** | Lifecycle (product only) | `domain:remove` refuses until you set `'locked' => false` in [`config/domains.php`](../../config/domains.php). |
| **Removable** | Derived | Product with `locked => false`. Not a config key. |
| **`DomainManager::PLATFORM_CORE`** | Hard guard | Never-remove list. Config cannot override. |
| **RBAC `domain_kind`** | Separate system | Spatie roles/permissions catalog ownership (`core` \| `product`). Same words, **not** this registry. See [Identity — Core vs product RBAC](../domains/identity/README.md#core-vs-product-rbac). |
| **Product activation** | Runtime access | Per-user product state (`pending` \| `active` \| `suspended` \| `expired`) in `product_activations`. Tracked by `App\Support\Product\ProductActivationService`, gated by the `product.active` middleware. **Not** module taxonomy and **not** RBAC catalog — a third axis. See [Product Activations](../domains/product-activations/README.md). |
| **Domain visibility** | Global product gate | Per-domain `public` \| `private` flag in `product_domain_visibilities`. Tracked by `App\Support\DomainAccess\DomainAccessService`, enforced by the `domain.access:{Domain}` middleware on web + API product groups. Private = Root/Admin/`Beta tester {Domain}` only. A fourth axis — **not** per-user activation. See [Domain Access](../domains/domain-access/README.md). |
| **Product analytics** | Denormalized per-(product, tier, metric) counters | Aggregate rows in `product_analytics` (`storage_used_bytes`, `file_count`, `workspace_count_owned`, …) — one row per `(product_key, tier, metric_key)` bucket. Incremented/decremented on domain mutations by `ProductAnalyticsService`; tier transitions move a user's contribution between buckets via `moveUserTier`. Per-user drill-down reads source tables directly. See [Product Activations § Stored analytics](../domains/product-activations/README.md#stored-analytics-v3). |

### Registry vs removal

| Kind | Examples | `domain:remove` |
|------|----------|-----------------|
| **Core** | Identity, Navigation, Notification, ActivityLog, ErrorLog, PaymentGateway, Service + Kernel | **Always refused** — `PLATFORM_CORE`; flipping `kind` in config does nothing |
| **Product + locked** | Default for `domain:make`; FileHosting, Vtual | Refused until `'locked' => false` in config |
| **Product + removable** | `domain:make --removable` | Allowed (`--force`) |

There is **no** `domain:unlock` command — unlock is a deliberate config edit.

### Config shape

```php
// config/domains.php
'Identity' => ['kind' => 'core'],
'FileHosting' => ['kind' => 'product', 'locked' => true],
'Vtual' => ['kind' => 'product', 'locked' => true],
// removable product: ['kind' => 'product', 'locked' => false]
```

Core entries omit `locked` (N/A). Product entries always carry `locked`.

## Day-2 commands

```powershell
php artisan domain:list
php artisan domain:make Billing                 # product, locked by default
php artisan domain:make Scratch --removable
php artisan domain:remove Scratch --force
# Locked product: edit config/domains.php → locked false, then domain:remove
```

| Command | Effect |
|---------|--------|
| `domain:list` | Name, **Kind** (`core`\|`product`), **Removal** (`never`\|`locked`\|`removable`), whether `app/Domain/{Name}` exists |
| `domain:make` | Scaffold folders, provider, route stubs, tests, **`{Name}RoleSeeder`** (Beta tester role + `access {domain} product` permission + Public visibility row); register as **product** (locked by default) and wire the seeder into `DatabaseSeeder` |
| `domain:remove` | Delete domain PHP/routes/tests/provider; **not** migrations. Refuses core and locked product. |

Implementation: [`DomainManager`](../../app/Support/Domain/DomainManager.php), `app/Console/Commands/Domain/*`.

## Directory layout

```
app/
├── Domain/
│   ├── Identity/       # Auth, User, RBAC, lookups (core)
│   ├── Navigation/     # Menu tree (core)
│   ├── Notification/   # Activity notifications (core)
│   ├── ActivityLog/    # Spatie audit trail (core)
│   ├── ErrorLog/       # Grouped persistent app errors (core)
│   ├── FileHosting/    # Nested files, chunk S3 upload, Drive-lite share (product, locked)
│   ├── Vtual/          # Creator aggregator foundation (product, locked)
│   ├── Microstock/     # Microstock asset mgmt on FH system workspace (product, locked)
│   ├── PaymentGateway/ # Midtrans, iPaymu, invoices (core)
│   └── Service/        # Catalog, entitlements, DB FX→IDR, FH grace/purge (core)
├── Http/Middleware/    # Kernel
├── Repositories/Eloquent/EloquentCrudRepository.php
├── Support/            # Api, DataTable, Storage (S3 direct upload), Localization, Web, Auth, Domain, Product (Kernel)
├── Exceptions/         # BusinessRuleException (Kernel)
└── Providers/
    ├── AppServiceProvider.php
    └── Domain/*ServiceProvider.php
```

Typical domain folders: `Http/Controllers/{API,Web}/`, `Http/Requests`, `Http/Resources`, `Services`, `Repositories`, `Models`, `Exceptions`, Events/Listeners as needed.

Tests: `tests/Domain/{Domain}/` (PHPUnit suite `Domain`).

## Routes

API loaders: `routes/api/v1.php` → `routes/api/v1/{domain}.php`. Web: `routes/web.php` → `routes/web/{domain}.php`.

**Loader convention (presentation only):** list **core** requires first, **product** last, with short `// Core` / `// Product` comment sections. Same order in `bootstrap/providers.php` and `config/domains.php`. Does **not** change URL paths or route names. `domain:make` still appends; re-order once if a new product lands mid-file. Guest/public web specials (`identity-guest`, `file-hosting-public`, provider returns) stay outside kind sections — those groups are auth surface, not taxonomy.

| API file | Domain | Kind |
|----------|--------|------|
| `identity.php` | Auth, users, lookups, admin | core |
| `navigation.php` | Menus | core |
| `notification.php` | Notifications | core |
| `activity-log.php` | Activity logs | core |
| `error-log.php` | Grouped persistent app errors | core |
| `payment-gateway.php` | Gateways, invoices, subscriptions | core |
| `service.php` | Service catalog, checkout, service purchases | core |
| `file-hosting.php` | File hosting (auth + public share) folders/files/uploads | product |
| `vtual.php` | vTual creators, managements, channels (foundation) | product |

Kernel product registry (`app/Support/Product`): `product-activations.php` is **not** a domain route file — it mounts the cross-domain Product Activation API (self-service + admin). Loaded from `routes/api/v1.php` between core and product domain requires.

Global: `routes/api.php` — `GET /api/ping` only (`App\Http\Controllers\API\PingController`).

**Public URLs stay stable** when route files split — e.g. `/api/v1/users/*`.

## Namespace convention

| Old | New |
|-----|-----|
| `App\Models\User` | `App\Domain\Identity\Models\User` |
| `App\Services\Navigation\MenuService` | `App\Domain\Navigation\Services\MenuService` |
| `App\Http\Controllers\API\V1\UserController` | `App\Domain\Identity\Http\Controllers\API\V1\UserController` |

Shared infra: `App\Support\*`, `App\Http\Middleware\*`, `EloquentCrudRepository`.

## Cross-domain dependencies

- **Identity** (`User`, `Role`, `Permission`) is the hub — other domains import those models.
- **RBAC catalog** lives in Identity only. Roles/permissions carry `domain_kind` (`core` | `product`) so platform and product catalogs share Spatie tables without colliding on hierarchy. This is **not** the same as the module registry in [`config/domains.php`](../../config/domains.php) (`kind` + `locked` for `domain:list` / `domain:remove`). Details: [identity README — Core vs product RBAC](../domains/identity/README.md#core-vs-product-rbac).
- **Product H-RBAC when core is not enough:** Core seeds a fixed platform ladder (Root → User) and platform permissions. If a **product** domain needs a richer hierarchy, more roles, or domain-specific permissions, **add new roles and permissions with `domain_kind = product`** (own seeder, own level ladder). Do **not** overload or rename core roles. Spatie names stay globally unique — use product-prefixed names (e.g. `Billing Admin`, not a second `Admin`). Product levels never drive platform user-management H-RBAC; wire product authorization in that domain’s routes/policies/services.
- Do not import domain services across boundaries unless explicit and documented.

## Adding a new domain

1. `php artisan domain:make {Name}` (or `--removable`) — always scaffolds a **product** domain (also generates `database/seeders/{Name}RoleSeeder.php` and registers it in `DatabaseSeeder`)
2. Implement under `app/Domain/{Name}/`
3. Fill `routes/api/v1/{slug}.php` and `routes/web/{slug}.php` — gate the product group with `domain.access:{Name}` **before** `product.active:{Name}` / `permission:` (see [Domain Access](../domains/domain-access/README.md))
4. Run `php artisan db:seed --class={Name}RoleSeeder` to create the Beta tester role + Public visibility row
5. Bind repos/policies in the generated ServiceProvider
6. Tests under `tests/Domain/{Name}/`
7. If the product needs its own H-RBAC (beyond core roles/permissions): seed product roles/permissions (`domain_kind = product`), unique names, optional product level ladder — see [Core vs product RBAC](../domains/identity/README.md#core-vs-product-rbac)
8. Update this doc and [docs/README.md](../README.md) if the domain map changes

## Deliberately not done

- No Composer path packages (`packages/*`) until justified
- No `nwidart/laravel-modules`
- No dynamic provider auto-discovery — `bootstrap/providers.php` stays explicit
- No `routes/api/v1/core|product/` subfolders — kind is registry + comment sections, not filesystem trees
- No `domain` / `domain_kind` column on menus — nav is a UX tree; module taxonomy stays in `config/domains.php` (and RBAC catalog separately)
- No auto menu registration from `domain:make` — seed / admin CRUD is enough at this scale

Historical migration notes: [archive/internal-monorepo-assessment.md](../archive/internal-monorepo-assessment.md), [archive/internal-monorepo-porting.md](../archive/internal-monorepo-porting.md).
