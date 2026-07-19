# AI Agent Guide — Monolith

Canonical guide for AI coding agents. Root entry: [AGENTS.md](../../AGENTS.md). Doc map: [docs/README.md](../README.md).

## Scope

Daily work stays in **this repository** — Laravel 13 API + Blade admin. Domain logic, DB, JWT/RBAC, and versioned routes (`/api/v1/*`) live here.

### Session lifecycle and keywords

Keywords must appear in the **current user prompt**. Re-loading AGENTS.md alone does not change phase.

| Phase | Trigger | Action |
|-------|---------|--------|
| **1. Open** | Load AGENTS / this guide | Scope: this repository. Ready for work. |
| **2. Work** | No special keyword | Implement + test. No close-out. |
| **3. Close** | **`upstream documentation session`** | Improve docs for session work; write **one** changelog entry (Monolith outcomes only). |

| Keyword | Purpose |
|---------|---------|
| *(none)* | Phase 2 |
| **`upstream documentation session`** | Phase 3 — docs + changelog |

Full procedure: [changelog/README.md](../changelog/README.md).

## Request flow

```
Browser → Blade admin (AdminLTE) → same-host /api/v1/* (JWT cookie → Bearer)
```

API auth is JWT + Spatie RBAC. There is no shared-secret API perimeter middleware; protect network exposure with firewall / reverse proxy as needed.

## Mandatory principles

### 1. This repository is the source of truth

- Business rules, validation, Spatie RBAC, persistence, and API contracts live here.
- **Full-stack every task:** API + Web + Blade + translations (`en`, `id`, `ja`) in the same PR. See root [README.md](../../README.md#living-documentation-required).

### 2. Living documentation

- Prefer editing the matching domain / platform / web / integration guide.
- **Never** add a new flat `docs/*.md` subject file. Rules: [docs/README.md](../README.md#rules-for-new-documentation-mandatory).
- English only.

### 3. Database safety

- Never `migrate:fresh` / `migrate:refresh` / `db:wipe` on non-sqlite without `ALLOW_DESTRUCTIVE_DB=true`.
- Prefer `composer test`. See [database-safety](../platform/database-safety.md).

## Where to read

Use [docs/README.md](../README.md) — domain folders under `docs/domains/`, platform under `docs/platform/`, web under `docs/web/`.

## Agent workflow checklist

### Daily work (phase 2)

1. Scope: this repository.
2. Read domain/platform docs from [docs/README.md](../README.md).
3. Implement; match existing patterns.
4. Test: `composer test` ([database-safety](../platform/database-safety.md)).
5. Update living docs when behavior changes — placement rules above.
6. No close-out without **`upstream documentation session`**.

### Upstream documentation session (phase 3)

1. Confirm exact keyword in prompt.
2. Inventory session work; fix stale docs (English).
3. One changelog entry with Monolith outcomes; place in `done/` when the session is closed, or `pending/` only for incomplete draft notes.
4. Summarize for the user: docs updated + changelog path.

## What not to do

- Close-out without the exact keyword **`upstream documentation session`**.
- Treat AGENTS reload as session close.
- `migrate:fresh` / `db:wipe` on non-sqlite without `ALLOW_DESTRUCTIVE_DB=true`.
- Assume Blade has hosted Snap checkout UI — channel-only on Blade; see [payment-gateway](../domains/payment-gateway/README.md).
- Create new documentation as a subject file at `docs/*.md` root — place under `domains/`, `platform/`, `web/`, `integration/`, `agents/`, `changelog/`, or `archive/` per [docs/README.md](../README.md#rules-for-new-documentation-mandatory).
