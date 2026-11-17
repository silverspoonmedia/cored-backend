# API Routing — reference

Detail behind [api-routing.md](api-routing.md): the layer tables, the directory layout, registration in `bootstrap/app.php`, and how to add a version.

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
├── web.php             # loader: server endpoints only (share, payment return, OAuth)
├── api/
│   └── v1.php          # loader — // Core requires, then // Product
│       ├── identity.php          # core
│       ├── inference.php         # core
│       ├── ai.php                # core
│       ├── payment-gateway.php   # core
│       ├── service.php           # core
│       ├── broadcasting.php      # core — broadcast auth for JWT clients (api middleware)
│       └── archivd.php           # product
└── web/
    ├── archivd-public.php        # public share
    ├── payment-ipaymu-return.php # provider return
    └── identity-oauth-callback.php # Discord OAuth callback
```

Packaged core domains load from their own tree — `packages/navigation/routes/api.php`, `packages/notification/routes/api.php`, and `packages/audit/routes/{activity-log,error-log}.php` — through `DomainManager::packageApiRoutePaths()`, so they have no entry under `routes/`.

Paths inside version files are relative to the version prefix.

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

Primary product surface. Add endpoints in `routes/api/v1/{domain}.php`. Includes auth, users, menus, lookups, notifications, activity-logs, error-logs, payments, services, archivd. Also includes `POST /api/v1/broadcasting/auth` — broadcast channel auth for cross-origin JWT clients (Vue-Workers SPA) under `auth:api` middleware plus the `throttle:broadcast` limiter. The path literal lives once in `App\Support\Broadcasting\BroadcastAuthEndpoint` (`SUBPATH` for `routes/api/v1/broadcasting.php`, `url()` for `BroadcastConnection::socketClientConfig()`); `withRouting(channels: ...)` also registers a CSRF-guarded **web twin** at `/broadcasting/auth`, which is why `config/cors.php` `paths` must list `broadcasting/auth` as well — see [reference-security.md — `broadcasting/auth` invariant](reference-security.md#broadcastingauth-invariant) and invariant test `tests/Feature/Http/CorsBroadcastAuthTest.php`.

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

- [api-routing.md](api-routing.md) — brief
- [architecture.md](architecture.md) — domain layout
- [crud.md](crud.md) — `apiResource` vs rich routes
- [domains/identity](../domains/identity/README.md) — auth/users on v1
