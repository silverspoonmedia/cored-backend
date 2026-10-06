# Domain modules (internal monorepo)

Domain code lives under `app/Domain/{Domain}/` inside one Laravel app and one git repo — a logical monorepo, not Composer path packages.

`config/domains.php` classifies each module on **three axes** — `kind` (`core` = foundation the app cannot run well without; `product` = a feature surface sold by the project), for products only `locked` (whether `domain:remove` refuses), and, also product only, the **composition** axis `parent`: a subproduct such as Microstock declares the product domain it extends (`'parent' => 'Archivd'`), and `domain:remove` refuses a parent while a registered child still points at it. A subproduct is still a full product, never a third `kind`. Core modules are additionally pinned by `DomainManager::PLATFORM_CORE`, which config cannot override. RBAC `domain_kind`, per-user product activation, per-domain visibility, and product analytics are **separate** systems that happen to reuse the words `core`/`product` — do not collapse them into the registry. `domain:make` always scaffolds a product (locked by default; `--parent={Product}` for a subproduct) with its route stubs, provider, tests, `{Name}RoleSeeder`, and a starter `uninstall.json`; `domain:remove` refuses core and locked products.

Each product domain owns an **uninstall manifest** — `app/Domain/{Name}/uninstall.json`, schema `domain-uninstall/v1` — the reviewed map of every path it deletes (backend, SPA, docs), every shared-file edit, and the data it would purge. Removing a product domain is therefore reading one manifest, not re-auditing the tree: `domain:remove` plans it (`--dry-run`), drafts it (`--draft-manifest`), executes it (`--force`), and purges data only behind `--purge-data` + the destructive-DB guard. Any file a product domain touches outside `app/Domain/{Name}/` is recorded in that manifest in the same task; `DomainUninstallManifestTest` fails on an unlisted reference.

| | |
|---|---|
| Code | `app/Domain/*/`, `app/Support/Domain/DomainManager.php`, `app/Console/Commands/Domain/*` |
| Config | `config/domains.php`, `bootstrap/providers.php` |
| Routes | `routes/api/v1.php` → `routes/api/v1/{domain}.php`; `routes/web.php` → `routes/web/{domain}.php` |
| Commands | `domain:list`, `domain:make`, `domain:remove` (reads the domain's `uninstall.json`) |
| Tests | `tests/Domain/{Domain}/` (PHPUnit suite `Domain`) |
| Pakem | [architecture.mdc](../../.cursor/rules/architecture.mdc) |
| Removal procedure | [.cursor/skills/domain-remove/SKILL.md](../../.cursor/skills/domain-remove/SKILL.md) |
| Deep dive | [reference-architecture.md](reference-architecture.md) |
