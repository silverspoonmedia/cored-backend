# Identity — JWT, 2FA, H-RBAC, users

Auth, roles/permissions, user management, and lookups. Code: `app/Domain/Identity/`. Routes: `routes/api/v1/identity.php`, `routes/web/identity*.php`.

Auth is **not** on global `/api/*` — only versioned v1.

## Overview

| Concern | Implementation |
|---------|----------------|
| Guard | `api` (JWT driver) |
| PK | UUID v7 (`HasUuids`) |
| Token | Bearer JWT |
| Authorization | Spatie roles/permissions (`guard_name: api`, `domain_kind: core\|product`) |
| Auth persistence | Dedicated `UserRepository` |
| User admin | `UserManagementService` + `UserPolicy` + `RoleService` |
| HTTP | Form Requests → thin controllers → Services |

## Packages

`php-open-source-saver/jwt-auth`, `pragmarx/google2fa-laravel`, `spatie/laravel-permission`. Config: `jwt.php`, `auth.php`, `permission.php` (custom `Role` / `Permission` models). Spatie permission catalog cache uses a century-scale `expiration_time` (package has no forever API) and flushes on role/permission mutation — [cache-policy.md](../../platform/cache-policy.md).

## Architecture

```
Form Request → V1 Auth / User controllers → Auth* / UserManagementService → UserRepository → User
```

Auth failures: `App\Domain\Identity\Exceptions\Auth\*` (`BusinessRuleException`). Audit: [activity-log](../activity-log/README.md) `log_name: auth` — see [platform/security.md](../../platform/security.md).

### Layout (high level)

```
app/Domain/Identity/
├── Http/Controllers/API/V1/Auth/*, AdminController, UserController
├── Http/Controllers/Web/…
├── Services/Auth/*, UserManagementService, RoleService, TwoFactorService
├── Repositories/…/UserRepository
├── Models/User, Role, Permission
└── Exceptions/, Notifications/
```

`User`: `JWTSubject`, `MustVerifyEmail`, `HasRoles`, `HasUuids`, SoftDeletes, `$guard_name = 'api'`.

## Configuration

Tier A (env only) vs operational settings: [configuration-policy.md](../../platform/configuration-policy.md).

| Variable / setting | Notes |
|------------------|-------|
| `JWT_SECRET` | HS256 signing secret (env) |
| `HCAPTCHA_SITEKEY`, `HCAPTCHA_SECRET` | hCaptcha credentials (env) |
| `AUTH_GUARD=api` | Default API guard |

**Settings → Platform** (`system_settings`; runtime `setting_config()`):

| Registry key | Config path | Notes |
|--------------|-------------|-------|
| `platform.jwt.ttl` | `jwt.ttl` | Access token lifetime (minutes). Blade cookie max-age tracks this. Default 60. |
| `platform.jwt.refresh_ttl` | `jwt.refresh_ttl` | Refresh window from `iat`. Default 20160 (~14d). |
| `platform.jwt.remember_ttl` | `jwt.remember_ttl` | Access TTL when `remember_me=true` on login |
| `platform.jwt.refresh_skew` | `jwt.refresh_skew` | Minutes before `exp` when Blade proactively refreshes. Default 5. |
| `platform.jwt.blacklist_grace_period` | `jwt.blacklist_grace_period` | Seconds predecessor JWT stays valid after refresh (parallel tabs). Default **30**. |
| `platform.auth.default_role` | `auth.default_role` | Role assigned on registration. Default `User`. |
| `platform.auth.verification_expire` | `auth.verification.expire` | Email verification link TTL |
| `platform.two_factor.window` | `auth.two_factor.window` | TOTP verification window |
| `platform.two_factor.issuer` | `auth.two_factor.issuer` | QR label issuer |
| `platform.hcaptcha.enabled` | `hcaptcha.enabled` | Master switch (sitekey/secret stay env) |

### Blade silent refresh

Blade does **not** use the Laravel session guard for identity. After login, `auth_token` is the only identity cookie. `RefreshJwtCookie` + `AuthService::refreshFromToken` slide access tokens within `setting_config('jwt.refresh_ttl')` so admins are not hard-kicked at `setting_config('jwt.ttl')`. API: `POST /api/v1/auth/refresh` (Bearer; single refresh inside `AuthService` — no double `jwt.refresh` middleware). Details: [web/blade-admin](../../web/blade-admin.md#silent-refresh-sliding-access).

### hCaptcha

When enabled: login, register, forgot-password, two-factor login require `h-captcha-response`. Server: `HcaptchaVerifier` + Form Request trait. Blade: `<x-adminlte4::form.hcaptcha />` — see [web/blade-admin](../../web/blade-admin.md).

## Auth API (`/api/v1/auth/*`)

Typical surface (all under `auth:api` where noted):

| Area | Endpoints (concept) |
|------|---------------------|
| Guest | register, login, forgot/reset password, 2FA login |
| Session | me, logout, refresh, locale |
| Email | verify, resend |
| 2FA setup | start, confirm, disable, QR |

JWT: Bearer header (API) or HttpOnly cookie on Blade (`auth_token` → Bearer via `AuthenticateWithJwtCookie` + silent `RefreshJwtCookie`). Octane: no sticky claims; cleanup guards after token ops — [platform/octane](../../platform/octane.md).

**Register side-effect:** `AuthService::register` auto-activates every implicit Product domain declared in [`config/products.php`](../../../config/products.php) (e.g. FileHosting) by inserting a `product_activations` row with `state = active` and `source = register_hook`. Failures are logged but do not abort registration — the `product.active` middleware lazy-creates a missing implicit activation on first product endpoint hit. See [Product Activations](../product-activations/README.md).

Roles seeded in `RoleSeeder` (Root, Admin, Moderator, Management, User) — all **`domain_kind = core`**. Example protected: `GET /api/v1/admin/ping` with permission middleware.

## Core vs product RBAC

| Field | On | Values | Purpose |
|-------|-----|--------|---------|
| `domain_kind` | `roles`, `permissions` | `core`, `product` | Catalog ownership (platform vs product modules) |
| `level` | `roles` only | unsigned int, nullable | Hierarchy **scoped per `domain_kind`** — unique `(domain_kind, level)` |

This is **not** the module registry in [`config/domains.php`](../../../config/domains.php) (`kind` + `locked` for `domain:list` / `domain:remove`). Same words (core/product) for taxonomy; different systems. See [platform/architecture.md](../../platform/architecture.md).

- **Spatie names stay globally unique** on `(name, guard_name)`. Product must not reuse core names (`Admin`, `view user`, …). Prefer e.g. `File Hosting Admin`.
- **H-RBAC user management uses core roles only.** Product role levels never change list/view/edit/delete/assignable for platform users.
- **Permission checks** (`permission:…`, `$user->can()`) remain **name-based**; `domain_kind` tags seed ownership and future admin filters, not Gate resolution.
- **Cross-kind pivots OK:** a core role may hold product permissions via `role_has_permissions`.
- **Seeding:** [`RoleSeeder`](../../../database/seeders/RoleSeeder.php) = core catalog. Product modules add their own seeder (`domain_kind = product`, own level ladder).

Enum: `App\Domain\Identity\Enums\DomainKind`. Models: scopes `Role::core()` / `Role::product()` (same on `Permission`).

## H-RBAC (user management)

**Level (core only):** lower number = higher authority. Root=1, Admin=100, Moderator=200, Management=300, User=400. Actor effective level = **min** of their **core** roles with non-null `level`. Product roles are ignored for hierarchy math.

| Operation | Rule |
|-----------|------|
| List/show | Target min **core** level ≥ actor **core** level (peers OK; higher authority hidden) |
| Update/delete | **Every** target **core** role level **>** actor **core** level |
| Assign role | **Core** role level **>** actor level only (`/users/assignable-roles` is core-only) |

Permissions: `view/create/edit/delete user` (space-separated names). Soft delete; cannot delete self (422).

| Method | URI |
|--------|-----|
| GET/POST | `/api/v1/users` |
| GET/PUT/DELETE | `/api/v1/users/{user}` |
| GET | `/api/v1/users/assignable-roles` |

`{user}` = UUID. Blade web controllers **only** proxy these APIs via `ApiClient` — no second RBAC path.

**Sidebar:** **Administration → Access control → User management** (`users.index`), visible to Root, Admin, Moderator, and Management. Menu visibility is presentation only; API policy and H-RBAC remain authoritative.

Notifications on create/update: [notification domain](../notification/README.md).

## Lookups

Paginated select options: `GET /api/v1/lookups/*` — [web/ui-patterns](../../web/ui-patterns.md).

## Tests

Feature/unit under `tests/Domain/Identity/`. Prefer `composer test -- --filter=…`.

## Related

- [platform/identifiers.md](../../platform/identifiers.md)
- [platform/api-routing.md](../../platform/api-routing.md)
- [web/blade-admin.md](../../web/blade-admin.md)
