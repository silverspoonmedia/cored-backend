# Inference — AI model catalog and token pricing

Core domain that owns the AI model catalog and the token rates billed for using it: which model slug exists, what each provider charges, and what a settled usage row was charged at the moment it happened. Product domains consume rates through `InferenceRateService`; markup and the prepaid gate stay product-specific. Code: `app/Domain/Inference/`. Invariants: [.cursor/rules/inference.mdc](../../../.cursor/rules/inference.mdc).

Pricing is **provider-first**: the customer pays provider cost × markup from the provider actually used, and every billed HTTP call freezes an immutable snapshot so a later catalog edit never rewrites history.

| Need | Path |
|------|------|
| Code | `app/Domain/Inference/` — `InferenceRateService`, `AiModelRateSnapshotService`, `InferenceUsageAnalyticsService` |
| Config | [`config/inference.php`](../../../config/inference.php) · Settings → Platform (`platform.inference.*`) |
| Tables | `ai_model_cards`, `ai_inference_providers`, `ai_provider_model_rates`, `ai_model_rate_snapshots` |
| Seeder | `database/seeders/InferenceCatalogSeeder.php` |
| API | [`routes/api/v1/inference.php`](../../../routes/api/v1/inference.php) |
| SPA | Vue Router `inference.model-cards.*`, `inference.providers.*`, `inference.usage-events.index` |
| Realtime | `inference.catalog` private channel — `.inference.catalog.changed` |
| Tests | `tests/Domain/Inference/` (`php artisan test --filter=Inference`) |
| Pakem | [.cursor/rules/inference.mdc](../../../.cursor/rules/inference.mdc) |
| Deep dive | [reference.md](reference.md) — pricing layers, snapshots, seeded rates, unknown-model workflow, admin UI |
| Related | [ai](../ai/README.md) (transport and key) · [microstock](../microstock/README.md) (billing consumer) · [platform/configuration-policy.md](../../platform/configuration-policy.md) (tiers) |
