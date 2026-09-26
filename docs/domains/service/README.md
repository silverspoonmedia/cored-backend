# Service — catalog, entitlements, FX checkout

Core platform domain for the SaaS services this project sells (subscriptions and one-time SKUs): the sellable catalog with per-currency list prices, the checkout that quotes credit for a plan change and settles to **IDR**, the entitlement lifecycle (active → grace → expired with quantity for stackable addons), and the fulfillment that pushes quota into product domains. It is the primary invoiceable source for payment checkout (`service_purchase` morph). Product SaaS lifecycles live here — **not** in the PaymentGateway `Subscription` model, which is a free-text demo morph. Code: `app/Domain/Service/`. Invariants: [.cursor/rules/service.mdc](../../../.cursor/rules/service.mdc).

| Need | Path |
|------|------|
| Code | `app/Domain/Service/` — `MainPlanChangeCalculator`, `ExchangeRateService`, `ServiceFulfillmentService`, `ArchivdQuotaRecomputeService`, `ArchivdCatalog` |
| Config | [`config/service.php`](../../../config/service.php) (`grace_days`, `settlement_currency`) · Settings → Platform (`platform.service.*`, `platform.frankfurter.*`) |
| Tables | `services`, `service_prices`, `service_purchases`, `service_entitlements`, `service_addon_renewals`, `currencies`, `exchange_rate_snapshots` |
| Seeders | `CurrencySeeder` → `ServiceCatalogSeeder` |
| API | [`routes/api/v1/service.php`](../../../routes/api/v1/service.php) — `/api/v1/services`, `/api/v1/service-purchases` |
| Commands | `service:process-entitlements` · `service:process-addon-renewals` (both hourly) |
| SPA | Vue Router `services.*`, `service-purchases.show`, `currencies.*` |
| Realtime | `services.catalog` (permission `view service`) · `settings.currencies` (permission `view settings`) |
| Tests | `tests/Domain/Service/` (`composer test -- --filter=Service`) |
| Pakem | [.cursor/rules/service.mdc](../../../.cursor/rules/service.mdc) |
| Deep dive | [reference.md](reference.md) — data model, catalog, plan-change math, addon anniversary, FX, lifecycle, API, config, manual verify |
| Related | [payment-gateway](../payment-gateway/README.md) (settle IDR) · [archivd](../archivd/README.md) (quota + purge) · [notification](../notification/README.md) (grace/expired types) · [product-activations](../product-activations/README.md) (fulfillment hook) |
