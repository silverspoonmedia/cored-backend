# AI Agents — Monolith

**Role:** Laravel 13 backend — domain logic, database, JWT/RBAC, versioned API (`/api/v1/*`), and minimal server-side `web` routes (OAuth callbacks, payment return, public share). The **sole browser UI** is the sibling `monolith-vue-workers` SPA. The `[frontend]` scope tag here means SPA-facing API/resources/URLs (`SpaPublicUrl`, menu route names), not Blade views.

## Workspace split

This IDE workspace may load any subset of:

- `c:\laragon\www\monolith\` — this repo (backend)
- `c:\laragon\www\monolith-vue-workers\` — SPA frontend
- `c:\laragon\www\gateway\` — Silverspoon Gateway (own `AGENTS.md`)

Open **one side by default**. Workspace files: [Monolith-Backend.code-workspace](Monolith-Backend.code-workspace) (this repo), sibling [Monolith-SPA.code-workspace](../monolith-vue-workers/Monolith-SPA.code-workspace), [Monolith-Fullstack.code-workspace](Monolith-Fullstack.code-workspace) only when the API↔SPA contract moves. Scope work to loaded projects only.

- **This repo** is the only backend. **monolith-vue-workers** is the only frontend. **Gateway** is shared CF Workers infra.
- This repo has no frontend stack. Never create Blade views here.
- Integration: [project-overview.md](docs/integration/project-overview.md) (summary) + [vue-workers-spa.md](docs/integration/vue-workers-spa.md) (contract). Open the contract doc **only** when endpoints, headers, cookies/CSRF, envelopes, or route names change.

## Agent protocol

Rules 1–9 in [core-rules.md](docs/agents/core-rules.md) apply. Open that file and the owning platform/domain guide **only when the matching rule is in play** — do not re-read them on every task. Context budget: [docs/agents/context-budget.md](docs/agents/context-budget.md).

1. **Assess before changing.** Read the guide for the area you are touching. No blind edits. Prefer `[backend]` or `[frontend]` unless the contract actually moves ([core-rules.md](docs/agents/core-rules.md) rule 1).
2. **Update docs with every task.** Same-task living docs. Keyword `wrap up the docs` = session-wide consolidation. Localization + SPA contract: [localization.md](docs/platform/localization.md) and [vue-workers-spa.md](docs/integration/vue-workers-spa.md), plus the SPA README when its workflow changes.
3. **`curate the env`.** `php artisan env:curate --dry-run`, then `php artisan env:curate` (`--sync-snapshot` when needed). Never hand-edit `.env` layout. [environment.md](docs/platform/environment.md).
4. **Load on demand** (not always-on essays): menus / `MenuSeeder` → [navigation README](docs/domains/navigation/README.md); config & secrets → [configuration-policy.md](docs/platform/configuration-policy.md); domain cache → [cache-policy.md](docs/platform/cache-policy.md); local settings seed → [configuration-policy.md](docs/platform/configuration-policy.md#local-profile-overrides); user-facing copy → [localization.md](docs/platform/localization.md) (patch keys; never dump locale files).

**Doc map:** [docs/README.md](docs/README.md). Backend work stays here; SPA work stays in the sibling.
