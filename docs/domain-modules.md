# Domain modules (internal monorepo)

Project Monolith uses a **logical monorepo** layout: domain code lives under `app/Domain/{Domain}/` inside a single Laravel application and git repository. Gateway and Frontend remain **separate repositories**; they only depend on Monolith **HTTP API contracts**, not PHP namespaces.

## Core, locked, and removable

| Kind | Domains | `domain:remove` |
|------|---------|-----------------|
| **Core** (platform) | Identity, Navigation, Notification, ActivityLog, Approval, PaymentGateway + Kernel | **Always refused** — hardcoded in `DomainManager::PLATFORM_CORE`; flipping `core` in config does nothing |
| **Locked** (product) | Default for `domain:make` | Refused until you **manually** set `'locked' => false` in [`config/domains.php`](../config/domains.php) |
| **Removable** | Playground, or `domain:make --removable` | Allowed (confirm / `--force`) |

There is **no** `domain:unlock` command — unlock is a deliberate config edit.

Registry: [`config/domains.php`](../config/domains.php) (`core`, `locked`).

## Day-2 commands

```powershell
php artisan domain:list
php artisan domain:make Billing                 # locked by default (product-safe)
php artisan domain:make Billing --locked        # explicit locked
php artisan domain:make Scratch --removable     # can be removed immediately
php artisan domain:remove Scratch --force
# Locked product: edit config/domains.php → 'locked' => false, then:
php artisan domain:remove Billing --force
php artisan domain:remove Playground --force    # demo; migrations kept
```

| Command | What it does |
|---------|----------------|
| `domain:list` | Name, kind (`core` / `locked` / `removable`), whether `app/Domain/{Name}` exists |
| `domain:make {Name}` | Scaffold folders, ServiceProvider, API/Web route stubs, test dir; register everywhere. **Locked by default**; `--removable` for throwaway modules |
| `domain:remove {Name}` | Delete domain PHP/routes/tests/provider; unregister. **Refuses platform core and locked.** Does **not** delete migrations; warns about factories/seeders/menus |

Implementation: [`app/Support/Domain/DomainManager.php`](../app/Support/Domain/DomainManager.php), commands under [`app/Console/Commands/Domain/`](../app/Console/Commands/Domain/).

## Directory layout

```
app/
├── Domain/
│   ├── Identity/       # Auth, User, RBAC, lookups, admin ping (core)
│   ├── Navigation/     # Menu tree and management (core)
│   ├── Notification/   # Activity notifications (bell) (core)
│   ├── ActivityLog/    # Spatie audit trail (core)
│   ├── Playground/     # Demo users, orders, attachments (locked)
│   ├── PaymentGateway/ # Midtrans, iPaymu, invoices, subscriptions (core)
│   └── Approval/       # Approval matrix engine (core)
├── Http/Middleware/    # Shared HTTP middleware (Kernel)
├── Repositories/Eloquent/EloquentCrudRepository.php
├── Support/            # Api, DataTable, Web, Auth, Domain helpers (Kernel)
├── Exceptions/         # BusinessRuleException (Kernel)
├── Http/Controllers/   # Base Controller + Web concerns only (Kernel)
├── Http/Requests/Concerns/
├── Http/Resources/Concerns/
├── Console/Commands/   # Env/locale/key + Domain/* artisan (Kernel)
└── Providers/
    ├── AppServiceProvider.php          # Kernel-only bindings (Kernel)
    └── Domain/
        ├── IdentityServiceProvider.php
        ├── NavigationServiceProvider.php
        ├── NotificationServiceProvider.php
        ├── ActivityLogServiceProvider.php
        ├── PlaygroundServiceProvider.php
        └── ApprovalServiceProvider.php
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
| `routes/api/v1/payment-gateway.php` | Payment gateways, invoices, subscriptions, Midtrans/iPaymu providers |

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

- **Identity** (`User`, `Role`, `Permission`) is the shared hub — other domains import `App\Domain\Identity\Models\*` for relationships.
- **Approval** is cross-cutting (attaches to `document_type` such as orders).
- Do not import domain services across boundaries unless the dependency is explicit and documented.

## Adding a new domain

Prefer the Artisan flow:

1. `php artisan domain:make {Name}` (locked) or `domain:make {Name} --removable`
2. Implement controllers, services, repositories, models under `app/Domain/{Name}/`.
3. Fill in `routes/api/v1/{slug}.php` and `routes/web/{slug}.php`.
4. Bind repositories / policies in the generated `*ServiceProvider` (already registered).
5. Add tests under `tests/Domain/{Name}/`.
6. If the domain exposes new **public URLs** or JSON shapes, add a [downstream changelog](downstream-changelog/README.md) entry (Gateway/Frontend sync happens in a downstream sync session).
7. To remove a locked domain later: set `'locked' => false` in `config/domains.php`, then `domain:remove {Name}`.
8. Update this doc and [internal-monorepo-assessment.md](./internal-monorepo-assessment.md) if the domain map changes.

## Ongoing scripts

| Script | Purpose |
|--------|---------|
| [`scripts/test-workspace.ps1`](../scripts/test-workspace.ps1) | Run Monolith + Gateway + Frontend tests |
| [`scripts/remove-empty-dirs.ps1`](../scripts/remove-empty-dirs.ps1) | Optional cleanup of empty directories (`-WhatIf`) |

One-shot porting scripts (`migrate-domain-*`, `fix-domain-*`, `scripts/domains/*.json`) were **removed** after the logical monorepo migration completed.

## What we deliberately did not do

- No Composer path packages (`packages/*`) — deferred until codebase or team size justifies the overhead.
- No `nwidart/laravel-modules`.
- No merge of Gateway or Frontend into this git repository.
- No dynamic provider auto-discovery — `bootstrap/providers.php` stays explicit; `domain:*` edits it.
- No `domain:unlock` Artisan command — unlocking a product domain is a manual `config/domains.php` edit.

See [internal-monorepo-assessment.md](./internal-monorepo-assessment.md) for rationale and phase-4 triggers.
