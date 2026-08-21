# AI Agents — Monolith

**Role:** Laravel 13 backend — domain logic, database, JWT/RBAC, versioned API (`/api/v1/*`), and minimal server-side `web` routes (OAuth callbacks, payment return, public share). The **sole browser UI** is the sibling `monolith-vue-workers` SPA — [vue-workers-spa.md](docs/integration/vue-workers-spa.md). The `[frontend]` scope tag in this repo means SPA-facing API/resources/URLs (`SpaPublicUrl`, menu route names consumed by the SPA router), not Blade views.

## Workspace split (canonical)

Defined once here — do not restate per prompt.

Day-to-day work happens in an IDE workspace named **"Monolith"** that usually contains three projects:

- `c:\laragon\www\monolith\` — this repo (backend)
- `c:\laragon\www\monolith-vue-workers\` — SPA frontend
- `c:\laragon\www\gateway\` — Silverspoon Gateway: shared Cloudflare Workers infrastructure (Vault origin masking + S3 retrieve, Uploader S3 proxy). Its own project with its own docs/`AGENTS.md` — separate from both this backend and the SPA frontend.

The workspace may load only a subset of those three. When it does, the task is scoped to the loaded projects only — do not assume or touch projects that are not loaded.

- **Monolith (this repo)** is the **only backend**. All backend work — domain logic, database/migrations, JWT/RBAC, API `/api/v1/*`, server `web` routes, seeders, config, env — happens here.
- **Monolith-Vue-Workers** (sibling `../monolith-vue-workers`) is the **only frontend / browser UI**. All frontend work — Vue components/views/router/stores, i18n JSON, styling, Worker BFF — happens in that repo, never here.
- **Gateway** (sibling `../gateway`) is shared CF Workers infrastructure, not part of the Monolith backend or the SPA — route infra/CDN/upload-proxy work there per its own `AGENTS.md` + `docs/INDEX.md`.
- This repo has **no frontend stack** (no `resources/views|js|css`, no `package.json`). Never create Blade views or an asset pipeline here.
- Integration contract: [docs/integration/project-overview.md](docs/integration/project-overview.md) (summary) + [docs/integration/vue-workers-spa.md](docs/integration/vue-workers-spa.md) (detail).
- Changes touching the contract (endpoints, API Resource shapes, cookie/CSRF, SPA route names) must stay in sync on both sides and update both docs.

## Agent protocol (mandatory)

Instructions below are canonical here — no other document may re-define them.

1. **Assess before changing.** Before any change, check, validate, and assess the task: read the relevant code and docs until the work context is fully understood. No blind edits.
2. **Update docs with every task.** After finishing a task, always update the relevant related documentation so code and docs never drift (details: rule 2 in [docs/agents/core-rules.md](docs/agents/core-rules.md)). Per the workspace split above: contract-touching changes update both integration docs, and SPA-side changes also update the sibling `monolith-vue-workers` README when its workflow changes.
3. **`wrap up the docs` keyword.** The exact phrase `wrap up the docs` triggers a session-wide consolidation: reconcile ALL implementations from the session against the documentation as a whole. Per-task doc updates (point 2) cover only that task; the final state may differ — this keyword closes that gap. For localization and SPA changes, reconcile both [docs/platform/localization.md](docs/platform/localization.md) and [docs/integration/vue-workers-spa.md](docs/integration/vue-workers-spa.md), plus the sibling SPA README when its workflow changes.
4. **`curate the env` keyword.** The exact phrase `curate the env` triggers env layout curation: run `php artisan env:curate --dry-run` first, show the summary, then `php artisan env:curate` (add `--sync-snapshot` when snapshot sync is needed). Curate layouts from the **active profile template**, preserves semantic values, and **normalizes typed quoting** (strings always double-quoted). Surprise orphans for catalog keys usually mean profile template key drift — fix templates, don’t hand-edit `.env` layout. Never rewrite `.env` layout with editor tools directly — guarded artisan commands handle transactional backup. For other env mutations, use guarded artisan commands (`env:install`, `env:switch`, `key:generate`) or `php artisan env:backup:create` before manual edits. Details: [docs/platform/environment.md](docs/platform/environment.md).
5. **Dynamic menu construction.** Before creating or changing dynamic menus, read [docs/domains/navigation/README.md](docs/domains/navigation/README.md); verify every menu `route` is a SPA Vue Router symbolic name listed in `config/spa-routes.php` (and present in `monolith-vue-workers/src/router/index.ts`), each product parent route appears in `config/products.php` `menu_routes`, and every referenced role has already been seeded. Treat `MenuSeeder` as the sole authoritative tree: group related pages under clear Workspace/Products/Administration branches; use submenus instead of duplicate hub pages; keep route names (never URLs), contiguous sibling order, `en`/`id`/`ja` translations, exact RBAC, and product-bound routes on product parents so ancestor Domain Access and activation gates protect every child. Parents may store real product routes in the DB for those gates; `navigationTree` presentationally masks a parent’s `route` to `#` while it still has visible children (expand-only), and restores the stored route when children are all pruned. Two-tier cache: forever snapshot of active rows plus forever presented trees keyed by epoch/layout/viewer — rebuild only on miss, epoch bump (menu/role/domain-visibility), or per-user presented forget (activation / role assignment). Rebuild active and trashed rows transactionally, flush navigation cache only after commit, never seed debug/test/probe nodes, and lock exact tree plus rebuild/visibility behavior with tests. Update the Navigation guide and every affected domain guide in the same task.
6. **Configuration & secrets.** `.env` is for **project-owned sensitive / infra bindings only** (crypto, JWT signing, DB/Redis, KMS/OCI, mail/broadcast secrets, S3/upload-proxy credentials, payment gateway keys, AI provider API keys). Non-sensitive operational knobs live in **`system_settings` (plaintext)** via Settings UI. Product-domain secrets live in **dedicated tables with envelope encryption** (same pattern as YouTube/Twitch credentials) — never in `.env` or `system_settings`. **All runtime reads of registry settings go through `setting()` / `setting_config()` → cached snapshot**; never `env()` outside config files; never direct `config()` for registry-backed paths in domain code. Details: [docs/platform/configuration-policy.md](docs/platform/configuration-policy.md).
7. **Cache (domain data).** Laravel Cache entries that store domain/derived data must be **lifetime** (`rememberForever` / `forever`), **rebuild on miss**, and **invalidate on underlying data change**. Must work on any cache driver (default `database`) — **no cache tags**. Exceptions (locks, throttles, 2FA tokens, JWT blacklist) stay short-TTL by design. Full contract: [docs/platform/cache-policy.md](docs/platform/cache-policy.md).
8. **Local settings seed.** Dev-only Tier B overrides live in [config/settings-local.php](config/settings-local.php) and [LocalSettingsSeeder](database/seeders/LocalSettingsSeeder.php) (`APP_ENV=local` only, runs after `SettingsSeeder`). Never put local ergonomics in `config/*.php` literals or `.env`. Staging/production: `SettingsSeeder` + config literals only.

## Read first

**Core rules (mandatory, read every task):** [docs/agents/core-rules.md](docs/agents/core-rules.md) — full-stack requirement, living docs, database safety, Octane readiness, localization. Tag a task `[backend]` / `[frontend]` / `[fullstack]` (default) to scope the full-stack requirement — see rule 1.

**Doc map (load domain/platform guides on demand):** [docs/README.md](docs/README.md)

Work routing (see **Workspace split** above): backend work → this repo; frontend work → sibling `monolith-vue-workers`. The `[frontend]` scope tag in this repo still means the SPA-facing API surface, not UI. The contract is [docs/integration/vue-workers-spa.md](docs/integration/vue-workers-spa.md).