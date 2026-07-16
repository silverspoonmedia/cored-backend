# Changelog entry

## Summary

Removed POC product domains **Playground** and **Product**. Core no longer depends on Playground Order. Primary product surface is **FileHosting**; sell path remains **Service** (`service_purchase`) + PaymentGateway.

## Session metadata

| Item | Detail |
|------|--------|
| Date | 2026-07-16 |
| Keyword trigger | work phase (plan implement) |
| Related commits / PR | Uncommitted — local working tree |

## Delegation

### Monolith (done in this session)

- Moved `GET /api/ping` to kernel `App\Http\Controllers\API\PingController` (`routes/api.php` only)
- Stripped PaymentGateway legacy `order` morph, `*ForOrder` attachable methods, Midtrans/iPaymu `syncOrderIfNeeded`, `Invoice::isForOrder()`, Order branch in customer resolver
- Removed ActivityLog `order` subject alias + resource label
- Deleted domains: `Playground`, `Product` (code, providers, routes, tests, seeders, factories, lang, Blade)
- Unregistered from `config/domains.php`, `bootstrap/providers.php`, `routes/api/v1.php`, `routes/web.php`
- Docs: architecture, crud, api-routing, environment, spa-gateway, service/PG/FH READMEs; dropped `docs/domains/playground` + `product`
- Tests updated: `DomainCommandsTest`, `ApiRoutingRegistrationTest`, `InvoiceCustomerDetailsResolverTest`

### Monolith Gateway

- [ ] Stop proxying `/api/playground/*` (routes gone upstream)
- [ ] Stop proxying `/api/v1/product/*` (and product-items SPA paths if any)
- [ ] Keep `/api/ping` pass-through if used for health

### Monolith Frontend

- [ ] Remove any SPA screens/routes for playground users/orders and product items
- [ ] Do not call removed API paths

## HTTP / contract deltas

| Change | Path / shape | Notes |
|--------|--------------|-------|
| Removed | `/api/playground/*` | Entire non-prod demo surface |
| Removed | `/api/v1/product/*` | Product items stub |
| Kept | `GET /api/ping` | Message key now `api/common.pong` (was playground pong) |
| Morph | Invoiceables | `service_purchase`, `subscription` only (no `order`) |

## Config / env

- Removed `config/attachments.php` (Playground-only)
- Domain registry: only product domain left is FileHosting (`locked => true`)

## Database

- Migrations **kept** as history (`orders`, `product_items`, `attachments`, …). No destructive wipe. Optional later: drop unused tables via new migrations.

## Tests

```powershell
composer test -- --filter=DomainCommandsTest
composer test -- --filter=ApiRoutingRegistration
composer test -- --filter=Payment
composer test
```

Domain/routing/payment suites green for this change. Unrelated env-catalog failures may exist outside this scope.
