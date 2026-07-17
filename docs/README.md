# Documentation index — Project Monolith

Living docs are organized **by domain** (mirror `app/Domain/*`) plus shared **platform**, **web**, and **integration** buckets. Do **not** add new subject files at `docs/*.md` root — put content under the correct folder.

**Language:** English only.

## Start here

| Need | Open |
|------|------|
| Agent scope, keywords, three-repo rules | [agents/guide.md](agents/guide.md) · root [AGENTS.md](../AGENTS.md) |
| Domain layout, `domain:*`, namespaces | [platform/architecture.md](platform/architecture.md) |
| Session / Gateway / Frontend handoff | [changelog/README.md](changelog/README.md) |
| Env profiles, secrets, gateway perimeter | [platform/environment.md](platform/environment.md) |
| Blade admin (API consumer) | [web/blade-admin.md](web/blade-admin.md) |
| SPA + Gateway wiring | [integration/spa-gateway.md](integration/spa-gateway.md) |

## Platform (kernel / cross-cutting)

| Document | Topic |
|----------|-------|
| [architecture.md](platform/architecture.md) | Logical monorepo, kind core\|product + locked lifecycle, routes, namespaces |
| [api-routing.md](platform/api-routing.md) | Global vs versioned API |
| [crud.md](platform/crud.md) | Repository pattern, layers, checklist |
| [identifiers.md](platform/identifiers.md) | UUID v7 primary keys |
| [environment.md](platform/environment.md) | `.env` profiles, config cache, gateway perimeter |
| [localization.md](platform/localization.md) | `en` / `id` / `ja` task rules |
| [database-safety.md](platform/database-safety.md) | Dev DB wipe prevention, tests |
| [octane.md](platform/octane.md) | Long-lived worker rules |
| [security.md](platform/security.md) | OWASP audit procedure + auth activity coverage |
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
| File Hosting | [domains/file-hosting/README.md](domains/file-hosting/README.md) — nested folders, chunk S3, link share (product, locked) |

## Web UI patterns

| Document | Topic |
|----------|-------|
| [blade-admin.md](web/blade-admin.md) | JWT cookie, ApiClient, routes, theming |
| [ui-patterns.md](web/ui-patterns.md) | Select2 lookups, list/DataTable modes |

## Integration

| Document | Topic |
|----------|-------|
| [spa-gateway.md](integration/spa-gateway.md) | Monolith + Gateway + Vue |
| [changelog/](changelog/README.md) | Session log (`pending/` → `done/`) — **stays at this path** |

## Archive (not daily reading)

Historical assessments and one-shot audits: [archive/](archive/). Prefer living docs above.

## Rules for new documentation (mandatory)

**Never create a new subject file at `docs/*.md` root** (except this index). Every new guide belongs in a **bucket** below. Prefer **editing an existing domain/platform file** over inventing a parallel subject.

### Where to put new content

| If the topic is… | Put it here | File pattern |
|------------------|-------------|--------------|
| A business domain under `app/Domain/{Name}/` | `docs/domains/{kebab-name}/` | Prefer `README.md`; split only when a subtopic is large (e.g. `encryption.md`) |
| Kernel / cross-cutting (routes, CRUD pattern, UUID, env, i18n, DB safety, Octane, security process, tooling) | `docs/platform/` | One concern per file; extend existing when possible |
| Blade admin only (cookie bridge, ApiClient, layout, pure UI widgets shared across domains) | `docs/web/` | e.g. `blade-admin.md`, `ui-patterns.md` |
| Three-repo wiring (Gateway, Vue SPA, production perimeter) | `docs/integration/` | e.g. `spa-gateway.md` |
| Agent scope / session keywords | `docs/agents/` | `guide.md` only unless a dedicated agent ops page is justified |
| Session handoff / HTTP contract log | `docs/changelog/` | Entry via [TEMPLATE.md](changelog/TEMPLATE.md) — **do not** invent a parallel log tree |
| One-shot assessment, migration narrative, audit snapshot, design dump | `docs/archive/` | Not linked from daily learning paths except as “historical” |

### Decision checklist (before writing a new file)

1. **Does a living guide already own this?** → Edit that file (section + anchor). Do not add `docs/foo-bar.md`.
2. **Is it domain-owned business behavior?** → `docs/domains/{domain}/README.md` (or a sibling under that folder).
3. **Is it shared kernel / convention?** → `docs/platform/`.
4. **Is it Blade UX plumbing, not domain rules?** → `docs/web/`.
5. **Is it multi-repo HTTP / SPA?** → `docs/integration/` + changelog if contracts change.
6. **Is it historical only?** → `docs/archive/` (do not bloat living guides).
7. **New `app/Domain/*` module?** → Add `docs/domains/{name}/README.md` and a row in **this index** in the same task.
8. **Language:** English only. Keep living guides **compact**; long design notes go to archive.

### Anti-patterns (reject these)

| Anti-pattern | Why |
|--------------|-----|
| New `docs/some-feature.md` at root | Subject sprawl — breaks domain learning path |
| Third parallel guide for the same topic | Horizontal duplication (notifications ×3 was the anti-pattern) |
| Mixing current product surface with gap analysis / dual-status dumps | Living docs go stale; archive the dump |
| Documenting Gateway/Frontend contracts only in Monolith prose without `changelog/pending/` when HTTP changes | Downstream sync will miss it |
| Skipping this index after adding a domain folder | Agents and humans lose the map |

Full-stack, localization, Octane, and database-safety task rules still apply — see root [README.md](../README.md).
