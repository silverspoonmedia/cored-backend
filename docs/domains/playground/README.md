# Playground — demo CRUD (non-production)

Reference implementations for simple CRUD and master-detail aggregates. **Disabled when `APP_ENV=production`.** Routes: `routes/api/playground.php` (from `routes/api.php`), `routes/web/playground.php`.

Code: `app/Domain/Playground/`. Domain registry: **locked** product demo (removable after unlock).

## Environment

Playground routes register only outside production. Do not treat as product API — real features go under `/api/v1/*`.

## Surfaces

### Users (simple CRUD)

`Route::apiResource` → `PlaygroundController` → `UserService` → `EloquentCrudRepository` + Identity `User` model. Namespaced route names `playground.users.*`.

Patterns: [platform/crud.md](../../platform/crud.md).

### Orders (master-detail)

| Piece | Role |
|-------|------|
| `Order` / `OrderDetail` | UUID PKs, cascade details |
| `OrderRepository` | `DB::transaction` create/update/delete with details |
| `OrderService` | Totals from lines, business rules, attachments |
| Attachments | Presigned S3 (`s3Custom`), staged uploads, polymorphic `attachments` |

Update semantics: **ID-preserving line sync** (id present → update; no id → insert; missing → delete). Completed orders may be frozen (409).

Order create may start approval workflow — [approval](../approval/README.md). Invoices: [payment-gateway](../payment-gateway/README.md).

### Attachments

Browser-direct upload queue (`max_concurrent_uploads` / `ATTACHMENT_UPLOAD_MAX_CONCURRENT`). Metadata in DB; objects outside transaction with compensation on failure.

## Web admin

Blade screens proxy playground API for users/orders. Vite assets for upload widgets as documented in views.

## Localization & tests

API/web copy under `lang/*/api/playground.php` and web counterparts. Tests: `tests/Domain/Playground/` — `composer test -- --filter=Playground` or `Order`.

## Related

- [platform/api-routing.md](../../platform/api-routing.md)
- [platform/crud.md](../../platform/crud.md)
- [web/blade-admin.md](../../web/blade-admin.md)
