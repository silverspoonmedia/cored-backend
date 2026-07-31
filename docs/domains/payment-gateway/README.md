# PaymentGateway — Midtrans, iPaymu, invoices, subscriptions

Core domain `App\Domain\PaymentGateway\`. Secrets in env/config, not DB. Polymorphic invoices → **service_purchase** (primary product sell path) / PG `subscription` demo morph. Idempotent webhooks via unique `(payment_gateway_id, external_transaction_id)`.

**Product SaaS subscriptions** (periods, grace, File Hosting quota) live in the [Service](../service/README.md) domain (`ServiceEntitlement`) — **not** the PaymentGateway `Subscription` model.

## Layout

```
Models/     PaymentGateway, Invoice, Subscription, PaymentChannel
Services/   Payment/* (channel checkout, Midtrans, iPaymu), Billing/*
Events/     InvoiceStatusChanged (ShouldBroadcastNow)
Support/    PaymentRealtime, InvoiceChannelAuthorizer, Routing/* (policy loader, materializer), DataTable/*
Http/       API V1 + Web proxies
Console/    payments:*
```

Registry: **`kind => core`** in [`config/domains.php`](../../../config/domains.php); listed in `DomainManager::PLATFORM_CORE`.

## API surface (`/api/v1/*`)

Relative prefixes below mount under **`/api/v1`** (not the Blade `/apps` shell).

| Area | API prefix | Auth |
|------|------------|------|
| Gateways | `/payment-gateways` | manage/view permissions |
| Invoices | `/invoices`, channel-checkout | view invoice / initiate payment |
| Channels | `/payment-channels`, routes | view / manage |
| Subscriptions | `/subscriptions` | view/manage subscription |
| Midtrans / iPaymu | `/payments/providers/{midtrans\|ipaymu}/*` | JWT except webhooks |

### Webhooks

Provider notify URLs should hit Monolith public webhook routes (or a trusted reverse proxy that forwards to them). Verify signatures in Monolith. Set `MIDTRANS_NOTIFY_URL` / `IPAYMU_NOTIFY_URL` to the public paths providers can reach.

## Checkout model

Primary sell path: [Service](../service/README.md) checkout → `ServicePurchase` → pending invoice (**currency IDR**, FX snapshot on purchase when catalog price is non-IDR) with **`payment_gateway_id` null** until channel charge or hosted fallback assigns gateway.

**IDR amounts:** Service FX settlement rounds to **whole rupiah** (`ExchangeRateService`). Invoice `subtotal` / initial `customer_gross_total` are whole IDR before channel fee.

**API display companions:** `InvoiceResource` / `SubscriptionResource` keep raw money + ISO timestamps and add `*_formatted` (money with currency code) and `*_formatted` / `*_human` (dates). Blade and DataTables bind companions only — see [platform/crud presentation](../../platform/crud.md#api-resource-presentation-dual-fields).

### iPaymu monetary reconcile

On webhook / `checkTransaction` sync (`IpaymuInvoiceSynchronizer`):

| Provider field | Invoice field | Role |
|----------------|---------------|------|
| `sub_total` / `SubTotal` / `paid_off` | must match `subtotal` (epsilon **1 IDR**) | Merchant sale (cart) — source of truth stays on invoice |
| `fee` | `payment_channel_fee` | Channel fee charged by iPaymu |
| `amount` / `total` | `customer_gross_total` | What customer paid (`sub + fee`) |

Mismatch beyond epsilon → reject sync (no paid status / no fulfillment). Fee + gross are updated from provider when SubTotal validates.

**Charge amount:** iPaymu direct/cart must send **merchant sale** (`invoice.subtotal`) only. Sending `customer_gross_total` (sale + prior fee) makes iPaymu treat that as a new SubTotal and add fee again — webhook then fails SubTotal match. Charge response may update fee/gross; before a new charge, fee is reset to 0 and gross = subtotal.

### Buyer-borne channel fees

Policy default: `PAYMENT_CHANNEL_FEE_BEARER=buyer` (`config/payment.php`). YAML route `fee_*` is the **buyer surcharge** shown at checkout and stored on the invoice. Checkout UI shows the **percentage basis** (e.g. `0.7% of service price`) when the route fee is percent-based, so buyers see why the fee amount changes with subtotal.

| Field | Bookkeeping role |
|-------|------------------|
| `subtotal` | Merchant sale (service price) — unchanged after invoice creation |
| `payment_channel_fee` | Buyer-paid channel surcharge |
| `customer_gross_total` | Cash collected from customer (`subtotal + fee`) |

| Gateway | Fee application |
|---------|-----------------|
| **Midtrans** | `InvoiceChannelFeeService` sets fee + gross from policy estimate **before** charge (`gross_amount` = `customer_gross_total`) |
| **iPaymu** | Charge sends `subtotal` only; provider returns actual fee + gross (webhook reconciles) |

Checkout UI shows subtotal / payment fee / total due. `GET /api/v1/payment-channels?subtotal=` returns per-channel estimates. Abandoning checkout resets fee/gross to pre-checkout values.

**Reconciliation:** invoice list shows subtotal + fee + gross; `GET /api/v1/invoices/export` (and `/apps/invoices/export`) streams CSV for bookkeeping.

| Surface | Channel mode | Snap/redirect fallback |
|---------|--------------|------------------------|
| **API** | Channel picker + instructions | Yes (`snap_fallback` when no active channels) |
| **Blade** | Channel only | **No** Snap UI — message when no channels |

`checkout_mode` = `channel` when active channels exist; else `snap_fallback` (API clients may use hosted Snap; Blade stays channel-only).

### Channel checkout

Logical channels (`qris`, `va_*`, e-wallets) are **materialized from** [Routing_Price.yaml](Routing_Price.yaml) into `payment_channels` + `payment_channel_routes` (fee, settlement tier, route role). Sync: `php artisan payments:sync-routing-policy` (also runs via `PaymentChannelSeeder` after gateways).

**Policy highlights (see [Routing_Rules.md](Routing_Rules.md)):**

| Channel | Routing |
|---------|---------|
| `qris` | Midtrans primary (0.7% VAT-incl, H+2) → iPaymu H+2 fallback; never `ipaymu_h0` |
| `va_*` | iPaymu H+4 primary → Midtrans H+2 fallback; per-bank overrides (SeaBank/Saqu → Midtrans only; Artha Graha/Muamalat → iPaymu only) |
| E-wallets (`gopay`, `ovo`, `dana`, `shopeepay`) | Midtrans direct only; `cheap_alt_code=qris` is a **checkout UX hint** only |
| `linkaja` | QRIS proxy (`routing_type=qris_proxy`) — charges via QRIS routes |

`PaymentChannelResolver` orders viable routes by `route_role` (primary/only before fallback) then `priority`, skips `avoid` tags, and checks gateway credentials. Successful checkout stores `payment_channel_route_id` on the invoice.

### Route schema (`payment_channel_routes`)

| Column | Role |
|--------|------|
| `route_role` | `only`, `primary`, or `fallback` — checkout order before `priority` |
| `priority` | Tie-breaker within the same `route_role` (lower = tried first) |
| `settlement_days` | Settlement tier from YAML (e.g. H+2, H+4) |
| `fee_flat` / `fee_percent` | Channel fee model materialized from YAML |
| `policy_tag` | YAML tag (e.g. `ipaymu_h0`) — resolver skips tags in policy `avoid` |
| `is_policy_managed` | When true, priority/fee/settlement are owned by YAML sync — admin cannot edit priority |

Shared sort helper: `PaymentRouteSort` (resolver, `PaymentChannelService::listAll`, admin Blade/JS).

### VA bank catalog (`VaBankCatalog`)

13 logical VA channels (`va_bca`, `va_bni`, …) materialized from `VaBankCatalog`. Per-bank PG codes differ (Midtrans vs iPaymu). YAML overrides: SeaBank/Saqu → Midtrans only; Artha Graha/Muamalat/BPD Bali → iPaymu only.

| Slug | Display | Midtrans | iPaymu |
|------|---------|----------|--------|
| `bca` | BCA VA | bca | bca |
| `bni` | BNI VA | bni | bni |
| `mandiri` | Mandiri VA | mandiri | mandiri |
| `bri` | BRI VA | bri | bri |
| `permata` | Permata VA | permata | permata |
| `cimb` | CIMB Niaga VA | cimb | cimb |
| `danamon` | Danamon VA | danamon | danamon |
| `bsi` | BSI VA | bsi | bsi |
| `seabank` | SeaBank VA | seabank | — |
| `saqu` | Bank Saqu VA | saqu | — |
| `artha_graha` | Artha Graha VA | — | bag |
| `muamalat` | Muamalat VA | — | bmi |
| `bpdbali` | BPD Bali VA | — | bpdbali |

### Admin UI (`/apps/payment-channels`)

- Routes listed in checkout order (`route_role`, then `priority`); **Tried first** badge on the first active route after that sort.
- **Sync routing policy** button → `POST /apps/payment-channels/sync-routing-policy` (same as `php artisan payments:sync-routing-policy`).
- Policy-managed routes: priority input disabled; toggle `is_active` still allowed.

### Admin income page (`/apps/payments/income`)

Read-only dashboard for payment gateway revenue (permission: `view invoice`). Menu: **Payments → Payment income**.

| Metric | Source field | Meaning |
|--------|--------------|---------|
| Service revenue | `SUM(subtotal)` | Product sales (merchant income) |
| Buyer payment fees | `SUM(payment_channel_fee)` | Surcharges collected from customers |
| Cash collected | `SUM(customer_gross_total)` | Total customer payments |

**Date basis toggle:** filter by `paid_at` (payment recognition) or `withdrawable_at` (PG settlement). Optional gateway filter (Midtrans / iPaymu).

**Date range timezone:** `from` / `to` filters use calendar days in `PAYMENT_REPORTING_TIMEZONE` (default `Asia/Jakarta`), not app UTC. This keeps early-morning WIB payments visible on the default “last 30 days” view when the server clock is still on the previous UTC date.

Also shows **funds pipeline** buckets (awaiting settlement, ready to disburse, in payout, disbursed, failed), breakdowns by gateway/channel/product type, daily trend table, and paginated paid-invoice drill-down with links to invoice detail.

API: `GET /api/v1/payments/income`, `GET /api/v1/payments/income/invoices`. Export: **Export CSV** on the page (same date filters via `paid_from` / `paid_to`).

Note: amounts are **recorded invoice values**, not PG bank-statement net-of-MDR. Disbursement actions remain CLI (`payments:disbursement-*`).

```
POST /api/v1/invoices/{id}/channel-checkout
{ "channel_code": "qris", "buyer": { … } }   // buyer optional
```

Response: normalized instructions (VA numbers, QR, expiry, etc.). All routes fail → `snap_fallback_available: true` for API clients that support Snap.

**Buyer / customer autofill** (`InvoiceCustomerDetailsResolver`):

| Priority | Source |
|----------|--------|
| 1 | Request `buyer` / `customer_details` (partial merge) |
| 2 | Invoiceable: Subscription `subscriber_*`, ServicePurchase → `user.name` / `user.email` |
| 3 | Authenticated user profile |
| 4 | Phone: request → invoiceable → `config('payment.default_buyer_phone')` (User has no phone column yet) |

iPaymu **requires** name + email + phone (`setBuyer`). Midtrans `customer_details` optional but always sent when resolvable (helps VA / Akulaku). Blade `checkout.js` does not send `buyer` — server fills from profile.

Services: `InvoiceChannelCheckoutService`, `PaymentChannelResolver`, initiation guards, active-checkout cancellation when switching channels.

## Blade / assets

Authenticated admin/checkout UI under `/apps/*` (route names unprefixed): e.g. `/apps/payment-channels`, `/apps/payment-gateways`, invoice/service-purchase show with checkout panel. QR image base is injected via `route('qr.show')` (`window.monolithQrBaseUrl`) — no hard-coded `/apps/qr` in JS. See [blade-admin — URL helpers](../../web/blade-admin.md#url-helpers-auth-shell).

Payment-first order/invoice show: hero, steps, `checkout-panel`, `checkout.js`. Web proxies: channel-checkout, sync-status, snap-token (API).

### Realtime invoice status (no hard reload)

Checkout pages soft-update hero / steps / panel / sidebar when invoice status changes. **No visual redesign** — same Blade chrome, client patches `data-payment-*` hooks (hero badges, steps, panel body, sidebar status). Entitlement/period sidebar fields are **not** live-refetched (known non-goal; payment progress is the target).

| Layer | Behavior |
|-------|----------|
| Broadcast gate | `BroadcastConnection::isConfigured()` only — **not** `APP_NOTIFICATION_TYPE` (same independence as File Hosting) |
| Helper | `PaymentRealtime` → `InvoiceStatusChanged` (`ShouldBroadcastNow`) |
| Channel | Private `payments.invoice.{invoiceId}` — auth via `InvoiceChannelAuthorizer`: `view invoice` **or** invoiceable owner (`ServicePurchase` / `ServiceAddonRenewal` `user_id`; Subscription matches `subscriber_email`) |
| Event name | `invoice.status.changed` (client: `.invoice.status.changed`) |
| Emit | Both Midtrans + iPaymu synchronizers after save + fulfillment, **only when status string actually changed** (webhook, sync-status, Midtrans reconcile cron all converge here) |
| Client | `checkout.js` reuses `createNotificationEcho` + `window.__monolithEcho`; injects `data-broadcast` from `PaymentRealtime::clientConfig()` |
| Soft updates | Channel checkout success → `showInstructions()` (no reload); terminal `paid` / `expired` / `cancelled` / `failed` → badge/steps/panel/sidebar patch, stop poll |
| Fallback | 15s `sync-status` poll while instructions active (and when broadcast unconfigured). Confirm-paid button kept |

Invoice status payload (slim): `invoice_id`, `status`, `previous_status`, `paid_at`, `expired_at`, `invoiceable_type`, `invoiceable_id`, `invoiceable_status`.

### Realtime routing catalog

When admins toggle payment channels/routes or run `payments:sync-routing-policy`, checkout pages refresh available methods and fee quotes without reload.

| Layer | Behavior |
|-------|----------|
| Event | `PaymentRoutingCatalogChanged` → `routing.catalog.changed` |
| Channel | Private `payments.routing` — auth: `initiate payment` or `view invoice` |
| Emit | `PaymentChannelService` (channel/route update), `PaymentRouteMaterializer::sync` (non-dry-run) |
| Refresh API | `GET /api/v1/invoices/{invoice}/checkout-channels` (invoice subtotal quotes); web proxy `GET /apps/invoices/{invoice}/checkout/channels` |
| Client | `checkout.js` listens on routing channel, refetches channels, re-renders picker, toasts fee/removal changes; **60s poll** fallback when broadcast unconfigured |

Payload: `revision`, `reason` (`channel_updated` | `route_updated` | `policy_synced`), optional `channel_code`.

## Permissions & morph map

Permissions in RoleSeeder (`view invoice`, `initiate payment`, `manage payment gateway`, …). Morph map registers invoiceables (`service_purchase`, `service_addon_renewal`, `subscription`).

## Scheduler / commands

`payments:*` artisan commands for billing/disbursement maintenance (see domain Console). Notable: `payments:sync-routing-policy` (`--dry-run`) reloads channel/route rows from YAML. Scheduler in `routes/console.php` / kernel as configured.

## Config

`config/payment.php` — Midtrans/iPaymu credentials from env; `default_buyer_phone` (`PAYMENT_DEFAULT_BUYER_PHONE`) for iPaymu when profile has no phone. `config/payment-routing.php` — path to [Routing_Price.yaml](Routing_Price.yaml). After secret edits: `php artisan config:rebuild`.

## Encryption

Sensitive invoice payloads, 2FA, etc.: [encryption.md](encryption.md).

## Tests

`composer test -- --filter=Payment` (and related Invoice/Channel filters). Realtime-specific: `PaymentRealtime`, `InvoiceChannelAuthorizer`, `InvoiceStatusChanged`, `MidtransInvoiceSynchronizerRealtime`.

## Related

- [service](../service/README.md) — catalog + FX + service_purchase invoiceable
- [file-hosting](../file-hosting/README.md) — primary product domain fulfilled from Service; [realtime / live listing](../file-hosting/README.md#realtime--live-listing) shares `BroadcastConnection` / `window.__monolithEcho` pattern
- [notification](../notification/README.md) — shared Echo factory; payment invoice channel is **not** the bell bus
- [platform/environment.md](../../platform/environment.md)
