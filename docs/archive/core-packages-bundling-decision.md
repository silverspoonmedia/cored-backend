# One core domain ≠ one package — audit bundle decision

**Date:** 2026-11-17 · **Branch:** `refactor/core-domain-packages` · **Status:** accepted, first bundle shipped

## Decision

> A package is a **distribution unit**, not a taxonomy mirror.

The POC proved the *mechanism* (see [core-domain-packages-poc.md](core-domain-packages-poc.md)). The next question was whether the remaining seven core domains become seven packages. They do not. **`silverspoon/audit` ships `ActivityLog` + `ErrorLog` together**: two namespaces, two route files, two registry entries, one `composer.json`, one provider, one test tree.

## Why the 1:1 topology was rejected

Measured across `app/Domain/*` before choosing:

| Domain | Coupling | Verdict |
|--------|----------|---------|
| `Identity` | Imported by nearly every domain (User, Role, Permission) | stays in the host — the hub cannot be a dependency of itself |
| `PaymentGateway` + `Service` | Mutual: models hold each other's foreign keys, morph maps both ways | would need `silverspoon/billing` — deferred, not 1:1 |
| `ActivityLog` + `ErrorLog` | Two edges only: `ErrorContextRedactor` (shared redaction list) and one docblock `@see` | **bundled now** |
| `Ai` + `Inference` | `Ai` consumes the inference catalog | **bundled** — `silverspoon/ai`, [decision](ai-package-decision.md) |
| `ActivityLog` alone | its own subject aliases referenced `Navigation\Models\Menu` | fixed, see below |
| `Currency` | originally sat inside `Service` (`App\Domain\Service\Models\Currency`) | promoted to its own namespace, same package — `App\Domain\Currency\*` + [`currency.mdc`](../../.cursor/rules/currency.mdc), registry entry ordered before `Service` so the `/currencies` URIs keep their position |

Splitting a mutually-dependent pair into two packages forces one of two defects: a cross-package runtime dependency with no versioning story, or a third `contract` package that exists only to break a cycle. Neither is worth the ceremony for a distribution unit whose consumers install *both* halves anyway.

## What had to change beyond `git mv`

Four kernel→package edges had to become kernel-owned contracts, because the kernel never imports a package:

- `App\Support\Encryption\DecryptionAuditRecorder` — `EnvelopeEncryptionService` no longer depends on `CryptoActivityRecorder`; the constructor parameter is optional and the audit call is skipped when the contract is unbound.
- `App\Support\ErrorLogging\ExceptionRecorder` — `bootstrap/app.php`'s `reportable()` calls it behind `app()->bound()`.
- `encryption:migrate-activity-log` moved into the package and is registered by its provider; the host `app/Console/Commands` directory discovery line is gone.
- `ErrorLogController`'s import of `ActivityLogController` existed only for a docblock `@see`; removed. A package-internal reference is fine, a decorative one is not.

`SubjectTypeResolver` hardcoded `Navigation\Models\Menu`, which would have made `silverspoon/audit` require `silverspoon/navigation`. The alias map is now a registry on a singleton implementing the new kernel contract `App\Support\ActivityLog\SubjectAliasRegistrar`; it seeds only `user` (Identity hub) and the navigation package declares `menu` from its own `boot()` behind `app()->bound()`. Each package keeps its own subject type, and a host without navigation offers fewer filters.

## Deliberate asymmetry

`config/errorlog.php` moved into the package; **`config/activitylog.php` stayed in the host**. The latter is Spatie's published config, merged last and host-owned, and it carries `sensitive_properties.*`. The same package owning one config file and leaving another in place is correct, not an oversight.

## Invariants this locks

1. A package declares **one PSR-4 prefix per domain** it carries — bundling must never merge namespaces, or every import in the host changes.
2. Registry entries share `package`/`path`/`provider` but keep their **own** `routes` file.
3. `PackageOverrideTest` asserts both domains resolve from `packages/audit`, both name the same package, both route files load, both kernel contracts are bound, and a probe class under `app/Domain/{ActivityLog,ErrorLog}/` still wins the PSR-4 race — the "host override wins" proof is per domain, not per package.
4. Route count is a baseline check: 204 before, 204 after.
5. **Kernel-without-package is boot, not routes.** With `silverspoon/audit` uninstalled, `php artisan about` and `domain:list` still work — the only failure is route registration, because the registry still names a route file whose controller no longer exists. Removing a package therefore means deleting its registry entries in the same change; the kernel contracts make the *boot* independent, not the routes.

Contract: [core-packages.md](../integration/core-packages.md). Registry: [architecture.md](../platform/architecture.md).
