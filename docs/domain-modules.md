# Domain modules (internal monorepo)

Project Monolith uses a **logical monorepo** layout: domain code lives under `app/Domain/{Domain}/` inside a single Laravel application and git repository. Gateway and Frontend remain **separate repositories**; they only depend on Monolith **HTTP API contracts**, not PHP namespaces.

## Directory layout

```
app/
├── Domain/
│   ├── Identity/       # Auth, User, RBAC, lookups, admin ping
│   ├── Navigation/     # Menu tree and management
│   ├── Notification/   # Activity notifications (bell)
│   ├── ActivityLog/    # Spatie audit trail
│   ├── Playground/     # Demo users, orders, attachments
│   └── Approval/       # Approval matrix engine
├── Http/Middleware/    # Shared HTTP middleware (Kernel)
├── Repositories/Eloquent/EloquentCrudRepository.php
├── Support/            # Api, DataTable, Web, Auth helpers (Kernel)
├── Exceptions/         # BusinessRuleException + Auth/* (Kernel; move to Identity later)
├── Http/Controllers/   # Base Controller + Web concerns only (Kernel)
├── Http/Requests/Concerns/
├── Http/Resources/Concerns/
├── Console/Commands/   # Env/locale/key artisan (Kernel)
└── Providers/AppServiceProvider.php
```

Each domain typically contains:

| Folder | Purpose |
|--------|---------|
| `Http/Controllers/API/` or `API/V1/` | JSON API surface |
| `Http/Controllers/Web/` | Blade proxy controllers |
| `Http/Requests/` | Form requests |
| `Http/Resources/` | API resources |
| `Services/` | Business rules |
| `Repositories/` | Persistence contracts + Eloquent |
| `Models/` | Eloquent models (when domain-owned) |
| `Exceptions/` | Domain exceptions |
| `Events/`, `Listeners/`, `Notifications/` | As needed |

Tests mirror domains under `tests/Domain/{Domain}/` and are registered in the `Domain` PHPUnit suite (`phpunit.xml`).

## Routes

Versioned API routes are split per domain under `routes/api/v1/` and loaded from [`routes/api/v1.php`](../routes/api/v1.php):

| File | Domain |
|------|--------|
| `routes/api/v1/identity.php` | Auth, users, lookups, admin |
| `routes/api/v1/navigation.php` | Menus |
| `routes/api/v1/notification.php` | Notifications |
| `routes/api/v1/activity-log.php` | Activity logs |
| `routes/api/v1/approval.php` | Approvals + workflow admin |

Global `/api` routes: `routes/api.php` (ping) and `routes/api/playground.php` (non-production playground).

Blade web routes are split per domain under `routes/web/` and loaded from [`routes/web.php`](../routes/web.php):

| File | Domain |
|------|--------|
| `routes/web/identity-guest.php` | Guest auth screens (login, register, password reset) |
| `routes/web/identity.php` | Dashboard, profile, users, lookups |
| `routes/web/approval.php` | Approvals + workflow admin |
| `routes/web/navigation.php` | Navigation tree + menu CRUD |
| `routes/web/playground.php` | Orders (playground reference) |
| `routes/web/activity-log.php` | Activity log screens |
| `routes/web/notification.php` | Notification bell screens |

**Public URLs are unchanged** — e.g. approvals remain `/api/v1/approvals/*`.

## Namespace convention

| Old (layer-first) | New (domain) |
|-------------------|--------------|
| `App\Models\User` | `App\Domain\Identity\Models\User` |
| `App\Services\Navigation\MenuService` | `App\Domain\Navigation\Services\MenuService` |
| `App\Http\Controllers\API\V1\UserController` | `App\Domain\Identity\Http\Controllers\API\V1\UserController` |

Shared infrastructure stays in `App\Support\*`, `App\Http\Middleware\*`, and `App\Repositories\Eloquent\EloquentCrudRepository`.

## Cross-domain dependencies

- **Identity** (`User`, `Role`, `Permission`) is the shared kernel — other domains import `App\Domain\Identity\Models\*` for relationships.
- **Approval** is cross-cutting (attaches to `document_type` such as orders).
- Do not import domain services across boundaries unless the dependency is explicit and documented.

## Adding a new domain

1. Create `app/Domain/{Name}/` with controllers, services, repositories, models.
2. Add `routes/api/v1/{name}.php` and `require` it from `routes/api/v1.php`.
3. Add `routes/web/{name}.php` (and guest routes if needed) and `require` from `routes/web.php`.
4. Register repository bindings in `AppServiceProvider` (or a dedicated domain service provider later).
5. Add tests under `tests/Domain/{Name}/`.
6. If the domain exposes new **public URLs** or JSON shapes, update Gateway/Frontend in the same task.
7. Update this doc and [internal-monorepo-assessment.md](./internal-monorepo-assessment.md).

## Migration tooling

Domain moves are scripted for repeatability:

| Script | Purpose |
|--------|---------|
| [`scripts/migrate-domain-structure.php`](../scripts/migrate-domain-structure.php) | Move files + namespace replacements from JSON config |
| [`scripts/migrate-domain.ps1`](../scripts/migrate-domain.ps1) | Orchestrate bulk namespace replace after file moves |
| [`scripts/domains/*.json`](../scripts/domains/) | Per-domain move/replace definitions (Navigation migrated manually — no JSON) |
| [`scripts/fix-domain-namespaces.php`](../scripts/fix-domain-namespaces.php) | Fix `namespace` lines from PSR-4 paths |
| [`scripts/fix-domain-controller-imports.php`](../scripts/fix-domain-controller-imports.php) | Add base `Controller` imports |
| [`scripts/fix-domain-model-factories.php`](../scripts/fix-domain-model-factories.php) | Add `newFactory()` for moved models |
| [`scripts/remove-empty-dirs.ps1`](../scripts/remove-empty-dirs.ps1) | Remove empty porting debris directories |
| [`scripts/test-workspace.ps1`](../scripts/test-workspace.ps1) | Run Monolith + Gateway + Frontend tests |

## What we deliberately did not do

- No Composer path packages (`packages/*`) — deferred until codebase or team size justifies the overhead.
- No `nwidart/laravel-modules`.
- No merge of Gateway or Frontend into this git repository.

See [internal-monorepo-assessment.md](./internal-monorepo-assessment.md) for rationale and phase-4 triggers.
