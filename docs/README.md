# Documentation index — Monolith

Living docs are organized **by domain** (mirror `app/Domain/*`) plus shared **platform**, **web**, and **integration** buckets. Do **not** add new subject files at `docs/*.md` root — put content under the correct folder.

**Language:** English only.

## Start here

| Need | Open |
|------|------|
| Agent scope | [agents/core-rules.md](agents/core-rules.md) · root [AGENTS.md](../AGENTS.md) |
| Domain layout, `domain:*`, namespaces | [platform/architecture.md](platform/architecture.md) |
| Env profiles, secrets, typed quoting, curate | [platform/environment.md](platform/environment.md) · [configuration-policy.md](platform/configuration-policy.md) |
| Blade admin (removed — SPA is sole UI) | [web/blade-admin.md](web/blade-admin.md) |
| Project overview (Monolith + Vue-Workers) | [integration/project-overview.md](integration/project-overview.md) |
| SPA frontend (Vue-Workers; separate project) | [integration/vue-workers-spa.md](integration/vue-workers-spa.md) |
| Auth URI layout | Authenticated SPA under **`/apps/*`** (Vue Router names unprefixed); guest/public at site root; API `/api/v1/*` unchanged — [vue-workers-spa — `/apps` shell](integration/vue-workers-spa.md) · [architecture — Routes](platform/architecture.md#routes) |

## Platform (kernel / cross-cutting)

| Document | Topic |
|----------|-------|
| [architecture.md](platform/architecture.md) | Logical monorepo, kind core\|product + locked lifecycle, routes, namespaces |
| [api-routing.md](platform/api-routing.md) | Global vs versioned API |
| [crud.md](platform/crud.md) | Repository pattern, layers, checklist |
| [list-display-modes.md](platform/list-display-modes.md) | `display_mode` list contract, query-mode `actions[]` (live SPA row buttons), `/api/v1/lookups/*`, retained-but-unused Yajra datatable mode |
| [identifiers.md](platform/identifiers.md) | UUID v7 primary keys |
| [environment.md](platform/environment.md) | `.env` profiles, typed quoting, assignment-key parity, `env:curate`, config cache |
| [configuration-policy.md](platform/configuration-policy.md) | Env vs DB tiers, Tier B settings cache contract, agent checklist |
| [cache-policy.md](platform/cache-policy.md) | Project-wide forever Cache + invalidate; driver-agnostic; exceptions |
| [api-aggregation.md](platform/api-aggregation.md) | Aggregation rules: standalone-first, SPA-only, backend fan-in via `/batch`; gate condition |
| [settings.md](platform/settings.md) | Runtime operational settings (Web UI + DB overrides) |
| [localization.md](platform/localization.md) | Backend `en` / `id` / `ja` rules, parity, and locale resolution; SPA guard: `monolith-vue-workers/npm run check:i18n` |
| [database-safety.md](platform/database-safety.md) | Dev DB wipe prevention, tests |
| [octane.md](platform/octane.md) | Long-lived worker rules |
| [security.md](platform/security.md) | OWASP audit procedure, auth activity coverage, **response data minimization** |
| [tooling.md](platform/tooling.md) | Laravel Pint wrapper |

## Domains (`app/Domain/*`)

| Domain | Document |
|--------|----------|
| Identity | [domains/identity/README.md](domains/identity/README.md) — JWT, 2FA, H-RBAC, users |
| Navigation | [domains/navigation/README.md](domains/navigation/README.md) — menu tree |
| Notification | [domains/notification/README.md](domains/notification/README.md) — bell, role vs user |
| ActivityLog | [domains/activity-log/README.md](domains/activity-log/README.md) — Spatie audit trail |
| ErrorLog | [domains/error-log/README.md](domains/error-log/README.md) — grouped persistent app errors |
| PaymentGateway | [domains/payment-gateway/README.md](domains/payment-gateway/README.md) · [encryption.md](domains/payment-gateway/encryption.md) |
| Service | [domains/service/README.md](domains/service/README.md) — catalog, entitlements, DB FX→IDR, FH grace/purge (core domain) |
| Inference | [domains/inference/README.md](domains/inference/README.md) — AI model catalog, provider model rates, per-usage rate snapshots (core domain) |
| File Hosting | [domains/file-hosting/README.md](domains/file-hosting/README.md) — nested folders, multi-disk S3, Gateway hybrid upload (25 MB parts, `gateway.silverspoon.me`), paid link share, collab workspaces, live listing via SPA (product, locked) |
| vTual | [domains/vtual/README.md](domains/vtual/README.md) — creator aggregator (product, locked); public SPA `/vtual` + `/vtual/vtubers/{slug}`; authenticated SPA screens: orphans + platform-credentials + profiles manage + vtuber hub + read profile; naming [naming-conventions.md](domains/vtual/naming-conventions.md); progress [implementation-status.md](domains/vtual/implementation-status.md); ops [youtube-stability-assessment-2026-08-07.md](domains/vtual/ops/youtube-stability-assessment-2026-08-07.md); legacy audit [archive/vtual-legacy-audit.md](archive/vtual-legacy-audit.md) |
| Microstock | [domains/microstock/README.md](domains/microstock/README.md) — microstock asset mgmt on FH system workspace: AI metadata, prepaid AI billing + usage/balance UI (ledger, usage detail, admin platform tab), CSV export, encrypted marketplace creds (product, locked) |
| Product Activations | [domains/product-activations/README.md](domains/product-activations/README.md) — kernel runtime-access registry for product domains |
| Domain Access | [domains/domain-access/README.md](domains/domain-access/README.md) — kernel Public/Private visibility gate + Beta tester roles for product domains |

## Web surface (archived Blade)

The browser UI lives in the sibling SPA. These three are kept for history only — the live list/lookup contract is [platform/list-display-modes.md](platform/list-display-modes.md).

| Document | Topic |
|----------|-------|
| [blade-admin.md](web/blade-admin.md) | Blade removal stub — what remains on the `web` stack |
| [partial-manifest.md](web/partial-manifest.md) | Archived Blade partial inventory |
| [ui-patterns.md](web/ui-patterns.md) | Archived Blade UI patterns — do not recreate; port to the SPA |

## Integration

| Document | Topic |
|----------|-------|
| [project-overview.md](integration/project-overview.md) | Monolith (API-first backend + server web endpoints) and Monolith-Vue-Workers (sole SPA frontend, separate project) — architecture and relationship |
| [vue-workers-spa.md](integration/vue-workers-spa.md) | Leading Vue 3 SPA frontend (Cloudflare Workers, sibling repo) — API contracts, `SPA_URL` minting, type alignment, i18n runtime/fallbacks, batch hydration, realtime, CI, production checklist |

## Archive (not daily reading)

Historical assessments and one-shot audits: [archive/](archive/). Prefer living docs above. Archive files may mention a former three-repo workflow; that model is dissolved — use living guides for current behavior.

## Rules for new documentation (mandatory)

**Never create a new subject file at `docs/*.md` root** (except this index). Every new guide belongs in a **bucket** below. Prefer **editing an existing domain/platform file** over inventing a parallel subject.

### Where to put new content

| If the topic is… | Put it here | File pattern |
|------------------|-------------|--------------|
| A business domain under `app/Domain/{Name}/` | `docs/domains/{kebab-name}/` | Prefer `README.md`; split only when a subtopic is large (e.g. `encryption.md`) |
| Kernel / cross-cutting (routes, CRUD pattern, UUID, env, i18n, DB safety, Octane, security process, tooling) | `docs/platform/` | One concern per file; extend existing when possible |
| Blade admin only (removed — historical reference) | `docs/web/` | e.g. `blade-admin.md` stub, archived `partial-manifest.md` |
| External clients / third-party integration wiring | `docs/integration/` | Prefer one concern per file; only when not domain-owned |
| Agent scope | `docs/agents/` | `core-rules.md` only unless a dedicated agent ops page is justified |
| One-shot assessment, migration narrative, audit snapshot, design dump | `docs/archive/` | Not linked from daily learning paths except as “historical” |

### Decision checklist (before writing a new file)

1. **Does a living guide already own this?** → Edit that file (section + anchor). Do not add `docs/foo-bar.md`.
2. **Is it domain-owned business behavior?** → `docs/domains/{domain}/README.md` (or a sibling under that folder).
3. **Is it shared kernel / convention?** → `docs/platform/`.
4. **Is it Blade UX plumbing, not domain rules?** → `docs/web/`.
5. **Is it external client / third-party integration?** → `docs/integration/`.
6. **Is it historical only?** → `docs/archive/` (do not bloat living guides).
7. **New `app/Domain/*` module?** → Add `docs/domains/{name}/README.md` and a row in **this index** in the same task.
8. **Language:** English only. Keep living guides **compact**; long design notes go to archive.

### Anti-patterns (reject these)

| Anti-pattern | Why |
|--------------|-----|
| New `docs/some-feature.md` at root | Subject sprawl — breaks domain learning path |
| Third parallel guide for the same topic | Horizontal duplication (notifications ×3 was the anti-pattern) |
| Mixing current product surface with gap analysis / dual-status dumps | Living docs go stale; archive the dump |
| Changing behavior without updating the matching living guide | Code and docs drift |
| Skipping this index after adding a domain folder | Agents and humans lose the map |

Full-stack, localization, Octane, and database-safety task rules still apply — see root [README.md](../README.md).
