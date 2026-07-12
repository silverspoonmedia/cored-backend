# AI Agents — Project Monolith

**Role:** Laravel 13 **source of truth** — domain logic, database, JWT/RBAC, versioned API (`/api/v1/*`), and optional Blade admin.

## Read first

**Canonical workspace guide (all three projects):**

`c:\laragon\www\monolith\docs\ai-agent-guide.md`

Read it before cross-project features, API changes, or anything that touches Gateway or Frontend.

**Database safety:** Never run `migrate:fresh`, `migrate:refresh`, or `db:wipe` on non-sqlite without `ALLOW_DESTRUCTIVE_DB=true`. Prefer `composer test` in Monolith (`composer test -- --filter=…` for filtered runs). See [docs/database-safety.md](docs/database-safety.md).

## Start here (this project)

| Document | Topic |
|----------|-------|
| [README.md](README.md) | Living documentation rules, full-stack requirements |
| [docs/api-routing.md](docs/api-routing.md) | API structure |
| [docs/jwt-authentication.md](docs/jwt-authentication.md) | Auth and permissions |
| [docs/spa-gateway-integration.md](docs/spa-gateway-integration.md) | Monolith + Gateway + Vue wiring |
| [docs/crud-architecture.md](docs/crud-architecture.md) | CRUD and repositories |
| [docs/domain-modules.md](docs/domain-modules.md) | Domain modules: core/locked/removable, `domain:list\|make\|remove` |
| [docs/payment-gateways.md](docs/payment-gateways.md) | Midtrans/iPaymu, invoices, subscriptions, webhooks |
| [docs/database-safety.md](docs/database-safety.md) | Prevent dev DB wipe; test vs MySQL; recovery |
| [docs/internal-monorepo-assessment.md](docs/internal-monorepo-assessment.md) | Monorepo assessment and phase-4 deferral |
| [docs/internal-monorepo-porting.md](docs/internal-monorepo-porting.md) | Porting implementation reference for session handoff |

## Related repositories

| Project | Path |
|---------|------|
| Monolith Gateway | `c:\laragon\www\monolith-gateway` — [AGENTS.md](c:\laragon\www\monolith-gateway\AGENTS.md) |
| Monolith Frontend | `c:\laragon\www\monolith-frontend` — [AGENTS.md](c:\laragon\www\monolith-frontend\AGENTS.md) |
