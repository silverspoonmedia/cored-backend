# Documentation index — Monolith

Two surfaces, one owner per topic (see [.cursor/rules/docs-pakem.mdc](../.cursor/rules/docs-pakem.mdc)):

- **Human briefs** live here in `docs/` — one paragraph of orientation plus a pointer table.
- **Agent pakem** lives in [`.cursor/rules/*.mdc`](../.cursor/rules/) — imperative rules, glob-scoped, self-sufficient. [`AGENTS.md`](../AGENTS.md) is the always-on map.

Every living brief is listed in this index; narrative, audits and progress trackers belong in [archive/](archive/). **Language: English only.**

## Start here

| Need | Open |
|------|------|
| Agent pakem map | [AGENTS.md](../AGENTS.md) · rules in [.cursor/rules/](../.cursor/rules/) |
| User-facing docs site | [help.silverspoon.me](https://help.silverspoon.me) — sibling `docs` repo (Docusaurus, customer manual); agent pakem in that repo's `AGENTS.md` + `.cursor/rules/` |
| IDE workspace | [Monolith-Backend.code-workspace](../Monolith-Backend.code-workspace) · sibling `Monolith-SPA.code-workspace` · [Monolith-Fullstack.code-workspace](../Monolith-Fullstack.code-workspace) for contract work; all three load the `docs` repo as a peer root |
| Domain layout, `domain:*`, namespaces, path packages | [platform/architecture.md](platform/architecture.md) · [integration/core-packages.md](integration/core-packages.md) |
| Removing a product domain (ordered procedure) | [.cursor/skills/domain-remove/SKILL.md](../.cursor/skills/domain-remove/SKILL.md) |
| Env profiles, secrets, typed quoting, curate | [platform/environment.md](platform/environment.md) · [platform/configuration-policy.md](platform/configuration-policy.md) |
| Project overview (Monolith + Vue-Workers) | [integration/project-overview.md](integration/project-overview.md) |
| SPA frontend contract | [integration/vue-workers-spa.md](integration/vue-workers-spa.md) (contract changes only) |
| Auth URI layout | Authenticated SPA under **`/apps/*`** (Vue Router names unprefixed); guest/public at site root; API `/api/v1/*` unchanged — [architecture — Routes](platform/reference-architecture.md#routes) |

## Platform (kernel / cross-cutting)

[platform/README.md](platform/README.md) is the sub-index for this tree.

| Document | Topic |
|----------|-------|
| [architecture.md](platform/architecture.md) | Logical monorepo + Composer path packages, kind core\|product + locked lifecycle + parent composition, routes, namespaces |
| [api-routing.md](platform/api-routing.md) | Global vs versioned API |
| [api-envelope.md](platform/api-envelope.md) | Canonical JSON envelope, error mapping, `/batch` partial-failure contract |
| [commands.md](platform/commands.md) | Artisan command index |
| [crud.md](platform/crud.md) | Repository pattern, layers, checklist |
| [list-display-modes.md](platform/list-display-modes.md) | `display_mode=query` list contract, `actions[]`, `/api/v1/lookups/*` |
| [identifiers.md](platform/identifiers.md) | UUID v7 primary keys |
| [environment.md](platform/environment.md) | `.env` profiles, typed quoting, `env:curate`, PHP 8.4 requirement |
| [configuration-policy.md](platform/configuration-policy.md) | Env vs DB tiers, Tier B settings cache contract |
| [cache-policy.md](platform/cache-policy.md) | Forever cache + explicit invalidation; key inventory |
| [api-aggregation.md](platform/api-aggregation.md) | Aggregation rules: standalone-first, backend fan-in via `/batch` |
| [settings.md](platform/settings.md) | Runtime operational settings (Web UI + DB overrides) |
| [localization.md](platform/localization.md) | Locale list, resolution order, parity guards, curation tooling |
| [naming-conventions.md](platform/naming-conventions.md) | Settled translation terms per locale + drift logs (glossary) |
| [database-safety.md](platform/database-safety.md) | Dev DB wipe prevention, migration portability, migration baseline, tests |
| [octane.md](platform/octane.md) | Long-lived worker rules |
| [security.md](platform/security.md) | OWASP audit procedure, auth activity coverage, response minimization, CORS perimeter |
| [envelope-encryption.md](platform/envelope-encryption.md) | Tiered crypto, OCI KMS, decrypt cache, P0 columns |
| [tooling.md](platform/tooling.md) | Pint usage, gate list |

**Platform deep dives:** [reference-architecture.md](platform/reference-architecture.md) · [reference-api-routing.md](platform/reference-api-routing.md) · [reference-commands.md](platform/reference-commands.md) · [reference-crud.md](platform/reference-crud.md) · [reference-environment.md](platform/reference-environment.md) · [reference-configuration-policy.md](platform/reference-configuration-policy.md) · [reference-settings.md](platform/reference-settings.md) · [reference-security.md](platform/reference-security.md) · [reference-localization.md](platform/reference-localization.md) · [reference-envelope-encryption.md](platform/reference-envelope-encryption.md) · [reference-tooling.md](platform/reference-tooling.md)

## Domains (`app/Domain/*`)

| Domain | Brief |
|--------|-------|
| Identity | [domains/identity/README.md](domains/identity/README.md) — JWT, 2FA, H-RBAC, users; deep dive [reference.md](domains/identity/reference.md) |
| Navigation | [domains/navigation/README.md](domains/navigation/README.md) — menu tree, visibility, cache; deep dive [reference.md](domains/navigation/reference.md) |
| Notification | [domains/notification/README.md](domains/notification/README.md) — bell, role vs user; deep dive [reference.md](domains/notification/reference.md) |
| ActivityLog | [domains/activity-log/README.md](domains/activity-log/README.md) — Spatie audit trail, PII envelopes; deep dive [reference.md](domains/activity-log/reference.md) |
| ErrorLog | [domains/error-log/README.md](domains/error-log/README.md) — grouped persistent app errors; deep dive [reference.md](domains/error-log/reference.md) |
| PaymentGateway | [domains/payment-gateway/README.md](domains/payment-gateway/README.md) — Midtrans/iPaymu, invoices, channels (`silverspoonmedia/billing`); deep dive [reference.md](domains/payment-gateway/reference.md) · credentials [platform/envelope-encryption.md](platform/envelope-encryption.md) |
| Currency | [domains/currency/README.md](domains/currency/README.md) — ISO catalogue, one settlement flag, Frankfurter FX snapshots (`silverspoonmedia/billing`) |
| Service | [domains/service/README.md](domains/service/README.md) — catalog, entitlements, FX→IDR, product fulfillment (`silverspoonmedia/billing`); deep dive [reference.md](domains/service/reference.md) |
| Inference | [domains/inference/README.md](domains/inference/README.md) — model catalog, provider rates, rate snapshots; deep dive [reference.md](domains/inference/reference.md) |
| Ai | [domains/ai/README.md](domains/ai/README.md) — central AI transport, connections, slug binding; deep dive [reference.md](domains/ai/reference.md) |
| Archivd | [domains/archivd/README.md](domains/archivd/README.md) — nested folders, multi-disk S3, Gateway upload, paid share, workspaces; base of the Microstock subproduct; deep dive [reference.md](domains/archivd/reference.md) · [reference-upload.md](domains/archivd/reference-upload.md) · [reference-realtime.md](domains/archivd/reference-realtime.md) |
| Microstock | [domains/microstock/README.md](domains/microstock/README.md) — AI metadata on an Archivd system workspace (subproduct, `parent => Archivd`); deep dive [reference.md](domains/microstock/reference.md) · [reference-pipeline.md](domains/microstock/reference-pipeline.md) · [reference-api.md](domains/microstock/reference-api.md) · [reference-marketplace-upload.md](domains/microstock/reference-marketplace-upload.md); [daily-free-trial.md](domains/microstock/daily-free-trial.md) · [billing-edge-cases.md](domains/microstock/billing-edge-cases.md) |
| Product Activations | [domains/product-activations/README.md](domains/product-activations/README.md) — runtime-access registry; deep dive [reference.md](domains/product-activations/reference.md) |
| Domain Access | [domains/domain-access/README.md](domains/domain-access/README.md) — Public/Private gate + Beta roles |

## Web surface (archived Blade)

The browser UI lives in the sibling SPA. These are historical only; the live list/lookup contract is [platform/list-display-modes.md](platform/list-display-modes.md).

| Document | Topic |
|----------|-------|
| [web/blade-admin.md](web/blade-admin.md) | Blade removal stub — what remains on the `web` stack |
| [web/partial-manifest.md](web/partial-manifest.md) | Archived Blade partial inventory |

## Integration

| Document | Topic |
|----------|-------|
| [project-overview.md](integration/project-overview.md) | Monolith and Monolith-Vue-Workers — architecture and relationship |
| [vue-workers-spa.md](integration/vue-workers-spa.md) | SPA contract: API shapes, `SPA_URL` minting, i18n runtime, batch hydration, realtime registry, CI; deep dive [reference-vue-workers-spa.md](integration/reference-vue-workers-spa.md) |
| [client-project.md](integration/client-project.md) | Building a project on the core packages: `client-scaffold` template, registry, route loaders, PSR-4 override hooks, config split, verification |
| [core-packages.md](integration/core-packages.md) | Core domains shipped as Composer path packages: layout, host wiring, override precedence, kernel contracts, how to add one |
| [reference-fork-seams.md](integration/reference-fork-seams.md) | Deep dive: the seams a downstream product registers (settings, menu, AI consumers, storage, share URL, SPA feature), their empty states, and the `domain:remove` recipe |
| [reference-package-seams.md](integration/reference-package-seams.md) | Deep dive: seam inventory a bundled package reads from a product, plus the one-way mirror, release-tag and `KernelServiceProvider` distribution rules |

## Archive (not daily reading)

[archive/](archive/) holds historical assessments, one-shot audits, the [security audit](archive/security-audit-2026-06-16.md), the [archived Blade UI patterns](archive/ui-patterns-blade.md), the [SPA consumer decision findings](archive/spa-consumer-decision-findings.md), [microstock probe summaries](archive/microstock-probes/), and the sunset post-mortems ([Blog](archive/blog-sunset-postmortem.md), [vTual](archive/vtual-sunset-postmortem.md)) that carry the restore recipe for each retired product domain. Archive files may mention a former three-repo workflow; that model is dissolved.

## Writing docs

Read [.cursor/rules/docs-pakem.mdc](../.cursor/rules/docs-pakem.mdc) first — it owns the brief template, size budgets, placement table and anti-patterns. Prefer editing an existing brief over adding a file; never add a subject file at `docs/*.md` root and never recreate `docs/agents/`.

Domain brief → `docs/domains/{kebab}/README.md`; kernel → `docs/platform/`; external wiring → `docs/integration/`; historical → `docs/archive/`. Add the new file to this index in the same task, then run `php scripts/docs-guard.php`.
