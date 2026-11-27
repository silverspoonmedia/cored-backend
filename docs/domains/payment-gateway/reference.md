# PaymentGateway — deep dive

Layout, API surface, checkout model, routing policy, route snapshot, VA catalog, admin UI, realtime, tests. Invariants: [.cursor/rules/payment-gateway.mdc](../../../.cursor/rules/payment-gateway.mdc). Brief: [README.md](README.md).

## Layout

```
Models/     PaymentGateway, Invoice, PaymentChannelRouteSnapshot, Subscription, PaymentChannel
Services/   Payment/* (channel checkout, Midtrans, iPaymu), Billing/*
Events/     InvoiceStatusChanged (ShouldBroadcastNow)
Support/    PaymentRealtime, InvoiceChannelAuthorizer, InvoiceWebDestination, Routing/* (policy loader, materializer)
Http/       API V1 + iPaymu return/cancel web
Console/    payments:*
```

Registry: **`kind => core`** in [`config/domains.php`](../../../config/domains.php); listed in `DomainManager::PLATFORM_CORE`.

## API surface (`/api/v1/*`)

| Area | API prefix | Auth |
|------|------------|------|
| Gateways | `/payment-gateways` | manage/view permissions |
| Invoices | `/invoices`, channel-checkout | **List/show/export:** `view invoice` (staff). Channel-checkout: `initiate payment`. Checkout-channels: owner or `view invoice` via `InvoiceChannelAuthorizer`. |
| Channels | `/payment-channels`, routes, sync-policy | view / manage |
| Subscriptions | `/subscriptions` | **index/show:** `view subscription`. **store/update/destroy:** `manage subscription`. Not owner-scoped. |
| Midtrans / iPaymu | `/payments/providers/{midtrans\|ipaymu}/*` | JWT except webhooks |

### Channel sync (API)

`POST /api/v1/payment-channels/sync-policy` — materializes the routing policy YAML (`vendor/silverspoonmedia/billing/resources/routing/price.yaml`) into `payment_channels` + `payment_channel_routes` rows. API equivalent of the web sync button (`POST /apps/payment-channels/sync-routing-policy`) and `php artisan payments:sync-routing-policy`. Permission: `manage payment gateway`. Returns `{ channels: int, routes: int, skipped: int }`.

### PaymentChannelResource checkout quote flattening

When `checkout_quote` is set on the model (via `PaymentChannelService::listAvailable` with a `subtotal` query param), `PaymentChannelResource` flattens the quote fields to the **top level** of the resource via `array_merge` — they are NOT nested under a `checkout_quote` key. Flattened fields: `estimated_channel_fee`, `estimated_channel_fee_formatted`, `estimated_customer_total`, `estimated_customer_total_formatted`, `fee_label`, `fee_type`, `fee_basis`, `fee_flat`, `fee_percent`, `gateway_name`, `route_role`.

### Webhooks

Provider notify URLs should hit the backend public webhook routes (or a trusted reverse proxy that forwards to them). Verify signatures in the backend. Set `MIDTRANS_NOTIFY_URL` / `IPAYMU_NOTIFY_URL` to the public paths providers can reach.

## Routing policy (the YAML contract)

The YAML in `vendor/silverspoonmedia/billing/resources/routing/price.yaml` (registry path in `vendor/silverspoonmedia/billing/config/payment-routing.php`) is the **source of truth**. Code loads it via `App\Domain\PaymentGateway\Support\Routing\RoutingPolicyLoader`, validates it with `RoutingPolicyValidator`, then writes it to the DB via `PaymentRouteMaterializer` (`php artisan payments:sync-routing-policy`). Checkout resolver: `PaymentChannelResolver` + `PaymentChannelFeeCalculator`.

The routing config is a **declarative contract**. An operator edits the YAML, then `payments:sync-routing-policy` rewrites the `payment_channels` / `payment_channel_routes` rows; the checkout resolver never reads the YAML directly — it reads the synced DB ordered by `route_role` then `priority`.

Reading flow: map the payment intent ("pay with BCA VA", "pay with GoPay") to the internal channel enum; then look at `type` — `single` means only one `pg` is valid with no routing choice, `dual` means try `primary` first, then `fallback` if the primary fails, is down, or the bank/channel is unavailable at that PG. `settlement` binds a payout-time tier already chosen because fees are lower on longer settlements — never upgrade to H+0 just for faster payout unless the request carries an explicit override. `fee` is the cost model used when estimating cost, ranking PGs, or showing a breakdown. `single_overrides` force a specific PG for a sub-case (SeaBank only via Midtrans), `avoid` is a hard ban (iPaymu QRIS H+0), and `cheap_alt` is an optional cheaper path when UX allows it (GoPay direct 2% may be replaced by QRIS 0.7% when QR scanning is acceptable).

Treat the config as an **immutable policy per environment**, versioned and schema-validated (`fee` type, `pg` enum, `settlement` enum). Never guess a fee outside the config, and never invent a route for a missing field — fail clearly or ask.

### Meaning of the `fee` field

`fee` answers one question: **how much the PG deducts from a successful transaction**, in which model. Two forms, never mixed in a single value.

| YAML form | Type | Meaning | Formula |
|---|---|---|---|
| `3500` / `4000` (number) | **Flat** | Fixed IDR deduction per successful trx | `fee` |
| `"0.7%"` / `"1.5%"` / `"2%"` (string `%`) | **Percentage** | Deduction from the **payment total** | `total × rate` |
| (not used in this config) | Hybrid | % of total **plus** flat | `(total × rate) + flat` |

- Flat is used for Virtual Account, where the PG/bank charges per VA payment, not an MDR percentage: `3500` = iPaymu VA on long settlement (H+4), `4000` = Midtrans VA.
- Percentage is used for QRIS and direct e-wallets (GoPay, OVO, DANA, ShopeePay), following the MDR scheme. A string ending in `%` parses as a decimal (`2%` → `0.02`).
- Hybrid (`2.9% + Rp 2,000`, classic credit card) has been curated out of this config; if it returns, use an explicit `fee: {percent: 2.9, flat: 2000}` shape, never `"2.9%+2000"`.
- **Number without `%`** is always flat IDR; **string with `%`** is always a fraction of the total. Fees apply only to **successful** transactions (not pending/expired/cancelled), matching the Midtrans/iPaymu model.
- The config `fee` is the **channel cost charged to the buyer** at checkout. It is materialized into `payment_channel_routes.fee_*`, estimated in the UI/API (`?subtotal=`), and recorded on the invoice as `payment_channel_fee`. Midtrans uses the policy estimate at charge time; iPaymu uses the actual provider figure after the charge. On success the applicable policy is **copied immutably** into `payment_channel_route_snapshots` (the route rows stay mutable after a YAML sync).
- **VAT** (e.g. 11% on the Midtrans fee) is not folded into `fee` unless the channel's list price is already VAT-inclusive (Midtrans QRIS 0.7%); `fee_vat_inclusive` carries that distinction.
- Settlement (`H+2`, `H+4`) is **not a fee** but determines which price tier is valid — changing settlement can change `fee` (iPaymu QRIS H+0 is far more expensive than H+2), which is why `settlement` and `fee` are always paired under `primary` / `fallback`.

### Policy highlights

| Channel | Routing |
|---------|---------|
| `qris` | Midtrans primary (0.7% VAT-incl, H+2) → iPaymu H+2 fallback; never `ipaymu_h0` |
| `va_*` | iPaymu H+4 primary → Midtrans H+2 fallback; per-bank overrides (SeaBank/Saqu → Midtrans only; Artha Graha/Muamalat → iPaymu only) |
| E-wallets (`gopay`, `ovo`, `dana`, `shopeepay`) | Midtrans direct only; `cheap_alt_code=qris` is a **checkout UX hint** only |
| `linkaja` | QRIS proxy (`routing_type=qris_proxy`) — charges via QRIS routes |

Worked readings:

- **BCA VA Rp 250,000** → dual, `primary.pg = ipaymu`, `settlement = H+4`, `fee = 3500` (flat) → cost **Rp 3,500**; on failure fallback Midtrans, flat **Rp 4,000**.
- **GoPay direct Rp 250,000** → single, `pg = midtrans`, `fee = "2%"` → **Rp 5,000**; with `cheap_alt: qris` at 0.7% → **Rp 1,750**.
- **QRIS Rp 250,000** → dual, primary Midtrans H+2, `fee = "0.7%"` → **Rp 1,750**; never pick an `avoid` path (iPaymu H+0) even though settlement is faster.

### Route schema (`payment_channel_routes`)

| Column | Role |
|--------|------|
| `route_role` | `only`, `primary`, or `fallback` — checkout order before `priority` |
| `priority` | Tie-breaker within the same `route_role` (lower = tried first) |
| `settlement_days` | Settlement tier from YAML (e.g. H+2, H+4) |
| `fee_flat` / `fee_percent` | Channel fee model materialized from YAML |
| `policy_tag` | YAML tag (e.g. `ipaymu_h0`) — resolver skips tags in policy `avoid` |
| `is_policy_managed` | When true, priority/fee/settlement are owned by YAML sync — admin cannot edit priority |

Shared sort helper: `PaymentRouteSort` (resolver, `PaymentChannelService::listAll`, admin list responses).

## Checkout model

Primary sell path: [Service](../service/README.md) checkout → `ServicePurchase` → pending invoice (**currency IDR**, FX snapshot on purchase when catalog price is non-IDR) with **`payment_gateway_id` null** until channel charge or hosted fallback assigns gateway.

**IDR amounts:** Service FX settlement rounds to **whole rupiah** ([Currency](../../domains/currency/README.md) `ExchangeRateService`). Invoice `subtotal` / initial `customer_gross_total` are whole IDR before channel fee.

**API display companions:** `InvoiceResource` / `SubscriptionResource` keep raw money + ISO timestamps and add `*_formatted` (money with currency code) and `*_formatted` / `*_human` (dates). Clients bind companions only, never reformat raw values — see [platform/crud presentation](../../platform/reference-crud.md#api-resource-presentation-dual-fields).

**InvoiceResource (per-viewer):** nested under `ServicePurchaseResource` for buyers. Staff (`view invoice`) also get `invoiceable_type`, `external_transaction_id`, route snapshot id + `payment_channel_route` (snapshot shape; the old duplicate `payment_channel_route_snapshot` key is gone), disbursement timestamps. Buyers keep amounts, status, `checkout_session`, and fee-basis labels they already paid. Post-checkout policy fields (`invoiceable_status`, fee basis companions) stay on the shared shape; snapshot internals are staff-only.

**InvoiceResource (post-checkout policy):** `invoiceable_status` (resolved from the invoiceable relation, mirrors `PaymentRealtime`) plus fee basis companions (`payment_channel_fee_type`, `payment_channel_fee_label`, `payment_channel_fee_basis`) — all from the immutable snapshot, **not** live `payment_channel_routes` rows.

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

**Reconciliation:** invoice list shows subtotal + fee + gross; `GET /api/v1/invoices/export` streams CSV for bookkeeping. `InvoiceService::list()` / `get()` eager-load `paymentGateway`, `paymentChannelRouteSnapshot.paymentGateway`, and `invoiceable` up front to avoid N+1 on the invoice list (consumed by `InvoiceResource` / `PaymentIncomeInvoiceResource`). List collections prefetch `PaymentChannel` by code for `checkout_session`.

**Purchase-hub redirect:** For `invoiceable_type = service_purchase`, invoice list **View** targets and invoice show resolve to the [Service purchase hub](../service/README.md) (`/apps/service-purchases/{id}`) — one combined payment + purchase page. Subscription and other morph types stay on `/apps/invoices/{id}`. Helper: `InvoiceWebDestination::showUrl()`.

| Surface | Channel mode | Snap/redirect fallback |
|---------|--------------|------------------------|
| **API** | Channel picker + instructions | Yes (`snap_fallback` when no active channels) |
| **SPA checkout** | Channel only | **No** Snap UI — message when no channels |

`checkout_mode` = `channel` when active channels exist; else `snap_fallback`. Non-SPA API clients may drive hosted Snap; the SPA checkout stays channel-only.

### Channel checkout

Logical channels (`qris`, `va_*`, e-wallets) are **materialized from** the routing policy YAML (`vendor/silverspoonmedia/billing/resources/routing/price.yaml`) into `payment_channels` + `payment_channel_routes` (fee, settlement tier, route role). Sync: `php artisan payments:sync-routing-policy` (also runs via `PaymentChannelSeeder` after gateways).

`PaymentChannelResolver` orders viable routes by `route_role` (primary/only before fallback) then `priority`, skips `avoid` tags, and checks gateway credentials. Successful checkout stores `payment_channel_route_id` and an immutable **`payment_channel_route_snapshot_id`** on the invoice.

### Route policy snapshot

Parallels the [Currency](../currency/README.md) FX snapshots (`exchange_rate_snapshots` + `fx_rate_snapshot_id`). YAML sync and admin edits mutate `payment_channel_routes`; without a snapshot, invoice fee **amounts** stay locked but fee **basis** (flat/percent, settlement tier, labels) could drift on display.

| When | What is captured |
|------|------------------|
| Channel checkout (`InvoiceChannelCheckoutService`) | Insert row in `payment_channel_route_snapshots`; FK on invoice |
| Midtrans pre-charge (`InvoiceChannelFeeService::applyBeforeCharge`) | Same snapshot reused for fee estimate + gross before charge |

`InvoiceResource` (staff viewers), the [Service purchase hub](../service/reference.md) (invoice nested in purchase API — buyers do **not** receive the snapshot object), and CSV export (`InvoiceCsvExportService`) read **`payment_channel_route`** (snapshot shape, staff-only on the Resource) — not live route rows. Standalone `/apps/invoices/{id}` applies only to non-`service_purchase` morphs. Abandoning checkout clears `payment_channel_route_snapshot_id` with the route FK. Pre-production: no backfill for invoices created before this feature.

**Snapshot columns:** `payment_channel_code`, `payment_gateway_id`, `route_role`, `settlement_days`, `fee_flat`, `fee_percent`, `fee_vat_inclusive`, `policy_tag`, `provider_payment_*`, `captured_at` (+ `captured_at_formatted` / `captured_at_human` companions via `DatePresenter`).

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

**Date range timezone:** `from` / `to` filters use calendar days in `platform.payment.reporting_timezone` (default `Asia/Jakarta`; Settings → Platform), not app UTC. This keeps early-morning WIB payments visible on the default "last 30 days" view when the server clock is still on the previous UTC date.

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

iPaymu **requires** name + email + phone (`setBuyer`). Midtrans `customer_details` optional but always sent when resolvable (helps VA / Akulaku). The SPA checkout does not send `buyer` — the server fills it from the profile.

Services: `InvoiceChannelCheckoutService`, `PaymentChannelResolver`, initiation guards, active-checkout cancellation when switching channels.

## Vue-Workers SPA consumer

The SPA uses `usePaymentsHydrate` for `PaymentsIncomeView`, batching income overview and invoice reads while retaining individual-loader fallback for failed entries. SPA-side realtime wiring is documented in [vue-workers-spa.md — realtime](../../integration/reference-vue-workers-spa.md#realtime); the backend broadcast contract is below.

iPaymu hosted redirect defaults (`IpaymuPaymentService::defaultReturnUrl` / `defaultCancelUrl`) use `SpaPublicUrl`: `{SPA_URL}/payment/return` and `/payment/cancel` when `SPA_URL` is set; otherwise the server endpoints `/payment/ipaymu/return|cancel` ([`vendor/silverspoonmedia/billing/routes/web-ipaymu-return.php`](../../../vendor/silverspoonmedia/billing/routes/web-ipaymu-return.php), loaded through the registry `web` key), which redirect onward. Channel checkout still sends empty `ureturn` (on-page QR/VA). SPA bounce views stay authenticated at the site root — [vue-workers-spa — payment return URLs](../../integration/reference-vue-workers-spa.md#payment-return-urls).

## UI surfaces (SPA)

**Sidebar:** **Administration → Payments** contains Payment income (`payments.income.index`), Invoices (`invoices.index`), Subscriptions (`subscriptions.index`), Payment channels (`payment-channels.index`), and Payment gateways (`payment-gateways.index`). Parent and children are visible to Root, Admin, Moderator, and Management; endpoint permissions remain authoritative.

All authenticated admin/checkout UI is Cored Frontend under `/apps/*` — Vue Router names registered in [`config/spa-routes.php`](../../../config/spa-routes.php): `payment-channels.index`, `payment-gateways.*`, `subscriptions.*`, `invoices.index` / `invoices.show`, `payments.income.index`, and `service-purchases.show` (primary payment surface for service sales). Query-mode list rows emit `actions[]` (gateways `edit`/`delete`; invoices `view` only; subscriptions `view`/`edit`). Invoice **show** (not the list View button) still resolves through `InvoiceWebDestination`, which points service-purchase morphs at the purchase hub. Breadcrumbs are SPA-side (`src/composables/useBreadcrumbs.ts`), not a Laravel breadcrumb registry. The QR helper route and its `window.monolithQrBaseUrl` injection were deleted with Blade — the SPA renders provider QR payloads from the invoice response.

### Checkout UI architecture

Primary buyer surface: `/apps/service-purchases/{uuid}` (`service-purchases.show`). Shared checkout chrome also mounts on invoice show for non–service-purchase morphs.

Fee breakdown shows **0** until the buyer selects a payment method (restored from the active `checkout_session` when instructions exist). The SPA calls the API directly: channel checkout, sync-status, and snap-token are all `/api/v1/*` — there are no web proxies. Message placeholders keep the Laravel `:name` convention on the API side (e.g. `Pay before :time`).

`CheckoutPanel.vue` renders payment instructions (VA number, QRIS, ewallet deeplink) **only** from its local `checkoutResult` state, which is filled two ways: a successful `channelCheckout()` response, or — on mount — by restoring `invoice.checkout_session` (when `instructions.type` is present) into `checkoutResult` and syncing `selectedChannel` from the session's `channel_code`. Without that restore a hard reload after channel-checkout would drop back to the bare channel picker as if no method was ever chosen, and the Confirm-paid button (`v-if="checkoutResult"`) would vanish with it.

Realtime + poll behavior lives in `src/composables/payment-gateway/usePaymentRealtime.ts` and `src/composables/payment-gateway/useInvoices.ts`; the backend contract is unchanged and documented below.

### Realtime invoice status (no hard reload)

Checkout pages soft-update hero / steps / panel / sidebar when invoice status changes, driven by reactive state rather than a page reload. Entitlement/period sidebar fields are **not** live-refetched (known non-goal; payment progress is the target).

| Layer | Behavior |
|-------|----------|
| Broadcast gate | `BroadcastConnection::isConfigured()` only — **not** `platform.notifications.delivery` (same independence as Archivd) |
| Helper | `PaymentRealtime` → `InvoiceStatusChanged` (`ShouldBroadcastNow`) |
| Channel | Private `payments.invoice.{invoiceId}` — auth via `InvoiceChannelAuthorizer`: `view invoice` **or** invoiceable owner (`ServicePurchase` / `ServiceAddonRenewal` `user_id`; Subscription matches `subscriber_email`) |
| Event name | `invoice.status.changed` (client: `.invoice.status.changed`) |
| Emit | Both Midtrans + iPaymu synchronizers after save + fulfillment, **only when status string actually changed** (webhook, sync-status, Midtrans reconcile cron all converge here) |
| Client | `src/composables/payment-gateway/usePaymentRealtime.ts` subscribes per-caller (invoice channel bindings are ref-counted per invoice id by `createKeyedRealtimeChannel` — `InvoiceShowView` and `CheckoutPanel` can hold subscriptions simultaneously) |
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
| Refresh API | `GET /api/v1/invoices/{invoice}/checkout-channels` (invoice subtotal quotes) — no web proxy |
| Client | `src/composables/payment-gateway/usePaymentRealtime.ts` listens on the routing channel; `useInvoices.fetchCheckoutChannels` refetches and re-quotes fees, re-renders the picker, and toasts fee/removal changes; **60s poll** fallback when broadcast unconfigured |

Payload: `revision`, `reason` (`channel_updated` | `route_updated` | `policy_synced`), optional `channel_code`.

### Realtime admin lists (`payments.admin`)

Admin CRUD surfaces soft-refetch via the `payments.admin` private channel (permission `view invoice`), all emitted by `PaymentRealtime` after successful persist:

| Event | Payload | Consumer |
|-------|---------|----------|
| `invoice.list.changed` | `action` | Admin invoice list count-only signal |
| `payments.gateway.changed` | `action`, `gateway_id` | `PaymentGatewaysView` |
| `payments.channel.changed` | `action`, `channel_code` | `PaymentChannelsView` |
| `payments.subscription.changed` | `action`, `subscription_id` | Subscriptions views (`Service` domain UI) |

SPA composable: `src/composables/payment-gateway/usePaymentRealtime.ts` — three registry bindings: `createKeyedRealtimeChannel` for `payments.invoice.{id}` (one ref-counted channel per invoice), `createRealtimeChannel` for `payments.routing`, and `createRealtimeChannel` for `payments.admin`, whose handler set is ref-counted over 4 streams (invoice list / gateway / channel / subscription); it binds when the first handler attaches and leaves only when every stream is empty. `unsubscribe*` requires the exact handler argument (no implicit clear-all). Lifecycle (attach/leave/refcount) lives in `src/composables/echo/channelRegistry.ts`, not here.

## Permissions & morph map

Permissions in RoleSeeder (`view invoice`, `initiate payment`, `manage payment gateway`, …). Morph map registers invoiceables (`service_purchase`, `service_addon_renewal`, `subscription`).

## Scheduler / commands

`payments:*` artisan commands for billing/disbursement maintenance (see domain Console). Notable: `payments:sync-routing-policy` (`--dry-run`) reloads channel/route rows from YAML. Scheduler in `routes/console.php` / kernel as configured.

## Config

`vendor/silverspoonmedia/billing/config/payment.php` — Midtrans/iPaymu credentials from env (Tier A); `default_buyer_phone` is Tier B (`platform.payment.default_buyer_phone` via Settings → Platform) for iPaymu when profile has no phone. `vendor/silverspoonmedia/billing/config/payment-routing.php` — `policy_path` → `vendor/silverspoonmedia/billing/resources/routing/price.yaml`. After secret edits: `php artisan config:rebuild`.

## Tests

`composer test -- --filter=Payment` on this host matches no package test: the billing suite lives in the `silverspoonmedia/billing` mirror and is `export-ignore`d from the tag, so run it from a clone (`composer install`, then `composer test`). It covers `PaymentRealtime`, `InvoiceChannelAuthorizer`, `InvoiceStatusChanged`, `MidtransInvoiceSynchronizerRealtime`, the route snapshot (`InvoiceChannelCheckoutTest` — snapshot FK + immutable fee basis) and the snap parameter builder (`MidtransSnapParameterBuilderTest` — IDR int `gross_amount` convention).
