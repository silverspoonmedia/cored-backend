# Service — sellable catalog, entitlements, multi-currency FX checkout

**Core** platform domain for SaaS services sold by the project (subscriptions + one-time). Replaces the Playground Order sell path as the invoiceable source for payment checkout.

| | |
|--|--|
| Code | `app/Domain/Service/` |
| Registry | `core => true` in [`config/domains.php`](../../../config/domains.php); listed in `DomainManager::PLATFORM_CORE` |
| API | `routes/api/v1/service.php` → `/api/v1/services`, `/api/v1/service-purchases` |
| Web | `routes/web/service.php` → `services.*`, `service-purchases.*` |
| FX | Frankfurter → **DB** `exchange_rate_snapshots` + L1 cache; settle **IDR** |
| Config | `config/service.php` (`grace_days`, `settlement_currency`) |
| Seeder | `CurrencySeeder` then `ServiceCatalogSeeder` |
| Scheduler | `service:process-entitlements` hourly |

## Why this domain exists

1. **Catalog** of sellable services with multi-currency list prices (codes in `currencies`)
2. **Checkout** that settles to **IDR** for Midtrans/iPaymu (rate history in DB)
3. **Entitlements** with period (subscription) or permanent (one-time)
4. **Fulfillment** into product domains (File Hosting unlimited while active)

**Not** PaymentGateway `Subscription` (free-text demo morph). Product lifecycle lives here.

Money convention: **decimal(15,2)** (same as invoices). Do not use Product `unit_price_cents`.

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
| `services` | Catalog: `code`, `billing_interval`, `fulfillment_target`, `entitlement`, `status` |
| `service_prices` | List price per currency (validated against `currencies`) |
| `service_purchases` | Invoiceable money event: display + settlement + FX + optional `period_*` |
| `service_entitlements` | One row per user+service: `active` / `grace` / `expired` / `cancelled` |

POC seed: `file-hosting.subscription.monthly` — USD 10 / month — `fulfillment_target=file_hosting`, `entitlement=unlimited_quota`.

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
       file_hosting + unlimited_quota → UserQuota denorm (quota_bytes=0)
```

## Subscription lifecycle (File Hosting unlimited)

| State | Upload | Quota |
|-------|--------|-------|
| **active** | OK | unlimited (`0`) |
| **grace** (ends_at passed, ≤7d) | **blocked** | files kept; notify once |
| **expired** (grace_ends_at passed) | free tier | `default_quota_bytes` (5 GiB); purge excess |

Commands (also scheduled hourly):

- `service:process-entitlements` → enter grace for due actives; expire grace + purge

Purge rule (oldest first retain free tier): sort `created_at ASC, id ASC`; accumulate sizes; soft-delete DB + hard-delete S3 for overflow. See [file-hosting](../file-hosting/README.md).

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

Purchase resource fields of note: `period_start`, `period_end`, `fx_rate_snapshot_id`, `entitlement.{status,ends_at,grace_ends_at,is_permanent}`.

## Web (Blade)

- Catalog index/create/edit/show (API consumer); billing interval includes quarterly
- Purchase hub: payment panel + period + entitlement status; grace banner when `status=grace`

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

**Deploy order:** migrate `2026_07_16_100001`…`100003` → `CurrencySeeder` → catalog prices (currency FK validation).

**Scheduler:** `service:process-entitlements` hourly (`routes/console.php`). Requires `schedule:work` / cron.

## Manual verify

1. Buy monthly FH → unlimited; entitlement `ends_at` ~+1 month  
2. Force `ends_at` past → run command → grace: upload 422; bell notify  
3. Force `grace_ends_at` past → free tier + oldest-first purge  
4. Renew in grace (same SKU checkout) → active + unlimited restored  
5. Checkout USD → row in `exchange_rate_snapshots` + IDR invoice  

## Tests

```powershell
composer test -- --filter=Service
composer test -- --filter=EnforceFreeTierQuota
```

Coverage: FX snapshot DB + cache, entitlement period/renew, grace upload block, expire → free tier, purge oldest-first, checkout FX→IDR.

## Related

- [payment-gateway](../payment-gateway/README.md) — settle IDR; product sub ≠ PG Subscription
- [file-hosting](../file-hosting/README.md) — quota states + purge
- [notification](../notification/README.md) — grace/expired types
- [playground](../playground/README.md) — Order sell path retired
- [platform/architecture.md](../../platform/architecture.md)
- Changelog: [pending/2026-07-16-service-entitlements-fx-db-grace.md](../../changelog/pending/2026-07-16-service-entitlements-fx-db-grace.md)
