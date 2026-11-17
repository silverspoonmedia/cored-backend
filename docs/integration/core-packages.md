# Core domain packages

A **core domain** can ship as a Composer path package under `packages/{slug}/` instead of living in `app/Domain/{Domain}/`. The point is downstream reuse: a client project installs `silverspoon/navigation` as a real dependency without forking this repo. Extraction moves **where** the code lives, never its contract — namespace `App\Domain\{Domain}\*`, route names, config keys, migration timestamps, and un-namespaced translation keys (`api/navigation.created`) are unchanged.

**One package may carry more than one core domain.** A package is a *distribution unit*, not a taxonomy mirror: domains that are consumed together and coupled to each other ship together, while `kind => core` stays one registry entry per domain. `silverspoon/audit` bundles ActivityLog + ErrorLog — two namespaces, two route files, two registry entries, one `composer.json`, one provider, one test tree. `silverspoon/billing` bundles PaymentGateway + **Currency** + Service for the same reason: they are one money surface — `Currency` owns the money vocabulary (the ISO catalog and the FX snapshots), `Service` owns the catalog priced in it and the entitlement, `PaymentGateway` owns the invoice that collects for it, and none is useful downstream without the others. `silverspoon/ai` bundles Inference + Ai: `Inference` owns the model catalog and the per-provider rate, `Ai` owns the endpoint and key that reach the provider, and a rate nobody can bill is not a price.

Product domains stay in `app/Domain/{Product}/` and keep their `uninstall.json` contract ([architecture.md](../platform/architecture.md)).

| | |
|---|---|
| Packages | `packages/navigation` (`silverspoon/navigation`) · `packages/notification` (`silverspoon/notification`) · `packages/audit` (`silverspoon/audit` — ActivityLog + ErrorLog) · `packages/billing` (`silverspoon/billing` — PaymentGateway + Currency + Service) · `packages/ai` (`silverspoon/ai` — Inference + Ai) |
| Registry | `config/domains.php` — `package`, `path`, `routes`, `web`, `provider` per packaged domain |
| Resolver | `DomainManager::packageOf()` / `isPackaged()` / `packageApiRoutePaths()` / `packageWebRoutePaths()`; `php artisan domain:list` prints `Package` |
| Route loader | `routes/api/v1.php` requires `packageApiRoutePaths()`; `routes/web.php` requires `packageWebRoutePaths()`; then the domains still in `app/` |
| Kernel contracts | `App\Support\Navigation\PresentedTreeEpoch` · `App\Support\Encryption\DecryptionAuditRecorder` · `App\Support\ErrorLogging\ExceptionRecorder` · `App\Support\ActivityLog\SubjectAliasRegistrar` · `App\Support\Billing\{FulfillmentRegistry,FulfillmentContext}` + `Contracts\{FulfillmentHandler,TierObserver}` · `App\Support\Notifications\Contracts\NotificationDispatcher` · `App\Support\Broadcasting\Contracts\ChannelRegistrar` · `App\Support\Encryption\Contracts\LegacyColumnMigrator` · `App\Support\Ai\AiUsageConsolidatorRegistry` + `Contracts\{AiUsageLedger,AiUsageConsolidator}` (each implemented by a package, used behind `app()->bound()`) |
| Override proof | `tests/Domain/Core/PackageOverrideTest.php` |
| Pakem | [architecture.mdc](../../.cursor/rules/architecture.mdc) |

## Invariants

1. **The host wins the PSR-4 race.** Root `composer.json` declares `"App\\Domain\\Navigation\\": "app/Domain/Navigation/"` explicitly, so the generated map is `[app/Domain/Navigation, vendor/silverspoon/navigation/src]`. Dropping that line makes every host override dead code, silently — `PackageOverrideTest` asserts it. A bundled package declares **one prefix per domain** it carries.
2. **The kernel never imports a package.** It declares the contract it needs — `PresentedTreeEpoch` for navigation, `DecryptionAuditRecorder` for the decrypt choke point, `ExceptionRecorder` for `bootstrap/app.php`'s `reportable()` — and the package binds it; a host without the package still boots because every call sits behind `app()->bound()`. A core package may import `App\Support\*` and the Identity hub, never a product domain.
3. **The package owns its wiring.** `extra.laravel.providers` auto-discovers the provider, which alone calls `mergeConfigFrom`, `loadMigrationsFrom`, `translator->getLoader()->addPath(__DIR__.'/../resources/lang')`, binds contracts, registers listeners and commands. `bootstrap/*` mentioning the package again is a defect. A bundled package has **one** provider for all its domains.

   Removing a package means removing its **registry entries** too: with the package gone but `config/domains.php` still naming its `routes`, the kernel boots (`php artisan about` works, no package class is referenced) and route registration fails on a missing controller. Clear `bootstrap/cache/{packages,services}.php` first — a stale `packages.php` still names the auto-discovered provider and fails provider load before the real cause is visible.
4. **Every path comes from the registry.** `domainPath()`, `providerPath()`, `apiRoutePath()`, `testsPath()` read `config/domains.php` and fall back to `app/Domain/*`; no caller hardcodes either shape. Bundled domains at the same `path` still name their **own** `routes` file.
5. **A package pins its `version`.** A path repository otherwise infers `dev-{branch}` and `composer update` fails against `minimum-stability: stable` on any feature branch.
6. **Bundling never merges namespaces.** A package with two domains keeps `App\Domain\A\*` and `App\Domain\B\*` as separate PSR-4 prefixes and separate test prefixes; a shared `App\Domain\Audit\*` would have changed every import, which extraction must never do.

## Anatomy

`composer.json` (version, PSR-4 for `src` + `database/{factories,seeders}` + `tests`, `extra.laravel.providers`) · `src/` · `routes/{domain}.php` — one per domain, no prefix/name, the host applies them (a single-domain package may use `routes/api.php`) · `config/{slug}.php` · `database/{migrations,seeders,factories}` (original timestamps, `App\Domain\{Domain}\Database\*` namespaces) · `resources/lang/{locale}/**` · `tests/` (`App\Domain\{Domain}\Tests`).

A package with a **server-side web route** (OAuth callback, payment return) adds a `web` key to its registry entry and a file beside the API ones (`packages/billing/routes/web-ipaymu-return.php`); `routes/web.php` loops `packageWebRoutePaths()` instead of a literal `require`, so the `web` middleware group still wraps it. Non-PHP package data (routing YAML) lives in `resources/` and is addressed from the host config (`config/payment-routing.php` → `packages/billing/resources/routing/price.yaml`).

Host side: `"type": "path"` repository over `packages/*`, a `require` entry per package, the explicit PSR-4 prefix, and a `Packages` suite in `phpunit.xml`. Locale guards scan `lang/` **plus** `packages/*/resources/lang` (`locale_scan_lang_roots()`); `LocaleParityTest` reads both trees.

A packaged domain's config file usually moves with it (`packages/notification/config/notification.php`, `packages/audit/config/errorlog.php`). The exception is a **published vendor config the host owns**: `config/activitylog.php` stays in the host because it is Spatie's file, merged last, with `sensitive_properties.*` in it — the same package can therefore own one config and leave another in place.

A product `uninstall.json` that cited a moved file records the package path (`packages/navigation/database/seeders/MenuSeeder.php`, `packages/billing/tests/Service/Unit/Services/ServiceEntitlementServiceTest.php`) — removing the product never removes the package.

## Seams a bundled package needs

A core package may import `App\Support\*` and the Identity hub — never a product domain ([architecture.mdc](../../.cursor/rules/architecture.mdc)). When the domains being packaged are *about* products (billing fulfills quotas into `Archivd` and `Microstock`), the coupling moves into kernel contracts the kernel declares and the product implements:

| Contract | Kernel owner | Package use | Product side |
|---|---|---|---|
| `Support\Billing\Contracts\FulfillmentHandler` + `FulfillmentContext` | `Support\Billing\FulfillmentRegistry` (singleton) | `ServiceFulfillmentService` / `ServiceEntitlementLifecycleService` call `forTarget($target)`, never a product class | `Archivd\Support\Billing\ArchivdFulfillmentHandler`, `Microstock\Support\Billing\MicrostockFulfillmentHandler` register in their provider behind `app()->bound()` |
| `Support\Billing\Contracts\TierObserver` | same registry (`registerTierObserver`) | reports derived tier moves (`paid`/`grace`/`free`) | `ArchivdTierObserver` moves the product analytics tier |
| `Support\Notifications\Contracts\NotificationDispatcher` | `packages/notification` binds the adapter | emits a **string** type (`service.entitlement.grace`) | adapter maps the string to `NotificationType` |
| `Support\Broadcasting\Contracts\ChannelRegistrar` | `routes/channels.php` loops it behind `app()->bound()` | registers `payments.invoice.{id}` / `payments.routing` / `payments.admin` / `services.catalog` | host keeps `settings.currencies`, `logs.*`, RBAC |
| `Support\Encryption\Contracts\LegacyColumnMigrator` | `Support\Encryption\EncryptionMigrationRegistry` | registers the `invoices.gateway_request_payload` / `gateway_response_payload` migrator | — (keeps `EncryptionMigrateP0Command` free of `Invoice`) |
| `Support\Ai\Contracts\AiUsageLedger` | bound by the product provider | `InferenceUsageAnalyticsService` reads the event model, its eager-load relations and USD formatting | `Microstock\Support\Ai\MicrostockAiUsageLedger` |
| `Support\Ai\AiUsageConsolidatorRegistry` + `Contracts\AiUsageConsolidator` | `AppServiceProvider` singleton | `InferenceCatalogService::syncProviderRates()` consolidates the ledger registered **under the provider slug** | `Microstock\Support\Ai\MicrostockAiUsageConsolidator` registers with `config('microstock.ai.inference_provider_slug')` |
| `config('ai.usage_event_resource')` | config key, published by the product provider | the dashboard controller renders the product's row class | `Microstock\Support\Ai\MicrostockAdminUsageEventResource` |

The AI seam is the one place a **config key** stands in for a contract: a JSON resource is a class that must be instantiated per row, and a kernel interface returning a class-string would only add a hop. `AiServiceProvider` ships `'usage_event_resource' => ''`; with no product installed the admin usage dashboard returns an empty page instead of a package import.

An EMPTY registry is a legal state: with no product installed the package runs the same code and every hook is a no-op — `PackageOverrideTest::test_kernel_boots_without_the_audit_package()` is the pattern. A registry of **class strings**, not instances, keeps a long-lived Octane worker from pinning a product service.

## Adding one

`git mv` each piece (never copy: history follows), rewrite only the `Database\*` and `Tests\*` namespaces, write `composer.json`, fold the old provider body into the package provider, add the registry entry + `require` + PSR-4 prefix, then delete the host copies and `bootstrap/*` references. Verify with `composer update silverspoon/*`, `composer dump-autoload`, `php artisan domain:list`, `php artisan route:list --path=api/v1`, `php artisan test packages`, and the locale parity scripts — the route count must equal the pre-move baseline.

Windows: `"symlink": true` produces a directory junction and works here; if it ever fails, set `"symlink": false` and re-run `composer update silverspoon/*` after every change under `packages/`.
