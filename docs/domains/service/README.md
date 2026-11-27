# Service — catalog, entitlements, FX checkout

Core platform domain for the SaaS services this project sells (subscriptions and one-time SKUs): the sellable catalog with per-currency list prices, the checkout that quotes credit for a plan change and settles to **IDR**, the entitlement lifecycle (active → grace → expired with quantity for stackable addons), and the fulfillment that pushes quota into product domains through the kernel `FulfillmentRegistry` (the package never imports a product). It is the primary invoiceable source for payment checkout (`service_purchase` morph). Product SaaS lifecycles live here — **not** in the PaymentGateway `Subscription` model, which is a free-text demo morph. Code: `vendor/silverspoonmedia/billing/src/Service/` (`App\Domain\Service\`); it ships inside `silverspoonmedia/billing` together with [payment-gateway](../payment-gateway/README.md) ([core-packages.md](../../integration/core-packages.md)). Invariants: [.cursor/rules/service.mdc](../../../.cursor/rules/service.mdc).

| Need | Path |
|------|------|
| Code | `vendor/silverspoonmedia/billing/src/Service/` — `MainPlanChangeCalculator`, `ServiceFulfillmentService`, `ServiceEntitlementService`, `Support/BillingPeriod`, `Support/ServiceCatalogWriter` (FX lives in [currency](../currency/README.md): `App\Domain\Currency\Services\ExchangeRateService`) |
| Config | [`vendor/silverspoonmedia/billing/config/service.php`](../../../vendor/silverspoonmedia/billing/config/service.php) (`grace_days`, `settlement_currency`) · Settings → Platform (`platform.service.*`, `platform.frankfurter.*`) |
| Tables | `services`, `service_prices`, `service_purchases`, `service_entitlements`, `service_addon_renewals` (`currencies` + `exchange_rate_snapshots` belong to [currency](../currency/README.md)) |
| Seeders | `vendor/silverspoonmedia/billing/src/Service/Database/Seeders/ServiceCatalogSeeder` · `vendor/silverspoonmedia/billing/src/Currency/Database/Seeders/CurrencySeeder` · `vendor/silverspoonmedia/billing/src/PaymentGateway/Database/Seeders/{PaymentGateway,PaymentChannel}Seeder` (package-generic) · product SKUs by a product's own catalog seeder through `ServiceCatalogWriter` |
| API | [`vendor/silverspoonmedia/billing/routes/service.php`](../../../vendor/silverspoonmedia/billing/routes/service.php) — `/api/v1/services`, `/api/v1/service-purchases` |
| Commands | `service:process-entitlements` · `service:process-addon-renewals` (both hourly) |
| SPA | Vue Router `services.*`, `service-purchases.show`, `currencies.*` |
| Realtime | `services.catalog` (permission `view service`) — the currency channel belongs to [currency](../currency/README.md) |
| Tests | The billing suite runs from a clone of `silverspoonmedia/billing` (`composer install` then `composer test`); `tests/` is `export-ignore`d, so nothing lands in `vendor/`. This host wires no `tests/Domain/**` and ships no product scenario |
| Pakem | [.cursor/rules/service.mdc](../../../.cursor/rules/service.mdc) |
| Deep dive | [reference.md](reference.md) — data model, catalog, plan-change math, addon anniversary, FX, lifecycle, API, config, manual verify |
| Related | [currency](../currency/README.md) (catalogue + FX) · [payment-gateway](../payment-gateway/README.md) (settle IDR) · [notification](../notification/README.md) (grace/expired types) · [product-activations](../product-activations/README.md) (fulfillment hook) |
