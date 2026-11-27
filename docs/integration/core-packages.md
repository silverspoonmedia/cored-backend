# Core domain packages

A **core domain** ships as a Composer package instead of living in `app/Domain/{Domain}/`. The point is downstream reuse: this repo installs `silverspoonmedia/navigation` without forking the source repo. Extraction moves **where** the code lives, never its contract — namespace `App\Domain\{Domain}\*`, route names, config keys, migration timestamps, and un-namespaced translation keys (`api/navigation.created`) are unchanged. **One package may carry more than one core domain.** A package is a *distribution unit*, not a taxonomy mirror: domains consumed together ship together. `silverspoonmedia/audit` bundles ActivityLog + ErrorLog; `silverspoonmedia/billing` bundles PaymentGateway + **Currency** + Service (one money surface — the vocabulary, the catalog priced in it, the invoice that collects for it); `silverspoonmedia/ai` bundles Inference + Ai, because a rate nobody can bill is not a price.

## Consumer install, not a `packages/` tree

The source of every package is the `monolith` repo, where the domains live under `packages/{slug}/`. This repo is the **consumer**: it declares six `"type": "vcs"` repositories on `github.com/silverspoonmedia/*`, requires each package on the `^1.2` line, and gets them installed under `vendor/silverspoonmedia/{slug}/` — no `packages/` directory, no path repository, no `version` key to edit, no junction. A package fix arrives as a tag plus `composer update silverspoonmedia/*`; editing anything under `vendor/` is lost on the next update.

There is **no product domain** here, so every one of the ten core domains is packaged. `app/Domain/{Domain}/` exists only as the host override hook.

## The kernel is a package too

`silverspoonmedia/platform` carries the code every package imports but no package owns: `App\Support\*`, the Identity hub (`App\Domain\Identity\*`), the HTTP glue (`App\Http\{Controllers,Requests,Resources,Middleware}`), `App\Repositories\*`, `App\Providers\{Encryption,Product,DomainAccess,Settings,Domain\Identity}ServiceProvider`, `App\Rules\*`, `App\Traits\*`, `App\Exceptions\*`. Namespaces are unchanged — extraction moved **where** the file lives, never the `use` line.

- **Every non-kernel package requires it.** In the source repo, `packages/{navigation,notification,audit,billing,ai}/composer.json` carry `"silverspoonmedia/platform": "^1.2"`, and `DependencyDirectionTest::test_every_non_kernel_package_requires_the_platform_package()` (living line) fails on a package that imports kernel code without declaring it. The same test holds the other direction with an **empty** burn-down list, so a package that reaches back into the kernel except through a declared contract fails too.
- **The host requires a range, not `*`.** This root `composer.json` requires each package with `"^1.2"`. That is a plain Composer requirement here, not a path pin: the caret is what lets a patch release install without an edit, and a minor release is a deliberate `composer update`.
- **No `version` key outside the source repo.** A path repository infers `dev-{branch}`, so the living line declares `version` per package; the tag on the mirror is the version this install resolves, and the mirror's tagged commit has the key stripped.
- **The host still wins.** Root `composer.json` re-declares `App\Support\`, `App\Http\`, `App\Repositories\`, `App\Domain\Identity\` and the eight `App\Domain\{ActivityLog,Ai,Currency,ErrorLog,Inference,Navigation,Notification,PaymentGateway,Service}\` prefixes over `app/`, which is how the two product-coupled files stay in the host as overrides: `app/Support/Product/Services/{ProductAnalyticsService,ProductInsightService}.php`. `PackageOverrideTest` (living line) asserts an override file wins the PSR-4 race.
- **`helpers.php` ships once.** `src/Support/Settings/helpers.php` loads through the package's `autoload.files`; the root manifest must **not** list it again or every function is declared twice (fatal on boot).
- **Migrations stay host-owned.** `users`, `password_reset_tokens`, `sessions`, `cache`, `jobs`, `permission_tables`, `system_settings` and `identity_oauth_accounts` live in `database/migrations/` — the package ships the models for a host-owned schema. A host may re-run those migrations from the package by publishing them with `php artisan vendor:publish --tag=silverspoon-platform-migrations`.

## Where things are

| | |
|---|---|
| Packages | `vendor/silverspoonmedia/platform` (the **kernel**) · `navigation` · `notification` · `audit` (ActivityLog + ErrorLog) · `billing` (PaymentGateway + Currency + Service) · `ai` (Inference + Ai) |
| Registry | `config/domains.php` — `package`, `path`, `routes`, `web`, `provider` per packaged domain |
| Resolver | `DomainManager::packageOf()` / `isPackaged()` / `packageApiRoutePaths()` / `packageWebRoutePaths()`; `php artisan domain:list` prints `Package` |
| Route loader | `routes/api/v1.php` requires `packageApiRoutePaths()` (registry files + `routes/kernel-*.php`); `routes/web.php` requires `packageWebRoutePaths()` |
| Kernel contracts | `App\Support\Navigation\PresentedTreeEpoch` · `App\Support\Encryption\DecryptionAuditRecorder` · `App\Support\ErrorLogging\ExceptionRecorder` · `App\Support\ActivityLog\{SubjectAliasRegistrar,SubjectLabelRegistrar}` · `App\Support\Billing\{FulfillmentRegistry,FulfillmentContext}` + `Contracts\{FulfillmentHandler,TierObserver}` · `App\Support\Notifications\Contracts\NotificationDispatcher` · `App\Support\Broadcasting\Contracts\ChannelRegistrar` · `App\Support\Encryption\Contracts\LegacyColumnMigrator` · `App\Support\Ai\AiUsageConsolidatorRegistry` + `Contracts\{AiUsageLedger,AiUsageConsolidator}` (each implemented by a package, used behind `app()->bound()`) |
| Pakem | [architecture.mdc](../../.cursor/rules/architecture.mdc) |
| Deep dive | [reference-package-seams.md](reference-package-seams.md) — seam inventory (fulfillment, tier, activity log, notifications, broadcasting, encryption, AI), distribution and release rules |

## Invariants

1. **The host wins the PSR-4 race.** Root `composer.json` declares `"App\\Domain\\Navigation\\": "app/Domain/Navigation/"` explicitly, so the generated map is `[app/Domain/Navigation, vendor/silverspoonmedia/navigation/src]`. Dropping that line makes every host override dead code, silently. A bundled package declares **one prefix per domain** it carries.
2. **The kernel never imports a package.** It declares the contract it needs — `PresentedTreeEpoch` for navigation, `DecryptionAuditRecorder` for the decrypt choke point, `ExceptionRecorder` for `bootstrap/app.php`'s `reportable()` — and the package binds it; a host without the package still boots because every call sits behind `app()->bound()`. A core package may import `App\Support\*` and the Identity hub, never a product domain.
3. **The package owns its wiring.** `extra.laravel.providers` auto-discovers the provider, which alone calls `mergeConfigFrom`, `loadMigrationsFrom`, `translator->getLoader()->addPath(...)`, binds contracts, registers listeners and commands. `bootstrap/*` mentioning the package again is a defect. A bundled package has **one** provider for all its domains.
4. **Removing a package means removing its registry entries too.** With the package gone but `config/domains.php` still naming its `routes`, the kernel boots (`php artisan about` works, no package class is referenced) and route registration fails on a missing controller. Clear `bootstrap/cache/{packages,services}.php` first — a stale `packages.php` still names the auto-discovered provider and fails provider load before the real cause is visible.
5. **Paths come from the registry.** `domainPath()`, `providerPath()`, `apiRoutePath()`, `testsPath()` read `config/domains.php` and fall back to `app/Domain/*`; no caller hardcodes either shape. Bundled domains at the same `path` still name their **own** `routes` file.
6. **Bundling never merges namespaces.** A package with two domains keeps `App\Domain\A\*` and `App\Domain\B\*` as separate PSR-4 prefixes and separate test prefixes; a shared `App\Domain\Audit\*` would have changed every import, which extraction must never do.

## Extending a packaged domain

A host extends a package **in the host**, never by editing the install: a settings group is a `SettingContributor`, a menu node a `MenuContributor`, a consumer row an `ai.consumers` entry, a class a file under the matching `app/` override prefix. The upstream half of that contract — how a package reaches a product through a kernel contract, and the empty state when no product exists — is [reference-package-seams.md](reference-package-seams.md).

## Tests

A package's own suite does **not** run here. `tests/` is `export-ignore`d from every tag, so `vendor/silverspoonmedia/*/tests/` does not exist and neither does a `Package:{slug}` suite in this `phpunit.xml`: `composer test -- --filter=ErrorLog` matches this install's host tests, not the package's. Run a package suite from a clone of its mirror (`composer install && composer test`, `phpunit.xml.dist` over its declared test dirs); the living line owns the `standalone-packages` CI job that proves that path across all six packages. What this checkout does test is its own surface: kernel boot, the domain registry, the documentation and localization guards — see [reference-tooling.md](../platform/reference-tooling.md).
