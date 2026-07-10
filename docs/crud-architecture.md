# CRUD Architecture & Repository Pattern

This document describes how simple API CRUD is structured in this application: layer responsibilities, request flow, and when to use the shared `EloquentCrudRepository` versus a dedicated repository.

The **Playground User CRUD** under `/api/playground/users` is the reference implementation (`App\Domain\Playground\Services\UserService`, `App\Domain\Playground\Http\Controllers\API\PlaygroundController`). It is intentionally placed on the global API surface for demonstration only. Production domain features should live on versioned routes (`/api/v1/*`). See [API Routing](./api-routing.md) and [Domain modules](./domain-modules.md).

---

## When to use `EloquentCrudRepository`

`EloquentCrudRepository` (`App\Repositories\Eloquent\EloquentCrudRepository`) is a **generic, model-agnostic** helper for **simple, repetitive CRUD**—the same create/read/update/delete operations across entities with little or no custom querying.

| Use the shared repository | Prefer a dedicated repository |
|---------------------------|-------------------------------|
| Straightforward list/show/create/update/delete | Complex queries (joins, subqueries, scopes, aggregations) |
| No entity-specific persistence rules | Transactions spanning multiple tables or models |
| Business rules live in the **Service** layer | Persistence logic that belongs in the data layer (e.g. upsert with conflict handling, bulk imports) |
| Same patterns repeated for many models | Domain-specific methods (`findAvailableSeats`, `reserveInventory`, etc.) |

**Important:** A difficult or rich **business process** does not belong inside `EloquentCrudRepository`. If the data access itself is non-trivial, implement a **separate repository interface and class** for that aggregate (e.g. `OrderRepository` with methods that match how the domain talks about data). Services then depend on that contract instead of the generic CRUD interface.

The generic repository must **never** contain business logic or validation—only Eloquent query and persistence operations.

When an entity fits this profile, its HTTP surface should be a **standard REST resource** registered with `Route::apiResource` (see [Routing simple CRUD](#routing-simple-crud) below). Do not hand-roll individual `GET`/`POST`/`PUT`/`DELETE` lines for the same five actions unless the endpoint set is genuinely non-standard.

---

## Routing simple CRUD

Controllers that expose **only** the classic resource actions—`index`, `show`, `store`, `update`, `destroy`—and are backed by simple persistence (often via `EloquentCrudRepository` or a thin dedicated repository) should be wired with **`Route::apiResource`**.

Laravel registers the conventional URI map and route names in one declaration:

| Method | URI (example) | Action | Route name suffix |
|--------|---------------|--------|-------------------|
| `GET` | `/resources` | `index` | `.index` |
| `POST` | `/resources` | `store` | `.store` |
| `GET` | `/resources/{resource}` | `show` | `.show` |
| `PUT` / `PATCH` | `/resources/{resource}` | `update` | `.update` |
| `DELETE` | `/resources/{resource}` | `destroy` | `.destroy` |

`apiResource` also registers `create` and `edit` actions meant for HTML forms. API-only controllers should exclude them:

```php
Route::apiResource('users', UserController::class)->except(['create', 'edit']);
```

On versioned routes (`routes/api/v{N}.php`), paths are relative to the version prefix—for example `Route::apiResource('users', …)` under `/api/v1` resolves to `/api/v1/users`, `/api/v1/users/{user}`, and so on.

### Examples in this project

**Playground (global demo)** — [`routes/api/playground.php`](routes/api/playground.php) (required from `routes/api.php`):

```php
Route::prefix('playground')->group(function () {
    Route::apiResource('users', PlaygroundController::class)
        ->except(['create', 'edit'])
        ->names('playground.users');
});
```

> Route names are namespaced (`playground.users.*`) so the demo surface does not squat the generic `users.*` names, which the [Blade web frontend](./web-frontend.md) uses for its admin user CRUD (web controllers forward to `/api/v1/users`, not to Playground).

**Production user CRUD (v1)** — [`routes/api/v1/identity.php`](routes/api/v1/identity.php):

```php
Route::middleware('auth:api')->group(function (): void {
    Route::apiResource('users', UserController::class);
});
```

The controller implements the five resource methods; middleware, policies, and Form Requests handle auth and validation. See [API Routing](./api-routing.md) for where versioned routes live.

### Route model binding and parameters

- Use **implicit route model binding** (`show(User $user)`) when the model is the route key.
- For UUID primary keys, ensure the model uses `HasUuids` and add `->whereUuid('{param}')` on individual routes when you register them manually; `apiResource` binding works once the model key type is correct.
- Override the route parameter name when Laravel’s default singularization does not match your binding key:

```php
Route::apiResource('children', ChildController::class)
    ->parameters(['children' => 'child']);
```

### When **not** to use `apiResource`

Use explicit routes (or a mix of `apiResource` plus extra lines) when the controller is **not** a plain five-action CRUD surface:

| Situation | Example in this project |
|-----------|-------------------------|
| Extra read endpoints (`list`, `trashed`, search) | [Navigation menus](./navigation-menu.md) — `list`, `trashed`, optional-auth `index` |
| List endpoints with query + DataTables dual mode | [List display modes](./list-display-modes.md) — `display_mode` query param, RBAC `actions` column |
| Non-CRUD actions (`restore`, `publish`, `approve`) | Menus — `POST …/restore` with `withTrashed()` |
| Different middleware or permissions per verb | Menus — `view menu` for reads, `manage menu` for writes |
| Public or optional-auth reads alongside protected writes | Menus — `jwt.optional` on tree `index` |

Those cases belong in a dedicated route group with named routes, not a single `apiResource` block. The [Menu system](./navigation-menu.md) is the reference for **rich** domain endpoints; Playground Users and v1 Users are the references for **simple** `apiResource` CRUD.

---

## Layer responsibilities

| Layer | Responsibility | Must not |
|-------|----------------|----------|
| **Form Request** | HTTP input validation (`required`, format, types), database existence checks (`unique`, `exists`), authorization via `authorize()` (Gates/Policies) | Business rules, data access |
| **Controller** | Receive validated input, delegate to Service, return HTTP/JSON responses | Validation, business rules, direct Eloquent/DB access |
| **Service** | Business logic and business-rule validation; orchestrate repositories | HTTP concerns, input format validation, raw queries |
| **Repository** | Data access (query, create, update, delete) | Business logic, validation |
| **Model** | Eloquent mapping, casts, relationships, factories | HTTP or application workflow logic |

### Validation placement

- **Form Request** — technical/input validation: required fields, email format, `unique:users,email`, etc.
- **Service** — business rules that depend on domain state: quotas, stock, allowed status transitions, “cannot delete the last user”, blocked disposable email domains, etc.

When a business rule is violated, the Service throws a **domain-specific exception** extending `BusinessRuleException`. It must **not** throw `ValidationException` from the HTTP layer—that keeps business failures separate from request validation.

---

## Request flow

```
HTTP Request
    │
    ▼
Form Request          ← validate input, authorize()
    │
    ▼
Controller            ← $request->validated() → Service → ApiResponse
    │
    ▼
Service               ← business rules, custom exceptions
    │
    ▼
Repository            ← EloquentCrudRepository or dedicated repository
    │
    ▼
Model / database
```

---

## Reference implementation (Playground Users)

### Routes

Registered with `Route::apiResource` in [`routes/api/playground.php`](routes/api/playground.php) (see [Routing simple CRUD](#routing-simple-crud)):

```php
Route::prefix('playground')->group(function () {
    Route::apiResource('users', PlaygroundController::class)
        ->except(['create', 'edit'])
        ->names('playground.users');
});
```

| Method | URI | Controller action |
|--------|-----|-------------------|
| `GET` | `/api/playground/users` | `index` |
| `GET` | `/api/playground/users/{user}` | `show` |
| `POST` | `/api/playground/users` | `store` |
| `PUT` / `PATCH` | `/api/playground/users/{user}` | `update` |
| `DELETE` | `/api/playground/users/{user}` | `destroy` |

### File layout

```
app/
├── Domain/Playground/
│   ├── Http/
│   │   ├── Controllers/API/PlaygroundController.php
│   │   ├── Requests/{Store,Update}UserRequest.php
│   │   └── Resources/PlaygroundUserResource.php
│   ├── Services/UserService.php
│   └── Exceptions/
│       ├── CannotDeleteLastRemainingUserException.php   (HTTP 409)
│       └── DisposableEmailNotAllowedException.php       (HTTP 422)
├── Domain/Identity/Models/User.php                      # playground users use Identity User model
├── Repositories/
│   ├── Contracts/EloquentCrudRepositoryInterface.php
│   └── Eloquent/EloquentCrudRepository.php
└── Exceptions/BusinessRuleException.php
```

### Binding

`EloquentCrudRepositoryInterface` is bound to `EloquentCrudRepository` in `App\Providers\AppServiceProvider` — this is a kernel-shared repository, so the binding stays in the kernel provider rather than a domain provider. `UserService` receives the interface via constructor injection; the controller receives `UserService` only.

### Controller (thin)

The controller does not call `$request->validate()` and does not touch the repository. Example:

```php
public function store(StoreUserRequest $request): JsonResponse
{
    $record = $this->userService->create($request->validated());

    return $this->successResponse($record, 'User created successfully.', 201);
}
```

### Form Request

- **`StoreUserRequest`** — `name`, `email` (`unique:users`), `password` (min length).
- **`UpdateUserRequest`** — same fields with `sometimes`; `email` uses `Rule::unique()->ignore($userId)`.
- **`authorize()`** — returns `true` in the playground (no auth). Replace with Gate/Policy checks when authorization is required.

### Service (business rules)

`UserService` uses `EloquentCrudRepositoryInterface` with `User::class` for persistence. Examples of rules kept in the Service:

- Reject disposable email domains (`DisposableEmailNotAllowedException`).
- Prevent deleting the last remaining user (`CannotDeleteLastRemainingUserException`).

### Generic repository usage

The Service passes the model class name for each operation:

```php
$this->repository->all(User::class);
$this->repository->findOrFail(User::class, $id);
$this->repository->create(User::class, $data);
$this->repository->update(User::class, $id, $data);
$this->repository->delete(User::class, $id);
$this->repository->count(User::class);
```

Available methods are listed on `EloquentCrudRepositoryInterface` (including pagination, soft-delete helpers when the model uses `SoftDeletes`, etc.).

### API responses

Success and error JSON envelopes use the `ApiResponse` trait (`status`, `message`, optional `data`). Uncaught `BusinessRuleException` subclasses render JSON via their `render()` method when the request expects JSON (e.g. `api/*`).

### API Resources (list / show)

**List/index** and **show** responses use Laravel **Eloquent API Resources** (`App\Domain\{Domain}\Http\Resources\…`) so field selection and formatting live in one class per shape. Controllers resolve resources to plain arrays before wrapping them in the `ApiResponse` envelope (via kernel `ResolvesResourceArray`); datatable rows use the same resource as query mode.

| Resource | Used for |
|----------|----------|
| `App\Domain\Identity\Http\Resources\UserManagementResource` | v1 user index & show |
| `App\Domain\Playground\Http\Resources\PlaygroundUserResource` | Playground user index & show |
| `App\Domain\Navigation\Http\Resources\MenuListResource` | Menu admin flat list |
| `App\Domain\Navigation\Http\Resources\MenuTrashedResource` | Trashed menu list |
| `App\Domain\Navigation\Http\Resources\MenuDetailResource` | Menu show |

Presenters under `App\Support\…` may delegate to resources for write responses (create/update) until those endpoints are migrated. When adding a new list or show endpoint, define a resource first; keep the initial `toArray()` **as-is** with the existing JSON fields, then adjust formatting in the resource only.

**Example success (create):**

```json
{
  "status": "success",
  "message": "User created successfully.",
  "data": {
    "id": 1,
    "name": "Jane Doe",
    "email": "jane@example.com"
  }
}
```

**Example business-rule error:**

```json
{
  "status": "error",
  "message": "Cannot delete the last remaining user."
}
```

---

## Master-detail aggregates (transactions, dedicated repository)

When one master row owns many detail rows (an order and its line items), the
write spans two tables and must be **atomic** — a failed line insert must never
leave a master without its details (no orphaned data). This is exactly the case
the generic `EloquentCrudRepository` is **not** for; it gets a **dedicated
repository** whose writes run inside `DB::transaction()`.

**Reference implementation — Playground Orders** (`/api/playground/orders`):

```
app/Domain/Playground/
├── Models/
│   ├── Order.php              # order_master — HasMany details, HasUuids, derived totals
│   └── OrderDetail.php        # order_detail — BelongsTo order, foreignUuid order_master_id
├── Repositories/
│   ├── Contracts/OrderRepositoryInterface.php
│   └── Eloquent/OrderRepository.php        # createWithDetails / updateWithDetails / deleteWithDetails — each in DB::transaction()
├── Services/OrderService.php               # business rules + derives subtotal/grand_total from lines
├── Http/
│   ├── Requests/{Store,Update,Index}OrderRequest.php
│   ├── Resources/PlaygroundOrder{,Detail}Resource.php
│   └── Controllers/API/PlaygroundOrderController.php
├── Exceptions/
│   ├── OrderRequiresDetailException.php     (HTTP 422)
│   └── OrderNotEditableException.php         (HTTP 409 — completed orders are frozen)
└── Support/
    ├── DataTable/PlaygroundOrderRowActionResolver.php
    └── Orders/DirectAttachmentUploadService.php
```

| Concern | Where it lives | Why |
|---------|----------------|-----|
| **Atomic two-table write** | `OrderRepository` (`DB::transaction`) | Master + details commit or roll back together; a mid-write failure leaves nothing behind |
| **Totals derived from lines** | `OrderService` | Persisted `subtotal`/`grand_total` always match the items — never trusted from the client |
| **Business rules** (≥1 line, completed orders immutable) | `OrderService` → `BusinessRuleException` subclasses | Domain state, not HTTP-shape validation |
| **Input shape** (`details[]` array, per-line fields) | `Store/UpdateOrderRequest` | `details` is `required|array|min:1`; lines validated with `details.*.*` rules |
| **Update semantics** | `OrderRepository::updateWithDetails` | **ID-preserving sync**: lines sent with an existing `id` are updated in place (UUID kept), lines without an id are inserted, lines absent from the payload are deleted — all inside the transaction |
| **Schema** | `order_master` / `order_detail` migrations | UUID PKs (`HasUuids`), `foreignUuid('order_master_id')->constrained()->cascadeOnDelete()` |
| **File attachments** | `OrderService` + `DirectAttachmentUploadService` + `OrderAttachmentStorage` + `Attachment` / `AttachmentUpload` models | Browser-direct uploads to `s3Custom` via presigned URLs (chunked multipart for large files). The web widget uses a **manual Start upload** queue with `max_concurrent_uploads` (env `ATTACHMENT_UPLOAD_MAX_CONCURRENT`) so the browser never saturates bandwidth with parallel PUTs. Completed upload sessions are referenced as `staged_attachments[]` on order create/update; metadata lives in the polymorphic **`attachments`** table and is reconciled inside the master-detail transaction. `Attachment::url()` serves cached signed GET URLs on S3 disks. Objects live outside the DB transaction — the service compensates on failed writes and purges dropped files only after commit |

The controller stays thin (validated input → `OrderService` → resource → response)
exactly like Playground Users; only the **data layer** differs (dedicated repo +
transaction instead of `EloquentCrudRepository`). The interface is bound in
`App\Providers\Domain\PlaygroundServiceProvider`. Web admin screens for this aggregate live in the
[web frontend](./web-frontend.md) and consume `/api/playground/orders`. Full
detail (request shapes, attachment reconciliation, transaction-safety ordering)
→ **[Playground](./playground.md#attachments-direct-s3-upload)**.

> The detail set is synced by **id**, not blindly replaced. Send a line's `id`
> to update it in place (its UUID survives); omit `id` for a new line; leave a
> line out to delete it. A line `id` that does not belong to the order is
> ignored and treated as a new line (no cross-order hijacking).

---

## Evolving to a dedicated repository

When an entity outgrows generic CRUD:

1. Define an interface, e.g. `App\Domain\Identity\Repositories\Contracts\UserRepositoryInterface`, with methods that match domain language (`findByEmail`, `paginateActive`, …).
2. Implement it in `App\Domain\Identity\Repositories\Eloquent\UserRepository` (or another persistence backend).
3. Bind the interface in the relevant domain ServiceProvider under `app/Providers/Domain/` (or `AppServiceProvider` for kernel-shared repositories like `EloquentCrudRepositoryInterface`).
4. Inject the dedicated interface into `UserService` instead of `EloquentCrudRepositoryInterface`.
5. Keep **business logic in the Service**; keep the new repository limited to data access.

Do not extend `EloquentCrudRepository` with business-specific methods—that couples domain logic to the generic CRUD helper.

---

## Tests

| Test | Location | Focus |
|------|----------|--------|
| Controller unit | `tests/Domain/Playground/Unit/Http/Controllers/API/PlaygroundControllerTest.php` | Controller delegates to `UserService`; no repository in controller |
| Service unit | `tests/Domain/Playground/Unit/Services/UserServiceTest.php` | Business rules; repository mocked |
| Feature | `tests/Domain/Playground/Feature/Http/Controllers/API/PlaygroundUserCrudTest.php` | Full HTTP stack, validation, persistence |
| Order service unit | `tests/Domain/Playground/Unit/Services/OrderServiceTest.php` | Master-detail rules + total derivation; repository mocked |
| Order repository feature | `tests/Domain/Playground/Feature/Repositories/OrderRepositoryTest.php` | Transaction rollback (no orphaned master/details) |
| Order feature | `tests/Domain/Playground/Feature/Http/Controllers/API/PlaygroundOrderCrudTest.php` | Full HTTP stack: atomic create, full-line replace, frozen completed orders |

Run:

```bash
php artisan test --filter=Playground
php artisan test --filter="Order"
```

---

## Checklist for new CRUD features

1. Add **Form Request(s)** for store/update (and authorization in `authorize()`).
2. Add a **Service** for business rules; throw `BusinessRuleException` subclasses when rules fail.
3. Use **`EloquentCrudRepository`** only if access patterns are simple; otherwise add a **dedicated repository**.
4. Add **API Resource(s)** for list/index and show payloads (`App\Domain\{Domain}\Http\Resources\…`).
5. Keep the **controller** thin: validated data in, Service call, resolve resource, response out.
6. If the surface is standard five-action CRUD, register it with **`Route::apiResource`** on the correct API layer (versioned for product features); use explicit routes when actions, middleware, or permissions diverge per endpoint.
7. Add unit tests for Service and Controller; feature tests for HTTP behavior.

---

## Related documentation

- [Web frontend (Blade + AdminLTE 4)](./web-frontend.md) — Blade admin consumes v1 API endpoints via `ApiClient`; business logic stays in Services behind the API
- [API Routing](./api-routing.md) — global vs versioned routes; product features belong on v1+
- [JWT Authentication & RBAC](./jwt-authentication.md) — dedicated `UserRepository` and versioned auth endpoints
- [User Management & Hierarchical RBAC](./user-management-hrbac.md) — production user CRUD on v1 (`UserManagementService`, `UserPolicy`, hierarchy)
- [User primary keys (UUID v7)](./user-uuid-v7.md) — UUID primary keys for `users` and related tables
