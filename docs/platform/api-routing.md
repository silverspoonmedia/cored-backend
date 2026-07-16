# API Routing

Two **independent** layers: **global** (unversioned) and **versioned**. They do not evolve into each other.

## Design principles

| Layer | Role |
|-------|------|
| **Global** (`/api/*`) | Small stable surface: liveness, non-prod playground demos |
| **Versioned** (`/api/v{N}/*`) | **All product business logic** |

Do not mirror the same endpoints on both layers. Breaking changes → new version (`v2`), not in-place contract breaks.

## Overview

| Layer | Route files | Controllers | Prefix | Name prefix |
|-------|-------------|-------------|--------|-------------|
| Global | `routes/api.php` + `routes/api/playground.php` | Domain Playground API | `/api` | *(none)* |
| Versioned | `routes/api/v{N}.php` + `routes/api/v{N}/*.php` | `App\Domain\{Domain}\Http\Controllers\API\V{N}` | `/api/v{N}` | `api.v{N}.` |

## Directory layout

```
routes/
├── api.php
├── web.php
├── api/
│   ├── playground.php
│   └── v1.php          # loader
│       ├── identity.php
│       ├── navigation.php
│       ├── notification.php
│       ├── activity-log.php
│       └── payment-gateway.php
└── web/
    ├── identity-guest.php
    ├── identity.php
    ├── navigation.php
    ├── notification.php
    ├── activity-log.php
    ├── payment-gateway.php
    └── playground.php
```

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
| `GET /api/ping` | Always | None | Liveness (`Pong Global`) |
| `/api/playground/*` | Non-production | None | Demo CRUD — [Playground](../domains/playground/README.md) |

## Versioned routes

Primary product surface. Add endpoints in `routes/api/v1/{domain}.php`. Includes auth, users, menus, lookups, notifications, activity-logs, payments.

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

## Verify

```bash
php artisan route:list --path=api
```

## Related

- [architecture.md](architecture.md) — domain layout
- [crud.md](crud.md) — `apiResource` vs rich routes
- [domains/identity](../domains/identity/README.md) — auth/users on v1
