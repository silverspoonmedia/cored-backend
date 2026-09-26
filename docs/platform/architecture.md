# Domain modules (internal monorepo)

Domain code lives under `app/Domain/{Domain}/` inside one Laravel app and one git repo — a logical monorepo, not Composer path packages.

`config/domains.php` classifies each module on **two independent axes**: `kind` (`core` = foundation the app cannot run well without; `product` = a feature surface sold by the project) and, for products only, `locked` (whether `domain:remove` refuses). Core modules are additionally pinned by `DomainManager::PLATFORM_CORE`, which config cannot override. RBAC `domain_kind`, per-user product activation, per-domain visibility, and product analytics are **separate** systems that happen to reuse the words `core`/`product` — do not collapse them into the registry. `domain:make` always scaffolds a product (locked by default) with its route stubs, provider, tests, and `{Name}RoleSeeder`; `domain:remove` refuses core and locked products and never touches migrations.

| | |
|---|---|
| Code | `app/Domain/*/`, `app/Support/Domain/DomainManager.php`, `app/Console/Commands/Domain/*` |
| Config | `config/domains.php`, `bootstrap/providers.php` |
| Routes | `routes/api/v1.php` → `routes/api/v1/{domain}.php`; `routes/web.php` → `routes/web/{domain}.php` |
| Commands | `domain:list`, `domain:make`, `domain:remove` |
| Tests | `tests/Domain/{Domain}/` (PHPUnit suite `Domain`) |
| Pakem | [architecture.mdc](../../.cursor/rules/architecture.mdc) |
| Deep dive | [reference-architecture.md](reference-architecture.md) |
