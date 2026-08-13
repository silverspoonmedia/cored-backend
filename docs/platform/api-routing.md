# API Routing

Two **independent** layers: **global** (unversioned) and **versioned**. They do not evolve into each other.

## Design principles

| Layer | Role |
|-------|------|
| **Global** (`/api/*`) | Small stable surface: liveness only (`GET /api/ping`) |
| **Versioned** (`/api/v{N}/*`) | **All product business logic** |

Do not mirror the same endpoints on both layers. Breaking changes → new version (`v2`), not in-place contract breaks.

## Overview

| Layer | Route files | Controllers | Prefix | Name prefix |
|-------|-------------|-------------|--------|-------------|
| Global | `routes/api.php` | `App\Http\Controllers\API\PingController` | `/api` | *(none)* |
| Versioned | `routes/api/v{N}.php` + `routes/api/v{N}/*.php` | `App\Domain\{Domain}\Http\Controllers\API\V{N}` | `/api/v{N}` | `api.v{N}.` |

## Directory layout

```
routes/
├── api.php             # GET /api/ping only
├── web.php             # loader: guest/public specials + web.auth (core then product)
├── api/
│   └── v1.php          # loader — // Core requires, then // Product
│       ├── identity.php          # core
│       ├── navigation.php        # core
│       ├── notification.php      # core
│       ├── activity-log.php      # core
│       ├── error-log.php         # core
│       ├── payment-gateway.php   # core
│       ├── service.php           # core
│       ├── broadcasting.php      # core — broadcast auth for JWT clients (api middleware)
│       └── file-hosting.php      # product
└── web/
    ├── identity-guest.php        # web.guest (auth surface, not kind)
    ├── identity.php              # core
    ├── navigation.php            # core
    ├── notification.php          # core
    ├── activity-log.php          # core
    ├── error-log.php             # core
    ├── payment-gateway.php       # core
    ├── service.php               # core
    ├── file-hosting.php          # product (web.auth)
    ├── file-hosting-public.php   # public share
    └── payment-ipaymu-return.php # provider return
```

Paths inside version files are relative to the version prefix.

**Kind ordering:** loaders keep core domain files before product (comment sections only). No `core/` / `product/` route subdirectories — see [architecture.md](architecture.md).

## Registration (`bootstrap/app.php`)

```php
->withRouting(
    api: __DIR__.'/../routes/api.php',
    then: function (): void {
        Route::middleware('api')
            ->prefix('api/v1')
            ->name('api.v1.')
            ->group(base_path('routes/api/v1.php'));
    },
)
```

JSON errors for `api/*` via `$exceptions->shouldRenderJsonWhen(...)`.

## Global routes today

| Path | When | Auth | Purpose |
|------|------|------|---------|
| `GET /api/ping` | Always | None | Liveness (`api/common.pong`) |

## Versioned routes

Primary product surface. Add endpoints in `routes/api/v1/{domain}.php`. Includes auth, users, menus, lookups, notifications, activity-logs, error-logs, payments, services, file-hosting. Also includes `POST /api/v1/broadcasting/auth` — broadcast channel auth for cross-origin JWT clients (Vue-Workers SPA) under `auth:api` middleware, distinct from Laravel's default web-middleware `/broadcasting/auth` used by Blade.

## Adding a new API version

1. Create `routes/api/v2.php` loader + domain files under `routes/api/v2/`.
2. Register group in `bootstrap/app.php` with `prefix('api/v2')` and `name('api.v2.')`.
3. Controllers under `app/Domain/{Domain}/Http/Controllers/API/V2/`.

## Conventions

1. Keep global minimal — most PRs touch `routes/api/v{N}/*.php` only.
2. Business logic under `App\Domain\{Domain}\…`.
3. No dual exposure on `/api` and `/api/v1`.
4. One loader per version; domain files underneath.
5. Breaking changes require a new version.
6. Route names use `api.v{N}.` prefix.
7. In loaders, core requires before product; `domain:make` appends — keep product block last when reordering.

## Verify

```bash
php artisan route:list --path=api
```

## Related

- [architecture.md](architecture.md) — domain layout
- [crud.md](crud.md) — `apiResource` vs rich routes
- [domains/identity](../domains/identity/README.md) — auth/users on v1
