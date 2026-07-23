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
5. **Paid perk gate** for File Hosting Collaborative Works (workspace create/invite/team upload) via `hasActivePaidFileHosting` (storage main/addon only). Extra owned team slots via catalog SKU `file-hosting.addon.workspace-slot.monthly`

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
| `service_purchases` | Invoiceable money event: display/list/credit/charge, `change_type`, `surplus_days`, settlement + FX + `period_*` |
| `service_entitlements` | One row per user+service: `active` / `grace` / `expired` / `cancelled` + **`quantity`** |

### File Hosting catalog (seed)

| Code | Name | Kind | Storage | USD/mo | Notes |
|------|------|------|---------|--------|-------|
| `file-hosting.plan.1tb.monthly` | Standard 1 TB | main | 1 TiB | 24.95 | Exclusive paid main |
| `file-hosting.plan.2tb.monthly` | Standard 2 TB | main | 2 TiB | 54.95 | Exclusive paid main |
| `file-hosting.addon.100gb.monthly` | Standard +100 GB | addon | 100 GiB | 2.95 | Stackable qty; fixed anniversary |
| `file-hosting.addon.500gb.monthly` | Standard +500 GB | addon | 500 GiB | 14.50 | Stackable qty; fixed anniversary |
| `file-hosting.addon.workspace-slot.monthly` | Team Workspace Slot | addon | 0 | 1.00 | +1 owned team slot per qty; not storage; stack anniversary with storage addons |

**Free tier is not a catalog SKU.** Runtime free always from `config('file-hosting.default_quota_bytes')` (5 GiB), always stacked. Seeder deletes any legacy `file-hosting.plan.free` row. Legacy `file-hosting.subscription.monthly` (unlimited POC) is archived on seed.

**Helpers:** `Service::isFileHostingStoragePlan()` requires `storage_bytes > 0` (excludes slot SKU). `Service::isAddonPlan()` includes storage **and** slot addons. `Service::isWorkspaceSlotAddon()` for slot-only checks. Paid collab gate queries still use `storage_bytes > 0`.

**Effective FH quota:**

```
effective_bytes =
  free_tier_bytes                                          # always (config 5 GiB)
  + (active_or_grace main.storage_bytes)                   # at most one paid main
  + sum(active_or_grace storage_addon.storage_bytes * quantity)
  # workspace-slot addon: storage_bytes = 0 → no GiB contribution
```

**Owned team slots (File Hosting):**

```
owned_limit =
  (hasActivePaidFileHosting ? base_owned_workspace_slots : 0)   # default 10
  + sum(active_or_grace workspace_slot.quantity)
```

Constants / codes: `App\Domain\Service\Support\FileHostingCatalog`.

### Main plan change (renew / upgrade / downgrade)

Checkout classifies via `MainPlanChangeCalculator` (compare current active/grace paid main vs target `storage_bytes`):

| `change_type` | When | Charge |
|---------------|------|--------|
| `new` | No current paid main | Full list |
| `renew` | Same `service_id` | Full list; stack `ends_at` from remaining end |
| `upgrade` | Target storage > current | `max(0, list − unused_credit)` |
| `downgrade` | Target storage < current | same; surplus credit → **extra days** on new plan |

**Billing period (FH storage plans):** fixed **+30 days** (`FileHostingBillingPeriod`), not calendar `addMonth`. Credit divisor uses the same 30 days.

**Unused gap:**

```
unused_credit = (remaining_days / 30) * last_paid_list_amount
net_charge    = max(0, list_new − unused_credit)
surplus_days  = floor(max(0, unused_credit − list_new) / list_new * 30)
```

- Credit base = **`list_amount`** of last paid purchase for that entitlement (never net `charge`/`display` after discount). Legacy rows without `list_amount` fall back to `display_amount`, then catalog list.
- `$0` charge: **immediate fulfill** (no Midtrans invoice). Next switch still credits from stored **list**, not the $0 charge.
- Paid up/down: expire other mains; `ends_at = now + 30 days + surplus_days`.
- Purchase stores: `change_type`, `list_amount`, `credit_amount`, `charge_amount` (= `display_amount`), `surplus_days`, `previous_entitlement_id`.

### Addon fixed anniversary + stack renewal

| Event | Quantity | `ends_at` |
|-------|----------|-----------|
| First addon buy (or rebuy after expired) | 1 | `now + 30 days` (or shared anniversary if user already has another active addon) |
| Additional unit buy (same or other addon SKU) | `quantity++` | **unchanged** (shared clock) |
| Anniversary due **or** pending stack renewal | — | **Unit checkout blocked** (422); pay stack first |
| Anniversary due | unchanged | Scheduler creates **one** pending invoice for full stack |

**Addon renewal invoice** (`service_addon_renewals` + lines, morph `service_addon_renewal`):

```
display_total = Σ(quantity × unit_list_price) across active/grace addons
→ FX → pending Invoice
on paid → extend ALL addon ends_at by +30 days; quantities stay
```

Command (hourly): `service:process-addon-renewals`. No auto-charge. Unpaid renewals do not cancel access in this task (grace path still from entitlement lifecycle), but **block** further addon unit buys until paid or stack no longer due.

**Not** PG `/subscriptions` — that table is free-text demo invoiceable only. Product SaaS lives in Service domain.

## Multi-currency / FX

```
Display charge (list or net after credit)
  → ExchangeRateService
       prefer L1 cache → fresh DB snapshot within TTL → else Frankfurter + INSERT snapshot
  → round with currencies.decimal_places (IDR whole rupiah)
  → ServicePurchase / ServiceAddonRenewal + Invoice IDR
```

Same-currency (IDR→IDR) skips HTTP; no snapshot row. Zero-charge skips FX HTTP (`provider=zero_charge`).

## Checkout + fulfillment

```
Checkout → MainPlanChangeCalculator quote
  → if charge > 0: pending purchase + pending invoice
  → if charge = 0: purchase + immediate fulfill
  → PG paid webhook/sync (when invoice)
  → ServiceFulfillmentService
       activate/extend entitlement
         main renew: stack ends_at; up/down: now+period+surplus; expire other mains
         addon unit: qty++; ends_at frozen / shared
       FileHostingQuotaRecomputeService
       ensureProductActive (fulfillment_target → product_activations row kept Active)
```

Fulfillment hook: `ServiceFulfillmentService::ensureProductActive` maps the Service catalog `fulfillment_target` (e.g. `file_hosting`) to a product key via `ProductRegistry::productKeyByFulfillmentTarget` and keeps the implicit product activation Active when an entitlement activates. Explicit products are **not** auto-activated here — admin approval remains the gate. See [Product Activations](../product-activations/README.md).

## Subscription lifecycle (File Hosting main + addons)

| State | Upload | Quota |
|-------|--------|-------|
| **active** paid main and/or addons | OK | free + main + Σ(addon×qty) |
| **grace** (any paid FH line; ends_at passed, ≤7d) | **blocked** | effective bytes still from recompute (files kept); notify once |
| **expired** (grace_ends_at passed on a line) | recompute remaining lines | `enforceToLimit(effective)`; if no paid left → free 5 GiB |

Commands (also scheduled hourly):

- `service:process-entitlements` → enter grace for due actives; expire grace → recompute → purge to **effective** limit
- `service:process-addon-renewals` → pending full-stack addon invoices when anniversary due

Purge rule: sort `created_at ASC, id ASC`; accumulate sizes; soft-delete DB + hard-delete S3 for overflow. See [file-hosting](../file-hosting/README.md).

* limit (not always free-only)

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
| POST | `/api/v1/services/{id}/checkout` | Body `{ "currency": "USD" }` → purchase (+ invoice if charge > 0); quote fields on purchase |
| GET | `/api/v1/service-purchases/{id}` | Purchase + invoice + **entitlement** (if any) |

Catalog resource fields of note: `plan_kind`, `storage_bytes` (+ `storage_bytes_human`), prices as `amount` + `amount_formatted`.

Purchase resource fields of note: `change_type`, raw money (`list_amount`, `credit_amount`, `charge_amount`, `display_amount`, `settlement_amount`) each with `*_formatted`, `surplus_days`, period timestamps with `*_formatted` / `*_human`, `entitlement.{status,quantity,ends_at,grace_ends_at,is_permanent}` (dates dual-field). Display contract: [platform/crud presentation](../../platform/crud.md#api-resource-presentation-dual-fields).

## Web (Blade)

- Catalog index/create/edit/show (API consumer); form fields `plan_kind` + `storage_bytes`
- Purchase hub: payment panel + quote breakdown (list/credit/charge/surplus) + entitlement status/quantity; grace banner when `status=grace`
- **Not** product UI on PG `/subscriptions`

## PaymentGateway integration

Morph aliases:
- `service_purchase` → `ServicePurchase`
- `service_addon_renewal` → `ServiceAddonRenewal`
- `subscription` → PG demo `Subscription` only

- `InvoiceAttachableService::createPendingInvoiceForServicePurchase` / `…ForServiceAddonRenewal`
- Midtrans/iPaymu synchronizers fulfill purchase or addon renewal on paid
- iPaymu return can redirect to `service-purchases.show`
- Product SaaS ≠ PG `Subscription` model

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

**Deploy order:** migrate through `2026_07_18_110002` (purchase quote fields + addon renewals) → `CurrencySeeder` → `ServiceCatalogSeeder` (removes free SKU if present).

**Scheduler** (`routes/console.php`): `service:process-entitlements` hourly; `service:process-addon-renewals` hourly. Requires `schedule:work` / cron.

## Manual verify

1. Buy main 1 TB → quota = 1 TiB + free 5 GiB; state `paid`  
2. Mid-cycle upgrade/downgrade → credit applied; $0 path fulfills without PG  
3. Buy +100 GB ×3 same day → `quantity=3`, **same** `ends_at` (not +3 months)  
4. Second addon SKU mid-cycle → shares anniversary  
5. Force addon `ends_at` past → `service:process-addon-renewals` → one pending invoice full stack; pay extends all  
6. Force main grace/expire → recompute + purge to effective limit  
7. Checkout USD with charge → FX snapshot + IDR invoice  

## Tests

```powershell
composer test -- --filter=Service
composer test -- --filter=EnforceFreeTierQuota
composer test -- --filter=FileHostingBilling
```

Coverage: unused-gap calculator (list-based credit), zero-charge fulfill, main renew/up/down, fixed 30-day FH periods, addon fixed anniversary, block unit buy when stack due/pending, addon stack renewal invoice, quota matrix, grace/expire, collaborative works paid gate. Key tests: `MainPlanChangeCalculatorTest`, `MainPlanFairnessFulfillmentTest`, `AddonAnniversaryTest`, `ServiceCheckoutZeroChargeTest`, `FileHostingPlanQuotaMatrixTest`, `FileHostingAdminJourneyScenarioTest`, `WorkspaceCollaborativeWorksTest`.

**Use-case matrix (45):** permanent regression suite — `FileHostingBillingTameUseCasesTest`, `FileHostingBillingNormalUseCasesTest`, `FileHostingBillingAnomalyUseCasesTest` + `FileHostingBillingScenarioHarness`.

## Related

- [payment-gateway](../payment-gateway/README.md) — settle IDR; product sub ≠ PG Subscription
- [file-hosting](../file-hosting/README.md) — quota recompute + states paid/grace/free
- [notification](../notification/README.md) — grace/expired types
- [platform/architecture.md](../../platform/architecture.md)
