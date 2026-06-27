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

**Everything else**—authentication, users, orders, payments, CRUD, workflows, integrations, and any logic that touches domain rules or persisted data. New features and changes always go through `routes/api/v{N}.php` and `App\Http\Controllers\API\V{N}\`.

When behavior changes incompatibly, add a new version (`v2`, `v3`, …) rather than altering an existing version’s contract.

## Overview

| Layer | Route file | Controller namespace | URL prefix | Route name prefix |
|-------|------------|----------------------|------------|-------------------|
| Global | `routes/api.php` | `App\Http\Controllers\API` | `/api` | *(none)* |
| Versioned | `routes/api/v{N}.php` | `App\Http\Controllers\API\V{N}` | `/api/v{N}` | `api.v{N}.` |

Laravel registers the global file through the default `api` routing option in `bootstrap/app.php`. Each API version is registered separately in the `then` callback with its own prefix, middleware stack, and route name prefix.

## Directory layout

```
routes/
├── api.php              # Global only — keep minimal
└── api/
    ├── v1.php           # Primary application API (v1)
    └── v2.php           # Optional next version (enable in bootstrap)

app/Http/Controllers/API/
├── PlaygroundController.php       # Global ping + playground users demo
├── PlaygroundOrderController.php  # Global playground orders demo
├── ApprovalController.php         # Authenticated approval actions (v1-prefixed in routes/api.php)
├── ApprovalWorkflowAdminController.php
└── V1/
    ├── Auth/                      # Versioned application controllers
    ├── Navigation/
    ├── UserController.php
    ├── NotificationController.php
    └── AdminController.php
```

Route paths inside version files are **relative** to the version prefix. For example, a hypothetical `Route::get('ping', ...)` in `routes/api/v1.php` would resolve to `GET /api/v1/ping`, not `GET /api/v1/v1/ping`.

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
| `/api/playground/*` | Non-production only (`APP_ENV` ≠ `production`) | None | Reference CRUD demos — see [Playground](./playground.md) |
| `/api/v1/approvals/*` | Always | `auth:api` | Approval inbox and slot actions |
| `/api/v1/admin/approval-workflows/*` | Always | `auth:api` | Approval workflow setup (API enforces Root/Admin) |

Minimal excerpt:

```php
use App\Http\Controllers\API\PlaygroundController;
use Illuminate\Support\Facades\Route;

Route::get('ping', [PlaygroundController::class, 'ping']);

// Approval routes use a v1/ prefix but live in this global file for historical wiring.
Route::middleware('auth:api')->prefix('v1/approvals')->group(function () { /* … */ });

if (! app()->environment('production')) {
    Route::prefix('playground')->group(function () { /* … */ });
}
```

**Why approval routes sit in `routes/api.php`:** they were added alongside playground wiring and use a `v1/` URL prefix without living in `routes/api/v1.php`. New versioned features should still go in `routes/api/v{N}.php` unless there is a strong reason to extend this file.

Global routes are **not** the default place to add new API features. Before adding a route here, confirm the endpoint is truly version-agnostic and safe. If it implements or orchestrates business logic, it belongs under a versioned route file instead.

Controllers live in `App\Http\Controllers\API\` (no `V{N}` segment). Keep this namespace small; most controllers should live under `API\V1\`, `API\V2\`, etc.

## Versioned routes

**File:** `routes/api/v1.php`

```php
use App\Http\Controllers\API\V1\AdminController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api', 'permission:view user'])
    ->get('admin/ping', [AdminController::class, 'ping'])
    ->name('admin.ping');
```

**Example endpoint:** `GET /api/v1/admin/ping`  
**Example route name:** `api.v1.admin.ping` (via `route('api.v1.admin.ping')`)

This is the **primary API surface** for the application. Implement new endpoints here unless they explicitly meet the global criteria above. The real v1 surface includes `auth/*`, `users/*`, `navigation/menus/*`, `notifications/*`, and `activity-logs/*` (see the related docs below).

Approval endpoints (`/api/v1/approvals/*`, `/api/v1/admin/approval-workflows/*`) are registered from [routes/api.php](routes/api.php) with a `v1/` prefix — see [Approval matrix engine](./approval-matrix-engine.md).

Place controllers under `App\Http\Controllers\API\V1\` (or `V2`, `V3`, etc.). Do not change existing version namespaces when introducing a new version; add a new namespace so older clients keep working.

## Adding a new API version

Use this when you need a new contract (breaking changes), not for routine features—routine features go into the current version until you deliberately ship a new one.

Example: enabling **v2**.

### 1. Create the route file

`routes/api/v2.php`:

```php
<?php

use App\Http\Controllers\API\V2\PlaygroundController;
use Illuminate\Support\Facades\Route;

Route::get('ping', [PlaygroundController::class, 'ping']);
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

`app/Http/Controllers/API/V2/PlaygroundController.php`:

```php
<?php

namespace App\Http\Controllers\API\V2;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;

class PlaygroundController extends Controller
{
    use ApiResponse;

    public function ping()
    {
        return $this->successResponse(null, 'Pong V2', 200);
    }
}
```

Repeat the same three steps for `v3`, `v4`, and so on.

## Conventions

1. **Global is minimal** — Resist growing `routes/api.php`. Most PRs should only touch `routes/api/v{N}.php`.
2. **Business logic is versioned** — Domain controllers, services, and HTTP resources belong under `API\V{N}\`, not `API\`.
3. **No duplication across layers** — Do not expose the same resource on `/api/...` and `/api/v1/...`.
4. **One route file per version** — Keep `routes/api/v1.php`, `v2.php`, etc. separate; avoid mixing versions in one file.
5. **Mirror controller namespaces** — `API\V1\*` for v1, `API\V2\*` for v2.
6. **Breaking changes require a new version** — Add `V{N}` and a new route file; do not break existing version contracts in place.
7. **Route names** — Use the `api.v{N}.` prefix so named routes stay unique across versions.

## Verifying routes

List registered API routes:

```bash
php artisan route:list --path=api
```

Expected output (when v1 and global are active, non-production):

```
GET|HEAD  api/ping                              … API\PlaygroundController@ping
GET|HEAD  api/playground/users                  … playground.users.index
GET|HEAD  api/v1/approvals/my-pending           … API\ApprovalController@myPending
GET|HEAD  api/v1/admin/approval-workflows       … API\ApprovalWorkflowAdminController@index
GET|HEAD  api/v1/admin/ping                     … api.v1.admin.ping › API\V1\AdminController@ping
```

In production, playground routes are absent; `api/ping` and versioned routes remain.

## Playground examples

The global `ping` endpoint exists only to demonstrate wiring and doubles as a liveness probe. Product behavior is implemented solely under versioned routes (`auth/*`, `users/*`, `navigation/menus/*`, `notifications/*`); there is intentionally **no** versioned `ping`.

The playground prefix also hosts reference CRUD surfaces (`playground/users`, `playground/orders`). These are **demonstrations** of the layered architecture — simple CRUD and a master-detail aggregate respectively — not product endpoints. Real order/user features would live under a version prefix. See [CRUD Architecture — master-detail aggregates](./crud-architecture.md#master-detail-aggregates-transactions-dedicated-repository).

| Endpoint | Layer | Response message (example) |
|----------|-------|----------------------------|
| `GET /api/ping` | Global | `Pong Global` |
| `GET /api/v1/admin/ping` | Versioned (RBAC-gated) | `api/admin.access_granted` |

Both use the shared `App\Traits\ApiResponse` trait for consistent JSON structure.

## Related documentation

- [JWT Authentication & RBAC](./jwt-authentication.md) — registration, login, JWT, email verification, password reset, and Spatie permissions on v1
- [User Management & Hierarchical RBAC](./user-management-hrbac.md) — versioned user CRUD, role levels, policies, and hierarchy rules on v1
- [CRUD Architecture & Repository Pattern](./crud-architecture.md) — layer responsibilities, generic vs dedicated repositories, `Route::apiResource` for simple CRUD, and the Playground User reference implementation
