# AI Agents — Monolith

**Role:** Laravel 13 backend — domain logic, database, JWT/RBAC, versioned API (`/api/v1/*`), and minimal server-side `web` routes (OAuth callbacks, payment return, public share). The **sole browser UI** is the sibling `monolith-vue-workers` SPA. The `[frontend]` scope tag here means SPA-facing API/resources/URLs, not Blade views.

## Workspace split

- This repo is the only backend; `monolith-vue-workers` is the only frontend; `gateway` is shared CF Workers infra (own `AGENTS.md`); the sibling `docs` repo is the user-facing Docusaurus site (own `AGENTS.md`).
- Open **one side by default**: [Monolith-Backend.code-workspace](Monolith-Backend.code-workspace) or the sibling SPA workspace. [Monolith-Fullstack.code-workspace](Monolith-Fullstack.code-workspace) only when the API↔SPA contract moves. All three workspaces load the `docs` repo as a peer root, so its rules inject on every turn.
- A **customer-visible** capability is not shipped until its page in `docs` says so; that page ships in the same task (see the `docs-sync` rule in that repo).
- Never create Blade views here. Integration summary: [docs/integration/project-overview.md](docs/integration/project-overview.md); contract: [docs/integration/vue-workers-spa.md](docs/integration/vue-workers-spa.md) (open only when endpoints, headers, cookies/CSRF, envelopes, or route names change).

## Protocol

1. **Assess before changing** — open the owning rule for the area you touch (table below) and the file's nearest test. No blind edits.
2. **Update docs and rules with every task** — the owning brief in `docs/` and the rule file when an invariant moved. `wrap up the docs` = session-wide consolidation of code ↔ docs ↔ rules across both repos.
3. **Respect scope and context** — tag the task `[fullstack]`/`[backend]`/`[frontend]`, and follow the context budget. User-facing copy follows the always-on i18n guardrail.

## Rule map (all `.cursor/rules/*.mdc`)

| Rule | Fires when |
|------|-----------|
| `docs-pakem.mdc` *(always-on)* | Writing or moving any doc, brief, or rule file |
| `task-scope.mdc` *(always-on)* | Deciding which layers a task must touch |
| `context-budget.mdc` *(always-on)* | Choosing a workspace / what to open |
| `i18n-guardrail.mdc` *(always-on)* | Adding or editing user-facing copy |
| `architecture.mdc` | `app/Domain/**`, `config/domains.php` — domain kind/lifecycle, namespaces, dependency direction |
| `api-routing.mdc` | `routes/api/**`, `bootstrap/app.php` — global vs versioned layer, `v{N}` loader, route names |
| `api-resources.mdc` | `app/**/Http/**`, `routes/api/**` — envelope, list `display_mode`/`actions[]`, standalone-first `/batch`, response minimization |
| `crud.mdc` | `app/Repositories/**`, `**/Http/Controllers/**`, `**/Http/Requests/**` — shared repository, FormRequest DRY |
| `envelope-encryption.mdc` | `app/Support/Encryption/**`, `app/Support/Oci/**`, `config/encryption.php` — Tier C crypto |
| `i18n-lang.mdc` | Touching `lang/**`, a translated string, or the locale guards |
| `environment.mdc` | `.env`, `config/*.php`, settings tiers, `curate the env` |
| `settings.mdc` | `app/Support/Settings/**`, `config/settings*.php`, `*SettingsSeeder.php` — snapshot chain, permissions, labels |
| `security.mdc` | `config/cors.php`, `bootstrap/app.php`, `app/Http/Middleware/**`, `app/Support/Security/**` — perimeter + audit procedure |
| `database-safety.mdc` | `database/**`, `app/Domain/**/Models/**`, `tests/**`, `phpunit.xml` — UUID v7 PKs, destructive commands |
| `octane.mdc` | Any file under `app/**` (long-lived worker safety) |
| `cache-policy.mdc` | `app/Domain/**`, `app/Support/**` — cache lifetime and invalidation |
| `navigation-menus.mdc` | Menu seeder, tree, visibility gates, navigation cache |
| `identity.mdc` | `app/Domain/Identity/**`, auth/JWT/RBAC config |
| `notification.mdc` | `app/Domain/Notification/**`, bell config/seeder |
| `domain-access.mdc` | `app/Support/DomainAccess/**`, Domain Access routes/config |
| `activity-log.mdc` | `app/Domain/ActivityLog/**`, audit trail config/API |
| `error-log.mdc` | `app/Domain/ErrorLog/**`, error-log config/API |
| `inference.mdc` | `app/Domain/Inference/**`, catalog seeder, rate config |
| `ai.mdc` | `app/Domain/Ai/**`, AI connection config/seeder |
| `service.mdc` | `app/Domain/Service/**`, catalog seeder, FX/service config |
| `product-activations.mdc` | `app/Support/Product/**`, product registry config |
| `payment-gateway.mdc` | `app/Domain/PaymentGateway/**`, payment config/routing |
| `vtual.mdc` / `vtual-content.mdc` | `app/Domain/Vtual/**`, `routes/api/v1/vtual*.php` |
| `archivd.mdc` | `app/Domain/Archivd/**`, Archivd routes/config |
| `microstock.mdc` | `app/Domain/Microstock/**`, microstock routes/config |

Doc map: [docs/README.md](docs/README.md). History and audits: [docs/archive/](docs/archive/). Backend work stays here; SPA work stays in the sibling.
