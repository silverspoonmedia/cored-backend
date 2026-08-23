# Identity — JWT, 2FA, H-RBAC, users

Auth, roles/permissions, user management, and lookups. Code: `app/Domain/Identity/`. Routes: `routes/api/v1/identity.php` plus one server endpoint, `routes/web/identity-oauth-callback.php` (Discord provider callback — no UI).

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
├── Services/Auth/*, UserManagementService, RoleService, TwoFactorService, UserOAuthAccountService
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
| `SPA_URL` | Vue-Workers public origin. OAuth `return_to` allowlist. Non-empty also mints password-reset, email-verify, File Hosting `share_url`, and iPaymu hosted return/cancel onto that origin (`App\Support\SpaPublicUrl`). Empty falls back to API-signed URLs and disables SPA OAuth return. |
| `AUTH_GUARD=api` | Default API guard |

**Settings → Platform** (`system_settings`; runtime `setting_config()`):

| Registry key | Config path | Notes |
|--------------|-------------|-------|
| `platform.jwt.ttl` | `jwt.ttl` | Access token lifetime (minutes). SPA cookie max-age tracks this. Config literal **240**; a `system_settings` row (Settings → Platform) wins when present. |
| `platform.jwt.refresh_ttl` | `jwt.refresh_ttl` | Refresh window from `iat`. Default 20160 (~14d). |
| `platform.jwt.remember_ttl` | `jwt.remember_ttl` | Access TTL when `remember_me=true` on login |
| `platform.jwt.refresh_skew` | `jwt.refresh_skew` | Minutes before `exp` when clients may proactively refresh. Default 5. |
| `platform.jwt.blacklist_grace_period` | `jwt.blacklist_grace_period` | Seconds predecessor JWT stays valid after refresh (parallel tabs). Default **30**. |
| `platform.auth.default_role` | `auth.default_role` | Role assigned on registration. Default `User`. |
| `platform.auth.verification_expire` | `auth.verification.expire` | Email verification link TTL |
| `platform.two_factor.window` | `auth.two_factor.window` | TOTP verification window |
| `platform.two_factor.issuer` | `auth.two_factor.issuer` | QR label issuer |
| `platform.hcaptcha.enabled` | `hcaptcha.enabled` | Master switch (sitekey/secret stay env) |

### SPA cookie-mode refresh & token strip

The SPA authenticates cross-site with the HttpOnly `__Host-spa_jwt` cookie instead of a Bearer header, so `AuthService::refresh()` falls back to that cookie when no `Authorization` header is present — a cookie-mode session refreshes the same way (otherwise it would hard-expire at `jwt.ttl`). On login/register/refresh/2FA-login the raw JWT is set as the `__Host-spa_jwt` cookie and **stripped from the JSON body** (`data.token` removed) so SPA JS never reads it and an XSS payload cannot exfiltrate it; `TwoFactorController` applies the same strip. The `__Host-` prefix enforces `Secure`, `Path=/`, and no `Domain`. Full contract: [../../integration/vue-workers-spa.md](../../integration/vue-workers-spa.md).

The removed Blade admin used a separate first-party JWT cookie bridge; that middleware is gone — see [web/blade-admin.md](../../web/blade-admin.md).

### Remember-flag preservation across refresh

`AuthService::refreshFromToken` preserves the `remember` flag across token refresh so a remember-me session does not silently downgrade to a short-TTL session. The flag is resolved with priority:

1. `remember` in the refresh request body (SPA opt-in per refresh).
2. `rm` custom claim on the original token (set at login: `1` = remember, `0` = normal; persisted across refreshes via `jwt.persistent_claims`).
3. Fallback `false`.

Login, 2FA login, and register all inject the `rm` claim via `AuthService::customClaims()`. The Vue SPA cross-tab auth layer relies on this — see [vue-workers-spa — Cross-tab auth](../../integration/vue-workers-spa.md#cross-tab-auth-multi-tab-session-sync).

### hCaptcha

When enabled: login, register, forgot-password, reset-password, and two-factor login require `h-captcha-response`. Server: `HcaptchaVerifier` + Form Request trait (`ValidatesHcaptcha`, merged into `ResetPasswordRequest` for parity with forgot-password). SPA: hCaptcha widget on auth views in `monolith-vue-workers`.

### Public URLs (`SPA_URL`)

`App\Support\SpaPublicUrl` is the single mint for browser-facing identity links when `config('services.spa.url')` is non-empty:

| Notification | Empty `SPA_URL` | Set `SPA_URL` |
|--------------|-----------------|---------------|
| Password reset | Signed API fallback | `{SPA_URL}/reset-password?token=&email=` |
| Email verify | Signed `GET /api/v1/auth/email/verify/{id}/{hash}` | `{SPA_URL}/verify-email/{id}/{hash}?expires=&signature=` — HMAC still generated against the API route on `APP_URL`. The SPA page GETs that signed path direct from the API origin (`VITE_API_BASE_URL`). |

CORS `allowed_origins` is `APP_URL` + `SPA_URL` (`config/cors.php`). Same-origin Worker traffic does not use CORS. Details: [vue-workers-spa — Production URL minting](../../integration/vue-workers-spa.md#production-url-minting-spa_url).

## Auth API (`/api/v1/auth/*`)

Typical surface (all under `auth:api` where noted):

| Area | Endpoints (concept) |
|------|---------------------|
| Guest | register, login, forgot/reset password, 2FA login |
| Session | me, logout, refresh, locale |
| Email | verify, resend |
| 2FA setup | start, confirm, disable, QR |

JWT: Bearer header (API) or HttpOnly `__Host-spa_jwt` cookie (SPA via `AuthenticateWithSpaCookie`). Octane: no sticky claims; cleanup guards after token ops — [platform/octane](../../platform/octane.md).

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

`{user}` = UUID. SPA views call these API endpoints directly — no second RBAC path.

**Sidebar:** **Administration → Access control → User management** (`users.index`), visible to Root, Admin, Moderator, and Management. Menu visibility is presentation only; API policy and H-RBAC remain authoritative.

Notifications on create/update: [notification domain](../notification/README.md).

## Lookups

Paginated select options: `GET /api/v1/lookups/*` — [platform/list-display-modes.md](../../platform/list-display-modes.md).

## Connected accounts (Discord)

SPA: `/apps/profile/connections`. API: `GET /api/v1/profile/connections`, `DELETE /api/v1/profile/connections/discord`, `GET /api/v1/auth/discord/authorize-url` (Bearer). OAuth **callback** stays Monolith web (`auth.discord.callback` at `/apps/auth/discord/callback`) — identity is the encrypted state, not a session cookie. SPA starts OAuth via the authorize-url endpoint, then `window.location` to Discord.

Scopes: `identify` + `connections`. Table `identity_oauth_accounts` stores envelope-encrypted tokens + **full** Discord connection snapshot (`connections_snapshot` JSON: all connection types, documented safe fields — no secrets). TTL setting `identity.discord.connections_snapshot_max_age_minutes` (default 60): stale snapshot refreshes on profile connections page load (`GET profile/connections`) and on vTual verify miss.

Post-OAuth browser return: encrypted state may carry `return_to`. Allowlisted origins are `APP_URL` and `SPA_URL`. Paths must start with `/apps/`. SPA passes `return_path` only; the API prefixes `SPA_URL`. Empty `SPA_URL` → authorize-url 503.

vTual consumes the snapshot in two ways: **`ownershipEligible()`** (YouTube/Twitch verified subset) for claim verify paths; other connection types map to creator external links via `DiscordConnectionExternalLinkMapper`. Private discovered YouTube/Twitch channels bind on orphan Discord claim until owner promote — see [vTual README — Creator claim](../vtual/README.md#creator-claim-phase-1---binding).

Env: non-empty `DISCORD_CLIENT_ID` + `DISCORD_CLIENT_SECRET` required; `DISCORD_REDIRECT_URI` defaults to `{APP_URL}/apps/auth/discord/callback` when unset. `SPA_URL` is the Vue-Workers public origin (OAuth return allowlist). Connected-accounts and claim Discord UI stay hidden until Discord credentials are configured.

## Tests

Feature/unit under `tests/Domain/Identity/`. Prefer `composer test -- --filter=…`.

## Related

- [platform/identifiers.md](../../platform/identifiers.md)
- [platform/api-routing.md](../../platform/api-routing.md)
- [web/blade-admin.md](../../web/blade-admin.md)
- [integration/vue-workers-spa.md](../../integration/vue-workers-spa.md) — SPA verify hop, `SPA_URL` minting
