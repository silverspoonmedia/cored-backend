# JWT Authentication & RBAC

This document describes the API authentication stack: **JWT-based auth** (`php-open-source-saver/jwt-auth`), **TOTP two-factor authentication** (`pragmarx/google2fa-laravel`), **role and permission management** (`spatie/laravel-permission`), and how they are wired into the versioned API under `/api/v1`.

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
| `pragmarx/google2fa-laravel` | Generate TOTP secrets and verify authenticator app codes |
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

**Audit trail:** Login, logout, 2FA, and password changes are recorded in Spatie activitylog (`log_name: auth`) and in Laravel's file log via event listeners. See [Authentication activity assessment](./auth-activity-assessment.md) and [Activity log](./activity-log.md).

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
│       ├── Concerns/ValidatesHcaptcha.php
│       ├── RegisterRequest.php
│       ├── LoginRequest.php
│       ├── ForgotPasswordRequest.php
│       ├── TwoFactorLoginRequest.php
│       ├── ResetPasswordRequest.php
│       └── ChangePasswordRequest.php
├── Rules/
│   └── Hcaptcha.php                     ← siteverify validation rule
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
├── Support/Security/
│   └── HcaptchaVerifier.php             ← POST to api.hcaptcha.com/siteverify
└── Exceptions/Auth/
    ├── InvalidCredentialsException.php
    ├── InvalidPasswordResetTokenException.php
    ├── EmailAlreadyVerifiedException.php
    └── CurrentPasswordIncorrectException.php

routes/api/v1.php                        ← all auth routes
database/seeders/RoleSeeder.php
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
AUTH_DEFAULT_ROLE=User
AUTH_VERIFICATION_EXPIRE=60

# hCaptcha (defense-in-depth on public auth endpoints; disabled by default for local dev)
HCAPTCHA_ENABLED=false
HCAPTCHA_SECRET=
HCAPTCHA_SITEKEY=
```

`phpunit.xml` sets `JWT_SECRET`, `AUTH_GUARD=api`, and `HCAPTCHA_ENABLED=false` for tests.

### hCaptcha (defense-in-depth)

When `HCAPTCHA_ENABLED=true`, public auth endpoints require a valid `h-captcha-response` token (verified server-side against `https://api.hcaptcha.com/siteverify`). No Composer package is used — verification lives in `App\Support\Security\HcaptchaVerifier` and `App\Rules\Hcaptcha`.

| Variable | Purpose |
|----------|---------|
| `HCAPTCHA_ENABLED` | `true` in production; `false` locally so devs are not solving puzzles on every login |
| `HCAPTCHA_SECRET` | Server secret from the [hCaptcha dashboard](https://dashboard.hcaptcha.com/) |
| `HCAPTCHA_SITEKEY` | Public site key (rendered in Blade when enabled) |

Config file: `config/hcaptcha.php`.

**Protected endpoints** (when enabled):

| Method | URI |
|--------|-----|
| `POST` | `/auth/login` |
| `POST` | `/auth/register` |
| `POST` | `/auth/forgot-password` |
| `POST` | `/auth/two-factor/login` |

Validation is enforced in API Form Requests via the `ValidatesHcaptcha` trait (`app/Http/Requests/Auth/Concerns/ValidatesHcaptcha.php`). Direct API clients (mobile apps, scripts) must include `h-captcha-response` in the JSON body — the web layer only forwards the field; it does not validate.

**Web UI:** see [Web frontend — hCaptcha](./web-frontend.md#hcaptcha-on-auth-forms). Summary: `<x-adminlte4::form.hcaptcha />` (widget + scaling + assets) and `<x-adminlte4::form.auth-submit />` (captcha + submit grid) on auth screens; `PasswordResetController` and `TwoFactorController` forward `h-captcha-response` to the API. On `422`, `ajax-form` dispatches `ajax-form:invalid`; the hCaptcha script resets and refits the widget.

**Tests:** `php artisan test --filter=Hcaptcha` (`HcaptchaVerifierTest`, `HcaptchaTest`, `HcaptchaConfigTest`, `HcaptchaAuthTest`).

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

'default_role' => env('AUTH_DEFAULT_ROLE', 'User'),

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
| `permission` | Spatie `PermissionMiddleware` | `middleware('permission:view user')` |
| `role_or_permission` | Spatie `RoleOrPermissionMiddleware` | Either role or permission |

Protected routes use `auth:api` (JWT guard). Example RBAC route:

```php
Route::middleware(['auth:api', 'permission:view user'])
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

API routes **never redirect** to a web login page. `bootstrap/app.php` sets `redirectGuestsTo` to return `null` for `api/*` requests so unauthenticated API clients always receive JSON **401** instead of a redirect. The Blade frontend has its own `login` route and uses the `web.auth` middleware alias to redirect guests there.

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
| `POST` | `/auth/login` | `api.v1.auth.login` | Authenticate and return JWT, or return a 2FA challenge when enabled |
| `POST` | `/auth/two-factor/login` | `api.v1.auth.two-factor.login` | Exchange `two_factor_token` + TOTP code for a JWT |
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
| `POST` | `/auth/two-factor/setup` | `api.v1.auth.two-factor.setup` | Generate encrypted secret + QR URL |
| `POST` | `/auth/two-factor/confirm` | `api.v1.auth.two-factor.confirm` | Verify setup code and activate 2FA |
| `POST` | `/auth/two-factor/disable` | `api.v1.auth.two-factor.disable` | Verify TOTP and remove 2FA |

### RBAC example

| Method | URI | Middleware | Description |
|--------|-----|------------|-------------|
| `GET` | `/admin/ping` | `auth:api`, `permission:view user` | Example permission-gated endpoint |

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

When `HCAPTCHA_ENABLED=true`, public auth requests (`login`, `register`, `forgot-password`, `two-factor/login`) must also include `h-captcha-response` (string from the client widget or hCaptcha SDK). See [hCaptcha (defense-in-depth)](#hcaptcha-defense-in-depth).

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

This API uses **stateless JWT**, not session cookies. "Remember me" means a **longer access-token TTL** (default: same window as `JWT_REFRESH_TTL`, two weeks), not a Laravel `remember_token` cookie. The response `data.remember` field and `data.expires_in` indicate the chosen TTL.

| `remember_me` | Token TTL | `data.remember` | `data.expires_in` |
|---------------|-----------|-----------------|-------------------|
| `false` / omitted | `JWT_TTL` (default 60 minutes) | `false` | `JWT_TTL * 60` seconds |
| `true` | `JWT_REMEMBER_TTL` (default 20160 minutes) | `true` | `JWT_REMEMBER_TTL * 60` seconds |

### Two-factor authentication (2FA)

2FA uses TOTP via `pragmarx/google2fa-laravel`. The user secret is stored encrypted in `users.two_factor_secret`; 2FA is considered active only when `users.two_factor_confirmed_at` is set.

**Setup flow (authenticated):**

1. `POST /api/v1/auth/two-factor/setup` generates a secret, stores it encrypted, and returns `secret`, `qr_code_url` (`otpauth://...`), and `qr_code_svg` (`data:image/svg+xml;base64,...`).
2. The client shows the QR image first and keeps the manual secret / setup URL as fallback.
3. `POST /api/v1/auth/two-factor/confirm` with `{ "code": "123456" }` verifies the current TOTP and sets `two_factor_confirmed_at`.
4. `POST /api/v1/auth/two-factor/disable` with a valid TOTP code clears the encrypted secret and confirmation timestamp.

**Login flow when 2FA is enabled:**

1. `POST /api/v1/auth/login` validates email/password but does **not** return a JWT.
2. The response contains `data.two_factor_required = true` and a short-lived `data.two_factor_token` (5 minutes).
3. The client calls `POST /api/v1/auth/two-factor/login` with `{ "two_factor_token": "...", "code": "123456" }`.
4. Only this second step returns the real JWT auth payload.

Why this works: password check proves factor 1; TOTP proves possession of the authenticator secret. The first step cannot return a JWT, or 2FA becomes decorative clown makeup.

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
      "roles": ["User"],
      "permissions": ["view notification"]
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

Seeded by `RoleSeeder` (`guard_name`: `api`). Roles use PascalCase names and a numeric **`level`** column for hierarchy (smaller level = higher authority). See [User Management & Hierarchical RBAC](./user-management-hrbac.md) for the full role matrix, user CRUD endpoints, and policy rules.

| Role | Level | User-management permissions |
|------|-------|-----------------------------|
| `Root` | 1 | `view user`, `create user`, `edit user`, `delete user` |
| `Admin` | 100 | All four |
| `Moderator` | 200 | `view user`, `create user`, `edit user` |
| `Management` | 300 | `view user` |
| `User` | 400 | *(none)* |

On registration, `AuthService` assigns the role from `config('auth.default_role')` (default: `User`).

### Checking access in routes

```php
// Single permission (names contain spaces)
Route::middleware(['auth:api', 'permission:view user'])->get(...);

// Single role
Route::middleware(['auth:api', 'role:Admin'])->get(...);

// Role or permission
Route::middleware(['auth:api', 'role_or_permission:Admin|view user'])->get(...);
```

### Checking access in code

```php
$user->hasRole('Admin');
$user->can('view user');
```

The `GET /api/v1/auth/me` response includes `roles` and `permissions` arrays for client-side UI hints; **always enforce authorization on the server** with middleware or policies (e.g. `UserPolicy` for `/api/v1/users/*`).

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
- Password: `123456789`
- Role: `Admin`

Ensure `MAIL_*` is configured for verification and reset emails (tests use `MAIL_MAILER=array`).

---

## Services reference

### `AuthService`

| Method | Description |
|--------|-------------|
| `register(array $data)` | Create user, assign default role, send verification email, return auth payload |
| `login(array $credentials)` | Attempt password login; returns JWT or a short-lived 2FA challenge when enabled; throws `InvalidCredentialsException` on failure |
| `loginWithTwoFactorToken(string $token, string $code)` | Verify the 2FA challenge and issue the real JWT |
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
| Auth service | `tests/Unit/Services/Auth/AuthServiceTest.php` | Register, login, credentials, 2FA challenge handoff |
| 2FA service | `tests/Unit/Services/Auth/TwoFactorServiceTest.php` | Secret generation/encryption, confirmation, disable, TOTP validation |
| Password service | `tests/Unit/Services/Auth/PasswordResetServiceTest.php` | Reset and change password |
| Email service | `tests/Unit/Services/Auth/EmailVerificationServiceTest.php` | Verify and resend rules |
| Auth controller | `tests/Unit/Http/Controllers/API/V1/Auth/AuthControllerTest.php` | Controller delegation |
| HTTP flows | `tests/Feature/Http/Controllers/API/V1/Auth/AuthFlowTest.php` | End-to-end auth, RBAC, password, email |

Run:

```bash
php artisan test --filter=Auth
```

Feature tests use `Tests\Concerns\SeedsRolesAndPermissions` to load `RoleSeeder` before auth scenarios.

---

## Adding new protected endpoints

1. Add the route under `routes/api/v1.php` (not `routes/api.php`).
2. Place the controller in `App\Http\Controllers\API\V1\`.
3. Wrap with `middleware('auth:api')` and, if needed, `permission:` or `role:` middleware.
4. Use a **dedicated repository** if persistence is more than simple user lookups; do not put business logic in `EloquentCrudRepository`.
5. Add Form Requests, Service methods, and unit/feature tests following [CRUD Architecture](./crud-architecture.md).

---

## Web frontend (Blade)

The Blade admin is a **pure consumer** of the auth endpoints above — it does not call `AuthService` directly. Login, register, logout, password reset/change, and email verification resend all forward to `/api/v1/auth/*` via `App\Support\Api\ApiClient`. The returned JWT is stored in the HttpOnly `auth_token` cookie (`IssuesAuthCookie`); on every subsequent request `AuthenticateWithJwtCookie` binds it to the `api` guard for `web.auth`/`web.guest`, and `ApiClient` re-forwards it as `Authorization: Bearer` to the API.

See [Web frontend (Blade + AdminLTE 4)](./web-frontend.md) for the full flow, `ConsumesApi` / `MonolithApi` error translation ([AJAX error handling](./web-frontend.md#ajax-error-handling-contract)), and the `PHP_CLI_SERVER_WORKERS` caveat for self-HTTP calls.

---

## Related documentation

- [Web frontend (Blade + AdminLTE 4)](./web-frontend.md) — JWT cookie bridge and API-client web controllers
- [API Routing](./api-routing.md) — global vs versioned routes; auth belongs on v1+
- [User Management & Hierarchical RBAC](./user-management-hrbac.md) — hierarchical user CRUD, `UserPolicy`, and `RoleService`
- [CRUD Architecture & Repository Pattern](./crud-architecture.md) — layer responsibilities and dedicated repositories
- [User primary keys (UUID v7)](./user-uuid-v7.md) — user ID type and migrations
