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
├── Models/              PaymentGateway, Invoice, Subscription, PaymentChannel
├── Enums/               DisbursementStatus, SubscriptionStatus, BillingCycle, PaymentChannelKind
├── Services/
│   ├── Payment/InvoicePaymentInitiationGuard.php  # lock + PSP sync before snap/charge
│   ├── Payment/InvoiceChannelCheckoutService.php  # channel direct checkout orchestrator
│   ├── Payment/PaymentChannelResolver.php         # route logical channel → gateway
│   ├── Payment/PaymentInstructionNormalizer.php   # PSP JSON → UI instructions DTO
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
| Channel checkout | `POST /api/v1/invoices/{id}/channel-checkout` | `initiate payment` |
| Payment channels | `GET /api/v1/payment-channels`, `GET/PUT /api/v1/payment-channels/{code}` | `view invoice` / `manage payment gateway` (PUT) |
| Payment channel routes | `PUT /api/v1/payment-channel-routes/{id}` | `manage payment gateway` |
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

Order create **does not** require `payment_gateway_id`. Monolith creates a pending invoice with **`payment_gateway_id` null** — no PSP is chosen until channel checkout succeeds or the user starts hosted Snap/redirect fallback (`PaymentGateway::defaultForInvoice()` is applied at Snap time only).

Order show API eager-loads `invoice.paymentGateway`.

**Paying an order:** Vue `PaymentCheckoutPanel` loads Gateway `GET /api/v1/payments/checkout-context` (order/subscription + channels). When `checkout_mode` is `channel`, the user picks a logical channel (QRIS, VA, e-wallet) and Monolith returns normalized payment instructions via `POST /api/v1/invoices/{id}/channel-checkout`. When no channels are available at load time, the panel falls back to hosted Snap (Midtrans) or redirect (iPaymu). When channel checkout fails on **all** configured routes, the API returns `422` with `data.snap_fallback_available: true` and the UI offers hosted checkout.

**Blade admin** (`orders.show`, `invoices.show`) uses a payment-first layout: status hero, 3-step hint, channel checkout panel with kind icons, and a financial sidebar. Same channel-first flow via [`checkout-panel.blade.php`](../resources/views/adminlte4/components/payments/checkout-panel.blade.php) + [`checkout.js`](../resources/js/payments/checkout.js). Styles: [`order-invoice-show.css`](../resources/css/payments/order-invoice-show.css). Web proxies:

| Web route | API |
|-----------|-----|
| `POST /invoices/{id}/checkout/channel` | `POST /api/v1/invoices/{id}/channel-checkout` |
| `POST /invoices/{id}/checkout/sync-status` | Provider `sync-status` |
| `POST /invoices/{id}/checkout/snap-token` | Snap/redirect fallback |

### Payment channel direct checkout

Logical channels are stored in **`payment_channels`** with per-gateway routes in **`payment_channel_routes`**. Seeded by [`PaymentChannelSeeder`](../database/seeders/PaymentChannelSeeder.php) after [`PaymentGatewaySeeder`](../database/seeders/PaymentGatewaySeeder.php).

| Channel code | Kind | Default routing (priority) |
|--------------|------|----------------------------|
| `qris` | qris | iPaymu `qris` → Midtrans Core API `qris` |
| `va_bca`, `va_bni`, `va_mandiri` | va | Midtrans `bank_transfer` → iPaymu `va` |
| `gopay`, `shopeepay`, `ovo` | ewallet | Midtrans `gopay` / `shopeepay` / `ovo` → iPaymu `qris` sub-channel |

Enable/disable per channel: `PUT /api/v1/payment-channels/{code}` with `{ "is_active": false }` (`manage payment gateway`). Admin list (including disabled channels and nested routes): `GET /api/v1/payment-channels?scope=admin`. Checkout list omits inactive channels from default `GET /api/v1/payment-channels`.

**Per-route admin** (toggle / priority on seeded bindings only — no route CRUD):

```
PUT /api/v1/payment-channel-routes/{route_id}
{ "is_active": false }
{ "priority": 10 }
```

Blade `/payment-channels` and Vue `/payment-channels` show nested route rows. Web proxy: `PUT /payment-channel-routes/{id}`.

**Charge-time routing:** [`InvoiceChannelCheckoutService`](../app/Domain/PaymentGateway/Services/Payment/InvoiceChannelCheckoutService.php) iterates `PaymentChannelResolver::listViableRoutes()` in priority order until a PSP charge succeeds. Gateway reassignment on the invoice happens **after** a successful charge, only when `external_transaction_id` is still null.

**Snap escape hatch:** when every route fails (or none are viable), channel-checkout error payload includes:

```json
{ "status": "error", "message": "...", "data": { "snap_fallback_available": true, "failed_routes": 2 } }
```

**Channel checkout request:**

```json
POST /api/v1/invoices/{id}/channel-checkout
{ "channel_code": "qris", "buyer": { "name": "...", "email": "...", "phone": "..." } }
```

`buyer` is optional when the invoice morph provides customer fields (order/subscription).

**Normalized response (`instructions` by kind):**

| `instructions.type` | Fields |
|-----------------------|--------|
| `qris` | `qr_string`, `qr_url` (URLs or `data:image/png;base64,...` from iPaymu `QrImage`) |
| `va` | `bank_name`, `va_number`, `payment_name` |
| `ewallet` | `deeplink_url`, `qr_url`, `qr_string` |

Provider-specific charge APIs (`POST .../transactions/charge`) remain available for programmatic use. Channel checkout uses [`InvoiceChannelCheckoutService`](../app/Domain/PaymentGateway/Services/Payment/InvoiceChannelCheckoutService.php) + [`InvoicePaymentInitiationGuard`](../app/Domain/PaymentGateway/Services/Payment/InvoicePaymentInitiationGuard.php) (same pre-payment sync rules as snap/charge).

### Pre-payment external verification

Before `createSnapToken` or `charge`, [`InvoicePaymentInitiationGuard`](../app/Domain/PaymentGateway/Services/Payment/InvoicePaymentInitiationGuard.php) runs inside a **row lock** (`lockForUpdate`) and polls the PSP so local `invoices.status` cannot drift behind a successful payment when webhooks are delayed or missing.

| Gateway | External check | Behaviour |
|---------|----------------|-----------|
| **Midtrans** | `Transaction::status(invoice_number)` on every initiation | Sync via `MidtransInvoiceSynchronizer`; **404 / transaction not found** → proceed (no PSP payment yet); any other API error → **503** (`external_sync_failed`) and checkout is blocked |
| **iPaymu** | `checkTransaction(external_transaction_id)` when the invoice already has an external id | First checkout (no external id) uses internal status only; if PSP reports **pending** while local is still payable → **422** (`active_payment_exists`); successful PSP status syncs to local before the payable check |

Rules:

- Sync updates are **committed** before the payable assertion, so discovering `paid` at the PSP persists even when a new checkout is rejected (`invoice_not_payable`).
- **Strict policy:** when an external sync is attempted and the PSP API fails (timeout, 5xx, misconfiguration), initiation returns **503** — no fallback to internal-only checks.
- iPaymu **does not overwrite** `external_transaction_id` on a second redirect/charge when one is already stored.
- Cron reconcile commands (`payments:midtrans-reconcile-pending`, `payments:reconcile-withdrawable-funds`) remain **backup** paths; they do not replace the pre-payment guard.

### Checkout session lifecycle

An invoice is considered to have an **active checkout session** when any of these are set:

- `external_transaction_id`
- `payment_channel_code`
- non-empty `gateway_response_payload` (Snap token or redirect response stored locally)

[`InvoiceActiveCheckoutCancellationService`](../app/Domain/PaymentGateway/Services/Payment/InvoiceActiveCheckoutCancellationService.php) abandons that session before:

- **Channel checkout** (`prepareForChannelCheckout` / `abandonActiveCheckout`)
- **Hosted Snap/redirect** (`createSnapToken` on Midtrans and iPaymu)
- **Order edit** when `grand_total` changes on a pending invoice that still has a session (`OrderService::update`)

Abandon behaviour:

| Gateway | Cancel API | On abandon |
|---------|------------|------------|
| **Midtrans** | `cancelTransaction(invoice_number)` when external id **or** Snap payload exists | Best-effort cancel, then clear checkout fields (`external_transaction_id`, `payment_channel_code`, gateway payloads, `payment_gateway_id`) |
| **iPaymu** | No reliable cancel API | Sync via guard; if PSP still reports **pending** → **422** `active_session_pending` (user must wait for PSP expiry) |

**Known limitations (documented, not fixed in this pass):**

- **iPaymu pending session** — cannot be cancelled via API; channel switch or order amount change may return 422 until the PSP session expires.
- **Concurrent browser tabs** — row lock is per request; two tabs can race before abandon completes.
- **Webhook during channel switch** — sync may mark paid while UI still shows old instructions; polling/reload mitigates partially.
- **Midtrans cancel failure** — abandon still clears local fields; an orphan transaction may remain at the PSP until reconcile/expire.
- **Pre-migration invoices** may still have a default gateway row; new orders use `payment_gateway_id` null until checkout.
- **Subscription API** still accepts optional `payment_gateway_id` on create (Vue UI no longer sends it).

**Order edit:** amount sync runs after abandon. If only non-monetary fields change (e.g. notes), checkout is **not** cancelled.

---

## Configuration

| Concern | Location |
|---------|----------|
| Credentials | `.env` → [`config/payment.php`](../config/payment.php) |
| Registry rows | `payment_gateways` table — [`PaymentGatewaySeeder`](../database/seeders/PaymentGatewaySeeder.php) |
| Sidebar navigation | [`MenuSeeder`](../database/seeders/MenuSeeder.php) — **Administration → Payments** (`payment-channels.index`, `payment-gateways.index`, `invoices.index`, `subscriptions.index`); gated to Root/Admin/Moderator/Management |
| Runtime config | `$gateway->gatewayConfig()` |

**Env vars:** `MIDTRANS_SERVER_KEY`, `MIDTRANS_CLIENT_KEY`, `MIDTRANS_IS_PRODUCTION`, optional `MIDTRANS_NOTIFY_URL` (paste into Midtrans Dashboard), `IPAYMU_VA_KEY`, `IPAYMU_API_KEY`, `IPAYMU_IS_PRODUCTION`, optional `IPAYMU_NOTIFY_URL` (sent as `unotify` per checkout). `PAYMENT_PREFER_CHANNEL_CHECKOUT` in [environment.md](./environment.md) is reserved for future use; checkout mode is derived from available channels at load time.

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
| **Gateway** | Proxy + `GET /api/v1/payments/checkout-context` aggregate (channels + `checkout_mode`) |
| **Frontend** | Channel picker, payment instructions panel, Snap/redirect fallback |

See [spa-gateway-integration.md](./spa-gateway-integration.md).

---

## Tests

```powershell
php artisan test --filter=PaymentGateway
php artisan test --testsuite=Domain
```

Signature verifiers, disbursement state machine, subscription invoice lifecycle, Midtrans webhook routing, **invoice payment initiation guard** (pre-checkout PSP sync), **payment channel resolver / channel checkout**.
