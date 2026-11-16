# Core domain packages

A **core domain** can ship as a Composer path package under `packages/{slug}/` instead of living in `app/Domain/{Domain}/`. The point is downstream reuse: a client project installs `silverspoon/navigation` as a real dependency without forking this repo. Extraction moves **where** the code lives, never its contract — namespace `App\Domain\{Domain}\*`, route names, config keys, migration timestamps, and un-namespaced translation keys (`api/navigation.created`) are unchanged.

Product domains stay in `app/Domain/{Product}/` and keep their `uninstall.json` contract ([architecture.md](../platform/architecture.md)).

| | |
|---|---|
| Packages | `packages/navigation`, `packages/notification` |
| Registry | `config/domains.php` — `package`, `path`, `routes`, `provider` per packaged domain |
| Resolver | `DomainManager::packageOf()` / `isPackaged()` / `packageApiRoutePaths()`; `php artisan domain:list` prints `Package` |
| Route loader | `routes/api/v1.php` requires `packageApiRoutePaths()`, then the domains still in `app/` |
| Kernel contract | `App\Support\Navigation\PresentedTreeEpoch` (bound by the package, used behind `app()->bound()`) |
| Override proof | `tests/Domain/Core/PackageOverrideTest.php` |
| Pakem | [architecture.mdc](../../.cursor/rules/architecture.mdc) |

## Invariants

1. **The host wins the PSR-4 race.** Root `composer.json` declares `"App\\Domain\\Navigation\\": "app/Domain/Navigation/"` explicitly, so the generated map is `[app/Domain/Navigation, vendor/silverspoon/navigation/src]`. Dropping that line makes every host override dead code, silently — `PackageOverrideTest` asserts it.
2. **The kernel never imports a package.** It declares the contract it needs (`PresentedTreeEpoch`) and the package binds it; a host without the package still boots because the call sits behind `app()->bound()`. A core package may import `App\Support\*` and the Identity hub, never a product domain.
3. **The package owns its wiring.** `extra.laravel.providers` auto-discovers the provider, which alone calls `mergeConfigFrom`, `loadMigrationsFrom`, `translator->getLoader()->addPath(__DIR__.'/../resources/lang')`, binds contracts, registers listeners and commands. `bootstrap/*` mentioning the package again is a defect.
4. **Every path comes from the registry.** `domainPath()`, `providerPath()`, `apiRoutePath()`, `testsPath()` read `config/domains.php` and fall back to `app/Domain/*`; no caller hardcodes either shape.
5. **A package pins its `version`.** A path repository otherwise infers `dev-{branch}` and `composer update` fails against `minimum-stability: stable` on any feature branch.

## Anatomy

`composer.json` (version, PSR-4 for `src` + `database/{factories,seeders}` + `tests`, `extra.laravel.providers`) · `src/` · `routes/api.php` (no prefix/name — the host applies them) · `config/{slug}.php` · `database/{migrations,seeders,factories}` (original timestamps, `App\Domain\{Domain}\Database\*` namespaces) · `resources/lang/{locale}/**` · `tests/` (`App\Domain\{Domain}\Tests`).

Host side: `"type": "path"` repository over `packages/*`, a `require` entry per package, the explicit PSR-4 prefix, and a `Packages` suite in `phpunit.xml`. Locale guards scan `lang/` **plus** `packages/*/resources/lang` (`locale_scan_lang_roots()`); `LocaleParityTest` reads both trees.

A product `uninstall.json` that cited a moved file records the package path (`packages/navigation/database/seeders/MenuSeeder.php`) — removing the product never removes the package.

## Adding one

`git mv` each piece (never copy: history follows), rewrite only the `Database\*` namespaces, write `composer.json`, fold the old provider body into the package provider, add the registry entry + `require` + PSR-4 prefix, then delete the host copies and `bootstrap/*` references. Verify with `composer update silverspoon/*`, `composer dump-autoload`, `php artisan domain:list`, `php artisan route:list --path=api/v1`, `php artisan test packages`, and the locale parity scripts.

Windows: `"symlink": true` produces a directory junction and works here; if it ever fails, set `"symlink": false` and re-run `composer update silverspoon/*` after every change under `packages/`.
