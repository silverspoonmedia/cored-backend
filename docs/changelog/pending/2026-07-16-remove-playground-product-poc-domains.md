# Changelog entry

## Summary

Removed POC domains **Playground** and **Product** (plus Approval leftovers). Kernel owns `GET /api/ping`. PaymentGateway invoiceables are **`service_purchase`** + **`subscription` only**. Primary product domain is **FileHosting**. Residual cleanup rewrote migration history (deleted order/attachment/product/approval create+drop files), stripped `ATTACHMENT_UPLOAD_*` env/catalog, and purged orphan order JS.

## Session metadata

| Item | Detail |
|------|--------|
| Date | 2026-07-16 |
| Keyword trigger | `upstream documentation session` |
| Related commits / PR | Uncommitted — local working tree (domain removal + residual fossil cleanup) |

## Delegation

### Monolith (done in this session)

- Domain registry: core only + FileHosting (`kind => product`, `locked => true`) in `config/domains.php`
- Deleted `app/Domain/{Playground,Product,Approval}`, providers, routes, tests, seeders, factories, lang, Blade
- `GET /api/ping` → `App\Http\Controllers\API\PingController` (`routes/api.php` only)
- PaymentGateway: no `order` morph; no `*ForOrder` / `syncOrderIfNeeded` / `isForOrder()`
- ActivityLog: no `order` subject alias; `log_name` allowlist without `order`
- Residual: deleted fossil migrations (orders, attachments, product_items, approval_* create+drop); deleted `resources/js/orders` + vite entry; removed `ATTACHMENT_UPLOAD_*` from env examples + `EnvironmentVariableCatalog`
- Docs: architecture, api-routing, crud, environment, spa-gateway, blade-admin, identifiers, service/PG/FH READMEs, root README; dropped domain READMEs for playground/product

### Monolith Gateway

- [ ] Stop proxying `/api/playground/*` (gone upstream)
- [ ] Stop proxying `/api/v1/product/*` (and product-items paths if any)
- [ ] Keep `/api/ping` pass-through if used for health
- [ ] Remove SPA vue-route transforms / menu entries for playground orders/users and product items if present

### Monolith Frontend

- [ ] Remove SPA screens/routes/API clients for playground users/orders and product items
- [ ] Do not call removed API paths
- [ ] Drop i18n keys only used by those surfaces (if any)

## API contract

**Before:**

```json
{
  "global": ["/api/ping", "/api/playground/*"],
  "v1": ["/api/v1/product/*", "..."],
  "invoiceable_morphs": ["order", "subscription", "service_purchase"]
}
```

**After:**

```json
{
  "global": ["/api/ping"],
  "v1": ["no /product/*", "..."],
  "invoiceable_morphs": ["subscription", "service_purchase"],
  "ping_message_key": "api/common.pong"
}
```

| Change | Path / shape | Notes |
|--------|--------------|-------|
| Removed | `/api/playground/*` | Entire non-prod demo surface |
| Removed | `/api/v1/product/*` | Product items stub |
| Kept | `GET /api/ping` | Kernel controller; message `api/common.pong` |
| Morph | Invoiceables | `service_purchase`, `subscription` only |

## Config / env

- Removed `config/attachments.php` (Playground-only)
- Removed `ATTACHMENT_UPLOAD_*` from `.env.example` and profile templates + catalog
- `s3Custom` / `FILE_HOSTING_*` retained (FileHosting)
- Domain registry: only product domain = FileHosting (`locked => true`)

## Database

- **Rewrite history:** deleted migration files for `order_master`, `order_detail`, `attachments`, `attachment_uploads`, `product_items`, and all `approval_*` create + drop. Fresh `migrate` does not create those tables.
- **Operator (env that already applied those migrations):** either `ALLOW_DESTRUCTIVE_DB=true` + `php artisan migrate:fresh --seed` (local only), or manually drop orphan tables + delete matching rows from `migrations`.

## Supersedes

- `2026-07-15-product-domain-items.md` — Product domain + `/api/v1/product/items` no longer exist; Gateway/Frontend must **not** implement that entry
- Soft-supersedes residual claims in older service/approval pending notes that still mention playground order sell path or `composer test --filter=Playground`

## Breaking change?

| Breaking? | Mitigation |
|-----------|------------|
| Yes (for consumers of playground/product APIs) | Stop calling removed paths; use Service checkout + FileHosting product surface. Historical `order` invoice rows may not resolve morph. DB rewrite requires operator steps above. |

## Test plan

| Repo | Command |
|------|---------|
| Monolith | `composer test -- --filter=DomainCommandsTest` |
| Monolith | `composer test -- --filter=ApiRoutingRegistration` |
| Monolith | `composer test -- --filter=Payment` |
| Monolith | `composer test -- --filter=ActivityLog` |
| Monolith | `composer test` (env catalog may still fail on unrelated `FILE_HOSTING_*` / S3 EU keys — pre-existing) |
| Gateway | After sync: assert no proxy for `/api/playground`, `/api/v1/product` |
| Frontend | After sync: no routes/clients for removed surfaces |

## Status

| Field | Value |
|-------|-------|
| Status | `pending` |
| Synced | — |
