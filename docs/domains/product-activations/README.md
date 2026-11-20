# Product Activations — runtime product access registry

Kernel registry (`packages/platform/src/Support/Product/`) that answers one question per request: **may this user use this product at all?** It stores a per-user `product_activations` row (`pending` / `active` / `suspended` / `expired`) per product and exposes the `product.active` middleware, an admin surface, and per-product analytics. It exists because the module registry is static config, the RBAC `domain_kind` is a catalog tag, and paid entitlements only covered paid SKUs — a free Archivd user had no row anywhere, so "logged in = can use" was an accident of config fallback. Code: `packages/platform/src/Support/Product/` (the two analytics services stay host-side in `app/Support/Product/Services/`). Invariants: [.cursor/rules/product-activations.mdc](../../../.cursor/rules/product-activations.mdc).

| Need | Path |
|------|------|
| Code | `packages/platform/src/Support/Product/` + host `app/Support/Product/Services/` — `ProductActivationService`, `ProductRegistry`, `ProductAnalyticsService`, `ProductInsightService` |
| Config | [`config/products.php`](../../../config/products.php) (`activation_type`, `fulfillment_target`, `menu_routes`) |
| Table | `product_activations` · `product_analytics` (bucketed aggregates) |
| Middleware | `product.active:{Product}` |
| API | [`packages/platform/routes/kernel-product-activations.php`](../../../packages/platform/routes/kernel-product-activations.php) — `/api/v1/product-activations` + `/api/v1/admin/product-activations` (kernel-owned, mounted by `DomainManager::packageApiRoutePaths()`) |
| SPA | Vue Router `product-activations.index` / `product-activations.show` under `/apps/product-activations` |
| Commands | `php artisan product:recompute-analytics [--product=Archivd]` |
| Realtime | `product-activations` private channel — `.product.activation.changed` |
| Tests | `tests/Domain/Product/` (`composer test -- --filter=Product`) |
| Pakem | [.cursor/rules/product-activations.mdc](../../../.cursor/rules/product-activations.mdc) |
| Deep dive | [reference.md](reference.md) — state machine, access flow, API tables, analytics, menu visibility, test matrix |
| Related | [service](../service/README.md) (paid entitlement) · [identity](../identity/README.md) (register hook) · [navigation](../navigation/README.md) (menu gate) · [domain-access](../domain-access/README.md) (public/private axis) |
