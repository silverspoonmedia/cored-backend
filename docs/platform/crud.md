# CRUD architecture & repository pattern

Simple API CRUD layering, when to use `EloquentCrudRepository`, and the Playground references.

Production features live on `/api/v1/*`. Playground under `/api/playground/*` is **demo only** (non-production). See [api-routing.md](api-routing.md), [architecture.md](architecture.md).

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

**References:** Playground users (`routes/api/playground.php`, names `playground.users.*`); v1 users (`routes/api/v1/identity.php`).

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

## Request flow

```
HTTP → Form Request → Controller → Service → Repository → Model
```

## Playground Users (simple CRUD)

- Controller: `PlaygroundController` → `UserService` → `EloquentCrudRepositoryInterface` + `User::class`
- Binding: kernel `AppServiceProvider`
- Resources: `PlaygroundUserResource`
- Rules example: disposable email, last-user delete

API envelope: `ApiResponse` (`status`, `message`, optional `data`). List/show use Eloquent API Resources under `App\Domain\{Domain}\Http\Resources\…`.

## Master-detail aggregates

Multi-table atomic writes → **dedicated repository** + `DB::transaction()`.

**Reference:** Playground Orders — `OrderRepository` (`createWithDetails` / `updateWithDetails` / `deleteWithDetails`), `OrderService` (totals, rules), attachments via presigned S3. Detail sync is **ID-preserving** (update by id, insert without id, delete omitted lines).

Full detail: [domains/playground/README.md](../domains/playground/README.md).

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
6. `apiResource` or explicit routes as appropriate
7. Unit (service/controller) + feature HTTP tests

## Related

- [web/blade-admin.md](../web/blade-admin.md) — Blade consumes same API
- [domains/identity/README.md](../domains/identity/README.md) — production user CRUD
- [identifiers.md](identifiers.md) — UUID keys
