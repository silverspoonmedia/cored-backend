# AI Agents — Monolith

**Role:** Laravel 13 application — domain logic, database, JWT/RBAC, versioned API (`/api/v1/*`), and Blade admin.

## Agent protocol (mandatory)

Instructions below are canonical here — no other document may re-define them.

1. **Assess before changing.** Before any change, check, validate, and assess the task: read the relevant code and docs until the work context is fully understood. No blind edits.
2. **Update docs with every task.** After finishing a task, always update the relevant related documentation so code and docs never drift (details: rule 2 in [docs/agents/core-rules.md](docs/agents/core-rules.md)).
3. **`wrap up the docs` keyword.** The exact phrase `wrap up the docs` triggers a session-wide consolidation: reconcile ALL implementations from the session against the documentation as a whole. Per-task doc updates (point 2) cover only that task; the final state may differ — this keyword closes that gap.
4. **`curate the env` keyword.** The exact phrase `curate the env` triggers env layout curation: run `php artisan env:curate --dry-run` first, show the summary, then `php artisan env:curate` (add `--sync-snapshot` when snapshot sync is needed). Never rewrite `.env` layout with editor tools directly — guarded artisan commands handle transactional backup. For other env mutations, use guarded artisan commands (`env:install`, `env:switch`, `key:generate`) or `php artisan env:backup:create` before manual edits. Details: [docs/platform/environment.md](docs/platform/environment.md).
5. **Dynamic menu construction.** Before creating or changing dynamic menus, read [docs/domains/navigation/README.md](docs/domains/navigation/README.md); verify every named web route exists, each product parent route appears in `config/products.php` `menu_routes`, and every referenced role has already been seeded. Treat `MenuSeeder` as the sole authoritative tree: group related pages under clear Workspace/Products/Administration branches; use submenus instead of duplicate hub pages; keep route names (never URLs), contiguous sibling order, `en`/`id`/`ja` translations, exact RBAC, and product-bound routes on product parents so ancestor Domain Access and activation gates protect every child. Rebuild active and trashed rows transactionally, flush navigation cache only after commit, never seed debug/test/probe nodes, and lock exact tree plus rebuild/visibility behavior with tests. Update the Navigation guide and every affected domain guide in the same task.
6. **Configuration & secrets.** `.env` is for **project-owned sensitive / infra bindings only** (crypto, JWT signing, DB/Redis, KMS/OCI, mail/broadcast secrets, S3/upload-proxy credentials, payment gateway keys, AI provider API keys). Non-sensitive operational knobs live in **`system_settings` (plaintext)** via Settings UI. Product-domain secrets live in **dedicated tables with envelope encryption** (same pattern as YouTube/Twitch credentials) — never in `.env` or `system_settings`. **All runtime reads of registry settings go through `setting()` / `setting_config()` → cached snapshot**; never `env()` outside config files; never direct `config()` for registry-backed paths in domain code. Details: [docs/platform/configuration-policy.md](docs/platform/configuration-policy.md).
7. **Local settings seed.** Dev-only Tier B overrides live in [config/settings-local.php](config/settings-local.php) and [LocalSettingsSeeder](database/seeders/LocalSettingsSeeder.php) (`APP_ENV=local` only, runs after `SettingsSeeder`). Never put local ergonomics in `config/*.php` literals or `.env`. Staging/production: `SettingsSeeder` + config literals only.

## Read first

**Core rules (mandatory, read every task):** [docs/agents/core-rules.md](docs/agents/core-rules.md) — full-stack requirement, living docs, database safety, Octane readiness, localization. Tag a task `[backend]` / `[frontend]` / `[fullstack]` (default) to scope the full-stack requirement — see rule 1.

**Doc map (load domain/platform guides on demand):** [docs/README.md](docs/README.md)

Daily work stays in **this repository only**.