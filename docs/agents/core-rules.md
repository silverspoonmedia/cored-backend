# AI agent core rules — Monolith

Canonical, single-source mandatory rules for AI coding agents. Read this once per task, before touching code. Root entry: [AGENTS.md](../../AGENTS.md). Doc map (load domain/platform guides on demand from here): [docs/README.md](../README.md).

## Scope

Daily work stays in **this repository** — Laravel 13 API + Blade admin. Domain logic, DB, JWT/RBAC, and versioned routes (`/api/v1/*`) live here. This repository is the source of truth: business rules, validation, Spatie RBAC, persistence, and API contracts all live here.

```
Browser → Blade admin (AdminLTE) → same-host /api/v1/* (JWT cookie → Bearer)
```

API auth is JWT + Spatie RBAC. There is no shared-secret API perimeter middleware; protect network exposure with firewall / reverse proxy as needed.

## 1. Full-stack every task (mandatory, scope-tag aware)

Every feature or change must be implemented end to end across all relevant layers in the **same task/PR** — never ship a change that touches only one layer **unless the task is explicitly scoped** with a tag below.

### Scope tags

Prefix the task instruction with one of these tags to declare intended scope. Only this rule (rule 1) is affected by the tag — rules 2-5 below (living docs, database safety, Octane, localization) stay mandatory **regardless of tag**.

| Tag | Default? | Touch |
|-----|----------|-------|
| `[fullstack]` | **Yes — assumed when no tag is given** | API + Web + Blade + translations, per table below |
| `[backend]` | No | API layer only — Web/Blade/translation layers are out of scope for rule 1 |
| `[frontend]` | No | Web + Blade layer only (assumes the API being consumed already exists) — API/migration layers are out of scope for rule 1 |

Omitting the tag is identical to `[fullstack]` — untagged tasks keep today's behavior.

### `[fullstack]` layer table

| Layer | What to touch |
|-------|----------------|
| **API** | Versioned routes (`/api/v1/*`), controllers, form requests, policies, repositories, API responses |
| **Web** | Web controllers consuming the API (`ConsumesApi` / `ApiClient`), `routes/web.php` / `routes/web/*.php`, request forwarding |
| **Blade views** | Screens, forms, list tables, partials under `resources/views/{layout}/` (default `adminlte4` via `WEB_LAYOUT` / `config/web.php`) — see [blade-admin.md](../web/blade-admin.md#layout-namespace-and-configwebphp) |
| **Translation** | All user-facing strings in `lang/{locale}/` for every supported locale (`en`, `id`, `ja`) |
| **Other (as applicable)** | Migrations, seeders, models, events/listeners, notifications, navigation menu entries, tests, and the relevant `docs/` guide |

### `[backend]` scope

API routes/controllers/form requests/policies/repositories/responses, plus migrations/models/tests for that layer. No Web controller, Blade view, or translation changes required by this rule (rule 5 still applies if the backend change happens to add user-facing text, e.g. a new validation message).

### `[frontend]` scope

Web controllers consuming the existing API (`ConsumesApi` / `ApiClient`), Blade views, and translations for any new copy. No API/migration changes required by this rule.

An API-only or Blade-only change without its counterpart layers is **incomplete** — unless explicitly scoped via `[backend]` or `[frontend]`. See [CRUD architecture](../platform/crud.md), [Web frontend](../web/blade-admin.md), [Localization](../platform/localization.md#task-rule-required) for layer-specific conventions.

## 2. Living documentation (mandatory)

- Update the matching domain/platform/web guide in the **same task/PR** whenever behavior changes — there is no separate close-out ritual or changelog.
- Prefer editing an existing guide over adding a new file. **Never** add a new flat subject file at `docs/*.md` root — placement rules (domains/platform/web/integration/agents/archive) and the full decision checklist: [docs/README.md — Rules for new documentation](../README.md#rules-for-new-documentation-mandatory).
- English only, in every guide.

## 3. Database safety (mandatory)

Never run `migrate:fresh`, `migrate:refresh`, or `db:wipe` on non-sqlite without `ALLOW_DESTRUCTIVE_DB=true`. Prefer `composer test` (`composer test -- --filter=…`). Full root cause, safe commands, and recovery: [docs/platform/database-safety.md](../platform/database-safety.md).

## 4. Octane / Swoole readiness (mandatory)

All new code must survive long-lived Swoole worker processes, even though Octane isn't installed yet: never store request/user state on static properties or singletons, never mutate facade/guard state without cleanup, never call `env()` outside config files, never use `config()->set()` at runtime. Full rules: [docs/platform/octane.md](../platform/octane.md).

## 5. Localization (mandatory when touching user-facing copy)

Any change that adds or modifies user-facing text (Blade labels, API `message` strings, validation/policy errors, flash/AJAX copy, DB content shown to users) must include translations for **all** supported locales (`en`, `id`, `ja`) in the same task/PR. Use `__('…')` with application-owned files under `lang/{locale}/`, or Spatie translatable fields for DB content. Full checklist: [docs/platform/localization.md](../platform/localization.md#task-rule-required).

## Agent workflow checklist

1. Scope: this repository.
2. Read domain/platform docs from [docs/README.md](../README.md) for the area you're touching.
3. Implement; match existing patterns.
4. Test: `composer test` ([database-safety](../platform/database-safety.md)).
5. Update living docs when behavior changes — placement rules above, same task/PR.

## What not to do

- `migrate:fresh` / `db:wipe` on non-sqlite without `ALLOW_DESTRUCTIVE_DB=true`.
- Assume Blade has hosted Snap checkout UI — channel-only on Blade; see [payment-gateway](../domains/payment-gateway/README.md).
- Create new documentation as a subject file at `docs/*.md` root — place under `domains/`, `platform/`, `web/`, `integration/`, `agents/`, or `archive/` per [docs/README.md](../README.md#rules-for-new-documentation-mandatory).
- Ship a change touching only one of API / Web / Blade / translations when the others are affected, without an explicit `[backend]`/`[frontend]` scope tag (rule 1).
- Change vTual `PlatformApiGateway` surface without updating the admin platform-api-probe in the same PR — probe must mirror production methods; see [vTual README — Admin probe](../domains/vtual/README.md#admin-probe-permanent-rootadmin-debug).
