# AI agent core rules — Monolith

Canonical, single-source mandatory rules for AI coding agents. Read this once per task, before touching code. Root entry: [AGENTS.md](../../AGENTS.md). Doc map (load domain/platform guides on demand from here): [docs/README.md](../README.md).

## Scope

Daily work stays in **this repository** — Laravel 13 API + minimal server `web` endpoints. Domain logic, DB, JWT/RBAC, and versioned routes (`/api/v1/*`) live here. This repository is the source of truth: business rules, validation, Spatie RBAC, persistence, and API contracts all live here. **The in-repo Blade admin has been removed** — see [blade-admin.md](../web/blade-admin.md).

The **sole browser UI** is the sibling `monolith-vue-workers` repository ([vue-workers-spa.md](../integration/vue-workers-spa.md)). Backend and that SPA are separate projects; that split is the intended architecture. SPA changes belong in that sibling. The `[frontend]` tag in this repo means **SPA contract work** (types, menu route registry parity, docs) — not Blade.

```
Browser → Monolith-Vue-Workers SPA → /api/v1/* (cross-site __Host-spa_jwt + CSRF)
Monolith web stack → server-only redirects (share, payment return, OAuth callback)
```

API auth is JWT + Spatie RBAC. There is no shared-secret API perimeter middleware; protect network exposure with firewall / reverse proxy as needed.

## 1. Full-stack every task (mandatory, scope-tag aware)

Every feature or change must be implemented end to end across all relevant layers in the **same task/PR** — never ship a change that touches only one layer **unless the task is explicitly scoped** with a tag below.

### Scope tags

Prefix the task instruction with one of these tags to declare intended scope. Only this rule (rule 1) is affected by the tag — rules 2–9 below (living docs, database safety, Octane, localization, configuration, cache, local settings, response data minimization) stay mandatory **regardless of tag**.

| Tag | Default? | Touch |
|-----|----------|-------|
| `[fullstack]` | **Yes — assumed when no tag is given** | API + SPA (sibling repo) + translations + menu route registry parity, per table below |
| `[backend]` | No | API layer only — SPA/UI layers are out of scope for rule 1 |
| `[frontend]` | No | SPA layer in `monolith-vue-workers` (assumes the API being consumed already exists) — API/migration layers are out of scope for rule 1 |

### `[fullstack]` layer table

| Layer | What to touch |
|-------|----------------|
| **API** | Versioned routes (`/api/v1/*`), controllers, form requests, policies, repositories, API responses |
| **Web (server)** | `routes/web.php` / `routes/web/*.php` — server endpoints only (public share, payment return, OAuth callback); no Blade views |
| **SPA** | Screens, forms, listings in `monolith-vue-workers` — see [vue-workers-spa.md](../integration/vue-workers-spa.md) |
| **Navigation** | Menu `route` column = SPA Vue Router symbolic name; keep `config/spa-routes.php` in sync with MenuSeeder and SPA router |
| **Translation** | All user-facing strings in `lang/{locale}/` for every supported locale (`en`, `id`, `ja`) |
| **Other (as applicable)** | Migrations, seeders, models, events/listeners, notifications, navigation menu entries, tests, and the relevant `docs/` guide |

### `[backend]` scope

API routes/controllers/form requests/policies/repositories/responses, plus migrations/models/tests for that layer. No SPA view or translation changes required by this rule (rule 5 still applies if the backend change happens to add user-facing text, e.g. a new validation message).

### `[frontend]` scope

SPA views/composables/routes in `monolith-vue-workers`, plus translations for any new copy. No API/migration changes required by this rule.

An API-only or SPA-only change without its counterpart layers is **incomplete** — unless explicitly scoped via `[backend]` or `[frontend]`. See [CRUD architecture](../platform/crud.md), [vue-workers-spa.md](../integration/vue-workers-spa.md), [Localization](../platform/localization.md#task-rule-required) for layer-specific conventions.

## 2. Living documentation (mandatory)

- Update the matching domain/platform/web guide in the **same task/PR** whenever behavior changes. Exception: the exact keyword `wrap up the docs` (defined in [AGENTS.md](../../AGENTS.md)) triggers a session-wide code↔docs consolidation on top of this per-task rule.
- Prefer editing an existing guide over adding a new file. **Never** add a new flat subject file at `docs/*.md` root — placement rules (domains/platform/web/integration/agents/archive) and the full decision checklist: [docs/README.md — Rules for new documentation](../README.md#rules-for-new-documentation-mandatory).
- English only, in every guide.

## 3. Database safety (mandatory)

Never run `migrate:fresh`, `migrate:refresh`, or `db:wipe` on non-sqlite without `ALLOW_DESTRUCTIVE_DB=true`. Prefer `composer test` (`composer test -- --filter=…`). Full root cause, safe commands, and recovery: [docs/platform/database-safety.md](../platform/database-safety.md).

## 4. Octane / Swoole readiness (mandatory)

All new code must survive long-lived Swoole worker processes, even though Octane isn't installed yet: never store request/user state on static properties or singletons, never mutate facade/guard state without cleanup, never call `env()` outside config files, never use `config()->set()` at runtime. Full rules: [docs/platform/octane.md](../platform/octane.md).

## 5. Localization (mandatory when touching user-facing copy)

Any change that adds or modifies user-facing text (API `message` strings, validation/policy errors, flash/AJAX copy, DB content shown to users) must include translations for **all** supported locales (`en`, `id`, `ja`) in the same task/PR. For SPA-facing copy, update `monolith-vue-workers/src/i18n/locales/{en,id,ja}.json` in the sibling repository and run `npm run check:i18n`. Use `__('…')` with application-owned files under `lang/{locale}/`, or Spatie translatable fields for DB content. Full backend checklist: [docs/platform/localization.md](../platform/localization.md#task-rule-required). SPA runtime, fallback, and `useI18n()` context rules: [vue-workers-spa.md](../integration/vue-workers-spa.md#i18n-porting-notes).

## 6. Configuration & secrets (mandatory)

`.env` stores **project-owned sensitive / infra bindings only** (see Tier A in [configuration-policy.md](../platform/configuration-policy.md)). Non-sensitive operational knobs belong in **`system_settings`** (Settings UI, plaintext). Product-domain secrets use **envelope-encrypted dedicated tables** — never `.env` or `system_settings`. Runtime reads of registry settings use **`setting()` / `setting_config()`** through the cached `system_settings` snapshot; never `env()` outside config files; never `config()` for registry-backed paths in domain code. New registry keys require seed + translations + policy tests — no new env vars for Tier B.

## 7. Cache — forever + invalidate (mandatory for domain data)

Laravel `Cache` entries that store domain/derived data must use **lifetime** storage (`rememberForever` / `forever`), **rebuild on miss**, and **explicit invalidation** when underlying data changes. Must work on any driver (including default `database`) — **no `Cache::tags`**. Short TTL is allowed only for documented exceptions (locks, throttles, 2FA challenge tokens, JWT blacklist). Full contract and inventory: [cache-policy.md](../platform/cache-policy.md).

## 8. Local settings seed (mandatory when adding dev ergonomics)

**`APP_ENV=local` only:** dev Tier B overrides live in [config/settings-local.php](../../config/settings-local.php) and [LocalSettingsSeeder](../../database/seeders/LocalSettingsSeeder.php) (runs after `SettingsSeeder`, `upsertMany`). Never change `config/*.php` literals or `.env` for local-only knobs. Staging/production use config literals + `SettingsSeeder` only. Details: [configuration-policy.md — Local profile overrides](../platform/configuration-policy.md#local-profile-overrides).

## 9. Response data minimization (mandatory)

Every JSON Resource is a **per-viewer** contract, not a dump of the Eloquent model. Guest endpoints must not reuse owner Resources. Never emit credential secrets, live accept/claim tokens to non-owners, upstream cost to customers, FX/settlement internals to buyers, other users’ emails to non-staff, or storage `path` / `disk` / `checksum` to non-owner/non-staff. New list/show endpoints need one “wrong viewer 403” test and one “sensitive field absent” test. Full rules: [platform/security.md](../platform/security.md#response-data-minimization-mandatory).

## Agent workflow checklist

1. Scope: this repository.
2. Read domain/platform docs from [docs/README.md](../README.md) for the area you're touching.
3. Implement; match existing patterns.
4. Test: `composer test` ([database-safety](../platform/database-safety.md)).
5. Update living docs when behavior changes — placement rules above, same task/PR.

## What not to do

- `migrate:fresh` / `db:wipe` on non-sqlite without `ALLOW_DESTRUCTIVE_DB=true`.
- Assume hosted Snap checkout UI exists in Monolith — channel-only; checkout UI lives in the SPA; see [payment-gateway](../domains/payment-gateway/README.md).
- Create new documentation as a subject file at `docs/*.md` root — place under `domains/`, `platform/`, `web/`, `integration/`, `agents/`, or `archive/` per [docs/README.md](../README.md#rules-for-new-documentation-mandatory).
- Ship a change touching only one of API / SPA / translations when the others are affected, without an explicit `[backend]`/`[frontend]` scope tag (rule 1).
- Change vTual `PlatformApiGateway` surface without updating the admin platform-api-probe in the same PR — probe must mirror production methods; see [vTual README — Admin probe](../domains/vtual/README.md#admin-probe-permanent-rootadmin-debug).
- Dump Eloquent models into JSON Resources without per-viewer gating — see rule 9.
