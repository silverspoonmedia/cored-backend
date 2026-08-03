# Inference — core domain

Core domain (`kind => core`) for **AI model catalog and token pricing**. Product domains (Microstock today) consume rates through `InferenceRateService`; markup and prepaid gates stay product-specific.

| | |
|--|--|
| Code | `app/Domain/Inference/` |
| Config | [`config/inference.php`](../../../config/inference.php) |
| Seeder | `InferenceCatalogSeeder` |

## Provider-first pricing

| Layer | Table | Role |
|-------|-------|------|
| Identity | `ai_model_cards` | One row per resolved model slug (`gpt-5.6-luna`, `anthropic/claude-sonnet-5`, …) |
| Upstream routing | `ai_inference_providers` | Provider identity (`slug`, `name`, `is_active`) |
| Mutable pricing | `ai_provider_model_rates` | Per-provider model rates (`inference_provider_id` + `model_card_id`, unique) |
| Immutable usage | `ai_model_rate_snapshots` | Freeze **provider + model + rates** at billing time |

Customer billable = **provider cost × markup** from the provider actually used (no separate public-list plan).

## Rate snapshot (per usage)

Parallels [PaymentGateway — route policy snapshot](../payment-gateway/README.md#route-policy-snapshot): mutable catalog rows can change; billed usage keeps an immutable snapshot.

| When | What |
|------|------|
| Each `AiBillingService::recordUsage` HTTP row | `AiModelRateSnapshotService::capture($resolvedModel, $providerSlug)` → FK `ai_model_rate_snapshot_id` on `microstock_ai_usage_events` |

Snapshots dedupe on `(inference_provider_id, model_slug, input_per_million_usd, output_per_million_usd)`.

**Snapshot columns:** `model_slug`, `model_card_id`, `inference_provider_id`, `input_per_million_usd`, `output_per_million_usd`, `captured_at`.

Usage events also store `inference_provider_id` when the upstream route is known.

Usage API exposes frozen rates via `BillingUsageEventResource.rate_snapshot` (input/output per 1M USD + `captured_at`).

## Seeded model rates

Seeded by `InferenceCatalogSeeder` into each provider via `ai_provider_model_rates`:

| Model slug | Input / 1M USD | Output / 1M USD |
|------------|----------------|-------------------|
| `grok-grok-4.5-high` | $3.00 | $15.00 |
| `gpt-5.4` | $2.50 | $15.00 |
| `kimi-k3` | $3.00 | $15.00 |
| `gpt-5.6` / `gpt-5.6-sol` | $5.00 | $30.00 |
| `gpt-5.6-terra` | $2.50 | $15.00 |
| `gpt-5.6-luna` | $1.00 | $6.00 |
| `claude-opus-5` / `anthropic/claude-opus-5` | $5.00 | $25.00 |
| `claude-sonnet-5` / `anthropic/claude-sonnet-5` | $2.00 | $10.00 |

Unknown resolved slugs (no `ai_provider_model_rates` row on the active provider) bill at **provisional** rates from `setting_config('inference.unknown_model_rates.*')` (registry `platform.inference.unknown_*`). Snapshots store `rate_source = unknown_provisional`. Admin gets a deduped `inference_unregistered_model` notification; model cards show **Needs classification**. After ops curates rates on the provider (`PUT …/providers/{id}/rates`), `UnknownModelBillingConsolidationService` (Microstock) recalculates settled prepaid usage and posts ledger **adjustments** (refund or extra debit). `platform.inference.default_*` rates are bootstrap fallbacks when no DB row exists yet.

### Unknown model workflow

| Step | Behavior |
|------|----------|
| Runtime | Unregistered resolved slug → provisional config rates; `ensureModelCard(..., markNeedsClassification: true)` |
| Snapshot | `ai_model_rate_snapshots.rate_source` = `known` \| `unknown_provisional` \| `config_default` |
| Admin alert | Deduped `inference_unregistered_model` → deep link `inference.model-cards.show` |
| Curation | Ops sets provider rate on model card; `InferenceCatalogService::syncProviderRates()` runs consolidation |
| Post-curation | Prepaid usage with `unknown_provisional` snapshots reconciled; `consolidated_at` set on usage rows |

**Schema (cross-domain):**

| Column | Table | Purpose |
|--------|-------|---------|
| `needs_rate_classification` | `ai_model_cards` | Admin badge; cleared after successful consolidation |
| `rate_source` | `ai_model_rate_snapshots` | Distinguishes catalog vs provisional vs bootstrap fallback |
| `consolidated_at` | `microstock_ai_usage_events` | Idempotency guard for consolidation reruns |
| `consolidation_adjustment_micro_usd` | `microstock_ai_usage_events` | Prepaid billable delta applied during consolidation |

Sonnet 5 list prices reflect Anthropic **introductory** pricing through Aug 31, 2026.

## Provider scaffold

| Provider slug | Name |
|---------------|------|
| `openwebui-9router` | Open WebUI / 9Router |
| `codex` | Codex upstream |
| `anthropic-api` | Anthropic API |

## Config

Operational rates and default provider slug: **Settings → Platform** — see [configuration-policy.md](../../platform/configuration-policy.md).

| Registry key | Config path | Default | Purpose |
|--------------|-------------|---------|---------|
| `platform.inference.default_provider_slug` | `inference.default_provider_slug` | `openwebui-9router` | Default provider for rate resolution and snapshots |
| `platform.inference.default_input_per_million_usd` | `inference.default_rates.input_per_million_usd` | `5` | Bootstrap fallback when no DB row |
| `platform.inference.default_output_per_million_usd` | `inference.default_rates.output_per_million_usd` | `20` | Bootstrap fallback when no DB row |
| `platform.inference.unknown_input_per_million_usd` | `inference.unknown_model_rates.input_per_million_usd` | `15` | Provisional input $/1M for unregistered resolved models |
| `platform.inference.unknown_output_per_million_usd` | `inference.unknown_model_rates.output_per_million_usd` | `60` | Provisional output $/1M for unregistered resolved models |

| Env | Default | Purpose |
|-----|---------|---------|
| `MICROSTOCK_AI_INFERENCE_PROVIDER` | `openwebui-9router` | Provider slug passed to `recordUsage` from Microstock HTTP AI (not in registry) |

## Services

| Service | Role |
|---------|------|
| `InferenceRateService` | `ratesForProviderModel()`, `isRegisteredOnProvider()`, `defaultProviderSlug()`, `ensureModelCard()` |
| `AiModelRateSnapshotService` | `capture()` — immutable provider-specific snapshot (`rate_source` on row) |
| `UnknownModelBillingConsolidationService` | Post-curation prepaid reconciliation (Microstock domain) |
| `InferenceUsageAnalyticsService` | Overview + events; aggregates include `by_provider` |

## Consumers

| Domain | Usage |
|--------|-------|
| Microstock | `AiBillingService` — provider cost + billable micro-USD; markup from `microstock.ai_billing.markup_multiplier` |

## Admin UI

Core administration screens under **Administration → Inference** (dynamic menu). Permissions: `view inference catalog`, `manage inference catalog` (`RoleSeeder`).

| Screen | Web route | API |
|--------|-----------|-----|
| Model catalog | `inference.model-cards.*` | `GET/POST /api/v1/inference/model-cards` |
| Providers | `inference.providers.*` | `GET/POST /api/v1/inference/providers`, `PUT /api/v1/inference/providers/{id}/rates` |
| Usage events | `inference.usage-events.index` | `GET /api/v1/inference/usage/overview`, `GET /api/v1/inference/usage-events` |

Model card **show** includes default-provider rate (`provider_rate`) and 30-day `usage_summary`. Model catalog **datatable** includes a **Needs classification** column/badge when `needs_rate_classification` is true. Provider **show** supports bulk rate sync via `PUT /api/v1/inference/providers/{id}/rates` (triggers consolidation for flagged cards). Usage reporting reads `microstock_ai_usage_events` with frozen `rate_snapshot` and optional `inference_provider` on each row.

## Tests

`php artisan test --filter=Inference` — includes `InferenceRateServiceTest` (provisional rates, `isRegisteredOnProvider`), `InferenceCatalogApiTest` (provider rate sync clears classification flag), and Microstock `UnknownModelBillingTest` (consolidation refund/debit, negative balance).
