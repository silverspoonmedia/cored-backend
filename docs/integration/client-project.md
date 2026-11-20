# Building a client project

A project on the Silverspoon core installs the packages with Composer and keeps only its own `app/`, `config/`, `routes/`, `database/`, `lang/` and `resources/`. The kernel arrives through `vendor/silverspoonmedia/platform` — never a copied `core/` directory. `client-scaffold/` is the template: copy it, install, run.

| Mode | When | `repositories` |
|------|------|----------------|
| **VCS tag** | A real project, no access to this repo | Six `{"type": "vcs", "url": "https://github.com/silverspoonmedia/{slug}.git"}` entries + `"silverspoonmedia/{slug}": "^1.0"` |
| **path** | A project beside this checkout (a dev sandbox) | `{"type": "path", "url": "../monolith/packages/*", "options": {"symlink": true}}` |

Both install the same code; only where Composer finds it differs. `DomainManager` resolves the install directory, so the registry paths differ per mode and nothing else does. **The mirrors are private** — `composer config --global github-oauth.github.com <token>` once.

## The registry is the contract

`config/domains.php` names ten core domains and the package each belongs to:

```php
'Identity' => [
    'kind' => 'core',
    'package' => 'silverspoonmedia/platform',
    'path' => 'vendor/silverspoonmedia/platform/src/Domain/Identity',
    'routes' => 'vendor/silverspoonmedia/platform/routes/identity.php',
    'web' => 'vendor/silverspoonmedia/platform/routes/web-identity-oauth-callback.php',
    'provider' => 'vendor/silverspoonmedia/platform/src/Providers/Domain/IdentityServiceProvider.php',
],
// Navigation, Notification, ActivityLog, ErrorLog, PaymentGateway, Currency,
// Service, Inference, Ai …
```

1. **Paths point at the install, not at this repo.** A consumer has no `packages/`.
2. **Order is route load order.** `packageApiRoutePaths()` walks the registry; alphabetizing moves URIs.
3. **No product domain.** Archivd and Microstock are this repo's sellable surface; an entry would make `domain:list` report a domain that cannot boot.

## Route assembly

Packages never call `loadRoutesFrom`; the project mounts them:

```php
// routes/api/v1.php
foreach (app(App\Support\Domain\DomainManager::class)->packageApiRoutePaths() as $packageRouteFile) {
    require $packageRouteFile;
}
require __DIR__.'/v1/client.php';
```

`packageApiRoutePaths()` returns every registry `routes` file plus the kernel's cross-domain surface — `vendor/silverspoonmedia/platform/routes/kernel-*.php` (settings, product activations, domain access, broadcast auth, batch), which no registry entry names. `routes/web.php` does the same with `packageWebRoutePaths()` (the iPaymu return, the Discord OAuth callback).

## Override hooks

The project wins the PSR-4 race: its own `app/` must be declared **before** the package copy.

```json
"psr-4": { "App\\": "app/", "App\\Support\\": "app/Support/", "App\\Http\\": "app/Http/",
  "App\\Repositories\\": "app/Repositories/", "App\\Traits\\": "app/Traits/", "App\\Rules\\": "app/Rules/",
  "App\\Exceptions\\": "app/Exceptions/", "Database\\Factories\\": "database/factories/",
  "Database\\Seeders\\": "database/seeders/" }
```

Drop a class in `app/Support/Storage/Foo.php` and it replaces the kernel's. Forget the prefix and your file silently loses to the packaged copy. The scaffold declares all nine; `ConsumerScaffoldTest` fails when one goes missing.

## The provider a consumer must register

`App\Providers\KernelServiceProvider` carries the kernel runtime: five config merges, four seam registries, the CRUD repository binding, the CORS service, `domain:list`, storage directories, the migration publish tag. It is **deliberately absent from `extra.laravel.providers`** — auto-discovery would boot it here too, and a second pass after a registry resolved would drop every registration. The scaffold lists it in `bootstrap/providers.php`; every binding sits behind `! $this->app->bound(...)` on both sides, so whichever provider runs first wins.

| Owner | Groups |
|-------|--------|
| Kernel (`mergeConfigFrom`) | `platform`, `spa-routes`, `localization`, `services`, `hcaptcha` |
| Package (`mergeConfigFrom`) | `navigation`, `notifications`, `payment`, `payment-routing`, `service`, `ai`, `inference`, `errorlog` |
| Project (`config/`) | `app`, `auth`, `broadcasting`, `cors`, `database`, `domains`, `encryption`, `filesystems`, `jwt`, `lookup`, `mail`, `oci`, `permission`, `session` |

The project groups name a key, connection or transport the kernel cannot invent; a missing one resolves `null` at the first request that reads it. Never copy a kernel group into `config/` — `mergeConfigFrom` is shallow, so the copy shadows the default for every key it omits.

The kernel's tables are **host-owned** (`users`, `password_reset_tokens`, `sessions`, `cache`, `jobs`, `permission_tables`, `system_settings`, `identity_oauth_accounts`, `product_activations`, `product_analytics`, `product_domain_visibilities`): the scaffold ships their migrations, or `php artisan vendor:publish --tag=silverspoon-platform-migrations` once a project has its own baseline. Every other package calls `loadMigrationsFrom`.

## Verification

```sh
php artisan package:discover   # domain providers auto-discovered
php artisan domain:list        # ten core, zero product
php artisan route:list --path=api/v1
php artisan migrate --force
```

`php -d memory_limit=1G scripts/run-consumer-scaffold-check.php` runs that sequence on a temporary copy of `client-scaffold` with path repositories, then asserts one real `POST /api/v1/auth/login` answers 422 with a translated sentence, not a raw key. The `standalone-packages` CI job runs it. `client-scaffold/tests/TestCase.php` boots `bootstrap/app.php` the way a request does, so a test sees the real provider set and route loader.

## What is retired

There is no `core/` subtree: `update-core.sh` and `push-core.sh` are deleted and `CoreScriptTest` fails if either returns. Mirrors come only from `scripts/publish-packages.ps1`; see [core-packages.md](core-packages.md#distribution).
