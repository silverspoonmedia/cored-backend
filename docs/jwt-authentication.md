# JWT Authentication & RBAC

This document describes the API authentication stack: **JWT-based auth** (`php-open-source-saver/jwt-auth`), **role and permission management** (`spatie/laravel-permission`), and how they are wired into the versioned API under `/api/v1`.

Authentication is **not** exposed on global routes (`/api/*`). All auth endpoints live in `routes/api/v1.php`. See [API Routing](./api-routing.md).

---

## Overview

| Concern | Implementation |
|---------|----------------|
| API guard | `api` (`jwt` driver) |
| User primary key | UUID v7 (`HasUuids` on `User`) |
| Token format | Bearer JWT |
| Authorization | Spatie roles & permissions (`guard_name`: `api`) |
| Persistence | Dedicated `UserRepository` (not `EloquentCrudRepository`) |
| HTTP layer | Form Requests → thin controllers → Services → Repository |

Business logic and auth orchestration stay in **Services**. Data access stays in **`UserRepository`**. Controllers only validate input, delegate, and return JSON via `ApiResponse`.

---

## Packages

These packages are already required in `composer.json`—do not reinstall them:

| Package | Purpose |
|---------|---------|
| `php-open-source-saver/jwt-auth` | Issue, validate, refresh, and blacklist JWTs |
| `spatie/laravel-permission` | Roles, permissions, and route middleware |

Published config files:

- `config/jwt.php`
- `config/auth.php` (includes `api` guard and auth-related env keys)
- `config/permission.php` (uses `App\Models\Role` and `App\Models\Permission`)

---

## Architecture

### Request flow

```
HTTP Request
    │
    ▼
Form Request              ← input validation (email format, password rules, etc.)
    │
    ▼
Controller (V1\Auth\*)    ← $request->validated() → Service → ApiResponse
    │
    ▼
Service (Auth\*)          ← login, register, password reset, email verification
    │
    ▼
UserRepository            ← Eloquent queries for User only
    │
    ▼
User model / database
```

Auth-specific failures use `App\Exceptions\Auth\*` classes extending `BusinessRuleException`, which render JSON for `api/*` requests.

### Directory layout

```
app/
├── Http/
│   ├── Controllers/API/V1/
│   │   ├── Auth/
│   │   │   ├── AuthController.php
│   │   │   ├── EmailVerificationController.php
│   │   │   └── PasswordController.php
│   │   └── AdminController.php          ← RBAC example (permission middleware)
│   └── Requests/Auth/
│       ├── RegisterRequest.php
│       ├── LoginRequest.php
│       ├── ForgotPasswordRequest.php
│       ├── ResetPasswordRequest.php
│       └── ChangePasswordRequest.php
├── Services/Auth/
│   ├── AuthService.php
│   ├── PasswordResetService.php
│   └── EmailVerificationService.php
├── Repositories/
│   ├── Contracts/UserRepositoryInterface.php
│   └── Eloquent/UserRepository.php
├── Models/
│   ├── User.php                         ← JWTSubject, MustVerifyEmail, HasRoles
│   ├── Role.php                         ← HasUuids
│   └── Permission.php                   ← HasUuids
├── Notifications/Auth/
│   ├── VerifyEmailNotification.php
│   └── ResetPasswordNotification.php
├── Support/Auth/
│   └── UserPresenter.php                ← consistent user JSON shape
└── Exceptions/Auth/
    ├── InvalidCredentialsException.php
    ├── InvalidPasswordResetTokenException.php
    ├── EmailAlreadyVerifiedException.php
    └── CurrentPasswordIncorrectException.php

routes/api/v1.php                        ← all auth routes
database/seeders/RolePermissionSeeder.php
```

`UserRepositoryInterface` is bound to `UserRepository` in `App\Providers\AppServiceProvider`.

---

## User model

`App\Models\User` implements:

| Contract / trait | Role |
|------------------|------|
| `JWTSubject` | Supplies `sub` claim and custom JWT claims |
| `MustVerifyEmail` | Enables email verification flow |
| `HasRoles` (Spatie) | Roles and permissions |
| `HasUuids` | UUID v7 primary key |

The permission guard is fixed on the model:

```php
protected string $guard_name = 'api';
```

Fillable attributes include `name`, `email`, `password`, and `email_verified_at` (set by the repository when verifying email).

See [User primary keys (UUID v7)](./user-uuid-v7.md) for schema and ID conventions.

---

## UUID usage

| Table / model | Primary key |
|---------------|-------------|
| `users` | `uuid` (v7 via `HasUuids`) |
| `roles` | `uuid` |
| `permissions` | `uuid` |
| `model_has_roles.model_id` | `uuid` (references `users.id`) |
| `model_has_permissions.model_id` | `uuid` |

Role and permission IDs use custom models (`App\Models\Role`, `App\Models\Permission`) with `HasUuids`. The permission tables migration uses UUID columns throughout.

JWT `sub` and route parameters (e.g. `{id}` on email verification) are UUID strings.

---

## Configuration

### Environment variables

Add to `.env` (see `.env.example`):

```env
JWT_SECRET=                          # run: php artisan jwt:secret
JWT_TTL=60
JWT_REFRESH_TTL=20160

AUTH_GUARD=api
AUTH_DEFAULT_ROLE=user
AUTH_VERIFICATION_EXPIRE=60
```

`phpunit.xml` sets `JWT_SECRET` and `AUTH_GUARD=api` for tests.

### Auth guard (`config/auth.php`)

```php
'defaults' => [
    'guard' => env('AUTH_GUARD', 'api'),
],

'guards' => [
    'api' => [
        'driver' => 'jwt',
        'provider' => 'users',
    ],
],

'default_role' => env('AUTH_DEFAULT_ROLE', 'user'),

'verification' => [
    'expire' => (int) env('AUTH_VERIFICATION_EXPIRE', 60),
],
```

### Middleware aliases (`bootstrap/app.php`)

| Alias | Class | Use |
|-------|-------|-----|
| `jwt.auth` | JWT Authenticate | *(registered by jwt-auth package)* |
| `jwt.refresh` | JWT RefreshToken | Token refresh endpoint |
| `role` | Spatie `RoleMiddleware` | `middleware('role:admin')` |
| `permission` | Spatie `PermissionMiddleware` | `middleware('permission:users.manage')` |
| `role_or_permission` | Spatie `RoleOrPermissionMiddleware` | Either role or permission |

Protected routes use `auth:api` (JWT guard). Example RBAC route:

```php
Route::middleware(['auth:api', 'permission:users.manage'])
    ->get('admin/ping', [AdminController::class, 'ping']);
```

### Authentication errors on API routes

For paths matching `api/*`, `bootstrap/app.php` normalizes all `401` auth failures to the standard API envelope:

```json
{
  "status": "error",
  "message": "Bearer token is required."
}
```

| Situation | HTTP | Message (example) |
|-----------|------|-------------------|
| No `Authorization` header on `auth:api` routes | `401` | `Bearer token is required.` |
| `Authorization` present but token invalid / unparseable | `401` | `Token is invalid.` |
| JWT middleware: token missing | `401` | `Bearer token is required.` |
| JWT middleware: user not found for token | `401` | `Token is invalid.` |
| Expired / blacklisted JWT | `401` | `Token has expired.` / `Token has been revoked.` |

API routes **never redirect** to a web login page. `bootstrap/app.php` overrides Laravel’s default `redirectGuestsTo(route('login'))` because this application has no `login` named route for the web stack.

Laravel’s default `{"message":"Unauthenticated."}` response and login redirects are **not** used on `api/*` routes.

### Authorization errors (roles & permissions)

When `role`, `permission`, or `role_or_permission` middleware denies access on `api/*`:

```json
{
  "status": "error",
  "message": "You are not authorized to perform this action."
}
```

HTTP status is `403`. Required role or permission names are **not** exposed in the response (`display_permission_in_exception` and `display_role_in_exception` remain `false` in `config/permission.php`).

---

## API endpoints

Base URL prefix: **`/api/v1`**. Route names are prefixed with `api.v1.`.

### Public

| Method | URI | Route name | Description |
|--------|-----|------------|-------------|
| `POST` | `/auth/register` | `api.v1.auth.register` | Create account, assign default role, send verification email, return JWT |
| `POST` | `/auth/login` | `api.v1.auth.login` | Authenticate and return JWT |
| `POST` | `/auth/forgot-password` | `api.v1.auth.password.forgot` | Queue password reset email |
| `POST` | `/auth/reset-password` | `api.v1.auth.password.reset` | Reset password with token + email |
| `GET` | `/auth/email/verify/{id}/{hash}` | `api.v1.auth.verification.verify` | Verify email (signed URL, throttled) |
| `POST` | `/auth/refresh` | `api.v1.auth.refresh` | Refresh JWT (`jwt.refresh` middleware) |

### Authenticated (`Authorization: Bearer {token}`)

| Method | URI | Route name | Description |
|--------|-----|------------|-------------|
| `POST` | `/auth/logout` | `api.v1.auth.logout` | Invalidate current token (blacklist) |
| `GET` | `/auth/me` | `api.v1.auth.me` | Current user with roles and permissions |
| `PUT` | `/auth/password` | `api.v1.auth.password.update` | Change password (requires current password) |
| `POST` | `/auth/email/verification-notification` | `api.v1.auth.verification.send` | Resend verification email (throttled) |

### RBAC example

| Method | URI | Middleware | Description |
|--------|-----|------------|-------------|
| `GET` | `/admin/ping` | `auth:api`, `permission:users.manage` | Example admin-only endpoint |

List routes:

```bash
php artisan route:list --path=api/v1/auth
```

---

## Request and response format

All endpoints use the shared `ApiResponse` trait:

**Success:**

```json
{
  "status": "success",
  "message": "Login successful.",
  "data": { }
}
```

**Business / auth rule error** (`BusinessRuleException`):

```json
{
  "status": "error",
  "message": "Invalid email or password."
}
```

**Validation error** (Form Request): Laravel standard `422` validation JSON.

### Login and remember me

**Request (`POST /api/v1/auth/login`):**

```json
{
  "email": "jane@example.com",
  "password": "password123",
  "remember_me": true
}
```

| Field | Required | Description |
|-------|----------|-------------|
| `email` | Yes | User email |
| `password` | Yes | User password |
| `remember_me` | No | When `true`, issues a longer-lived JWT (see `JWT_REMEMBER_TTL` / `jwt.remember_ttl`) |

This API uses **stateless JWT**, not session cookies. “Remember me” means a **longer access-token TTL** (default: same window as `JWT_REFRESH_TTL`, two weeks), not a Laravel `remember_token` cookie. The JWT includes a persistent `rm` claim so `POST /auth/refresh` keeps the extended lifetime for that session.

| `remember_me` | Token TTL | `data.remember` | `data.expires_in` |
|---------------|-----------|-----------------|-------------------|
| `false` / omitted | `JWT_TTL` (default 60 minutes) | `false` | `JWT_TTL * 60` seconds |
| `true` | `JWT_REMEMBER_TTL` (default 20160 minutes) | `true` | `JWT_REMEMBER_TTL * 60` seconds |

### Register payload

**Request (`POST /api/v1/auth/register`):**

```json
{
  "name": "Jane Doe",
  "email": "jane@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```

**Response (`201`):**

```json
{
  "status": "success",
  "message": "Registration successful.",
  "data": {
    "user": {
      "id": "019e8766-857b-7259-a580-a21e1ddfa27e",
      "name": "Jane Doe",
      "email": "jane@example.com",
      "email_verified_at": null,
      "roles": ["user"],
      "permissions": ["profile.view", "profile.update"]
    },
    "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "token_type": "bearer",
    "expires_in": 3600,
    "remember": false
  }
}
```

`expires_in` is the token TTL in **seconds**. `remember` reflects whether the client requested a long-lived token.

### Authenticated requests

```http
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc...
Accept: application/json
```

### Password reset

**Forgot (`POST /api/v1/auth/forgot-password`):**

```json
{ "email": "user@example.com" }
```

Always returns a generic success message (does not reveal whether the email exists).

**Reset (`POST /api/v1/auth/reset-password`):**

```json
{
  "email": "user@example.com",
  "token": "token-from-email",
  "password": "new-password-123",
  "password_confirmation": "new-password-123"
}
```

The reset email includes a link with `token` and `email` query parameters pointing at the API reset path; clients may also call the POST endpoint directly with those values.

### Email verification

1. User receives a signed link: `GET /api/v1/auth/email/verify/{id}/{hash}?expires=...&signature=...`
2. On success, `email_verified_at` is set via `UserRepository::markEmailAsVerified`.
3. Authenticated users can request a new link via `POST /api/v1/auth/email/verification-notification` unless already verified (`422`).

---

## Roles and permissions

Seeded by `RolePermissionSeeder` (`guard_name`: `api`):

| Role | Permissions |
|------|-------------|
| `user` | `profile.view`, `profile.update` |
| `admin` | All permissions (`users.view`, `users.manage`, `profile.view`, `profile.update`) |

On registration, `AuthService` assigns the role from `config('auth.default_role')` (default: `user`).

### Checking access in routes

```php
// Single permission
Route::middleware(['auth:api', 'permission:users.manage'])->get(...);

// Single role
Route::middleware(['auth:api', 'role:admin'])->get(...);

// Role or permission
Route::middleware(['auth:api', 'role_or_permission:admin|users.manage'])->get(...);
```

### Checking access in code

```php
$user->hasRole('admin');
$user->can('users.manage');
```

The `GET /api/v1/auth/me` response includes `roles` and `permissions` arrays for client-side UI hints; **always enforce authorization on the server** with middleware or policies.

---

## Local setup

```bash
# Generate signing secret (once per environment)
php artisan jwt:secret

# Migrate and seed roles + sample admin
php artisan migrate:fresh --seed
```

Default seeded admin (from `DatabaseSeeder`):

- Email: `admin@example.com`
- Password: `password` (factory default)
- Role: `admin`

Ensure `MAIL_*` is configured for verification and reset emails (tests use `MAIL_MAILER=array`).

---

## Services reference

### `AuthService`

| Method | Description |
|--------|-------------|
| `register(array $data)` | Create user, assign default role, send verification email, return auth payload |
| `login(array $credentials)` | Attempt JWT login; throws `InvalidCredentialsException` on failure |
| `refresh()` | Refresh token for current user |
| `logout()` | Blacklist current token |
| `me()` | Return `UserPresenter` array for authenticated user |

### `PasswordResetService`

| Method | Description |
|--------|-------------|
| `sendResetLink(string $email)` | Send reset notification via password broker |
| `reset(array $data)` | Validate token and update password |
| `changePassword(string $userId, array $data)` | Verify current password and update |

### `EmailVerificationService`

| Method | Description |
|--------|-------------|
| `sendNotification(User $user)` | Resend verification; throws if already verified |
| `verify(string $userId, string $hash)` | Validate hash and mark email verified |

---

## Tests

| Test | Location | Focus |
|------|----------|--------|
| Repository | `tests/Unit/Repositories/Eloquent/UserRepositoryTest.php` | UUID create/find, email verification |
| Auth service | `tests/Unit/Services/Auth/AuthServiceTest.php` | Register, login, credentials |
| Password service | `tests/Unit/Services/Auth/PasswordResetServiceTest.php` | Reset and change password |
| Email service | `tests/Unit/Services/Auth/EmailVerificationServiceTest.php` | Verify and resend rules |
| Auth controller | `tests/Unit/Http/Controllers/API/V1/Auth/AuthControllerTest.php` | Controller delegation |
| HTTP flows | `tests/Feature/Http/Controllers/API/V1/Auth/AuthFlowTest.php` | End-to-end auth, RBAC, password, email |

Run:

```bash
php artisan test --filter=Auth
```

Feature tests use `Tests\Concerns\SeedsRolesAndPermissions` to load `RolePermissionSeeder` before auth scenarios.

---

## Adding new protected endpoints

1. Add the route under `routes/api/v1.php` (not `routes/api.php`).
2. Place the controller in `App\Http\Controllers\API\V1\`.
3. Wrap with `middleware('auth:api')` and, if needed, `permission:` or `role:` middleware.
4. Use a **dedicated repository** if persistence is more than simple user lookups; do not put business logic in `EloquentCrudRepository`.
5. Add Form Requests, Service methods, and unit/feature tests following [CRUD Architecture](./crud-architecture.md).

---

## Related documentation

- [API Routing](./api-routing.md) — global vs versioned routes; auth belongs on v1+
- [CRUD Architecture & Repository Pattern](./crud-architecture.md) — layer responsibilities and dedicated repositories
- [User primary keys (UUID v7)](./user-uuid-v7.md) — user ID type and migrations
