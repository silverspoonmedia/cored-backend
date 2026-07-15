# Product — catalog items (locked product domain)

First locked product module under `/api/v1/product/*`. Not platform core — removable only after unlock in `config/domains.php`.

Code: `app/Domain/Product/`. Routes: `routes/api/v1/product.php`, `routes/web/product.php`.

## Surface

| Layer | Implementation |
|-------|----------------|
| Model | `Item` (`product_items`, UUID v7, soft deletes, `LogsActivity`) |
| Service | `ItemService` + `EloquentCrudRepository` + `DuplicateSkuException` |
| API | `/api/v1/product/items` — auth JWT + product permissions |
| Blade | `product-items.*` proxies API via `ApiClient` |
| RBAC | `ProductRoleSeeder` — `domain_kind = product` |

## Product H-RBAC

| Role | Level | Permissions |
|------|-------|-------------|
| Product Admin | 100 | view/create/edit/delete product item |
| Product Editor | 200 | view/create/edit product item |
| Product Viewer | 300 | view product item |

- Names are product-prefixed (never `Admin` / `view user`).
- Levels scoped by `domain_kind`; do not drive platform user-management hierarchy.
- Seeder also grants item permissions to **core** `Admin` (cross-kind pivot) so demo admin can manage items without a product role.

See [identity README — Core vs product RBAC](../identity/README.md#core-vs-product-rbac).

## API

| Method | URI | Permission |
|--------|-----|------------|
| GET | `/api/v1/product/items` | `view product item` |
| GET | `/api/v1/product/items/{item}` | `view product item` |
| POST | `/api/v1/product/items` | `create product item` |
| PUT/PATCH | `/api/v1/product/items/{item}` | `edit product item` |
| DELETE | `/api/v1/product/items/{item}` | `delete product item` |

`{item}` = UUID.

Payload fields: `sku`, `name`, `description`, `unit_price_cents`, `status` (`draft` \| `active` \| `archived`).

## Seed / menu

- `DatabaseSeeder` → `ProductRoleSeeder` after `RoleSeeder`
- `MenuSeeder` adds **Product items** under Administration (gated by product-capable roles)

## Tests

`tests/Domain/Product/` — `composer test -- --filter=Product`

## Related

- [platform/architecture.md](../../platform/architecture.md) — locked product domains
- [platform/crud.md](../../platform/crud.md) — layering checklist
- [domains/playground/README.md](../playground/README.md) — demo patterns only (do not ship production on Playground)
