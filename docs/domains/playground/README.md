# Playground — demo CRUD (non-production)

Reference implementation for simple CRUD. **Disabled when `APP_ENV=production`.** Routes: `routes/api/playground.php` (from `routes/api.php`), `routes/web/playground.php` (empty — no Blade sell UI).

Code: `app/Domain/Playground/`. Domain registry: **locked** product demo (removable after unlock).

## Environment

Playground routes register only outside production. Do not treat as product API — real features go under `/api/v1/*`.

## Surfaces

### Users (simple CRUD) — active

`Route::apiResource` → `PlaygroundController` → `UserService` → `EloquentCrudRepository` + Identity `User` model. Namespaced route names `playground.users.*`.

Patterns: [platform/crud.md](../../platform/crud.md).

### Orders (master-detail) — retired as sell path

Order models, migrations, and factories remain on disk for historical data and residual PaymentGateway morph `order` compatibility. **Public routes and Blade UI were removed.**

Sell / checkout now lives in the [Service](../service/README.md) domain (`ServicePurchase` + FX→IDR + PaymentGateway).

Do not re-expose `/api/playground/orders` or `/orders/*` as the product payment demo.

### Attachment direct upload (residual)

`DirectAttachmentUploadService` still demonstrates browser-direct S3 upload. Storage mechanics share `App\Support\Storage\S3DirectUploadEngine` with [File Hosting](../file-hosting/README.md); this class only owns session model + `attachments.*` config + order path layout. Prefer File Hosting for product uploads.

## Localization & tests

API copy under `lang/*/api/playground.php`. Tests: `tests/Domain/Playground/` — users + production guard (orders tests deleted with surface).

```powershell
composer test -- --filter=Playground
```

## Related

- [service](../service/README.md) — current sellable catalog + checkout
- [payment-gateway](../payment-gateway/README.md)
- [platform/api-routing.md](../../platform/api-routing.md)
- [platform/crud.md](../../platform/crud.md)
