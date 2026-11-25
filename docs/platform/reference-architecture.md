# Domain modules — reference

Detail behind [architecture.md](architecture.md): the two taxonomy axes plus the composition axis, the day-2 commands, the directory and route layout, and the cross-domain rules.

## Glossary (three axes)

Do **not** collapse taxonomy, lifecycle and composition into one label.

| Term | Axis | Meaning |
|------|------|---------|
| **Core domain** | Taxonomy (`kind`) | Foundation. Without it the app does not run well (Identity, Navigation, Notification, ActivityLog, ErrorLog, PaymentGateway, Service, Inference, Ai). |
| **Product domain** | Taxonomy (`kind`) | SaaS / feature surface built on the core (Identity, H-RBAC, payments, …). This boilerplate ships none: its two former products were removed through `domain:remove` — see [boilerplate-forks.md](../integration/boilerplate-forks.md). |
| **Subproduct domain** | Composition (`parent`) | A product domain that extends another **product** domain: `'parent' => '{BaseProduct}'`. It is **not** a third `kind` value — a subproduct is still a full product with its own key, activation, RBAC, SKU, namespace and `uninstall.json`. Only the removal order changes: `domain:remove` refuses a parent while a registered child points at it. |
| **`locked`** | Lifecycle (product only) | `domain:remove` refuses until you set `'locked' => false` in [`config/domains.php`](../../config/domains.php). |
| **Removable** | Derived | Product with `locked => false`. Not a config key. |
| **`DomainManager::PLATFORM_CORE`** | Hard guard | Never-remove list. Config cannot override. |
| **RBAC `domain_kind`** | Separate system | Spatie roles/permissions catalog ownership (`core` \| `product`). Same words, **not** this registry. See [Identity — Core vs product RBAC](../domains/identity/reference.md#core-vs-product-rbac). |
| **Product activation** | Runtime access | Per-user product state (`pending` \| `active` \| `suspended` \| `expired`) in `product_activations`. Tracked by `App\Support\Product\ProductActivationService`, gated by the `product.active` middleware. A separate axis — **not** module taxonomy and **not** RBAC catalog. See [Product Activations](../domains/product-activations/README.md). |
| **Domain visibility** | Global product gate | Per-domain `public` \| `private` flag in `product_domain_visibilities`. Tracked by `App\Support\DomainAccess\DomainAccessService`, enforced by the `domain.access:{Domain}` middleware on web + API product groups. Private = Root/Admin/`Beta tester {Domain}` only. A separate axis — **not** per-user activation. See [Domain Access](../domains/domain-access/README.md). |
| **Product analytics** | Denormalized per-(product, tier, metric) counters | Aggregate rows in `product_analytics` (`storage_used_bytes`, `file_count`, `workspace_count_owned`, …) — one row per `(product_key, tier, metric_key)` bucket. Incremented/decremented on domain mutations by `ProductAnalyticsService`; tier transitions move a user's contribution between buckets via `moveUserTier`. Per-user drill-down reads source tables directly. See [Product Activations § Stored analytics](../domains/product-activations/reference.md#stored-analytics-v3). |

### Registry vs removal

| Kind | Examples | `domain:remove` |
|------|----------|-----------------|
| **Core** | Identity, Navigation, Notification, ActivityLog, ErrorLog, PaymentGateway, Service, Inference, Ai + Kernel | **Always refused** — `PLATFORM_CORE`; flipping `kind` in config does nothing |
| **Product + locked** | Default for `domain:make` | Refused until `'locked' => false` in config |
| **Product + removable** | `domain:make --removable` | Allowed (`--force`) |
| **Subproduct + locked** | `'parent' => '{BaseProduct}'` | Same as product **plus** a composition guard: refused while a registered child points here |

There is **no** `domain:unlock` command — unlock is a deliberate config edit.

Removal then reads that domain's [`uninstall.json`](#domain-uninstall-manifest). A product without one still falls back to the structural behaviour (`app/Domain/{Name}`, provider, route files, tests, seeder) with an explicit warning; the manifest is what adds the shared-file edits, the cross-repo deletes, and the data purge.

### Config shape

```php
// config/domains.php
'Identity' => ['kind' => 'core'],
'ExampleProduct' => ['kind' => 'product', 'locked' => true],
'ExampleSubproduct' => ['kind' => 'product', 'locked' => true, 'parent' => 'ExampleProduct'],
// removable product: ['kind' => 'product', 'locked' => false]
```

Core entries omit `locked` (N/A). Product entries always carry `locked`. Only a subproduct carries `parent` — the name of a **registered product** domain it extends.

### Parent / subproduct

`parent` is the composition axis: it records that one product domain builds on another (a subproduct stores its assets in its base product's workspaces, and reaches the user through the base product's quota and workspace switcher). It is orthogonal to `kind` and `locked` — a subproduct is a full product with its own product key, activation row, RBAC, SKU, namespace and `uninstall.json`; only the **removal order** is constrained. `domain:remove` refuses the parent while a registered child still points at it, so a subproduct can never outlive the base it extends. `DomainManager::parentOf()` / `childrenOf()` expose the relation; `domain:list` prints it in the `Parent` column.

The cross-domain coupling itself (extra columns on the base product's tables, a shared quota pool) is unchanged by this axis — it lives in the subproduct manifest `notes[]`.

## Day-2 commands

```powershell
php artisan domain:list
php artisan domain:make Billing                 # product, locked by default
php artisan domain:make Scratch --removable
php artisan domain:make Addon --parent=Scratch  # subproduct of a registered product
php artisan domain:remove Scratch --dry-run     # plan + unlisted references, changes nothing
php artisan domain:remove Scratch --force       # executes the manifest
# Locked product: edit config/domains.php → locked false, then domain:remove
# Subproduct: remove the child first — the parent is refused while a child is registered
```

| Command | Effect |
|---------|--------|
| `domain:list` | Name, **Kind** (`core`\|`product`), **Parent** (the product it extends, `—` = none), **Removal** (`never`\|`locked`\|`removable`), whether the domain directory exists (`yes`/`missing`), and **Package** (`package` = Composer package — `packages/{slug}` here, `vendor/silverspoonmedia/{slug}` in a consumer install | `—` = `app/Domain/`) |
| `domain:make` | Scaffold folders, provider, route stubs, tests, **`{Name}RoleSeeder`** (Beta tester role + `access {domain} product` permission + Public visibility row) and a starter **`uninstall.json`**; register as **product** (locked by default) and wire the seeder into `DatabaseSeeder`. `--parent={Name}` registers a **subproduct** instead — the parent must already be a registered product domain. |
| `domain:remove` | Execute the domain's `uninstall.json`: delete paths (backend/SPA/docs), apply shared-file edits, optionally purge data. Refuses core and locked product, and refuses a product while a registered **subproduct** still points at it. Migrations stay without `--purge-data`. |

Implementation: [`DomainManager`](../../packages/platform/src/Support/Domain/DomainManager.php), `domain:list` in [`packages/platform/src/Console/Commands/Domain/`](../../packages/platform/src/Console/Commands/Domain/) (kernel — a consumer install runs the same class from `vendor`), `domain:make` / `domain:remove` in `app/Console/Commands/Domain/` (host — they rewrite host trees).

## Domain uninstall manifest

A product domain records everything it touched outside `app/Domain/{Name}/` in **one file inside the domain**: `app/Domain/{Name}/uninstall.json`, schema `domain-uninstall/v1`. `domain:make` scaffolds it, it dies with the domain, and `domain:remove` executes it — so removing a domain is reading one reviewed map instead of re-auditing the tree. Only product domains have one; core domains are refused before a manifest is ever read. The sample below is illustrative and abridged — the upstream `monolith` still carries the real manifests, far longer (`edits[]` and `ignore[]` carry hundreds of entries each).

```json
{
  "schema": "domain-uninstall/v1",
  "domain": "{Name}",
  "repos": {
    "backend": {
      "delete": ["app/Domain/{Name}", "app/Providers/Domain/{Name}ServiceProvider.php", "routes/api/v1/{slug}.php", "config/{slug}.php", "lang/*/api/{slug}.php", "tests/Domain/{Name}", "docs/domains/{slug}"],
      "edits": [
        { "file": ".env.example", "op": "remove_lines_matching", "match": "^({SLUG}_DISK|{SLUG}_DIRECTORY)=" },
        { "file": "config/spa-routes.php", "op": "remove_quoted_strings", "match": ["{slug}.index"] },
        { "file": "lang/*/list_actions.php", "op": "remove_array_keys", "match": ["{slug}."] }
      ],
      "unresolved": [],
      "ignore": []
    },
    "spa": { "delete": ["src/features/{slug}", "src/i18n/features/{slug}"], "edits": [], "unresolved": [], "ignore": [] },
    "docs": { "delete": ["docs/domains/{slug}"], "edits": [], "unresolved": [], "ignore": [] }
  },
  "data": {
    "tables": ["{slug}_files", "{slug}_folders"],
    "migrations": ["database/migrations/*_create_{slug}_tables.php"],
    "rbac": { "permissions": ["access {slug} product"], "roles": ["Beta tester {Name}"] },
    "rows": [
      { "table": "product_activations", "key": { "product_key": "{Name}" } },
      { "table": "menus", "match": { "column": "route", "operator": "like", "value": "{slug}.%" } }
    ]
  },
  "needles": [],
  "notes": ["Without --purge-data the migrations stay; drop them by hand once the tables are gone."]
}
```

| Section | Meaning |
|---------|---------|
| `repos.backend` / `repos.spa` / `repos.docs` | One section per checkout. `backend` is this repo; `spa` and `docs` are sibling checkouts. |
| `delete[]` | Repo-relative file or directory paths; `*` globs allowed (`lang/*/api/{slug}.php`). A path that matches nothing is a plan warning, not a failure — the domain may never have added it. |
| `edits[]` | Shared-file surgery: `file` (may glob) + `op` + its spec. |
| `unresolved[]` | Draft-time worklist — `{file, reason}`. `domain:remove` refuses to run while it is not empty (unless `--force`) and the drift guard fails on it. |
| `ignore[]` | Reviewed survivor references — `{path, reason}`. The reason is mandatory and may not start with `TODO`; it says why the reference survives **or** spells out the hand edit the closed op vocabulary cannot express. |
| `data.tables` / `data.migrations` | Dropped / deleted only under `--purge-data`. |
| `data.rbac` | Role and permission **names** deleted by name under `--purge-data`. |
| `data.rows` | Row purge under `--purge-data`: `{table, key: {col: value}}` or `{table, match: {column, operator, value}}`. |
| `needles[]` | Extra literals the drift scan looks for beyond the derived set. |
| `notes[]` | Work no op can express (external subscriptions, queue lanes, docblock rewordings). Read it before calling the removal done. |

### Op vocabulary (closed)

Text surgery on line/bracket boundaries — no AST round-trip, so exact indentation and comments survive.

| Op | Spec | Effect |
|----|------|--------|
| `remove_lines_matching` | `match` (regex) | Drop every line matching the regex. |
| `remove_quoted_strings` | `match` (list) | Remove each listed literal, including inside inline arrays. |
| `remove_array_keys` | `match` (list of dotted PHP paths) | Remove a balanced `'key' => […]` block; prunes a container left empty. |
| `remove_block` | `begin` (one anchor) **or** `begins` (one anchor per entry) | A selector, not a layout. When the anchor opens a container (`'Key' => [`), remove that balanced `[…]` / `{…}`. Otherwise remove the entry itself: its line, any wrapped continuation lines, and the comment block attached above it — so an import, a `config([...])` line or a commented-out reference leaves without a positional match. `begins` removes several such entries and still credits one removal, which keeps a multi-line registration from reading as dead in the plan. |
| `remove_json_keys` | `match` (list of dotted JSON paths) | Remove a JSON key; prunes a container left empty. |

Anything the five ops cannot express goes to `notes[]` or an `ignore[]` reason. The vocabulary stays closed so a removal cannot invent a new way to edit files.

**Anchor the entry, never the neighbourhood.** The regression this cost: the kernel seams moved a product's settings, menu nodes and AI bindings out of the packages into `App\Domain\{Product}\Support\*` contributors. The manifests still pointed `remove_lines_matching` / `remove_block` at the package lines that used to hold them, so on a clean plan they removed nothing — green-looking entries that would have left a broken package behind. Write the op against the *thing that remains once the product's own files are gone* (the kernel's opt-in hook line, the provider's registration), or list the file in `ignore[]` with the reason it leaves with a sibling.

### The scan

`UninstallReferenceScanner` finds every reference to the domain across the three checkouts (PHP, JSON, TS, Vue, YAML, env — markdown prose is out of scope, that is the docs-sync contract). Needles: `App\Domain\{Name}\`, `{Name}ServiceProvider`, `{Name}RoleSeeder`, `Beta tester {Name}`, `domain.access:{Name}`, `product.active:{Name}`, `{slug}.` route names, `product.{slug}`, `docs/domains/{slug}`, the bare `{Name}` / `{slug}` tokens, plus the manifest's own `needles[]` and `data.tables`.

A reference is **covered** when its file is inside a `delete` path, is the target of an `edits[]` entry, is listed in `ignore`, is one of the five structural files `domain:remove` already rewrites (`config/domains.php`, `bootstrap/providers.php`, `routes/api/v1.php`, `routes/web.php`, `database/seeders/DatabaseSeeder.php`), or is a `data.migrations` entry. Everything else is an **unlisted reference**.

The scan runs in both directions: `--draft-manifest` turns it into a candidate manifest, and `DomainUninstallManifestTest` uses it to prove nothing a domain touched is missing.

Machine-local files are out of scope. A `.gitignore`d override (for example `database/seeders/data/*.local.php`) exists on one machine and not in a clean checkout, so scanning it would make the manifest — and the guard — depend on who runs them; a backend path has no "unverifiable" escape the way an absent sibling checkout does. The reference is neither scanned nor recorded, and a hand edit such an override still needs goes in `notes[]`.

**Size is a silent blind spot.** The scanner reads files up to `UninstallReferenceScanner::MAX_FILE_BYTES` (2 MB) and skips anything larger without a warning, so a big sidecar can sit unscanned for months and then surface the moment a removal shrinks it under the cap. That happened when a domain removal dropped the SPA `src/i18n/curation-log.json` from 2.06 MB to 1.44 MB: the drift guard started reporting product references in it for the first time. Both manifests carry an `ignore` entry for it. Treat a large generated sidecar as **unscanned, not clean** — either add the `ignore` entry up front with the reason, or raise the cap deliberately.

### Day-2 workflow

```powershell
php artisan domain:remove {Name} --draft-manifest      # only when no uninstall.json exists yet
php artisan domain:remove {Name} --dry-run             # plan + unresolved + unlisted references
php artisan domain:remove {Name} --force               # execute the file half
php artisan domain:remove {Name} --purge-data --force  # …and drop tables/rows/RBAC/migrations
```

`--dry-run` is read-only and is allowed while the domain is locked. `--force` skips the confirmation prompt but **not** the `unresolved` check. Without `--purge-data` the migrations stay, matching the historical `domain:remove` behaviour; `--purge-data` is refused unless `DestructiveDatabaseCommandGuard` allows it (`ALLOW_DESTRUCTIVE_DB=true` outside sqlite/local). After a run the command flushes the navigation and permission caches, because menus and RBAC moved.

Sibling checkouts resolve from `config/domains.php` `siblings` (`'spa'` / `'docs'` directory names beside this project) — a fork points `spa` at its own frontend. An absent sibling is reported **unverifiable** — never clean — and its section is skipped, so a CI that checks out one repo cannot silently pass a cross-repo removal.

The AI-facing procedure is [.cursor/skills/domain-remove/SKILL.md](../../.cursor/skills/domain-remove/SKILL.md); this section owns the schema and the op vocabulary.

Implementation: [`UninstallManifest`](../../packages/platform/src/Support/Domain/UninstallManifest.php), [`UninstallFileEditor`](../../packages/platform/src/Support/Domain/UninstallFileEditor.php), [`UninstallReferenceScanner`](../../packages/platform/src/Support/Domain/UninstallReferenceScanner.php).

## Directory layout

```
app/
├── Domain/
│   ├── Identity/       # Auth, User, RBAC, lookups (core)
│   ├── ActivityLog/    # Spatie audit trail (core)
│   ├── ErrorLog/       # Grouped persistent app errors (core)
│   ├── Inference/      # AI model catalog + provider model rates + snapshots (core)
│   └── Ai/             # Central AI transport: ai_connections (core; not pricing)
├── Http/Middleware/    # Kernel
├── Repositories/Eloquent/EloquentCrudRepository.php
├── Support/            # Api, ListActions, Storage (S3 direct upload), Localization, Web, Auth, Domain, Product, Navigation contract (Kernel)
├── Exceptions/         # BusinessRuleException (Kernel)
└── Providers/
    ├── AppServiceProvider.php
    └── Domain/*ServiceProvider.php

packages/                  # Core domains shipped as Composer path packages
├── navigation/            # Menu tree (core) — src, routes, config, database, resources/lang, tests
├── notification/          # Activity notifications (core) — same shape
├── audit/                 # ActivityLog + ErrorLog (core) — two namespaces, one provider,
│                          #   routes/{activity-log,error-log}.php, config/errorlog.php,
│                          #   database/migrations, resources/lang, tests/{ActivityLog,ErrorLog}
└── billing/               # PaymentGateway + Currency + Service (core) — three namespaces, one provider,
                           #   routes/{payment-gateway,currency,service}.php + web-ipaymu-return.php (registry `web` key),
                           #   config/{payment,payment-routing,service}.php, resources/routing/price.yaml,
                           #   database/{migrations,seeders,factories}, resources/lang, tests/{PaymentGateway,Currency,Service}
```

A packaged domain keeps namespace `App\Domain\{Domain}\*`; see [core-packages.md](../integration/core-packages.md). The host `app/` prefix resolves first, so an override at `app/Domain/Navigation/...` shadows the package.

Typical domain folders: `Http/Controllers/{API,Web}/`, `Http/Requests`, `Http/Resources`, `Services`, `Repositories`, `Models`, `Exceptions`, Events/Listeners as needed.

Tests: `tests/Domain/{Domain}/` (PHPUnit suite `Domain`) and `packages/*/tests/` (suite `Packages`).

## Routes

API loaders: `routes/api/v1.php` → `routes/api/v1/{domain}.php`, plus `DomainManager::packageApiRoutePaths()` for packaged domains (`packages/*/routes/{domain}.php`, one file per domain even when two share a package). Web: `routes/web.php` → `routes/web/{domain}.php`, plus `DomainManager::packageWebRoutePaths()` for a packaged domain's registry `web` key (`packages/billing/routes/web-ipaymu-return.php`), so the `web` middleware group still wraps it.

**Web URI layout:** The Blade admin is removed. Authenticated UI lives in the SPA under `/apps/*` paths (Vue Router symbolic names stored in menu rows, validated via `config/spa-routes.php`). The backend `web` routes are **server endpoints only**: `GET /` → SPA redirect; a product's public share `/s/*`; iPaymu return; Discord OAuth callback. API paths (`/api/v1/*`) are unchanged. See [blade-admin.md](../web/blade-admin.md) and [vue-workers-spa.md](../integration/vue-workers-spa.md).

**Loader convention (presentation only):** list **core** requires first, **product** last, with short `// Core` / `// Product` comment sections. Same order in `bootstrap/providers.php` and `config/domains.php`. Does **not** change API URL paths or route names. `domain:make` still appends into the `/apps` auth group; re-order once if a new product lands mid-file. Guest/public web specials stay outside kind sections — those groups are auth surface, not taxonomy.

| API / web file | Domain | Kind |
|----------------|--------|------|
| `identity.php` | Auth, users, lookups, admin | core |
| `packages/navigation/routes/api.php` | Menus | core (package) |
| `packages/notification/routes/api.php` | Notifications | core (package) |
| `packages/audit/routes/activity-log.php` | Activity logs | core (package `silverspoonmedia/audit`) |
| `packages/audit/routes/error-log.php` | Grouped persistent app errors | core (package `silverspoonmedia/audit`) |
| `packages/ai/routes/inference.php` | AI model catalog, provider rates, usage | core (package `silverspoonmedia/ai`) |
| `packages/ai/routes/ai.php` | AI connections (Tier C transport) | core (package `silverspoonmedia/ai`) |
| `packages/billing/routes/payment-gateway.php` | Gateways, invoices, subscriptions | core (package `silverspoonmedia/billing`) |
| `packages/billing/routes/service.php` | Service catalog, checkout, service purchases | core (package `silverspoonmedia/billing`) |
| `packages/billing/routes/web-ipaymu-return.php` | iPaymu hosted return/cancel (public web) | core (package `silverspoonmedia/billing`) |

Kernel product registry (`packages/platform/src/Support/Product`): `product-activations.php` is **not** a domain route file — it mounts the cross-domain Product Activation API (self-service + admin). Loaded from `routes/api/v1.php` between core and product domain requires.

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
- **RBAC catalog** lives in Identity only. Roles/permissions carry `domain_kind` (`core` | `product`) so platform and product catalogs share Spatie tables without colliding on hierarchy. This is **not** the same as the module registry in [`config/domains.php`](../../config/domains.php) (`kind` + `locked` + `parent` for `domain:list` / `domain:remove`). Details: [identity README — Core vs product RBAC](../domains/identity/reference.md#core-vs-product-rbac).
- **Product H-RBAC when core is not enough:** Core seeds a fixed platform ladder (Root → User) and platform permissions. If a **product** domain needs a richer hierarchy, more roles, or domain-specific permissions, **add new roles and permissions with `domain_kind = product`** (own seeder, own level ladder). Do **not** overload or rename core roles. Spatie names stay globally unique — use product-prefixed names (e.g. `Billing Admin`, not a second `Admin`). Product levels never drive platform user-management H-RBAC; wire product authorization in that domain's routes/policies/services.
- Do not import domain services across boundaries unless explicit and documented.

## Adding a new domain

1. `php artisan domain:make {Name}` (or `--removable`; `--parent={Product}` for a **subproduct**) — always scaffolds a **product** domain (also generates `database/seeders/{Name}RoleSeeder.php`, registers it in `DatabaseSeeder`, and writes a starter `uninstall.json`)
2. Implement under `app/Domain/{Name}/`
3. Fill `routes/api/v1/{slug}.php` and `routes/web/{slug}.php` — gate the product group with `domain.access:{Name}` **before** `product.active:{Name}` / `permission:` (see [Domain Access](../domains/domain-access/README.md))
4. Run `php artisan db:seed --class={Name}RoleSeeder` to create the Beta tester role + Public visibility row
5. Bind repos/policies in the generated ServiceProvider
6. Tests under `tests/Domain/{Name}/`
7. If the product needs its own H-RBAC (beyond core roles/permissions): seed product roles/permissions (`domain_kind = product`), unique names, optional product level ladder — see [Core vs product RBAC](../domains/identity/reference.md#core-vs-product-rbac)
8. Update this doc and [docs/README.md](../README.md) if the domain map changes

## Deliberately not done

- No new Composer path package until a core domain is proven to need one — the POC covers Navigation + Notification only, and product domains never become packages
- No `nwidart/laravel-modules`
- No dynamic provider auto-discovery beyond `extra.laravel.providers` in a package — `bootstrap/providers.php` stays explicit for everything that is not packaged
- No `routes/api/v1/core|product/` subfolders — kind is registry + comment sections, not filesystem trees
- No `domain` / `domain_kind` column on menus — nav is a UX tree; module taxonomy stays in `config/domains.php` (and RBAC catalog separately)
- No third `kind` for subproducts — composition stays the orthogonal `parent` axis, and a subproduct is a full product in every other respect
- No auto menu registration from `domain:make` — seed / admin CRUD is enough at this scale

Historical migration notes: [archive/internal-monorepo-assessment.md](../archive/internal-monorepo-assessment.md), [archive/internal-monorepo-porting.md](../archive/internal-monorepo-porting.md).

## Related

- [architecture.md](architecture.md) — brief
- [api-routing.md](api-routing.md) — route layers
- [crud.md](crud.md) — repository pattern and layer responsibilities