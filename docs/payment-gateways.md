# Payment gateways, billing, and invoices

Core domain **`PaymentGateway`** (`App\Domain\PaymentGateway\`) — ported from the boilerplate payment stack. Supports **Midtrans** and **iPaymu** with invoice-centric persistence, subscription billing, and Playground order integration.

**Related:** [domain-modules.md](./domain-modules.md) · [playground.md](./playground.md) · [environment.md](./environment.md) · Gateway [aggregate-endpoints.md](../../monolith-gateway/docs/aggregate-endpoints.md) · Frontend [payment-gateways.md](../../monolith-frontend/docs/payment-gateways.md)

---

## Goals

- Multiple gateways via `payment_gateways` rows + `config/payment.php` credentials.
- Secrets stay in **env/config**, not the database.
- Polymorphic **`invoices`** attach to Playground **`order`** or **`subscription`** morph types.
- Idempotent webhooks via unique `(payment_gateway_id, external_transaction_id)`.

---

## Domain layout

```
app/Domain/PaymentGateway/
├── Models/              PaymentGateway, Invoice, Subscription
├── Enums/               DisbursementStatus, SubscriptionStatus, BillingCycle
├── Services/
│   ├── Payment/Midtrans/   # vertical slice (Snap, Core API, webhooks)
│   ├── Payment/Ipaymu/   # vertical slice (redirect, direct, webhooks)
│   └── Billing/            # funds ledger + disbursement state machine
├── Http/Controllers/API/V1/
├── Repositories/
└── Console/Commands/    payments:*
```

Registry: [`config/domains.php`](../config/domains.php) — **core** (platform, non-removable).

---

## API routes (`/api/v1/*`)

Loaded from [`routes/api/v1/payment-gateway.php`](../routes/api/v1/payment-gateway.php):

| Area | Prefix | Auth |
|------|--------|------|
| Gateway registry | `GET/POST/PUT/DELETE /api/v1/payment-gateways` | `auth:api` + permissions |
| Invoices | `GET /api/v1/invoices`, `GET /api/v1/invoices/{id}` | `view invoice` |
| Subscriptions | `GET/POST/PUT/DELETE /api/v1/subscriptions` | `view/manage subscription` |
| Midtrans | `/api/v1/payments/providers/midtrans/*` | JWT except `webhook` |
| iPaymu | `/api/v1/payments/providers/ipaymu/*` | JWT except `webhook` |

### Webhooks (public)

Register in PSP dashboards:

```
{GATEWAY_PUBLIC_URL}/api/v1/payments/providers/midtrans/webhook
{GATEWAY_PUBLIC_URL}/api/v1/payments/providers/ipaymu/webhook
```

Gateway proxies to Monolith with `X-Gateway-Secret`. Signature verification happens in Monolith services.

Set `MIDTRANS_NOTIFY_URL` / `IPAYMU_NOTIFY_URL` in Monolith `.env` to the **Gateway** public URL (same path as above). Midtrans: copy into Dashboard → Payment Notification URL. iPaymu: also sent automatically on each redirect checkout.

### Playground orders

Required `payment_gateway_id` on order create (must reference an **active** gateway) creates a pending invoice atomically via `InvoiceAttachableService`. Order show API eager-loads `invoice.paymentGateway`.

**Paying an order:** Blade order/invoice show pages render a checkout panel that proxies to `POST /invoices/{id}/checkout/snap-token` → provider `snap/token` (Midtrans Snap or iPaymu redirect). Vue order/invoice show uses `PaymentCheckoutPanel` + Gateway `GET /api/v1/payments/checkout-context` (orders/subscriptions) or inline invoice data.

---

## Configuration

| Concern | Location |
|---------|----------|
| Credentials | `.env` → [`config/payment.php`](../config/payment.php) |
| Registry rows | `payment_gateways` table — [`PaymentGatewaySeeder`](../database/seeders/PaymentGatewaySeeder.php) |
| Sidebar navigation | [`MenuSeeder`](../database/seeders/MenuSeeder.php) — **Administration → Payments** (`payment-gateways.index`, `invoices.index`, `subscriptions.index`); gated to Root/Admin/Moderator/Management |
| Runtime config | `$gateway->gatewayConfig()` |

**Env vars:** `MIDTRANS_SERVER_KEY`, `MIDTRANS_CLIENT_KEY`, `MIDTRANS_IS_PRODUCTION`, optional `MIDTRANS_NOTIFY_URL` (paste into Midtrans Dashboard), `IPAYMU_VA_KEY`, `IPAYMU_API_KEY`, `IPAYMU_IS_PRODUCTION`, optional `IPAYMU_NOTIFY_URL` (sent as `unotify` per checkout).

Run `php artisan payments:webhook-urls` to print resolved webhook URLs from env or route defaults.

### iPaymu return / cancel URLs (browser redirect)

After hosted checkout, iPaymu redirects the payer to **`ureturn`** / **`ucancel`** URLs sent when checkout starts (`IpaymuPaymentService::createSnapToken`). Defaults:

```
{APP_URL}/payment/ipaymu/return
{APP_URL}/payment/ipaymu/cancel
```

- **`APP_URL`** in `.env` sets the host (e.g. `http://monolith.test` for Blade admin).
- Query parameters (`trx_id`, `status`, `sid`, …) are appended by iPaymu.
- Web routes [`payment-ipaymu-return.php`](../routes/web/payment-ipaymu-return.php) handle the landing page: optional `syncInvoiceStatus`, then redirect to the related order/invoice.
- Checkout may override via API `return_url` / `cancel_url` (order show passes order-specific URLs).
- **Return URL does not replace the webhook** (`unotify`); it is UX-only. Invoice status is authoritative from webhook + sync.

**Vue SPA:** pass frontend URLs such as `{SPA_ORIGIN}/payments/ipaymu/return?invoice_id=…&redirect=order&order=…` (see `PaymentCheckoutPanel`).

**Webhook troubleshooting:** if sandbox `send-notify` does not update invoices, check Monolith logs for `iPaymu webhook: invalid signature`. Gateway must forward the **raw** JSON body and `va` / `signature` / `X-Signature` headers (see Gateway `MonolithClient::forwardPaymentWebhookRaw`). QRIS sandbox may report `transaction_status_code: 7` (escrow); Monolith maps escrow to invoice `paid` while `withdrawable_at` stays null until iPaymu settlement (`Status === 1`).

**Composer:** `midtrans/midtrans-php`, `ipaymu/ipaymu-php-api` — run `composer install` after pull.

---

## Morph map

Registered in [`PaymentGatewayServiceProvider`](../app/Providers/Domain/PaymentGatewayServiceProvider.php):

| Alias | Model |
|-------|-------|
| `order` | `App\Domain\Playground\Models\Order` |
| `subscription` | `App\Domain\PaymentGateway\Models\Subscription` |

---

## Scheduler

[`routes/console.php`](../routes/console.php):

- `payments:midtrans-reconcile-pending` — every 5 minutes
- `payments:reconcile-withdrawable-funds` — every 10 minutes

Ops commands: `payments:audit-invoice-funds`, `payments:disbursement-{begin,complete,fail,reopen}`.

---

## Permissions

Seeded in [`RoleSeeder`](../database/seeders/RoleSeeder.php):

- `view payment gateway`, `manage payment gateway`
- `view invoice`, `initiate payment`
- `view subscription`, `manage subscription`

---

## Three-project wiring

| Layer | Role |
|-------|------|
| **Monolith** | Source of truth — all payment rules, persistence, webhooks |
| **Gateway** | Proxy + `GET /api/v1/payments/checkout-context` aggregate |
| **Frontend** | Admin screens, subscription CRUD, checkout panel on orders |

See [spa-gateway-integration.md](./spa-gateway-integration.md).

---

## Tests

```powershell
php artisan test --filter=PaymentGateway
php artisan test --testsuite=Domain
```

Signature verifiers, disbursement state machine, subscription invoice lifecycle, Midtrans webhook routing.
