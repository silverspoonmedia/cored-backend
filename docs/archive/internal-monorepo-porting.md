# Internal monorepo porting — implementation reference

Session handoff guide for the **logical monorepo** migration inside Project Monolith. Use this when continuing work, onboarding agents, or porting additional domains.

**Related docs:**

- [internal-monorepo-assessment.md](./internal-monorepo-assessment.md) — why, domain map, phases, deferrals
- [domain-modules.md](../platform/architecture.md) — conventions and how to add a domain
- [ai-agent-guide.md](../agents/core-rules.md) — agent workflow and downstream sync sessions

---

## Goals and scope

| In scope | Out of scope |
|----------|--------------|
| Reorganize Monolith PHP under `app/Domain/{Domain}/` | Merge Gateway or Frontend into this git repo |
| Split route files per domain (URLs unchanged) | Composer path packages (`packages/*`) |
| Mirror tests under `tests/Domain/{Domain}/` | Shared PHP/TS libraries across the three repos |
| Single `App\` PSR-4 autoload | `nwidart/laravel-modules` |

Gateway and Frontend **only change** when HTTP URLs or JSON response shapes change — not when internal PHP namespaces move.

---

## Phases completed

| Phase | Status | Deliverable |
|-------|--------|-------------|
| 0 — Assessment & baseline | Done | [internal-monorepo-assessment.md](./internal-monorepo-assessment.md), baseline test counts |
| 1 — Logical domain folders | Done | Six domains under `app/Domain/*` |
| 2 — Route normalization | Done | `routes/api/v1/*.php`, `routes/web/*.php`, approvals on v1 |
| Post-migration cleanup | Done | 78 empty directories removed via `scripts/remove-empty-dirs.ps1`; 14 more removed after the housekeeping pass below |
| 3 — Per-domain ServiceProviders | Done | `app/Providers/Domain/{Identity,Navigation,Notification,ActivityLog,Playground,Approval}ServiceProvider.php`; `AppServiceProvider` now kernel-only |
| 4 — Composer packages | **Deferred** | See assessment doc for triggers |

---

## Domain migration order

Migrate **one domain per PR**, least coupled first:

| Order | Domain | Why |
|-------|--------|-----|
| 1 | **Navigation** | Own tables, few FKs, isolated `MenuRepository` |
| 2 | **ActivityLog** | Mostly read-only Spatie wrapper |
| 3 | **Notification** | Relatively isolated |
| 4 | **Playground** | Sandbox tables, own lifecycle |
| 5 | **Identity** | Hub — `User`, `Role`, `Permission`; all domains depend on it |
| 6 | **Approval** | Cross-cutting, attaches to `document_type` |

Navigation was migrated **manually** (pilot). Other domains used JSON configs under `scripts/domains/` (those configs and migrate/fix scripts were **deleted** after the port completed — use `domain:*` for day-2 work).

---

## Per-domain checklist

For each domain, in one PR:

1. **Move files** into `app/Domain/{Domain}/` (controllers, services, repositories, models, requests, resources, events, listeners, notifications, exceptions, support classes).
2. **Update namespaces** to `App\Domain\{Domain}\…`.
3. **Update imports** across the codebase (routes, config, factories, seeders, other domains).
4. **Move tests** to `tests/Domain/{Domain}/Feature|Unit/…`.
5. **Register bindings** in the domain's ServiceProvider under `app/Providers/Domain/{Domain}ServiceProvider.php` (repository interfaces → Eloquent implementations), and add it to `bootstrap/providers.php`.
6. **Discover listeners/commands** in `bootstrap/app.php` if the domain has them.
7. Run `composer dump-autoload`.
8. **Do not change** public route URLs or JSON response shapes.
9. Run `composer test` — all suites must pass.
10. Update relevant `docs/` guides (paths, route files).

---

## Namespace mapping

| Old (layer-first) | New (domain) |
|-------------------|--------------|
| `App\Models\User` | `App\Domain\Identity\Models\User` |
| `App\Models\Menu` | `App\Domain\Navigation\Models\Menu` |
| `App\Models\Order` | `App\Domain\Playground\Models\Order` |
| `App\Services\Navigation\MenuService` | `App\Domain\Navigation\Services\MenuService` |
| `App\Http\Controllers\API\V1\UserController` | `App\Domain\Identity\Http\Controllers\API\V1\UserController` |
| `App\Http\Controllers\API\PlaygroundController` | `App\Domain\Playground\Http\Controllers\API\PlaygroundController` |
| `App\Http\Controllers\Web\UserController` | `App\Domain\Identity\Http\Controllers\Web\UserController` |
| `App\View\Composers\NavigationComposer` | `App\Domain\Navigation\View\Composers\NavigationComposer` |
| `App\Exceptions\Auth\InvalidCredentialsException` | `App\Domain\Identity\Exceptions\Auth\InvalidCredentialsException` |

**Kernel (unchanged):**

| Path | Role |
|------|------|
| `App\Http\Controllers\Controller` | API base controller |
| `App\Http\Controllers\Web\Controller` + `Concerns/*` | Blade proxy base (`ConsumesApi`, `IssuesAuthCookie`, …) |
| `App\Http\Middleware\*` | Shared middleware |
| `App\Support\*` | ApiClient, DataTable, Auth cookie, Web helpers |
| `App\Repositories\Eloquent\EloquentCrudRepository` | Generic CRUD base |
| `App\Exceptions\BusinessRuleException` | Shared base exception (domain-specific subclasses, e.g. `App\Domain\Identity\Exceptions\Auth\*`, live under their domain) |
| `App\Http\Requests\Concerns\InteractsWithListDisplayMode` | Shared request trait |
| `App\Http\Resources\Concerns\ResolvesResourceArray` | Shared resource trait |
| `App\Console\Commands/*` | Env/locale/key artisan commands |
| `App\Providers\AppServiceProvider` | Kernel bindings only (`EloquentCrudRepositoryInterface`, `DataTableResponseFactory`, Blade component path). Historical snapshot — `DataTableResponseFactory` was later removed; lists are query-mode only. |
| `App\Providers\Domain\*ServiceProvider` | Per-domain bindings, policies, event listeners, view composers (registered in `bootstrap/providers.php`) |

Domain controllers **extend** kernel base classes and **import** kernel concerns — they do not duplicate them.

---

## Bootstrap changes

[`bootstrap/app.php`](../bootstrap/app.php):

```php
->withEvents(discover: [
    __DIR__.'/../app/Domain/Identity/Listeners',
    __DIR__.'/../app/Domain/Notification/Listeners',
    __DIR__.'/../app/Domain/Approval/Listeners',
])
->withCommands([
    __DIR__.'/../app/Domain/Approval/Console/Commands',
    __DIR__.'/../app/Domain/Navigation/Console/Commands',
])
```

`config/auth.php` and `config/permission.php` reference `App\Domain\Identity\Models\*`.

---

## Route normalization

### API

```
routes/api.php              → ping + require playground.php
routes/api/playground.php   → /api/playground/* (non-production)
routes/api/v1.php           → loader
routes/api/v1/identity.php  → auth, users, lookups, admin
routes/api/v1/navigation.php
routes/api/v1/notification.php
routes/api/v1/activity-log.php
routes/api/v1/approval.php  → /api/v1/approvals/*, workflow admin
```

### Web (Blade)

```
routes/web.php              → loader (guest + auth middleware groups)
routes/web/identity-guest.php
routes/web/identity.php
routes/web/approval.php
routes/web/navigation.php
routes/web/playground.php
routes/web/activity-log.php
routes/web/notification.php
```

**Public URLs were not changed** during porting.

---

## Test migration

[`phpunit.xml`](../phpunit.xml) defines three suites:

| Suite | Path |
|-------|------|
| `Domain` | `tests/Domain/` (primary — mirrors domains) |
| `Feature` | `tests/Feature/` (kernel, console, middleware, integration leftovers) |
| `Unit` | `tests/Unit/` (kernel helpers only — `App\Rules\Hcaptcha`, `HcaptchaVerifier`, config, localization parity, support classes) |

Example: `tests/Domain/Playground/Feature/Http/Controllers/API/PlaygroundUserCrudTest.php`.

---

## Scripts inventory

One-shot porting scripts (`migrate-domain-*`, `fix-domain-*`, `scripts/domains/*.json`) were **deleted** after phases 0–3 completed. Do not reintroduce them for day-2 work.

| Script | Purpose |
|--------|---------|
| [`scripts/remove-empty-dirs.ps1`](../scripts/remove-empty-dirs.ps1) | Optional empty-dir cleanup (`-WhatIf`) |
| [`scripts/test-workspace.ps1`](../scripts/test-workspace.ps1) | Run Monolith + Gateway + Frontend tests |

**Day-2 domain management** (add/remove modules): see [domain-modules.md](../platform/architecture.md) — `php artisan domain:list|make|remove`.

---

## Gotchas encountered

| Issue | Fix |
|-------|-----|
| `UserFactory` resolving `App\User` | Set `protected $model = User::class` on factory |
| Cross-domain model relations | Explicit `use App\Domain\Identity\Models\{User, Role}` on domain models |
| Domain Web controllers missing base class | `use App\Http\Controllers\Web\Controller` |
| Event listeners not firing after move | `withEvents(discover:)` in `bootstrap/app.php` |
| Artisan commands not found | `withCommands([...])` in `bootstrap/app.php` |
| `Menu` factory after move | `newFactory()` pointing to `Database\Factories\MenuFactory` |
| Empty directory shells after move | `.\scripts\remove-empty-dirs.ps1` |
| Stray empty dirs at repo root (`Http/`, `Models/`, …) | Migration script run in wrong cwd — removed by cleanup script |
| Moving a domain provider's `boot()` cross-domain dependency (e.g. Navigation's cache flush needs `Role`) | Fine to depend on another domain's model/event in `boot()` — Laravel calls every provider's `register()` before any `boot()`, so bindings from other providers are already available |
| Two providers composing the same hashed view (`WebLayout::navbarComposerView()`) | Register **separate** `View::composer()` calls per provider (Navigation, Notification) — Laravel runs all composers bound to a view, no need to chain them in one callback |

---

## Gateway and Frontend contract

| Monolith change | Gateway | Frontend |
|-----------------|---------|----------|
| Internal folder / namespace | None | None |
| Route file split, same URLs | None | None |
| API JSON shape change | Proxy/transform | `src/api/*` |
| URL prefix change | Route proxy | `src/api/*`, router |

---

## Remaining follow-ups

1. **Phase 4** — Composer packages only if assessment triggers are met (see [internal-monorepo-assessment.md](./internal-monorepo-assessment.md#phase-4-composer-packages-deferred)).
2. **Day-2 QOL (done 2026-07-10)** — `config/domains.php`; `domain:list|make|remove`; porting scripts removed. Lock safety (2026-07-10): hardcoded `PLATFORM_CORE`, product `locked` flag (manual unlock only). See [domain-modules.md](../platform/architecture.md).

---

## Verification commands

```powershell
cd c:\laragon\www\monolith
composer dump-autoload                           # required after moving/renaming classes
composer test                                    # run for current pass count (historical snapshots: 392–395+ in 2026-07-10 assessment)
php artisan domain:list                          # kind core|product + removal never|locked|removable (see platform/architecture.md)
.\scripts\remove-empty-dirs.ps1 -WhatIf          # expect: no empty dirs in scope
.\scripts\test-workspace.ps1                     # Monolith + Gateway + Frontend
php artisan route:list --path=api/v1/approvals   # confirm approval routes
```

---

## Anti-patterns (do not repeat)

1. Composer path packages before logical domains are stable.
2. Partial migration (API only, not Web Blade).
3. Changing public URLs without a **`downstream sync session`** to update Gateway/Frontend.
4. Merging three git repos without a clear operational win.
5. Using `nwidart/laravel-modules` instead of plain `app/Domain/*`.

See [internal-monorepo-assessment.md](./internal-monorepo-assessment.md) for full rationale.
