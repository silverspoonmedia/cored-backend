# Domain modules (host overrides + a Composer consumer install)

Domain code lives under `app/Domain/{Domain}/` (host-owned) inside one Laravel app and one git repo. This fork is a **logical monorepo at the repo level only**: the extracted core domains are not a `packages/` tree here, they arrive from `github.com/silverspoonmedia/*` tags and install under `vendor/silverspoonmedia/{slug}/`, required from the root `composer.json` as `silverspoonmedia/{slug}` on the `^1.2` line. The namespace is `App\Domain\{Domain}\*` in both cases, so extraction never changes an import, a translation key, or a route name. The host repository (`app/`) is still resolved first, which is what makes an override in `app/Domain/Navigation/...` shadow the packaged class.

Three layers, in dependency order: **kernel** (`vendor/silverspoonmedia/platform` — `App\Support\*`, the Identity hub, HTTP glue, repositories, the platform providers; it imports a package only through a contract it owns), **core package** (`vendor/silverspoonmedia/{navigation,notification,audit,billing,ai}` — each requires `silverspoonmedia/platform` and may import the kernel and the Identity hub, never a product domain), **product domain** (`app/Domain/{Product}/` in the living line — the sellable surface, never a Composer package; this fork ships none). The host (`app/`) keeps `AppServiceProvider`, the domain console commands, and the product-coupled overrides under `app/Support/`; its PSR-4 prefixes shadow the platform package, which is what makes an override win.

**This repo is the consumer host shape, not a fourth layer.** It installs the kernel and core packages from `vendor/` and owns only `app/`, `config/`, `routes/`, `database/`, `lang/` and `resources/` — no product domain, no `packages/`, no `core/` subtree. Its `config/domains.php` names the ten core domains with `vendor/silverspoonmedia/{slug}/…` paths, its route loaders mount them through `DomainManager`, and it registers `App\Providers\KernelServiceProvider` explicitly because that provider carries the bindings the living line's `AppServiceProvider` owns. The template is the living line's `client-scaffold/` and the recipe is [client-project.md](../integration/client-project.md); `ConsumerScaffoldTest` and `KernelConfigSelfSufficiencyTest` (both in the living line) keep both halves true.

The kernel follows the same package rules as every other package — unchanged namespaces, host override prefixes, a `Package:platform` suite in its own mirror, a `v1.2.x` release line — so "add code the packages import" is not a host edit: it belongs in the monolith source and arrives as a release. A host override that cites kernel files records `vendor/silverspoonmedia/platform/src/{path}` in the product's `uninstall.json`; the handful of host overrides keep `app/Support/{path}`.

`config/domains.php` classifies each module on **three axes** — `kind` (`core` = foundation the app cannot run well without; `product` = a feature surface sold by the project), for products only `locked` (whether `domain:remove` refuses), and, also product only, the **composition** axis `parent`: a subproduct such as Microstock declares the product domain it extends (`'parent' => 'Archivd'`), and `domain:remove` refuses a parent while a registered child still points at it. A subproduct is still a full product, never a third `kind`. Core modules are additionally pinned by `DomainManager::PLATFORM_CORE`, which config cannot override. RBAC `domain_kind`, per-user product activation, per-domain visibility, and product analytics are **separate** systems that happen to reuse the words `core`/`product` — do not collapse them into the registry. `domain:make` always scaffolds a product (locked by default; `--parent={Product}` for a subproduct) with its route stubs, provider, tests, `{Name}RoleSeeder`, and a starter `uninstall.json`; `domain:remove` refuses core and locked products.

A packaged domain declares where its parts live, and `DomainManager` reads it — no caller hardcodes `app/Domain/*`. A package may carry **two** domains (a distribution unit, not a taxonomy mirror): each keeps its own `routes` file while sharing `package` and `path`:

```php
'Navigation' => [
    'kind' => 'core',
    'package' => 'silverspoonmedia/navigation',
    'path' => 'vendor/silverspoonmedia/navigation/src',
    'routes' => 'vendor/silverspoonmedia/navigation/routes/api.php',
    'provider' => 'vendor/silverspoonmedia/navigation/src/NavigationServiceProvider.php',
],
'ActivityLog' => [
    'kind' => 'core',
    'package' => 'silverspoonmedia/audit',
    'path' => 'vendor/silverspoonmedia/audit/src',
    'routes' => 'vendor/silverspoonmedia/audit/routes/activity-log.php',
    'provider' => 'vendor/silverspoonmedia/audit/src/ActivityLog/AuditServiceProvider.php',
],
// ErrorLog: same package/path/provider, its own route file.
// PaymentGateway + Currency + Service: same package/path/provider
// (`silverspoonmedia/billing`), each with its own `routes` file; PaymentGateway also
// carries a `web` key for its server-side payment-return route. Registry order
// is the file load order, so `currency.php` before `service.php` keeps the
// `/currencies` URIs where they sat before the split.
// Inference + Ai: same package/path/provider (`silverspoonmedia/ai`), each with its
// own `routes` file (catalog/rates vs connections/keys).
```

Each product domain owns an **uninstall manifest** — `app/Domain/{Name}/uninstall.json`, schema `domain-uninstall/v1` — the reviewed map of every path it deletes (backend, SPA, docs), every shared-file edit, and the data it would purge. Removing a product domain is therefore reading one manifest, not re-auditing the tree: `domain:remove` plans it (`--dry-run`), drafts it (`--draft-manifest`), executes it (`--force`), and purges data only behind `--purge-data` + the destructive-DB guard. Any file a product domain touches outside `app/Domain/{Name}/` is recorded in that manifest in the same task; `DomainUninstallManifestTest` fails on an unlisted reference. A packaged core domain's files are *not* product-domain surface: the manifest records them as shared-file paths (`vendor/silverspoonmedia/navigation/database/seeders/MenuSeeder.php`), because deleting the package is not what removing a product does. Write every op against the entry that **survives next to the product's own files** — the kernel's opt-in hook line, the provider's registration — because a product that moved code into its own `Support/` contributors leaves nothing at the old package location to remove.

| | |
|---|---|
| Code | `app/Domain/*/` (host overrides), `vendor/silverspoonmedia/platform/src/` (kernel: `Support/Domain/DomainManager.php`, `Console/Commands/` — `domain:list` + `encryption:flush-decrypted-cache`), `vendor/silverspoonmedia/*/src/` (core packages, read-only), `app/Console/Commands/Domain/*` (host-only `domain:make` / `domain:remove`) |
| Config | `config/domains.php`, `bootstrap/providers.php`, `vendor/silverspoonmedia/*/config/*` |
| Routes | `routes/api/v1.php` → `routes/api/v1/{domain}.php` or `vendor/silverspoonmedia/*/routes/{domain}.php`; `routes/web.php` → `routes/web/{domain}.php` or a packaged registry `web` key |
| Commands | `domain:list` (kernel — `vendor/silverspoonmedia/platform/src/Console/Commands/Domain/ListDomainsCommand.php`, registered by `KernelServiceProvider` for a consumer), `domain:make` / `domain:remove` (host — they rewrite host trees) |
| Tests | `tests/Domain/` (living line, host-bound) · `tests/Unit` · `tests/Feature`; a package's own tree runs in its mirror (`composer test`), since `tests/` is `export-ignore`d from the tag |
| Pakem | [architecture.mdc](../../.cursor/rules/architecture.mdc) |
| Package contract | [core-packages.md](../integration/core-packages.md) |
| Removal procedure | [.cursor/skills/domain-remove/SKILL.md](../../.cursor/skills/domain-remove/SKILL.md) |
| Deep dive | [reference-architecture.md](reference-architecture.md) |
