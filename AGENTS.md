# AI Agents — Monolith

**Role:** Laravel 13 application — domain logic, database, JWT/RBAC, versioned API (`/api/v1/*`), and Blade admin.

## Read first

**Canonical agent guide:** [docs/agents/guide.md](docs/agents/guide.md)  
**Doc map (by domain):** [docs/README.md](docs/README.md)

**New documentation:** place under the matching domain / platform / web / integration section — **never** a new flat subject file at `docs/*.md` root. Full placement rules: [docs/README.md — Rules for new documentation](docs/README.md#rules-for-new-documentation-mandatory).

Daily work stays in **this repository only**.

**Database safety:** Never run `migrate:fresh`, `migrate:refresh`, or `db:wipe` on non-sqlite without `ALLOW_DESTRUCTIVE_DB=true`. Prefer `composer test` (`composer test -- --filter=…`). See [docs/platform/database-safety.md](docs/platform/database-safety.md).

## Session lifecycle

Behavior depends on keywords in the **current user prompt**, not on whether this file was attached.

| Phase | When | Agent action |
|-------|------|--------------|
| **1. Open** | User loads this guide at chat start | Read context. Scope: this repository. Ready for work. |
| **2. Work** | No special session keyword | Implement and test. Do **not** run close-out. |
| **3. Close** | Exact keyword **`upstream documentation session`** | Improve `docs/` for session work, then write **one** entry under [docs/changelog/](docs/changelog/README.md) (Monolith outcomes only). |

### Keywords (exact phrase only)

| Keyword | Meaning |
|---------|---------|
| *(none)* | Phase 2 — keep working |
| **`upstream documentation session`** | Phase 3 — docs + changelog entry (`done/` by default; `pending/` only for incomplete draft notes). Paraphrases do **not** qualify. |

Without **`upstream documentation session`**, stay in **phase 2** even if this file was re-loaded.

Full workflow: [docs/agents/guide.md](docs/agents/guide.md) · [docs/changelog/README.md](docs/changelog/README.md).

## Start here (this project)

| Document | Topic |
|----------|-------|
| [docs/README.md](docs/README.md) | Domain-oriented documentation index |
| [README.md](README.md) | Living documentation rules, full-stack requirements |
| [docs/changelog/README.md](docs/changelog/README.md) | Session log (`pending/` / `done/`) |
| [docs/platform/architecture.md](docs/platform/architecture.md) | Domain modules, `domain:*`, namespaces |
| [docs/platform/api-routing.md](docs/platform/api-routing.md) | Global vs versioned API |
| [docs/domains/identity/README.md](docs/domains/identity/README.md) | Auth, JWT, H-RBAC |
| [docs/domains/error-log/README.md](docs/domains/error-log/README.md) | Grouped persistent app errors |
| [docs/integration/api-perimeter.md](docs/integration/api-perimeter.md) | Trusted-proxy / `GATEWAY_*` API perimeter |
| [docs/platform/crud.md](docs/platform/crud.md) | Repository pattern |
| [docs/domains/payment-gateway/README.md](docs/domains/payment-gateway/README.md) | Payments, invoices |
| [docs/domains/payment-gateway/encryption.md](docs/domains/payment-gateway/encryption.md) | Envelope encryption + OCI KMS |
| [docs/domains/service/README.md](docs/domains/service/README.md) | Catalog, entitlements, DB FX→IDR, FH grace/purge |
| [docs/platform/database-safety.md](docs/platform/database-safety.md) | Dev DB wipe prevention |
| [docs/web/blade-admin.md](docs/web/blade-admin.md) | Blade admin (API consumer) |
