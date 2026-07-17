# CRUD architecture & repository pattern

Simple API CRUD layering and when to use `EloquentCrudRepository`.

Production features live on `/api/v1/*`. See [api-routing.md](api-routing.md), [architecture.md](architecture.md).

## When to use `EloquentCrudRepository`

`App\Repositories\Eloquent\EloquentCrudRepository` — generic helper for simple list/show/create/update/delete.

| Use shared repo | Prefer dedicated repo |
|-----------------|----------------------|
| Straightforward CRUD | Complex queries, multi-table transactions |
| Business rules in **Service** | Domain-specific persistence methods |
| Same patterns across models | Upserts, bulk imports, aggregate roots |

Never put business logic in the generic repository. Standard five-action CRUD → `Route::apiResource` (exclude `create`/`edit` for API-only).

## Routing simple CRUD

| Method | URI | Action |
|--------|-----|--------|
| `GET` | `/resources` | `index` |
| `POST` | `/resources` | `store` |
| `GET` | `/resources/{resource}` | `show` |
| `PUT`/`PATCH` | `/resources/{resource}` | `update` |
| `DELETE` | `/resources/{resource}` | `destroy` |

```php
Route::apiResource('users', UserController::class)->except(['create', 'edit']);
```

**References:** v1 users (`routes/api/v1/identity.php`); File Hosting folders/files under `routes/api/v1/file-hosting.php`.

**Not** `apiResource` when you need extra actions, dual list/DataTable modes, per-verb permissions, or optional-auth reads — see [Navigation](../domains/navigation/README.md), [ui-patterns](../web/ui-patterns.md).

## Layer responsibilities

| Layer | Does | Must not |
|-------|------|----------|
| **Form Request** | Input validation, `authorize()` | Business rules, data access |
| **Controller** | Validated input → Service → JSON | Validation, Eloquent, business rules |
| **Service** | Business rules; orchestrate repos | HTTP shape validation, raw queries |
| **Repository** | Query/persist | Business logic |
| **Model** | Mapping, casts, relations | App workflow |

Business failures → `BusinessRuleException` subclasses (not `ValidationException`).

### FormRequest DRY (store vs update)

Do **not** copy-paste full rule arrays between Store/Update. Prefer domain `Http/Requests/Concerns/*` that share field definitions:

| Pattern | Example |
|---------|---------|
| Shared field map + modality | `App\Support\Http\Requests\Concerns\ModalFieldRules` (`storeRules` / `updateRules`) |
| Domain catalog | Service `ServiceCatalogRules`, PaymentGateway gateway/subscription concerns |
| Micro-rules | FileHosting `FileHostingRequestRules` (rename/move/bulk/visibility via `Visibility::values()`) |
| Identity users | `UserMutationRules` (assignable roles + password once) |

Keep separate Store/Update classes for route binding; only the **rules body** is shared. Input normalization (e.g. service `prices[]` currency uppercasing) belongs in FormRequest `prepareForValidation`, not the web proxy.

## Request flow

```
HTTP → Form Request → Controller → Service → Repository → Model
```

## Identity Users (simple CRUD)

Reference for shared-repo CRUD: Identity `UserController` → user services/repos under `app/Domain/Identity/`, routes in `routes/api/v1/identity.php`.

API envelope: `ApiResponse` (`status`, `message`, optional `data`). List/show use Eloquent API Resources under `App\Domain\{Domain}\Http\Resources\…`.

## Master-detail / aggregate writes

Multi-table atomic writes → **dedicated repository** + `DB::transaction()`.

Domain-specific examples: Service catalog prices, File Hosting folder trees + upload sessions. Prefer domain READMEs over inventing a global demo surface.

## Evolving to a dedicated repository

1. Interface with domain language methods under `App\Domain\{Domain}\Repositories\Contracts\`
2. Eloquent implementation
3. Bind in domain ServiceProvider
4. Inject into Service; keep business rules in Service

Do not extend `EloquentCrudRepository` with domain methods.

## Checklist for new CRUD

1. Form Request(s) + `authorize()`
2. Service + `BusinessRuleException` subclasses
3. Shared or dedicated repository
4. API Resource(s) for list/show
5. Thin controller
6. Tests under `tests/Domain/{Domain}/`
7. Routes under `routes/api/v1/{slug}.php`
