# Inference — AI model catalog and token pricing

Core domain that owns the AI model catalog and the token rates billed for using it: which model slug exists, what each provider charges, and what a settled usage row was charged at the moment it happened. Product domains consume rates through `InferenceRateService`; markup and the prepaid gate stay product-specific. Code: `vendor/silverspoonmedia/ai/src/Inference/` (package `silverspoonmedia/ai`, bundled with Ai). Invariants: [.cursor/rules/inference.mdc](../../../.cursor/rules/inference.mdc).

Pricing is **provider-first**: the customer pays provider cost × markup from the provider actually used, and every billed HTTP call freezes an immutable snapshot so a later catalog edit never rewrites history.

| Need | Path |
|------|------|
| Code | `vendor/silverspoonmedia/ai/src/Inference/` — `InferenceRateService`, `AiModelRateSnapshotService`, `InferenceUsageAnalyticsService` |
| Config | [`vendor/silverspoonmedia/ai/config/inference.php`](../../../vendor/silverspoonmedia/ai/config/inference.php) · Settings → Platform (`platform.inference.*`) |
| Tables | `ai_model_cards`, `ai_inference_providers`, `ai_provider_model_rates`, `ai_model_rate_snapshots` |
| Seeder | `vendor/silverspoonmedia/ai/src/Inference/Database/Seeders/InferenceCatalogSeeder.php` |
| API | [`vendor/silverspoonmedia/ai/routes/inference.php`](../../../vendor/silverspoonmedia/ai/routes/inference.php) |
| SPA | Vue Router `inference.model-cards.*`, `inference.providers.*`, `inference.usage-events.index` |
| Realtime | `inference.catalog` private channel — `.inference.catalog.changed` |
| Tests | The AI suite runs from a clone of `silverspoonmedia/ai` (`composer install` then `composer test`); `tests/` is not installed in `vendor/` |
| Pakem | [.cursor/rules/inference.mdc](../../../.cursor/rules/inference.mdc) |
| Deep dive | [reference.md](reference.md) — pricing layers, snapshots, seeded rates, unknown-model workflow, admin UI |
| Related | [ai](../ai/README.md) (transport and key) · [platform/configuration-policy.md](../../platform/configuration-policy.md) (tiers) |
