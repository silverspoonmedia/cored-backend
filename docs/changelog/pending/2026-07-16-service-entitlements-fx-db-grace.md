# Service entitlements + DB FX + File Hosting grace/purge

## Summary

Monolith Service lifecycle is real SaaS billing (not POC forever-unlimited): **DB currencies + rate snapshots**, **subscription vs one-time entitlements** with period and manual renew, **File Hosting grace** (7 days, block upload, notify) then **free-tier purge** (oldest-first soft-delete DB + hard-delete S3). Settlement remains **IDR** for payment rails. Product lifecycle is `ServiceEntitlement` — not PaymentGateway `Subscription`.

## Session metadata

| Item | Detail |
|------|--------|
| Date | 2026-07-16 |
| Keyword trigger | `upstream documentation session` |
| Related commits / PR | uncommitted — Service entitlements + FX DB + FH grace/purge + docs close-out |

## Delegation

### Monolith (done in this session)

- Tables: `currencies`, `exchange_rate_snapshots`, `service_entitlements`; purchase `period_start` / `period_end` / `fx_rate_snapshot_id`
- `ExchangeRateService`: L1 cache → fresh DB snapshot → Frankfurter + persist; round via `currencies.decimal_places` (IDR whole)
- `BillingInterval`: monthly / quarterly / yearly / once; renew extends `max(ends_at, now) + period`
- `ServiceEntitlementService` + `ServiceFulfillmentService` (paid → activate/extend + FH unlimited denorm)
- `QuotaService` entitlement-aware; grace upload block (`quota_grace_blocked`)
- `EnforceFreeTierQuotaService` + `service:process-entitlements` (hourly)
- Notifications: `service_subscription_grace`, `service_subscription_expired` (personal)
- API: purchase resource includes period + entitlement; checkout/price currency validated against active `currencies`
- Web: purchase hub period/entitlement + grace banner; form interval quarterly
- Seed: `CurrencySeeder` before `ServiceCatalogSeeder`
- i18n en/id/ja (services, file-hosting grace, notifications)
- Living docs: service, file-hosting, payment-gateway, notification, architecture, docs index, AGENTS

### Monolith Gateway

- [ ] Confirm proxy pass-through for Service routes (if not already from prior entry):
  - `GET/POST /api/v1/services`
  - `GET/PUT/DELETE /api/v1/services/{id}`
  - `POST /api/v1/services/{id}/checkout`
  - `GET /api/v1/service-purchases/{id}`
- [ ] Do not strip new purchase fields: `entitlement`, `period_start`, `period_end`, `fx_rate_snapshot_id`
- [ ] No new Monolith aggregate required for grace (lifecycle is scheduler); optional checkout context only if Vue already aggregates invoice channels
- [ ] Tests for proxy paths if routes newly registered

### Monolith Frontend

- [ ] Purchase / “my subscription” UI: entitlement status, `ends_at`, grace banner copy
- [ ] Renew = same catalog SKU `POST .../checkout` with `{ "currency": "..." }` (no auto-charge this phase)
- [ ] File Hosting upload: surface `quota_grace_blocked` (HTTP 422) when grace
- [ ] Catalog: support `billing_interval` value `quarterly`; currency must be active catalog currency
- [ ] i18n parity for service entitlement + FH grace strings (Monolith keys under `lang/*/api|web/services.php`, `api/file-hosting.php`, `api/notifications.php`)

## API contract

**Before (POC purchase show):**

```json
{
  "id": "uuid",
  "display_currency": "USD",
  "display_amount": 10,
  "settlement_currency": "IDR",
  "settlement_amount": 180000,
  "fx_rate": 18000,
  "fx_provider": "frankfurter",
  "status": "paid",
  "invoice": { "status": "paid", "currency": "IDR" }
}
```

Paid FH unlimited was permanent (`file_hosting_user_quotas.quota_bytes = 0`) with no period clock.

**After:**

```json
{
  "id": "uuid",
  "display_currency": "USD",
  "display_amount": 10,
  "settlement_currency": "IDR",
  "settlement_amount": 180000,
  "fx_rate": 18000,
  "fx_provider": "frankfurter",
  "fx_rate_snapshot_id": "uuid-or-null",
  "period_start": "2026-07-16T12:00:00+00:00",
  "period_end": "2026-08-16T12:00:00+00:00",
  "status": "paid",
  "entitlement": {
    "id": "uuid",
    "status": "active",
    "starts_at": "2026-07-16T12:00:00+00:00",
    "ends_at": "2026-08-16T12:00:00+00:00",
    "grace_ends_at": null,
    "is_permanent": false
  },
  "invoice": { "status": "paid", "currency": "IDR", "invoiceable_type": "service_purchase" }
}
```

| Endpoint | Change |
|----------|--------|
| `POST /api/v1/services/{id}/checkout` | Currency must exist in `currencies` and be active; purchase may set `fx_rate_snapshot_id` |
| `GET /api/v1/service-purchases/{id}` | Adds `period_*`, `fx_rate_snapshot_id`, nested `entitlement` (null if none) |
| Service catalog create/update | `billing_interval` includes `quarterly`; price currency DB-validated |
| FH upload (existing) | May return `quota_grace_blocked` when entitlement in grace |

Checkout body shape unchanged: `{ "currency": "USD" }`. Invoice channel/Snap URLs unchanged.

## Supersedes

- Partial overlap with [2026-07-16-service-domain-fx-checkout.md](./2026-07-16-service-domain-fx-checkout.md) on FX/checkout baseline — **keep both**: base entry covers domain introduction + route proxy; this entry covers lifecycle, DB rates, grace/purge, expanded purchase JSON. Downstream sync should apply **both** (or consolidate later via `sync consolidation`).

## Breaking change?

| Breaking? | Mitigation |
|-----------|------------|
| Soft (additive purchase JSON + stricter currency validation) | Clients ignoring unknown fields OK. Admin prices/checkout with free-text currency codes fail until currency seeded. Existing unlimited-only users without entitlement rows still have denorm `UserQuota` until new pay or lifecycle expire path runs. |
| Deploy | Migrations `2026_07_16_100001`–`100003` then `CurrencySeeder` before traffic |

## Test plan

| Repo | Command |
|------|---------|
| Monolith | `composer test -- --filter=Service`; `composer test -- --filter=EnforceFreeTierQuota` |
| Gateway | `php artisan test` after proxy work |
| Frontend | `npm run test:unit -- --run`; `npm run build` after Vue work |

Monolith scenarios already covered: FX snapshot persist/cache, once permanent, monthly period, renew extend, grace block upload, expire free tier, purge A+B keep C drop.

## Status

| Field | Value |
|-------|-------|
| Status | `pending` |
| Synced | — |
