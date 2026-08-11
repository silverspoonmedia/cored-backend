# PaymentGateway — Midtrans, iPaymu, invoices, subscriptions

Core domain `App\Domain\PaymentGateway\`. Secrets in env/config, not DB. Polymorphic invoices → **service_purchase** (primary product sell path) / PG `subscription` demo morph. Idempotent webhooks via unique `(payment_gateway_id, external_transaction_id)`.

**Product SaaS subscriptions** (periods, grace, File Hosting quota) live in the [Service](../service/README.md) domain (`ServiceEntitlement`) — **not** the PaymentGateway `Subscription` model.

## Layout

```
Models/     PaymentGateway, Invoice, PaymentChannelRouteSnapshot, Subscription, PaymentChannel
Services/   Payment/* (channel checkout, Midtrans, iPaymu), Billing/*
Events/     InvoiceStatusChanged (ShouldBroadcastNow)
Support/    PaymentRealtime, InvoiceChannelAuthorizer, InvoiceWebDestination, Routing/* (policy loader, materializer), DataTable/*
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
| Channels | `/payment-channels`, routes, sync-policy | view / manage |
| Subscriptions | `/subscriptions` | view/manage subscription |
| Midtrans / iPaymu | `/payments/providers/{midtrans\|ipaymu}/*` | JWT except webhooks |

### Channel sync (API)

`POST /api/v1/payment-channels/sync-policy` — materializes routing policy from [Routing_Price.yaml](Routing_Price.yaml) into `payment_channels` + `payment_channel_routes` rows. API equivalent of the web sync button (`POST /apps/payment-channels/sync-routing-policy`) and `php artisan payments:sync-routing-policy`. Permission: `manage payment gateway`. Returns `{ channels: int, routes: int, skipped: int }`.

### PaymentChannelResource checkout quote flattening

When `checkout_quote` is set on the model (via `PaymentChannelService::listAvailable` with a `subtotal` query param), `PaymentChannelResource` flattens the quote fields to the **top level** of the resource via `array_merge` — they are NOT nested under a `checkout_quote` key. Flattened fields: `estimated_channel_fee`, `estimated_channel_fee_formatted`, `estimated_customer_total`, `estimated_customer_total_formatted`, `fee_label`, `fee_type`, `fee_basis`, `fee_flat`, `fee_percent`, `gateway_name`, `route_role`.

### Webhooks

Provider notify URLs should hit Monolith public webhook routes (or a trusted reverse proxy that forwards to them). Verify signatures in Monolith. Set `MIDTRANS_NOTIFY_URL` / `IPAYMU_NOTIFY_URL` to the public paths providers can reach.

## Checkout model

Primary sell path: [Service](../service/README.md) checkout → `ServicePurchase` → pending invoice (**currency IDR**, FX snapshot on purchase when catalog price is non-IDR) with **`payment_gateway_id` null** until channel charge or hosted fallback assigns gateway.

**IDR amounts:** Service FX settlement rounds to **whole rupiah** (`ExchangeRateService`). Invoice `subtotal` / initial `customer_gross_total` are whole IDR before channel fee.

**API display companions:** `InvoiceResource` / `SubscriptionResource` keep raw money + ISO timestamps and add `*_formatted` (money with currency code) and `*_formatted` / `*_human` (dates). Blade and DataTables bind companions only — see [platform/crud presentation](../../platform/crud.md#api-resource-presentation-dual-fields).

**InvoiceResource (post-checkout policy):** `invoiceable_status` (resolved from the invoiceable relation, mirrors `PaymentRealtime`), `payment_channel_route_snapshot_id`, nested `payment_channel_route_snapshot` (and `payment_channel_route` alias with the same snapshot shape), plus fee basis companions (`payment_channel_fee_type`, `payment_channel_fee_label`, `payment_channel_fee_basis`) — all from the immutable snapshot, **not** live `payment_channel_routes` rows.

**PaymentIncomeInvoiceResource** (income drill-down): a subset of `InvoiceResource` money + date companions (`subtotal`, `payment_channel_fee`, `customer_gross_total`, `paid_at`, `withdrawable_at`), plus fee basis companions from the snapshot, and `show_url` via `InvoiceWebDestination`. The `.paymentGateway` eager load lives in `PaymentIncomeAnalyticsService::invoicesPaginated()` (the query backing this resource); the resource itself only does `loadMissing('paymentChannelRouteSnapshot')` as a defensive fallback.

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

Policy default: `platform.payment.channel_fee_bearer` = `buyer` (Settings → Platform). YAML route `fee_*` is the **buyer surcharge** shown at checkout and stored on the invoice. Checkout UI shows the **percentage basis** (e.g. `0.7% of service price`) when the route fee is percent-based, so buyers see why the fee amount changes with subtotal.

| Field | Bookkeeping role |
|-------|------------------|
| `subtotal` | Merchant sale (service price) — unchanged after invoice creation |
| `payment_channel_fee` | Buyer-paid channel surcharge |
| `customer_gross_total` | Cash collected from customer (`subtotal + fee`) |

| Gateway | Fee application |
|---------|-----------------|
| **Midtrans** | `InvoiceChannelFeeService` sets fee + gross from policy estimate **before** charge (`gross_amount` = `customer_gross_total`). `MidtransSnapParameterBuilder` / `MidtransPaymentService::midtransGrossAmount()` cast `gross_amount` to **int** for IDR (whole rupiah) and **float (2dp)** for non-IDR — the Snap/charge payload type, not just the invoice column. Codified by `MidtransSnapParameterBuilderTest` (`assertSame(180000, …)` int). |
| **iPaymu** | Charge sends `subtotal` only; provider returns actual fee + gross (webhook reconciles) |

Checkout UI shows subtotal / payment fee / total due. `GET /api/v1/payment-channels?subtotal=` returns per-channel estimates. Abandoning checkout resets fee/gross to pre-checkout values.

**Reconciliation:** invoice list shows subtotal + fee + gross; `GET /api/v1/invoices/export` (and `/apps/invoices/export`) streams CSV for bookkeeping. `InvoiceService::list()` / `get()` eager-load `paymentGateway`, `paymentChannelRouteSnapshot.paymentGateway`, and `invoiceable` up front to avoid N+1 on the `/apps/invoices` list (consumed by `InvoiceResource` / `PaymentIncomeInvoiceResource`).

**Blade payment surface:** For `invoiceable_type = service_purchase`, `/apps/invoices` datatable **View** links and `GET /apps/invoices/{id}` redirect to the [Service purchase hub](../service/README.md) (`/apps/service-purchases/{id}`) — one combined payment + purchase page. Subscription and other morph types still use `/apps/invoices/{id}`. Helper: `InvoiceWebDestination::showUrl()`.

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

`PaymentChannelResolver` orders viable routes by `route_role` (primary/only before fallback) then `priority`, skips `avoid` tags, and checks gateway credentials. Successful checkout stores `payment_channel_route_id` and an immutable **`payment_channel_route_snapshot_id`** on the invoice (policy captured at checkout — see below).

### Route policy snapshot

Parallels [Service FX snapshots](../service/README.md) (`exchange_rate_snapshots` + `fx_rate_snapshot_id`). YAML sync and admin edits mutate `payment_channel_routes`; without a snapshot, invoice fee **amounts** stay locked but fee **basis** (flat/percent, settlement tier, labels) could drift on display.

| When | What is captured |
|------|------------------|
| Channel checkout (`InvoiceChannelCheckoutService`) | Insert row in `payment_channel_route_snapshots`; FK on invoice |
| Midtrans pre-charge (`InvoiceChannelFeeService::applyBeforeCharge`) | Same snapshot reused for fee estimate + gross before charge |

`InvoiceResource`, the [Service purchase hub](../service/README.md) (invoice nested in purchase API), CSV export (`InvoiceCsvExportService`), and any Blade consuming invoice API data read **`payment_channel_route_snapshot`** only — not live route rows. Standalone `/apps/invoices/{id}` Blade applies only to non-`service_purchase` morphs (service purchases redirect per **Blade payment surface** above). Abandoning checkout clears `payment_channel_route_snapshot_id` with the route FK. Pre-production: no backfill for invoices created before this feature.

**Snapshot columns:** `payment_channel_code`, `payment_gateway_id`, `route_role`, `settlement_days`, `fee_flat`, `fee_percent`, `fee_vat_inclusive`, `policy_tag`, `provider_payment_*`, `captured_at` (+ `captured_at_formatted` / `captured_at_human` companions via `DatePresenter`).

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

Read-only dashboard for payment gateway revenue (permission: `view invoice`). Menu: **Administration → Payments → Payment income**.

| Metric | Source field | Meaning |
|--------|--------------|---------|
| Service revenue | `SUM(subtotal)` | Product sales (merchant income) |
| Buyer payment fees | `SUM(payment_channel_fee)` | Surcharges collected from customers |
| Cash collected | `SUM(customer_gross_total)` | Total customer payments |

**Date basis toggle:** filter by `paid_at` (payment recognition) or `withdrawable_at` (PG settlement). Optional gateway filter (Midtrans / iPaymu).

**Date range timezone:** `from` / `to` filters use calendar days in `platform.payment.reporting_timezone` (default `Asia/Jakarta`; Settings → Platform), not app UTC. This keeps early-morning WIB payments visible on the default “last 30 days” view when the server clock is still on the previous UTC date.

Also shows **funds pipeline** buckets (awaiting settlement, ready to disburse, in payout, disbursed, failed), breakdowns by gateway/channel/product type, daily trend table, and paginated paid-invoice drill-down. Drill-down `show_url` uses `InvoiceWebDestination::showUrl()` → purchase hub for `service_purchase`, else `/apps/invoices/{id}`.

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
| 4 | Phone: request → invoiceable → `setting_config('payment.default_buyer_phone')` (User has no phone column yet) |

iPaymu **requires** name + email + phone (`setBuyer`). Midtrans `customer_details` optional but always sent when resolvable (helps VA / Akulaku). Blade `checkout.js` does not send `buyer` — server fills from profile.

Services: `InvoiceChannelCheckoutService`, `PaymentChannelResolver`, initiation guards, active-checkout cancellation when switching channels.

## Blade / assets

**Sidebar:** **Administration → Payments** contains Payment income (`payments.income.index`), Invoices (`invoices.index`), Subscriptions (`subscriptions.index`), Payment channels (`payment-channels.index`), and Payment gateways (`payment-gateways.index`). Parent and children are visible to Root, Admin, Moderator, and Management; endpoint permissions remain authoritative.

Authenticated admin/checkout UI under `/apps/*` (route names unprefixed): e.g. `/apps/payment-channels`, `/apps/payment-gateways`, `/apps/service-purchases/{id}` (primary payment surface for service sales), `/apps/invoices` (list **View** uses `InvoiceWebDestination`). QR image base is injected via `route('qr.show')` (`window.monolithQrBaseUrl`) — no hard-coded `/apps/qr` in JS. See [blade-admin — URL helpers](../../web/blade-admin.md#url-helpers-auth-shell).

**Breadcrumbs:** every payment web route has a trail in `routes/breadcrumbs.php` (invoices, payments.income, payment-channels, payment-gateways, subscriptions) — same coverage as users/services/menus. Param routes (`invoices.show`, `subscriptions.show/edit`) call `Breadcrumbs::setCurrentRoute()` in their Blade `@php` block.

Payment-first purchase hub / subscription invoice show: hero, steps, `checkout-panel`, `checkout.js`. Fee breakdown shows **0** until the buyer selects a payment method (restored from active `checkout_session` when instructions exist). Web proxies: channel-checkout, sync-status, snap-token (API). Checkout UX: channel picker buttons carry `aria-pressed` on first paint; sync-status button shows a loading state (disabled + text) during the request; both purchase hub and invoice show use the same mobile column ordering (sidebar first on mobile). Message placeholders use Laravel `:name` convention throughout (e.g. `Pay before :time`).

### Checkout UI architecture (vanilla soft-update — not a Vue island)

Primary buyer surface: `/apps/service-purchases/{uuid}` (`service-purchases.show`). Shared checkout chrome also mounts on invoice show for non–service-purchase morphs; service purchases redirect to the hub via `InvoiceWebDestination`.

| Asset | Role |
|-------|------|
| `resources/js/payments/checkout.js` | Channel picker, instructions, Echo + poll soft status/routing updates |
| `resources/js/services/purchase-quantity.js` | Qty controls on purchase hub |
| `resources/js/services/purchase-pricing-preview.js` | Pure pricing preview math |

**Fair Vue boundary:** this page does **not** earn a page-level Vue island under the [dual-path checklist](../../web/blade-admin.md#dual-path--when-to-use-which). Soft-SPA here means Echo + poll + DOM patch on one detail URL until terminal — already implemented in vanilla. Contrast File Hosting auth browse (soft-nav + Drive gestures + upload queue), which earns one island. Do **not** Vue-wash pricing math, Echo subscribe, sync-status poll, or spawn widget islands for channel picker / QR / qty.

**Triggers for Vue later (not now):** (1) channel/instruction templates become unmaintainable → small Blade-mounted Vue **components** driven by the same vanilla orchestrator, not a page island; (2) true multi-view soft-nav on one document; (3) dual Blade+JS ownership war on the same nodes.

Admin siblings (`channels-admin.js`, `income.js`, invoice DataTables) stay vanilla for the same reason. Invoice/gateway DataTables use `exposeForReload: true` so bell-driven soft reload works without hard refresh.

### Realtime invoice status (no hard reload)

Checkout pages soft-update hero / steps / panel / sidebar when invoice status changes. **No visual redesign** — same Blade chrome, client patches `data-payment-*` hooks (hero badges, steps, panel body, sidebar status). Entitlement/period sidebar fields are **not** live-refetched (known non-goal; payment progress is the target).

| Layer | Behavior |
|-------|----------|
| Broadcast gate | `BroadcastConnection::isConfigured()` only — **not** `platform.notifications.delivery` (same independence as File Hosting) |
| Helper | `PaymentRealtime` → `InvoiceStatusChanged` (`ShouldBroadcastNow`) |
| Channel | Private `payments.invoice.{invoiceId}` — auth via `InvoiceChannelAuthorizer`: `view invoice` **or** invoiceable owner (`ServicePurchase` / `ServiceAddonRenewal` `user_id`; Subscription matches `subscriber_email`) |
| Event name | `invoice.status.changed` (client: `.invoice.status.changed`) |
| Emit | Both Midtrans + iPaymu synchronizers after save + fulfillment, **only when status string actually changed** (webhook, sync-status, Midtrans reconcile cron all converge here) |
| Client | `checkout.js` reuses `createNotificationEcho` + `window.__monolithEcho`; injects `data-broadcast` from `PaymentRealtime::clientConfig()` |
| Soft updates | Channel checkout success → `showInstructions()` (no reload); terminal `paid` / `expired` / `cancelled` / `failed` → badge/steps/panel/sidebar patch, stop poll |
| Fallback | 15s `sync-status` poll while instructions active (and when broadcast unconfigured). Confirm-paid button kept; loading state while syncing. Poll response carries `invoiceable_status` so the purchase badge soft-updates without a reload. |

Invoice status payload (slim): `invoice_id`, `status`, `previous_status`, `paid_at`, `expired_at`, `invoiceable_type`, `invoiceable_id`, `invoiceable_status`. The sync-status API response (Midtrans + iPaymu) also includes `expired_at` and `invoiceable_status` on the nested `invoice` object so polling-only clients can patch the purchase badge.

### Realtime routing catalog

When admins toggle payment channels/routes or run `payments:sync-routing-policy`, checkout pages refresh available methods and fee quotes without reload.

| Layer | Behavior |
|-------|----------|
| Event | `PaymentRoutingCatalogChanged` → `routing.catalog.changed` |
| Channel | Private `payments.routing` — auth: `initiate payment` or `view invoice` |
| Emit | `PaymentChannelService` (channel/route update), `PaymentRouteMaterializer::sync` (non-dry-run) |
| Refresh API | `GET /api/v1/invoices/{invoice}/checkout-channels` (invoice subtotal quotes); web proxy `GET /apps/invoices/{invoice}/checkout/channels` |
| Client | `checkout.js` listens on routing channel, refetches channels, re-quotes fees via `quoteChannelForSubtotal` (current invoice/preview subtotal + `formatMoney`), re-renders picker, toasts fee/removal changes; **60s poll** fallback when broadcast unconfigured |

Payload: `revision`, `reason` (`channel_updated` | `route_updated` | `policy_synced`), optional `channel_code`.

## Permissions & morph map

Permissions in RoleSeeder (`view invoice`, `initiate payment`, `manage payment gateway`, …). Morph map registers invoiceables (`service_purchase`, `service_addon_renewal`, `subscription`).

## Scheduler / commands

`payments:*` artisan commands for billing/disbursement maintenance (see domain Console). Notable: `payments:sync-routing-policy` (`--dry-run`) reloads channel/route rows from YAML. Scheduler in `routes/console.php` / kernel as configured.

## Config

`config/payment.php` — Midtrans/iPaymu credentials from env (Tier A); `default_buyer_phone` is Tier B (`platform.payment.default_buyer_phone` via Settings → Platform) for iPaymu when profile has no phone. `config/payment-routing.php` — path to [Routing_Price.yaml](Routing_Price.yaml). After secret edits: `php artisan config:rebuild`.

## Encryption

Sensitive invoice payloads, 2FA, etc.: [encryption.md](encryption.md).

## Tests

`composer test -- --filter=Payment` (and related Invoice/Channel filters). Realtime-specific: `PaymentRealtime`, `InvoiceChannelAuthorizer`, `InvoiceStatusChanged`, `MidtransInvoiceSynchronizerRealtime`. Route snapshot: `InvoiceChannelCheckoutTest` (snapshot FK + immutable fee basis). Web navigation: `InvoiceWebDestinationTest`, `InvoiceWebNavigationTest` (datatable + show redirect to purchase hub). Snap parameter building + IDR int `gross_amount` convention: `MidtransSnapParameterBuilderTest`.

## Related

- [service](../service/README.md) — catalog + FX + service_purchase invoiceable
- [file-hosting](../file-hosting/README.md) — primary product domain fulfilled from Service; [realtime / live listing](../file-hosting/README.md#realtime--live-listing) shares `BroadcastConnection` / `window.__monolithEcho` pattern
- [notification](../notification/README.md) — shared Echo factory; payment invoice channel is **not** the bell bus
- [platform/environment.md](../../platform/environment.md)
