# API Routing

This application uses two **independent** API routing layers: **global** (unversioned) and **versioned**. They are not related—global routes do not evolve into versioned routes, and versioned routes are not “namespaced globals.” They exist for different purposes and follow different rules.

## Design principles

| Layer | Role |
|-------|------|
| **Global** (`/api/*`) | A small, stable surface for operations that are genuinely safe to expose without a version contract. |
| **Versioned** (`/api/v{N}/*`) | Where **all application business logic** lives. Every feature clients depend on should be implemented here. |

**Global and versioned are unrelated.** Do not mirror the same endpoints in both layers. Do not treat global as “the latest API” or versioned as “the old API.” Clients that need product behavior call a version prefix; global is reserved for cross-cutting, low-risk utilities only.

### What belongs in global routes

Use global routes **sparingly**, only when an endpoint is:

- Unlikely to change in breaking ways
- Not part of the product domain (no business rules, no domain models)
- Safe to expose without versioning (e.g. liveness probes, simple public metadata)

Typical examples: health/liveness checks, readiness probes, static capability discovery that never carries business payloads.

### What belongs in versioned routes

**Everything else**—authentication, users, orders, payments, CRUD, workflows, integrations, and any logic that touches domain rules or persisted data. New features and changes always go through `routes/api/v{N}/*.php` (domain route files loaded from `routes/api/v{N}.php`) and `App\Domain\{Domain}\Http\Controllers\…`.

When behavior changes incompatibly, add a new version (`v2`, `v3`, …) rather than altering an existing version’s contract.

## Overview

| Layer | Route file | Controller namespace | URL prefix | Route name prefix |
|-------|------------|----------------------|------------|-------------------|
| Global | `routes/api.php` + `routes/api/playground.php` | `App\Domain\Playground\Http\Controllers\API` (playground only) | `/api` | *(none)* |
| Versioned | `routes/api/v{N}.php` + `routes/api/v{N}/*.php` | `App\Domain\{Domain}\Http\Controllers\API\V{N}` (or `API\` for domain-specific globals) | `/api/v{N}` | `api.v{N}.` |

Laravel registers the global file through the default `api` routing option in `bootstrap/app.php`. Each API version is registered separately in the `then` callback with its own prefix, middleware stack, and route name prefix.

## Directory layout

```
routes/
├── api.php                    # Global ping + requires playground
├── web.php                    # Blade loader (guest + auth groups)
├── api/
│   ├── playground.php         # Non-production playground CRUD
│   └── v1.php                 # Loader for domain route files
│       ├── identity.php
│       ├── navigation.php
│       ├── notification.php
│       ├── activity-log.php
│       └── approval.php
└── web/
    ├── identity-guest.php
    ├── identity.php
    ├── navigation.php
    ├── notification.php
    ├── activity-log.php
    ├── approval.php
    └── playground.php

app/Domain/{Domain}/Http/Controllers/…   # Primary controller location (see domain-modules.md)
```

Route paths inside version files are **relative** to the version prefix. For example, `Route::get('approvals/my-pending', …)` in `routes/api/v1/approval.php` resolves to `GET /api/v1/approvals/my-pending`.

## Registration (`bootstrap/app.php`)

```php
->withRouting(
    api: __DIR__.'/../routes/api.php',
    then: function (): void {
        Route::middleware('api')
            ->prefix('api/v1')
            ->name('api.v1.')
            ->group(base_path('routes/api/v1.php'));

        // Uncomment when v2 is ready:
        // Route::middleware('api')
        //     ->prefix('api/v2')
        //     ->name('api.v2.')
        //     ->group(base_path('routes/api/v2.php'));
    },
    // ...
)
```

| Setting | Purpose |
|---------|---------|
| `api: routes/api.php` | Loads global routes with Laravel’s default `api` middleware and `/api` prefix. |
| `middleware('api')` | Applies the same middleware group as global API routes (e.g. rate limiting, bindings). |
| `prefix('api/v1')` | Mounts all routes in `v1.php` under `/api/v1`. |
| `name('api.v1.')` | Prefixes route names (e.g. `api.v1.auth.login`) for `route()` and tests. |

JSON error responses for API requests are enabled when the path matches `api/*`:

```php
$exceptions->shouldRenderJsonWhen(
    fn (Request $request) => $request->is('api/*'),
);
```

## Global routes

**File:** [routes/api.php](routes/api.php)

What is registered here today:

| Prefix / path | When active | Auth | Purpose |
|---------------|-------------|------|---------|
| `GET /api/ping` | Always | None | Liveness probe (`Pong Global`) |
| `/api/playground/*` | Non-production only | None | Reference CRUD demos — see [Playground](./playground.md) |

Minimal excerpt:

```php
Route::get('ping', [PlaygroundController::class, 'ping']);
require __DIR__.'/api/playground.php';
```

Approval and all other versioned product endpoints live under `routes/api/v1/*.php` (loaded from `routes/api/v1.php`). Public URLs are unchanged (`/api/v1/approvals/*`, etc.).

## Versioned routes

**File:** `routes/api/v1.php` (requires domain files under `routes/api/v1/`)

This is the **primary API surface** for the application. Implement new endpoints in the appropriate `routes/api/v1/{domain}.php` file unless they explicitly meet the global criteria above. The v1 surface includes `auth/*`, `users/*`, `navigation/menus/*`, `lookups/*`, `notifications/*`, `activity-logs/*`, and `approvals/*` (see the related docs below).

Controllers live under `App\Domain\{Domain}\Http\Controllers\…` — see [Domain modules](./domain-modules.md).

## Adding a new API version

Use this when you need a new contract (breaking changes), not for routine features—routine features go into the current version until you deliberately ship a new one.

Example: enabling **v2**.

### 1. Create the route file

`routes/api/v2.php` (loader — mirror v1 pattern):

```php
<?php

require __DIR__.'/v2/identity.php';
// require other domain files as v2 surfaces are added
```

`routes/api/v2/identity.php` (example):

```php
<?php

use App\Domain\Identity\Http\Controllers\API\V2\AdminController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api', 'permission:view user'])
    ->get('admin/ping', [AdminController::class, 'ping'])
    ->name('admin.ping');
```

### 2. Register the route group

In `bootstrap/app.php`, inside the `then` callback:

```php
Route::middleware('api')
    ->prefix('api/v2')
    ->name('api.v2.')
    ->group(base_path('routes/api/v2.php'));
```

### 3. Add versioned controllers

Place controllers under `app/Domain/{Domain}/Http/Controllers/API/V2/` (extend kernel `App\Http\Controllers\Controller`). See [Domain modules](./domain-modules.md) and [Internal monorepo porting](./internal-monorepo-porting.md).

## Conventions

1. **Global is minimal** — Resist growing `routes/api.php`. Most PRs should only touch `routes/api/v{N}/*.php`.
2. **Business logic is versioned** — Domain controllers, services, and HTTP resources belong under `App\Domain\{Domain}\…`, not legacy `App\Http\Controllers\API\*`.
3. **No duplication across layers** — Do not expose the same resource on `/api/...` and `/api/v1/...`.
4. **One loader per version** — Keep `routes/api/v1.php` as a loader; add domain files under `routes/api/v1/`.
5. **Mirror domain layout** — `App\Domain\Identity\Http\Controllers\API\V1\*` for v1 identity endpoints, etc.
6. **Breaking changes require a new version** — Add `V{N}` and a new route file tree; do not break existing version contracts in place.
7. **Route names** — Use the `api.v{N}.` prefix so named routes stay unique across versions.

## Verifying routes

List registered API routes:

```bash
php artisan route:list --path=api
```

Expected output (when v1 and global are active, non-production):

```
GET|HEAD  api/ping                              … Domain\Playground\…\PlaygroundController@ping
GET|HEAD  api/playground/users                  … playground.users.index
GET|HEAD  api/v1/approvals/my-pending           … Domain\Approval\…\ApprovalController@myPending
GET|HEAD  api/v1/admin/approval-workflows       … Domain\Approval\…\ApprovalWorkflowAdminController@index
GET|HEAD  api/v1/admin/ping                     … api.v1.admin.ping › Domain\Identity\…\AdminController@ping
```

In production, playground routes are absent; `api/ping` and versioned routes remain.

## Playground examples

The global `ping` endpoint exists only to demonstrate wiring and doubles as a liveness probe. Product behavior is implemented solely under versioned routes (`auth/*`, `users/*`, `navigation/menus/*`, `notifications/*`); there is intentionally **no** versioned `ping`.

The playground prefix also hosts reference CRUD surfaces (`playground/users`, `playground/orders`). These are **demonstrations** of the layered architecture — simple CRUD and a master-detail aggregate respectively — not product endpoints. Real order/user features would live under a version prefix. See [CRUD Architecture — master-detail aggregates](./crud-architecture.md#master-detail-aggregates-transactions-dedicated-repository).

| Endpoint | Layer | Response message (example) |
|----------|-------|----------------------------|
| `GET /api/ping` | Global | `Pong Global` |
| `GET /api/v1/admin/ping` | Versioned (RBAC-gated) | `api/admin.access_granted` |

Both use the shared `App\Support\ApiResponder` (or domain controller response helpers) for consistent JSON structure.

## Related documentation

- [JWT Authentication & RBAC](./jwt-authentication.md) — registration, login, JWT, email verification, password reset, and Spatie permissions on v1
- [User Management & Hierarchical RBAC](./user-management-hrbac.md) — versioned user CRUD, role levels, policies, and hierarchy rules on v1
- [CRUD Architecture & Repository Pattern](./crud-architecture.md) — layer responsibilities, generic vs dedicated repositories, `Route::apiResource` for simple CRUD, and the Playground User reference implementation
