# Changelog entry

## Summary

New locked **Product** domain with product-items CRUD under `/api/v1/product/items`, product RBAC catalog (`domain_kind = product`), Blade admin screens, and en/id/ja copy.

## Session metadata

| Item | Detail |
|------|--------|
| Date | 2026-07-15 |
| Keyword trigger | mid-task adaptation / session work |
| Related commits / PR | Uncommitted — local working tree |

## Delegation

### Monolith (done in this session)

- `domain:make Product` (locked) + full-stack items CRUD
- Migration `product_items`
- `ProductRoleSeeder` (product roles/permissions + core Admin pivot)
- Menu entry for product items
- Feature tests for API items
- Docs: `docs/domains/product/README.md`, architecture + index

### Monolith Gateway

- [ ] Proxy `/api/v1/product/items` (and nested UUID show/update/delete) if SPA needs them
- [ ] No auth middleware changes required beyond existing JWT + gateway secret pass-through

### Monolith Frontend

- [ ] Add API client + views only if SPA surfaces product catalog (optional until product UI is scheduled)
- [ ] Role types: product roles may appear on user payloads with `domain_kind: product` (see domain_kind changelog)

## API contract

**Before:** no product endpoints.

**After:**

```
GET    /api/v1/product/items
POST   /api/v1/product/items
GET    /api/v1/product/items/{uuid}
PUT    /api/v1/product/items/{uuid}
DELETE /api/v1/product/items/{uuid}
```

Item JSON shape:

```json
{
  "id": "uuid",
  "sku": "SKU-1",
  "name": "Sample",
  "description": null,
  "unit_price_cents": 1000,
  "status": "draft",
  "created_at": "…",
  "updated_at": "…"
}
```

## Supersedes

- None

## Breaking change?

| Breaking? | Mitigation |
|-----------|------------|
| No (new URLs) | Additive API surface |

## Test plan

| Repo | Command |
|------|---------|
| Monolith | `php vendor/bin/phpunit --filter "Product\|UserManagementTest\|LocaleParity"` |
| Gateway | Proxy smoke after sync |
| Frontend | N/A until SPA work |

## Status

| Field | Value |
|-------|-------|
| Status | pending |
| Downstream sync | not started |
