# PaymentGateway — Midtrans, iPaymu, invoices, subscriptions

Core domain `App\Domain\PaymentGateway\`. Secrets in env/config, not DB. Polymorphic invoices → **service_purchase** (primary product sell path) / PG `subscription` demo morph (legacy morph `order` still mapped for historical rows). Idempotent webhooks via unique `(payment_gateway_id, external_transaction_id)`.

**Product SaaS subscriptions** (periods, grace, File Hosting quota) live in the [Service](../service/README.md) domain (`ServiceEntitlement`) — **not** the PaymentGateway `Subscription` model.

## Layout

```
Models/     PaymentGateway, Invoice, Subscription, PaymentChannel
Services/   Payment/* (channel checkout, Midtrans, iPaymu), Billing/*
Http/       API V1 + Web proxies
Console/    payments:*
```

Registry: **core** in `config/domains.php`.

## API surface (`/api/v1/*`)

| Area | Prefix | Auth |
|------|--------|------|
| Gateways | `/payment-gateways` | manage/view permissions |
| Invoices | `/invoices`, channel-checkout | view invoice / initiate payment |
| Channels | `/payment-channels`, routes | view / manage |
| Subscriptions | `/subscriptions` | view/manage subscription |
| Midtrans / iPaymu | `/payments/providers/{midtrans\|ipaymu}/*` | JWT except webhooks |

### Webhooks

Public on Gateway URL; Gateway proxies to Monolith with secret. Verify signatures in Monolith. Set `MIDTRANS_NOTIFY_URL` / `IPAYMU_NOTIFY_URL` to **Gateway** public paths.

## Checkout model

Primary sell path: [Service](../service/README.md) checkout → `ServicePurchase` → pending invoice (**currency IDR**, FX snapshot on purchase when catalog price is non-IDR) with **`payment_gateway_id` null** until channel charge or hosted fallback assigns gateway.

**IDR amounts:** Service FX settlement rounds to **whole rupiah** (`ExchangeRateService`). Invoice `subtotal` / initial `customer_gross_total` are whole IDR before channel fee.

### iPaymu monetary reconcile

On webhook / `checkTransaction` sync (`IpaymuInvoiceSynchronizer`):

| Provider field | Invoice field | Role |
|----------------|---------------|------|
| `sub_total` / `SubTotal` / `paid_off` | must match `subtotal` (epsilon **1 IDR**) | Merchant sale (cart) — source of truth stays on invoice |
| `fee` | `payment_channel_fee` | Channel fee charged by iPaymu |
| `amount` / `total` | `customer_gross_total` | What customer paid (`sub + fee`) |

Mismatch beyond epsilon → reject sync (no paid status / no fulfillment). Fee + gross are updated from provider when SubTotal validates.

**Charge amount:** iPaymu direct/cart must send **merchant sale** (`invoice.subtotal`) only. Sending `customer_gross_total` (sale + prior fee) makes iPaymu treat that as a new SubTotal and add fee again — webhook then fails SubTotal match. Charge response may update fee/gross; before a new charge, fee is reset to 0 and gross = subtotal.

Legacy Playground Order → invoice path is **retired** from public routes; synchronizers still tolerate morph `order` if old rows exist.

| Surface | Channel mode | Snap/redirect fallback |
|---------|--------------|------------------------|
| **Vue** (via Gateway) | Channel picker + instructions | Yes |
| **Blade** | Channel only | **No** Snap UI — message when no channels |

`checkout_mode` = `channel` when active channels exist; else `snap_fallback` (Vue). Aggregate: Gateway `checkout-context` (downstream docs).

### Channel checkout

Logical channels (`qris`, `va_*`, e-wallets) + `payment_channel_routes` priority. Seed: `PaymentChannelSeeder` after gateways.

```
POST /api/v1/invoices/{id}/channel-checkout
{ "channel_code": "qris", "buyer": { … } }   // buyer optional
```

Response: normalized instructions (VA numbers, QR, expiry, etc.). All routes fail → `snap_fallback_available: true` for Vue.

**Buyer / customer autofill** (`InvoiceCustomerDetailsResolver`):

| Priority | Source |
|----------|--------|
| 1 | Request `buyer` / `customer_details` (partial merge) |
| 2 | Invoiceable: Order `customer_*`, Subscription `subscriber_*`, ServicePurchase → `user.name` / `user.email` |
| 3 | Authenticated user profile |
| 4 | Phone: request → invoiceable → `config('payment.default_buyer_phone')` (User has no phone column yet) |

iPaymu **requires** name + email + phone (`setBuyer`). Midtrans `customer_details` optional but always sent when resolvable (helps VA / Akulaku). Blade `checkout.js` does not send `buyer` — server fills from profile.

Services: `InvoiceChannelCheckoutService`, `PaymentChannelResolver`, initiation guards, active-checkout cancellation when switching channels.

## Blade / assets

Payment-first order/invoice show: hero, steps, `checkout-panel`, `checkout.js`. Web proxies: channel-checkout, sync-status, snap-token (API/Vue).

## Permissions & morph map

Permissions in RoleSeeder (`view invoice`, `initiate payment`, `manage payment gateway`, …). Morph map registers invoiceables (`service_purchase`, `subscription`, legacy `order`).

## Scheduler / commands

`payments:*` artisan commands for billing/disbursement maintenance (see domain Console). Scheduler in `routes/console.php` / kernel as configured.

## Config

`config/payment.php` — Midtrans/iPaymu credentials from env; `default_buyer_phone` (`PAYMENT_DEFAULT_BUYER_PHONE`) for iPaymu when profile has no phone. After secret edits: `php artisan config:rebuild`.

## Encryption

Sensitive invoice payloads, 2FA, etc.: [encryption.md](encryption.md).

## Three-project

HTTP contract changes → [changelog/pending](../../changelog/pending/). SPA/Gateway implementation in **`downstream sync session`** only.

## Tests

`composer test -- --filter=Payment` (and related Invoice/Channel filters).

## Related

- [service](../service/README.md) — catalog + FX + service_purchase invoiceable
- [playground](../playground/README.md) — orders sell path retired
- [platform/environment.md](../../platform/environment.md)
- [integration/spa-gateway.md](../../integration/spa-gateway.md)
