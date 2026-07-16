# Service domain + FX checkout + Playground Order retirement

## Summary

Monolith gains a **core Service** domain: multi-currency catalog, Frankfurter FX to IDR at checkout, `ServicePurchase` invoiceable, File Hosting unlimited quota on paid. Playground Order public sell path removed (models/migrations retained).

## Session metadata

| Item | Detail |
|------|--------|
| Date | 2026-07-16 |
| Keyword trigger | mid-task implementation (plan approved) |
| Related commits / PR | uncommitted — Service domain + payment morph + docs |

## Delegation

### Monolith (done in this session)

- Core domain `Service` (`PLATFORM_CORE`): models `Service`, `ServicePrice`, `ServicePurchase`; migrations; factories
- Catalog CRUD API/Web + i18n (`en`/`id`/`ja`) + RBAC (`view|manage|purchase service`)
- `ExchangeRateService` (Frankfurter) + `ServiceCheckoutService` + `ServiceFulfillmentService`
- PaymentGateway: morph `service_purchase`; invoice attachable; Midtrans/iPaymu sync → fulfillment
- PaymentGateway: `InvoiceCustomerDetailsResolver` autofills buyer/customer from ServicePurchase user profile (iPaymu requires name/email/phone; phone falls back to `PAYMENT_DEFAULT_BUYER_PHONE`)
- File Hosting: `file_hosting_user_quotas` + `QuotaService` override; paid → unlimited
- Seed: `file-hosting.subscription.monthly` USD 10
- Playground Order routes/menu/Blade sell surface retired; payment tests retargeted
- Docs: `docs/domains/service/`, architecture, payment-gateway, file-hosting, playground, index

### Monolith Gateway

- [ ] Proxy/pass-through for new Monolith routes when SPA needs them:
  - `GET/POST /api/v1/services`
  - `GET/PUT/DELETE /api/v1/services/{id}`
  - `POST /api/v1/services/{id}/checkout`
  - `GET /api/v1/service-purchases/{id}`
- [ ] Optional aggregate: service catalog + checkout context (channels) if Vue combines fetches
- [ ] Stop documenting/proxying playground order sell as primary payment demo if still referenced
- [ ] Tests for new proxy paths

### Monolith Frontend

- [ ] Service catalog list/detail + checkout flow against Gateway
- [ ] Purchase payment hub (reuse channel checkout patterns from orders/invoices)
- [ ] i18n for service/purchase strings
- [ ] Remove or re-route any playground **orders** sell UI that assumed Monolith Order invoiceable

## API contract

**Before:**

Playground sell path (non-prod):

- `POST /api/playground/orders` → free-text lines → pending invoice morph `order`, currency hard-coded IDR

**After:**

Service sell path (v1):

- `GET/POST /api/v1/services` — catalog with nested `prices[]`
- `POST /api/v1/services/{id}/checkout` body `{ "currency": "USD" }` →

```json
{
  "status": "success",
  "data": {
    "id": "uuid",
    "display_currency": "USD",
    "display_amount": 10,
    "settlement_currency": "IDR",
    "settlement_amount": 180000,
    "fx_rate": 18000,
    "fx_provider": "frankfurter",
    "status": "pending",
    "invoice": {
      "currency": "IDR",
      "customer_gross_total": 180000,
      "status": "pending",
      "invoiceable_type": "service_purchase"
    }
  }
}
```

- `GET /api/v1/service-purchases/{id}` — payment show payload

Invoice channel/Snap checkout endpoints unchanged; invoiceable may be `service_purchase`.

## Supersedes

- Playground Order as documented primary payment POC (see playground README)

## Follow-up (same product area)

Lifecycle / DB FX / grace-purge for Service is documented in [2026-07-16-service-entitlements-fx-db-grace.md](./2026-07-16-service-entitlements-fx-db-grace.md) — apply that entry after (or with) this one on downstream sync.

## Breaking change?

| Breaking? | Mitigation |
|-----------|------------|
| Yes (for consumers of playground orders sell path) | Routes removed outside residual code; migrate to Service checkout. Historical `order` morph still readable. |

## Test plan

| Repo | Command |
|------|---------|
| Monolith | `composer test -- --filter=Service`; also InvoiceActiveCheckout, IpaymuReturn, PaymentCheckoutWeb, InvoicePaymentInitiation |
| Gateway | `php artisan test` after proxy work |
| Frontend | `npm run test:unit -- --run`; `npm run build` after Vue work |

## Status

| Field | Value |
|-------|-------|
| Monolith | Done |
| Gateway | Pending downstream sync |
| Frontend | Pending downstream sync |
