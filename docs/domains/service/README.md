# Service — sellable catalog, entitlements, multi-currency FX checkout

**Core** platform domain for SaaS services sold by the project (subscriptions + one-time). Primary invoiceable source for payment checkout (`service_purchase` morph).

| | |
|--|--|
| Code | `app/Domain/Service/` |
| Registry | `kind => core` in [`config/domains.php`](../../../config/domains.php); listed in `DomainManager::PLATFORM_CORE` |
| API | `routes/api/v1/service.php` → `/api/v1/services`, `/api/v1/service-purchases` |
| Web | `routes/web/service.php` → `services.*`, `service-purchases.*` |
| FX | Frankfurter → **DB** `exchange_rate_snapshots` + L1 cache; settle **IDR** |
| Config | `config/service.php` (`grace_days`, `settlement_currency`) |
| Seeder | `CurrencySeeder` then `ServiceCatalogSeeder` |
| Scheduler | `service:process-entitlements` hourly |

## Why this domain exists

1. **Catalog** of sellable services with multi-currency list prices (codes in `currencies`)
2. **Checkout** that settles to **IDR** for Midtrans/iPaymu (rate history in DB)
3. **Entitlements** with period (subscription) or permanent (one-time), plus **quantity** for stackable addons
4. **Fulfillment** into product domains (File Hosting **main + stackable addons**, free 5 GiB always stacked)

**Not** PaymentGateway `Subscription` (free-text demo morph). Product lifecycle lives here.

Money convention: **decimal(15,2)** (same as invoices).

## Service types

| `billing_interval` | Kind | Period | After pay |
|--------------------|------|--------|-----------|
| `monthly` | subscription | +1 month | `ServiceEntitlement` active until `ends_at` |
| `quarterly` | subscription | +3 months | same |
| `yearly` | subscription | +1 year | same |
| `once` | one-time | none | permanent entitlement (`ends_at` null) |

**Renew = manual checkout** of the same catalog SKU (new `ServicePurchase` + invoice). Extends existing entitlement: `ends_at = max(ends_at, now) + period` (grace renew uses `now`).

## Data model

| Table | Role |
|-------|------|
| `currencies` | Active FX codes; `decimal_places` (IDR=0); `is_settlement` (IDR) |
| `exchange_rate_snapshots` | Rate history (provider, rate, fetched_at, optional payload) |
| `services` | Catalog: `code`, `billing_interval`, `fulfillment_target`, `entitlement`, **`plan_kind`** (`main`\|`addon`), **`storage_bytes`**, `status` |
| `service_prices` | List price per currency (validated against `currencies`) |
| `service_purchases` | Invoiceable money event: display + settlement + FX + optional `period_*` |
| `service_entitlements` | One row per user+service: `active` / `grace` / `expired` / `cancelled` + **`quantity`** (addon stack count; main always 1) |

### File Hosting catalog (seed)

| Code | Name | Kind | Storage | USD/mo | Notes |
|------|------|------|---------|--------|-------|
| `file-hosting.plan.free` | Gratis | main | 5 GiB | 0 | **Display only** (`draft`); runtime free always from config, always stacked. Not purchasable. |
| `file-hosting.plan.1tb.monthly` | Standard 1 TB | main | 1 TiB | 24.95 | Exclusive paid main |
| `file-hosting.plan.2tb.monthly` | Standard 2 TB | main | 2 TiB | 54.95 | Exclusive paid main |
| `file-hosting.addon.100gb.monthly` | Standard +100 GB | addon | 100 GiB | 2.95 | Stackable; `quantity++` per paid purchase |
| `file-hosting.addon.500gb.monthly` | Standard +500 GB | addon | 500 GiB | 14.50 | Stackable |

Legacy `file-hosting.subscription.monthly` (unlimited POC) is **archived** on seed.

**Effective FH quota:**

```
effective_bytes =
  free_tier_bytes                                          # always (config 5 GiB)
  + (active_or_grace main.storage_bytes)                   # at most one paid main
  + sum(active_or_grace addon.storage_bytes * quantity)
```

Constants / codes: `App\Domain\Service\Support\FileHostingCatalog`.

## Multi-currency / FX

```
Display price (e.g. USD 10)
  → ExchangeRateService
       prefer L1 cache → fresh DB snapshot within TTL → else Frankfurter + INSERT snapshot
  → round with currencies.decimal_places (IDR whole rupiah)
  → ServicePurchase (fx_rate*, fx_rate_snapshot_id) + Invoice IDR
```

Same-currency (IDR→IDR) skips HTTP; no snapshot row.

## Checkout + fulfillment

```
Checkout → pending purchase + pending invoice
  → PG paid webhook/sync
  → ServiceFulfillmentService
       mark purchase paid + period_start/end
       ServiceEntitlementService activate/extend
         main: quantity = 1; expire other paid mains for user
         addon: quantity += 1 while active/grace (else reset to 1)
       file_hosting plan (main|addon) → FileHostingQuotaRecomputeService
         write UserQuota denorm (source=service_recompute)
```

Free catalog SKU is blocked at checkout and skipped on fulfill.

## Subscription lifecycle (File Hosting main + addons)

| State | Upload | Quota |
|-------|--------|-------|
| **active** paid main and/or addons | OK | free + main + Σ(addon×qty) |
| **grace** (any paid FH line; ends_at passed, ≤7d) | **blocked** | effective bytes still from recompute (files kept); notify once |
| **expired** (grace_ends_at passed on a line) | recompute remaining lines | `enforceToLimit(effective)`; if no paid left → free 5 GiB |

Commands (also scheduled hourly):

- `service:process-entitlements` → enter grace for due actives; expire grace → recompute → purge to **effective** limit (not always free-only)

Purge rule: sort `created_at ASC, id ASC`; accumulate sizes; soft-delete DB + hard-delete S3 for overflow. See [file-hosting](../file-hosting/README.md).

## Permissions (core RBAC)

| Permission | Typical roles |
|------------|---------------|
| `view service` | Root, Admin, Moderator, Management, User |
| `manage service` | Root, Admin |
| `purchase service` | Root, Admin, Moderator, User (also accepts `initiate payment`) |

## API (`auth:api`)

| Method | Path | Action |
|--------|------|--------|
| GET/POST | `/api/v1/services` | List / create (prices nested; currency must exist) |
| GET/PUT/DELETE | `/api/v1/services/{id}` | Show / update / soft-delete |
| POST | `/api/v1/services/{id}/checkout` | Body `{ "currency": "USD" }` → purchase + pending invoice |
| GET | `/api/v1/service-purchases/{id}` | Purchase + invoice + **entitlement** (if any) |

Catalog resource fields of note: `plan_kind`, `storage_bytes`.

Purchase resource fields of note: `period_start`, `period_end`, `fx_rate_snapshot_id`, `entitlement.{status,quantity,ends_at,grace_ends_at,is_permanent}`.

## Web (Blade)

- Catalog index/create/edit/show (API consumer); billing interval includes quarterly; form fields `plan_kind` + `storage_bytes`
- Purchase hub: payment panel + period + entitlement status/quantity; grace banner when `status=grace`

## PaymentGateway integration

Morph alias: `service_purchase` → `ServicePurchase`.

- `InvoiceAttachableService::createPendingInvoiceForServicePurchase`
- Midtrans/iPaymu synchronizers call `ServiceFulfillmentService` on paid/cancel/fail
- iPaymu return can redirect to `service-purchases.show`
- Product subscription ≠ PG `Subscription` model

## Config / env

| Key | Default | Role |
|-----|---------|------|
| `FRANKFURTER_BASE_URL` | `https://api.frankfurter.app` | FX HTTP client |
| `FRANKFURTER_TIMEOUT` | `10` | seconds |
| `FRANKFURTER_CACHE_TTL` | `3600` | L1 + “fresh DB snapshot” window (seconds) |
| `SERVICE_GRACE_DAYS` | `7` | `config/service.php` → grace after `ends_at` |
| `SERVICE_SETTLEMENT_CURRENCY` | `IDR` | PG settlement currency |
| `PAYMENT_DEFAULT_BUYER_PHONE` | (see payment docs) | iPaymu buyer phone fallback |

Documented in `.env.example` for Frankfurter; grace/settlement via `config/service.php` + optional env.

**Deploy order:** migrate through `2026_07_18_100001` (plan_kind/storage_bytes/quantity) → `CurrencySeeder` → `ServiceCatalogSeeder`.

**Scheduler:** `service:process-entitlements` hourly (`routes/console.php`). Requires `schedule:work` / cron.

## Manual verify

1. Buy main 1 TB → quota = 1 TiB + free 5 GiB; state `paid`; one active main  
2. Buy main 2 TB while 1 TB active → old main expired; quota = 2 TiB + free  
3. Buy +100 GB ×3 → entitlement `quantity=3`; quota includes 300 GiB  
4. No main + +500 GB ×2 → free + 1000 GiB  
5. Force any paid `ends_at` past → grace: upload 422; bell notify  
6. Force `grace_ends_at` past → recompute remaining lines + purge to effective limit  
7. Checkout USD → row in `exchange_rate_snapshots` + IDR invoice  

## Tests

```powershell
composer test -- --filter=Service
composer test -- --filter=EnforceFreeTierQuota
```

Coverage: FX snapshot DB + cache, entitlement period/renew/quantity, main replace, addon stack matrix, grace upload block, expire → recompute + purge, checkout FX→IDR. Matrix: `FileHostingPlanQuotaMatrixTest`.

## Related

- [payment-gateway](../payment-gateway/README.md) — settle IDR; product sub ≠ PG Subscription
- [file-hosting](../file-hosting/README.md) — quota recompute + states paid/grace/free
- [notification](../notification/README.md) — grace/expired types
- [platform/architecture.md](../../platform/architecture.md)
- Changelog: [pending/2026-07-16-service-entitlements-fx-db-grace.md](../../changelog/pending/2026-07-16-service-entitlements-fx-db-grace.md)
