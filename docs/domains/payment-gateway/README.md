# PaymentGateway — Midtrans, iPaymu, invoices, channels

Core domain that turns a Service purchase into money in the bank: it owns payment gateways, polymorphic invoices, the payment-channel catalog and its routing policy, buyer-borne channel fees, and the checkout surfaces the SPA renders. Invoices morph to `service_purchase` (the primary product sell path) or the PG `subscription` demo morph, and every webhook is idempotent on `(payment_gateway_id, external_transaction_id)`. Product SaaS subscriptions (periods, grace, Archivd quota) live in [service](../service/README.md) (`ServiceEntitlement`) — **not** the PG `Subscription` model. Code: `App\Domain\PaymentGateway\`. Invariants: [.cursor/rules/payment-gateway.mdc](../../../.cursor/rules/payment-gateway.mdc).

| Need | Path |
|------|------|
| Code | `app/Domain/PaymentGateway/` — `Payment/*`, `Support/Routing/*`, `InvoiceChannelCheckoutService`, `InvoiceWebDestination` |
| Config | [`config/payment.php`](../../../config/payment.php) (Tier A credentials) · [`config/payment-routing.php`](../../../config/payment-routing.php) → `Routing_Price.yaml` |
| Tables | `invoices`, `payment_gateways`, `payment_channels`, `payment_channel_routes`, `payment_channel_route_snapshots` |
| API | [`routes/api/v1/payment-gateway.php`](../../../routes/api/v1/payment-gateway.php) — `/api/v1/payment-gateways`, `/invoices`, `/payment-channels`, `/subscriptions`, `/payments/*` |
| Web route | [`routes/web/payment-ipaymu-return.php`](../../../routes/web/payment-ipaymu-return.php) — iPaymu hosted return/cancel |
| Commands | `php artisan payments:sync-routing-policy [--dry-run]` · `payments:disbursement-*` |
| SPA | Vue Router `payments.income.index`, `invoices.*`, `payment-channels.index`, `payment-gateways.*`, `subscriptions.*`, `service-purchases.show` |
| Realtime | `payments.invoice.{id}` · `payments.routing` · `payments.admin` |
| Tests | `tests/Domain/PaymentGateway/` (`composer test -- --filter=Payment`) |
| Pakem | [.cursor/rules/payment-gateway.mdc](../../../.cursor/rules/payment-gateway.mdc) |
| Deep dive | [reference.md](reference.md) — API surface, checkout model, routing YAML contract, route snapshot, VA catalog, admin UI, realtime |
| Related | [service](../service/README.md) (purchase + FX) · [notification](../notification/README.md) (shared Echo, separate bus) · [envelope-encryption.md](../../platform/envelope-encryption.md) (stored credentials) · [platform/environment.md](../../platform/environment.md) |
