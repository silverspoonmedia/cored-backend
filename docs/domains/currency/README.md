# Currency — catalogue and FX rates

Core platform domain for the platform's money vocabulary: the ISO-4217 catalogue with one settlement flag, and the persisted FX rate pipeline that converts a display amount into the settlement amount. It exists because every price the platform renders or stores has to name its currency and be convertible without a deploy. Code: `packages/billing/src/Currency/` (`App\Domain\Currency\`); it ships inside `silverspoonmedia/billing` together with [service](../service/README.md) and [payment-gateway](../payment-gateway/README.md) ([core-packages.md](../../integration/core-packages.md)). Invariants: [.cursor/rules/currency.mdc](../../../.cursor/rules/currency.mdc).

The catalogue is admin-editable — activate a currency, move the settlement flag; rates are never edited by hand. A conversion asks `ExchangeRateService`, which reads a fresh snapshot, then the forever cache, then Frankfurter, and persists every fetched rate to `exchange_rate_snapshots` so a later audit can prove which rate a purchase used.

| Need | Path |
|------|------|
| Code | `packages/billing/src/Currency/` — `Models/{Currency,ExchangeRateSnapshot}`, `Services/ExchangeRateService` (Frankfurter client, `SETTLEMENT_CURRENCY = 'IDR'`), `Services/CurrencyAdminService`, `Events/CurrencyChanged` |
| Config | [`config/services.php`](../../../config/services.php) `frankfurter.base_url` · Settings → Platform `platform.frankfurter.timeout` / `platform.frankfurter.cache_ttl` |
| Env | `FRANKFURTER_BASE_URL` (no API key — Frankfurter is keyless) |
| Tables | `currencies`, `exchange_rate_snapshots` (`packages/billing/database/migrations`) |
| Seeders | `packages/billing/src/Currency/Database/Seeders/CurrencySeeder` (baseline; exactly one `is_settlement`) |
| API | [`packages/billing/routes/currency.php`](../../../packages/billing/routes/currency.php) — `GET /api/v1/currencies` (`view settings`), `PUT /api/v1/currencies/{code}` (`manage settings`) |
| SPA | Vue Router `currencies.index`; Settings → Currencies card |
| Realtime | private `settings.currencies` — `.service.currency.changed` |
| Translations | `packages/billing/resources/lang/*/api/currencies.php` |
| Tests | `packages/billing/tests/Currency/` (`composer test -- --filter=Currency`) |
| Pakem | [.cursor/rules/currency.mdc](../../../.cursor/rules/currency.mdc) (domain) · [.cursor/rules/cache-policy.mdc](../../../.cursor/rules/cache-policy.mdc) (FX key) |
| Related | [service](../service/README.md) (prices and settles in it) · [payment-gateway](../payment-gateway/README.md) (collects in the settlement currency) · [platform/cache-policy.md](../../platform/cache-policy.md) |
