# AI is one package, not two — `silverspoonmedia/ai`

**Date:** 2026-11-17 · **Branch:** `refactor/core-domain-packages` · **Status:** accepted, third bundle shipped

## Decision

> `Inference` + `Ai` ship as **one** package, `silverspoonmedia/ai`, with two namespaces, two API route files, one provider.

The bundling rule from [core-packages-bundling-decision.md](core-packages-bundling-decision.md) named this pair as the next candidate. This is the proof.

## Why not two packages

A rate nobody can bill is not a price:

| Edge | Direction | Example |
|------|-----------|---------|
| A catalog entry is only reachable through a connection | `Inference` → `Ai` | `InferenceRateService` answers "what does this slug cost", `AiConnectionResolver` answers "can we reach it" — every consumer calls both in sequence |
| Same admin journey | both | Administration → **AI connections** and Administration → **Inference** are one operator task: wire the endpoint, then price the models it serves |
| Same consumer settings | both | `microstock.ai.{vision,thinking}.connection` (Ai) and `microstock.ai.inference_provider_slug` (Inference) are read by the same product service |

Splitting them forces a cross-package runtime dependency with no versioning story, and a downstream consumer installs both halves anyway.

Two routes files stay because the URL surfaces are separate: `/api/v1/ai/connections*` is Tier C transport, `/api/v1/inference/*` is catalog + rates + usage. A host override of one must not move the other.

## The real blocker: coupling *into products*

`Inference` did not merely couple to `Ai`. It imported the **product** that bills tokens, which a core package may never do ([architecture.mdc](../../.cursor/rules/architecture.mdc)):

```
InferenceUsageAnalyticsService  → Microstock\AiUsageEvent, Microstock\BillingLedgerService
InferenceCatalogService         → Microstock\UnknownModelBillingConsolidationService
InferenceUsageController        → Microstock\AdminBillingUsageEventResource
```

The fix is the same shape as `silverspoonmedia/billing`'s `FulfillmentRegistry`: **kernel declares, product implements, package consumes** — behind `app()->bound()`, with an empty registry as a legal state.

| Kernel contract | Product implementation |
|---|---|
| `Support\Ai\Contracts\AiUsageLedger` (event model, eager-load relations, USD formatting) | `Microstock\Support\Ai\MicrostockAiUsageLedger`, bound in `MicrostockServiceProvider` |
| `Support\Ai\AiUsageConsolidatorRegistry` + `Contracts\AiUsageConsolidator` (keyed by provider slug) | `Microstock\Support\Ai\MicrostockAiUsageConsolidator`, registered with `config('microstock.ai.inference_provider_slug')` |
| `config('ai.usage_event_resource')` — a class-string, not an interface | `Microstock\Support\Ai\MicrostockAdminUsageEventResource` published into the config in the product provider |

The resource is the one seam that is a config key rather than an interface: a JSON resource is instantiated per row by `$resource::collection(...)`, so an interface returning the class-string would only add a hop. `AiServiceProvider` ships `''`; with no product installed the dashboard renders an empty page.

`InferenceCatalogService` now walks `AiUsageConsolidatorRegistry::forProvider($provider->slug)` — the same slug the product uses when it records usage — so curating a rate consolidates exactly the ledger that billed it and nothing else.

## What else had to move

- **The admin usage dashboard stayed in the package; its rows did not.** Analytics, filters and the paginated endpoint are catalog concerns and ship with `silverspoonmedia/ai`. The event table, its relations, the money formatting and the refund/debit arithmetic stay with the product that owns the balance.
- **Seeders moved with their domains into `App\Domain\{Domain}\Database\Seeders`** — `AiConnectionSeeder` (Ai) and `InferenceCatalogSeeder` (Inference). A domain seeder keeps its domain namespace; only `DatabaseSeeder` changed its `use` lines. The gitignored data file (`database/seeders/data/ai-connections.local.php`) stays in the host — it is deployment input, addressed through `ai.seed.path`, not package source.
- **List-action builders moved into the package's own namespace** (`App\Domain\Ai\Support\ListActions`, `App\Domain\Inference\Support\ListActions`), matching `silverspoonmedia/audit` / `silverspoonmedia/billing`, instead of staying in the host's `App\Support\ListActions\Builders`.
- **Migrations moved with their tables** (`create_ai_connections_table`, `create_inference_pricing_tables`). The latter still attaches billing columns to `microstock_ai_usage_events`; that is a column the product's table must have for the ledger seam to work, and the manifest records it as package-owned.
- **Route binds moved into the package provider** — `{connection}`, `{model_card}` and `{provider}` are package routes, so the binding lives beside them.

## Invariants this locks

1. Two PSR-4 prefixes (`App\Domain\Ai\`, `App\Domain\Inference\`) and two test prefixes; bundling never merges namespaces.
2. `PackageOverrideTest` proves both resolve from `packages/ai`, both name `silverspoonmedia/ai`, both route files load, both host prefixes win the PSR-4 race, and the kernel seams resolve after product providers boot **without the test naming any product**.
3. Locale keys did not change: `api/ai.*` and `api/inference.*` now resolve from `packages/ai/resources/lang/{locale}/api/`. The seven-locale parity, prose, raw-key, glossary, dynamic-key and curation-flag guards stay green — moving a file is not a translation change.
4. `DomainUninstallManifestTest` stays green: the Microstock manifest cites `packages/ai/**` for files that used to sit under `app/Domain/{Ai,Inference}/`, `database/seeders/`, `config/` and `tests/Domain/{Ai,Inference}/`.
5. Route set and names are identical before and after. The registry loads AI after billing, so the usage and catalog URIs still land in the same order.

Contract: [core-packages.md](../integration/core-packages.md). Registry: [architecture.md](../platform/architecture.md). Domain briefs: [ai](../domains/ai/README.md) · [inference](../domains/inference/README.md).
