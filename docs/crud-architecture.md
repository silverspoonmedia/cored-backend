# CRUD Architecture & Repository Pattern

This document describes how simple API CRUD is structured in this application: layer responsibilities, request flow, and when to use the shared `EloquentCrudRepository` versus a dedicated repository.

The **Playground User CRUD** under `/api/playground/users` is the reference implementation. It is intentionally placed on the global API surface for demonstration only. Production domain features should live on versioned routes (`/api/v1/*`). See [API Routing](./api-routing.md).

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

Defined in `routes/api.php`:

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
├── Http/
│   ├── Controllers/API/
│   │   └── PlaygroundController.php
│   └── Requests/Playground/
│       ├── StoreUserRequest.php
│       └── UpdateUserRequest.php
├── Services/Playground/
│   └── UserService.php
├── Repositories/
│   ├── Contracts/
│   │   └── EloquentCrudRepositoryInterface.php
│   └── Eloquent/
│       └── EloquentCrudRepository.php
├── Exceptions/
│   ├── BusinessRuleException.php
│   └── Playground/
│       ├── CannotDeleteLastRemainingUserException.php   (HTTP 409)
│       └── DisposableEmailNotAllowedException.php       (HTTP 422)
└── Models/
    └── User.php
```

### Binding

`EloquentCrudRepositoryInterface` is bound to `EloquentCrudRepository` in `App\Providers\AppServiceProvider`. `UserService` receives the interface via constructor injection; the controller receives `UserService` only.

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

## Evolving to a dedicated repository

When an entity outgrows generic CRUD:

1. Define an interface, e.g. `App\Repositories\Contracts\UserRepositoryInterface`, with methods that match domain language (`findByEmail`, `paginateActive`, …).
2. Implement it in `App\Repositories\Eloquent\UserRepository` (or another persistence backend).
3. Bind the interface in `AppServiceProvider`.
4. Inject the dedicated interface into `UserService` instead of `EloquentCrudRepositoryInterface`.
5. Keep **business logic in the Service**; keep the new repository limited to data access.

Do not extend `EloquentCrudRepository` with business-specific methods—that couples domain logic to the generic CRUD helper.

---

## Tests

| Test | Location | Focus |
|------|----------|--------|
| Controller unit | `tests/Unit/Http/Controllers/API/PlaygroundControllerTest.php` | Controller delegates to `UserService`; no repository in controller |
| Service unit | `tests/Unit/Services/Playground/UserServiceTest.php` | Business rules; repository mocked |
| Feature | `tests/Feature/Http/Controllers/API/PlaygroundUserCrudTest.php` | Full HTTP stack, validation, persistence |

Run:

```bash
php artisan test --filter=Playground
```

---

## Checklist for new CRUD features

1. Add **Form Request(s)** for store/update (and authorization in `authorize()`).
2. Add a **Service** for business rules; throw `BusinessRuleException` subclasses when rules fail.
3. Use **`EloquentCrudRepository`** only if access patterns are simple; otherwise add a **dedicated repository**.
4. Keep the **controller** thin: validated data in, Service call, response out.
5. Register routes on the correct API layer (versioned for product features).
6. Add unit tests for Service and Controller; feature tests for HTTP behavior.

---

## Related documentation

- [API Routing](./api-routing.md) — global vs versioned routes; product features belong on v1+
- [JWT Authentication & RBAC](./jwt-authentication.md) — dedicated `UserRepository` and versioned auth endpoints
- [User Management & Hierarchical RBAC](./user-management-hrbac.md) — production user CRUD on v1 (`UserManagementService`, `UserPolicy`, hierarchy)
- [User primary keys (UUID v7)](./user-uuid-v7.md) — UUID primary keys for `users` and related tables
