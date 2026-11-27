# AI Agents — Cored Backend

**Role:** Laravel 13 backend for a private Cored boilerplate — the ten core domain packages (`silverspoonmedia/{platform,navigation,notification,audit,billing,ai}`), JWT/RBAC, the versioned API (`/api/v1/*`), and minimal server-side `web` routes (OAuth callbacks, payment return). The **sole browser UI** is the sibling `cored-frontend` SPA. The `[frontend]` scope tag here means SPA-facing API/resources/URLs, not Blade views.

This repository is a **fork of `monolith` with both product domains removed** (Microstock first, then Archivd) through `php artisan domain:remove`, with the former `packages/` tree replaced by a consumer install of the released `^1.2` line under `vendor/silverspoonmedia/`, and with the language packs pruned to `en` + `id`. Nothing product-specific should come back here: [docs/integration/boilerplate-forks.md](docs/integration/boilerplate-forks.md) records what left and why.

## Workspace split

- This repo is the only backend; `cored-frontend` is the only frontend; `monolith` / `monolith-vue-workers` remain the living upstream line, pulled forward manually (never mirrored).
- The `docs` sibling is the shared documentation checkout; its always-on rules inject on every turn.
- Never create Blade views here. Integration summary: [docs/integration/project-overview.md](docs/integration/project-overview.md); SPA contract: [docs/integration/vue-workers-spa.md](docs/integration/vue-workers-spa.md).
- A **core domain** lives in its Composer package; check `php artisan domain:list` (`Package` column) before assuming a source path. Contract: [docs/integration/core-packages.md](docs/integration/core-packages.md).

## Protocol

1. **Assess before changing** — open the owning rule for the area you touch (table below) and the file's nearest test. No blind edits.
2. **Extend in the host, never by editing a package** — a settings group is a `SettingContributor`, a menu node a `MenuContributor`, a consumer row an `ai.consumers` entry, a class a file under the matching `app/` override prefix. Package edits are lost on the next `composer update`.
3. **Update docs and rules with every task** — the owning brief in `docs/` and the rule file when an invariant moved.
4. **Respect scope and context** — tag the task `[fullstack]`/`[backend]`/`[frontend]`, and follow the context budget. User-facing copy follows the always-on i18n guardrail.

## Rule map (all `.cursor/rules/*.mdc`)

| Rule | Fires when |
|------|-----------|
| `docs-pakem.mdc` *(always-on)* | Writing or moving any doc, brief, or rule file |
| `task-scope.mdc` *(always-on)* | Deciding which layers a task must touch |
| `context-budget.mdc` *(always-on)* | Choosing a workspace / what to open |
| `i18n-guardrail.mdc` *(always-on)* | Adding or editing user-facing copy |
| `working-branch.mdc` *(always-on)* | Before the first edit — never work on a protected branch |
| `architecture.mdc` | `app/**`, `config/domains.php`, `vendor/silverspoonmedia/**` — domain kind/lifecycle/composition, the consumer install, the kernel as `silverspoonmedia/platform`, namespaces, dependency direction |
| `api-routing.mdc` | `routes/api/**`, `bootstrap/app.php` — global vs versioned layer, `v{N}` loader, route names |
| `api-resources.mdc` | `vendor/silverspoonmedia/**/src/Http/**`, `routes/api/**` — envelope, list `display_mode`/`actions[]`, standalone-first `/batch`, response minimization |
| `crud.mdc` | `vendor/silverspoonmedia/**/src/Repositories/**`, `app/Http/**`, `vendor/silverspoonmedia/**/src/Http/**` — shared repository, FormRequest DRY |
| `envelope-encryption.mdc` | `vendor/silverspoonmedia/platform/src/Support/{Encryption,Oci}/**`, `config/encryption.php` — Tier C crypto |
| `i18n-lang.mdc` | Touching `lang/**`, a translated string, or the locale guards |
| `environment.mdc` | `.env`, `config/*.php`, settings tiers, `curate the env` |
| `settings.mdc` | `vendor/silverspoonmedia/platform/src/Support/Settings/**`, `config/settings*.php`, `*SettingsSeeder.php` — snapshot chain, permissions, labels |
| `security.mdc` | `config/cors.php`, `bootstrap/app.php`, `vendor/silverspoonmedia/platform/src/Http/Middleware/**`, `vendor/silverspoonmedia/platform/src/Support/Security/**` — perimeter + audit procedure |
| `database-safety.mdc` | `database/**`, `tests/**`, `phpunit.xml` — UUID v7 PKs, destructive commands |
| `migration-baseline.mdc` | `database/**` — edit the owning migration in place, no alter/data migration (dev phase, deleted at production) |
| `octane.mdc` | Any file under `app/**` (long-lived worker safety) |
| `cache-policy.mdc` | `app/**/Domain/**`, `vendor/silverspoonmedia/**/src/Support/**` — cache lifetime and invalidation |
| `navigation-menus.mdc` | `vendor/silverspoonmedia/navigation/**`, `config/spa-routes.php` — menu seeder, tree, visibility gates, navigation cache |
| `identity.mdc` | `vendor/silverspoonmedia/platform/src/Domain/Identity/**`, auth/JWT/RBAC config |
| `notification.mdc` | `vendor/silverspoonmedia/notification/**`, bell config/seeder |
| `domain-access.mdc` | `vendor/silverspoonmedia/platform/src/Support/DomainAccess/**`, Domain Access routes/config |
| `activity-log.mdc` | `vendor/silverspoonmedia/audit/src/ActivityLog/**`, audit trail config/API |
| `error-log.mdc` | `vendor/silverspoonmedia/audit/src/ErrorLog/**`, error-log config/API |
| `inference.mdc` | `vendor/silverspoonmedia/ai/src/Inference/**`, catalog seeder, rate config |
| `ai.mdc` | `vendor/silverspoonmedia/ai/src/Ai/**`, AI connection config/seeder |
| `service.mdc` | `vendor/silverspoonmedia/billing/src/Service/**`, `app/Support/**` (host override), catalog seeder, FX/service config |
| `product-activations.mdc` | `vendor/silverspoonmedia/platform/src/Support/Product/**`, product registry config |
| `payment-gateway.mdc` | `vendor/silverspoonmedia/billing/src/PaymentGateway/**`, payment config/routing YAML |
| `currency.mdc` | `vendor/silverspoonmedia/billing/src/Currency/**`, currency route, Frankfurter config |

Doc map: [docs/README.md](docs/README.md). History and audits: [docs/archive/](docs/archive/).
