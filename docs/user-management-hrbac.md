# User Management & Hierarchical RBAC (H-RBAC)

This document describes **versioned user management** under `/api/v1`: hierarchical roles, Spatie permissions, Laravel policies, and CRUD rules enforced on the backend.

All endpoints require a valid JWT (`auth:api`). Authorization combines **Spatie permissions** (what actions are allowed) with **role levels** (which users may be targeted). See [JWT Authentication & RBAC](./jwt-authentication.md) for auth setup and [API Routing](./api-routing.md) for the versioned API surface.

---

## Overview

| Concern | Implementation |
|---------|----------------|
| API prefix | `/api/v1` |
| Authentication | JWT (`auth:api` guard) |
| User primary key | UUID v7 (`HasUuids` on `User`) |
| Roles & permissions | `spatie/laravel-permission` (`guard_name`: `api`) |
| Hierarchy | `roles.level` (unsigned integer, unique) |
| Authorization | `UserPolicy` + `RoleService` |
| Persistence | `UserRepository` (dedicated, not `EloquentCrudRepository`) |
| Soft delete | `SoftDeletes` on `User` |
| HTTP layer | Form Requests → `UserController` → `UserManagementService` → `UserRepository` |

Business rules (self-delete safeguard, assignable roles) live in the **controller** and **service**. Hierarchy and permission checks for HTTP actions live in **`UserPolicy`**. Shared hierarchy helpers live in **`RoleService`**.

---

## Role hierarchy

Roles are ordered by **`level`**. A **smaller** level number means **higher** authority.

| Role | Level | Notes |
|------|-------|-------|
| Root | 1 | Reserved for critical / system roles (single-digit levels) |
| Admin | 100 | Department-style levels use multiples of 100 |
| Moderator | 200 | Room between levels allows future roles (e.g. 150) |
| Management | 300 | |
| User | 400 | Default role for self-registration |

**Actor effective level** — when a user has multiple roles, the **lowest** level value (highest authority) is used:

```
Actor with Admin (100) + Moderator (200) → effective level = 100
```

**Target visibility (list / show)** — a target is visible only if the target’s **lowest** role level is **greater than** the actor’s effective level:

```
target_min_level > actor_effective_level
```

**Target modification (update / delete)** — **every** role assigned to the target must satisfy:

```
role_level > actor_effective_level
```

If any target role is equal to or above the actor, the operation is denied.

---

## Permissions

Permissions are created and assigned in `database/seeders/RoleSeeder.php` using `firstOrCreate` / `syncPermissions` so the seeder is safe to re-run.

| Permission | Root | Admin | Moderator | Management | User |
|------------|:----:|:-----:|:---------:|:----------:|:----:|
| `view user` | ✓ | ✓ | ✓ | ✓ | |
| `create user` | ✓ | ✓ | ✓ | | |
| `edit user` | ✓ | ✓ | ✓ | | |
| `delete user` | ✓ | ✓ | | | |

Spatie permission names use a **space** (e.g. `view user`), not dots.

---

## General hierarchy rules

These rules apply to **all** user-management operations:

1. An actor may only interact with users whose role level(s) satisfy the rules above.
2. Rules are enforced in **`UserPolicy`** on the backend, not only in the client.
3. Violations return **403 Forbidden** with an **Indonesian** error message in the standard API envelope.
4. If the actor has multiple roles, use the **minimum** `level` as the actor’s effective level.

Example: Admin (100) may assign Moderator (200), Management (300), or User (400). Admin **cannot** assign Root (1) or Admin (100).

---

## Operation-specific rules

### Create

- Actor must have `create user`.
- The `role` field may only be a role whose `level` is **greater than** the actor’s effective level.
- Assignable roles for forms are exposed at `GET /api/v1/users/assignable-roles` and validated in `StoreUserRequest` / `UpdateUserRequest`.

### Read (index / show)

- Actor must have `view user`.
- **Index** returns only users whose minimum role level is above the actor’s effective level (repository query).
- **Show** returns 403 if the target’s minimum level is not above the actor’s (policy).

### Update

- Actor must have `edit user`.
- Target must pass the **all roles above actor** check.
- If `role` is sent, it must be assignable per hierarchy (same rules as create).

### Delete

- Actor must have `delete user`.
- Target must pass the **all roles above actor** check.
- Uses **soft delete** (`deleted_at` on `users`).
- Actor **cannot** delete their own account → **422 Unprocessable Entity** with a clear Indonesian message (`CannotDeleteSelfException`), checked in the controller **before** policy authorization.

---

## API endpoints

Base URL prefix: **`/api/v1`**. Route names are prefixed with `api.v1.`.

All routes below use `middleware('auth:api')`. Send `Authorization: Bearer {token}`.

| Method | URI | Route name | Policy / notes |
|--------|-----|------------|----------------|
| `GET` | `/users` | `api.v1.users.index` | `viewAny` |
| `GET` | `/users/{user}` | `api.v1.users.show` | `view` |
| `POST` | `/users` | `api.v1.users.store` | `create` |
| `PUT` / `PATCH` | `/users/{user}` | `api.v1.users.update` | `update` |
| `DELETE` | `/users/{user}` | `api.v1.users.destroy` | Self-delete check, then `delete` |
| `GET` | `/users/assignable-roles` | `api.v1.users.assignable-roles` | `assignableRoles` |

`{user}` is a UUID (route model binding on `App\Models\User`).

List routes:

```bash
php artisan route:list --path=api/v1/users
```

### Blade web UI (API client)

The server-rendered admin does **not** call `UserManagementService` or `UserPolicy` directly. `App\Http\Controllers\Web\UserController` forwards every action to the endpoints above via `App\Support\Api\ApiClient` (JWT from the `auth_token` cookie sent as `Authorization: Bearer`). Validation, H-RBAC filtering, and authorization all run **only** in the API layer documented here.

| Web route | Forwards to |
|-----------|-------------|
| `GET /users` | Blade shell only (no API call) |
| `GET /users/datatable` | `GET /api/v1/users?display_mode=datatable` — proxies the Yajra payload and rewrites allowed action keys to web URLs |
| `GET /users/create` | `GET /api/v1/users/assignable-roles` |
| `POST /users` | `POST /api/v1/users` |
| `GET /users/{user}/edit` | `GET /api/v1/users/{id}` + assignable roles |
| `PUT /users/{user}` | `PUT /api/v1/users/{id}` |
| `DELETE /users/{user}` | `DELETE /api/v1/users/{id}` |

Web routes carry **no** `permission:` middleware — a `403` from the API is translated into a flash error by `ConsumesApi`. See [Web frontend](./web-frontend.md) and [List display modes](./list-display-modes.md).

---

## Request and response format

Responses use the shared `ApiResponse` / `ApiResponder` envelope. User-facing `message` values come from `lang/{locale}/api/users.php` (and related keys) — they are **not** hardcoded in controllers. The JSON examples below illustrate Indonesian (`id`); the active locale may return English instead.

**Success:**

```json
{
  "status": "success",
  "message": "Daftar pengguna berhasil diambil.",
  "data": []
}
```

**Policy / authorization denial (403):**

```json
{
  "status": "error",
  "message": "Anda tidak dapat mengakses pengguna dengan level role yang sama atau lebih tinggi dari Anda."
}
```

**Self-delete (422):**

```json
{
  "status": "error",
  "message": "Anda tidak dapat menghapus akun Anda sendiri."
}
```

**Resource not found (404)** — route model binding or repository `findOrFail` on `api/*`:

```json
{
  "status": "error",
  "message": "Pengguna tidak ditemukan."
}
```

Other Eloquent models on the API receive `Data tidak ditemukan.` instead of Laravel’s default `No query results for model [...]` message.

> **Locale note:** All `message` strings above are resolved via `__('api/users.*')` / `__('api/common.*')` and follow the active request locale (`SetLocale`: `users.locale` → cookie → `APP_LOCALE`). Examples show Indonesian (`id`); with `APP_LOCALE=en` the same keys return English from `lang/en/api/users.php`. See [Localization](./localization.md).

User payloads in management endpoints use `App\Support\User\UserManagementPresenter` (includes `roles` with `name` and `level`).

### Create user

**Request (`POST /api/v1/users`):**

```json
{
  "name": "Jane Doe",
  "email": "jane@example.com",
  "password": "password123",
  "password_confirmation": "password123",
  "role": "Moderator"
}
```

| Field | Required | Description |
|-------|----------|-------------|
| `name` | Yes | Display name |
| `email` | Yes | Unique email |
| `password` | Yes | Min. 8 characters |
| `password_confirmation` | Yes | Must match `password` |
| `role` | Yes | Must be in assignable roles for the actor |

**Response (`201`):** created user object in `data`.

### Update user

**Request (`PUT` / `PATCH /api/v1/users/{id}`):**

```json
{
  "name": "Jane Updated",
  "email": "jane.updated@example.com",
  "password": "new-password123",
  "password_confirmation": "new-password123",
  "role": "User"
}
```

All fields are optional (`sometimes`); `password_confirmation` is required when `password` is present.

### Assignable roles

**Request (`GET /api/v1/users/assignable-roles`):**

**Response (`200`):**

```json
{
  "status": "success",
  "message": "Daftar role yang dapat ditetapkan berhasil diambil.",
  "data": [
    { "name": "Moderator", "level": 200 },
    { "name": "Management", "level": 300 },
    { "name": "User", "level": 400 }
  ]
}
```

Example for an Admin actor (effective level 100). Root and Admin are excluded.

---

## Architecture

### Request flow

```
HTTP Request (Bearer JWT)
    │
    ▼
Form Request (StoreUserRequest / UpdateUserRequest)   ← input + assignable role validation
    │
    ▼
UserController                                        ← authorize() via UserPolicy
    │
    ▼
UserManagementService                                 ← create / update / delete orchestration
    │
    ▼
RoleService (assignable role checks) + UserRepository ← queries & persistence
    │
    ▼
User model / roles (Spatie) / database
```

### Directory layout

```
app/
├── Http/
│   ├── Controllers/API/V1/
│   │   └── UserController.php
│   └── Requests/User/
│       ├── StoreUserRequest.php
│       └── UpdateUserRequest.php
├── Policies/
│   └── UserPolicy.php
├── Services/
│   ├── Rbac/
│   │   └── RoleService.php
│   └── User/
│       └── UserManagementService.php
├── Repositories/
│   ├── Contracts/UserRepositoryInterface.php
│   └── Eloquent/UserRepository.php
├── Support/User/
│   └── UserManagementPresenter.php
├── Exceptions/User/
│   └── CannotDeleteSelfException.php
└── Models/
    ├── User.php          ← HasRoles, SoftDeletes, HasUuids
    └── Role.php          ← HasUuids, fillable: level

database/
├── migrations/
│   ├── 2026_06_03_000001_add_level_to_roles_table.php
│   └── 2026_06_03_000002_add_soft_deletes_to_users_table.php
└── seeders/
    └── RoleSeeder.php

routes/api/v1.php
tests/Feature/Http/Controllers/API/V1/UserManagementTest.php
```

`UserPolicy` is registered in `App\Providers\AppServiceProvider` via `Gate::policy(User::class, UserPolicy::class)`.

---

## RoleService

`App\Services\Rbac\RoleService` centralizes hierarchy math:

| Method | Purpose |
|--------|---------|
| `getLowestRoleLevel(User $user): ?int` | Actor/target effective level (min of role levels) |
| `getRoleLevels(User $user): Collection` | All level values for a user’s roles |
| `getAssignableRoles(User $actor): Collection` | Roles with `level > actor` effective level |
| `isRoleAssignableBy(User $actor, string $roleName): bool` | Whether a role name may be assigned |
| `canActorViewTarget(User $actor, User $target): bool` | List/show hierarchy check |
| `allTargetRolesAboveActor(User $actor, User $target): bool` | Update/delete hierarchy check |

---

## UserPolicy

`App\Policies\UserPolicy` integrates Spatie permissions with hierarchy checks. Policy methods return `Illuminate\Auth\Access\Response` (`allow()` / `deny($message)`) for Laravel 11+.

| Method | Permission | Hierarchy |
|--------|------------|-----------|
| `viewAny` | `view user` | — |
| `view` | `view user` | `canActorViewTarget` |
| `create` | `create user` | — (role validated in Form Request + service) |
| `update` | `edit user` | `allTargetRolesAboveActor` |
| `delete` | `delete user` | `allTargetRolesAboveActor` |
| `assignableRoles` | `create user` **or** `edit user` | — |

Denied responses use **Indonesian** messages. For `api/*` routes, `bootstrap/app.php` renders `AuthorizationException` and `AccessDeniedHttpException` through `ApiResponder` so clients always receive the standard JSON envelope.

---

## Database

### `roles.level`

Added by migration `2026_06_03_000001_add_level_to_roles_table.php`:

- Column: `level` — `unsignedInteger`, **unique**, nullable in migration (seeded values are always set)
- Model: `App\Models\Role` includes `level` in `$fillable` and casts it to `integer`

### Soft deletes on `users`

Migration `2026_06_03_000002_add_soft_deletes_to_users_table.php` adds `deleted_at`. The `User` model uses `SoftDeletes`. List queries exclude trashed users by default.

---

## Seeding

**Primary seeder:** `Database\Seeders\RoleSeeder`

```bash
php artisan db:seed --class=RoleSeeder
```

`DatabaseSeeder` calls `RoleSeeder` and creates a sample admin:

- Email: `admin@example.com`
- Role: `Admin`

`RolePermissionSeeder` is deprecated and extends `RoleSeeder` for backward compatibility.

### Environment

Registration assigns the default role from config:

```env
AUTH_DEFAULT_ROLE=User
```

Defined in `config/auth.php` as `default_role`. Must match a role name created by `RoleSeeder` (PascalCase, e.g. `User`, not `user`).

---

## Listing users (repository)

`UserRepository::listVisibleToActorLevel(int $actorLevel)` returns users whose **minimum** assigned role level is greater than `$actorLevel`, using a grouped subquery on `model_has_roles` and `roles`. Users with no roles are excluded.

---

## Related endpoints

| Endpoint | Change |
|----------|--------|
| `GET /api/v1/admin/ping` | Middleware updated to `permission:view user` (replaces legacy `users.manage`) |

---

## Tests

Feature tests: `tests/Feature/Http/Controllers/API/V1/UserManagementTest.php`

Covers hierarchy filtering, create/update role validation, permission denials, soft delete, assignable roles, multi-role actors, and self-delete (422).

```bash
php artisan test --filter=UserManagement
```

---

## Related documentation

- [Web frontend (Blade + AdminLTE 4)](./web-frontend.md) — Blade admin as a pure API client for user management screens
- [API Routing](./api-routing.md) — versioned routes; user management lives on v1 only
- [JWT Authentication & RBAC](./jwt-authentication.md) — JWT guard, Spatie setup, registration default role
- [CRUD Architecture & Repository Pattern](./crud-architecture.md) — layer responsibilities; user management uses a dedicated repository
- [User primary keys (UUID v7)](./user-uuid-v7.md) — UUID route parameters and model keys
