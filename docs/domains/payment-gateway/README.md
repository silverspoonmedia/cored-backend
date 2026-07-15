# PaymentGateway — Midtrans, iPaymu, invoices, subscriptions

Core domain `App\Domain\PaymentGateway\`. Secrets in env/config, not DB. Polymorphic invoices → order/subscription. Idempotent webhooks via unique `(payment_gateway_id, external_transaction_id)`.

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

Order create → pending invoice with **`payment_gateway_id` null** until channel charge or hosted fallback assigns gateway.

| Surface | Channel mode | Snap/redirect fallback |
|---------|--------------|------------------------|
| **Vue** (via Gateway) | Channel picker + instructions | Yes |
| **Blade** | Channel only | **No** Snap UI — message when no channels |

`checkout_mode` = `channel` when active channels exist; else `snap_fallback` (Vue). Aggregate: Gateway `checkout-context` (downstream docs).

### Channel checkout

Logical channels (`qris`, `va_*`, e-wallets) + `payment_channel_routes` priority. Seed: `PaymentChannelSeeder` after gateways.

```
POST /api/v1/invoices/{id}/channel-checkout
{ "channel_code": "qris", "buyer": { … } }
```

Response: normalized instructions (VA numbers, QR, expiry, etc.). All routes fail → `snap_fallback_available: true` for Vue.

Services: `InvoiceChannelCheckoutService`, `PaymentChannelResolver`, initiation guards, active-checkout cancellation when switching channels.

## Blade / assets

Payment-first order/invoice show: hero, steps, `checkout-panel`, `checkout.js`. Web proxies: channel-checkout, sync-status, snap-token (API/Vue).

## Permissions & morph map

Permissions in RoleSeeder (`view invoice`, `initiate payment`, `manage payment gateway`, …). Morph map registers invoiceables (order, subscription).

## Scheduler / commands

`payments:*` artisan commands for billing/disbursement maintenance (see domain Console). Scheduler in `routes/console.php` / kernel as configured.

## Config

`config/payment.php` — Midtrans/iPaymu credentials from env. After secret edits: `php artisan config:rebuild`.

## Encryption

Sensitive invoice payloads, 2FA, etc.: [encryption.md](encryption.md).

## Three-project

HTTP contract changes → [changelog/pending](../../changelog/pending/). SPA/Gateway implementation in **`downstream sync session`** only.

## Tests

`composer test -- --filter=Payment` (and related Invoice/Channel filters).

## Related

- [playground](../playground/README.md)
- [platform/environment.md](../../platform/environment.md)
- [integration/spa-gateway.md](../../integration/spa-gateway.md)
