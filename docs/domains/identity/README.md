# Identity — JWT, 2FA, H-RBAC, users

Auth, roles/permissions, user management, and lookups. Code: `app/Domain/Identity/`. Routes: `routes/api/v1/identity.php` plus one server endpoint, `routes/web/identity-oauth-callback.php` (Discord provider callback — no UI).

Auth is **not** on global `/api/*` — only versioned v1.

## Overview

| Concern | Implementation |
|---------|----------------|
| Guard | `api` (JWT driver) |
| PK | UUID v7 (`HasUuids`) |
| Token | SPA: HttpOnly `__Host-spa_jwt` cookie. Machine clients: `Authorization: Bearer`. |
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
| `SPA_URL` | Vue-Workers public origin. OAuth `return_to` allowlist. Non-empty also mints password-reset, email-verify, Archivd `share_url`, and iPaymu hosted return/cancel onto that origin (`App\Support\SpaPublicUrl`); empty falls back to API-signed URLs and disables SPA OAuth return. Matrix: [Production URL minting](../../integration/vue-workers-spa.md#production-url-minting-spa_url). Env semantics: [environment.md](../../platform/environment.md). |
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

Logout sets persistent barrier before API call. Subsequent cold-boot `auth/me` cannot restore authenticated state, including direct `/apps/dashboard`, until `applyAuth()` handles a successful login, registration, or 2FA session. Logout cookie deletion uses same `__Host-`, `Path=/`, `Secure`, `SameSite=None`, host-only attributes Laravel used at issuance.

The SPA authenticates cross-site with HttpOnly `__Host-spa_jwt` cookie instead of Bearer header, so `AuthService::refresh()` falls back to cookie when no `Authorization` header exists — cookie-mode session refreshes same way (otherwise hard-expires at `jwt.ttl`). On login/register/refresh/2FA-login raw JWT is set as `__Host-spa_jwt` cookie and **stripped from JSON body** (`data.token` removed), so SPA JS never reads it and XSS cannot exfiltrate it; `TwoFactorController` applies same strip. Logout blacklists current JWT and expires `__Host-spa_jwt`; later `auth/me` carrying same Bearer token or stale cookie must return `401`. The `__Host-` prefix enforces `Secure`, `Path=/`, and no `Domain`.

The SPA-side session lifecycle (logout barrier/epoch, `BroadcastChannel` cross-tab sync, post-login cookie-settle serialization, guest cold-boot single `auth/me` probe) is owned by sibling [auth-session.md](../../../../monolith-vue-workers/docs/auth-session.md). Contract pointer: [vue-workers-spa.md — Cross-tab auth](../../integration/vue-workers-spa.md#cross-tab-auth-multi-tab-session-sync).

The removed Blade admin used a separate first-party JWT cookie bridge; that middleware is gone — see [web/blade-admin.md](../../web/blade-admin.md).

### Remember-flag preservation across refresh

`AuthService::refreshFromToken` preserves the `remember` flag across token refresh so a remember-me session does not silently downgrade to a short-TTL session. The flag is resolved with priority:

1. `remember` in the refresh request body (SPA opt-in per refresh).
2. `rm` custom claim on the original token (set at login: `1` = remember, `0` = normal; persisted across refreshes via `jwt.persistent_claims`).
3. Fallback `false`.

Login, 2FA login, and register all inject the `rm` claim via `AuthService::customClaims()`. The Vue SPA cross-tab auth layer relies on this — sibling [auth-session.md](../../../../monolith-vue-workers/docs/auth-session.md); contract [vue-workers-spa — Cross-tab auth](../../integration/vue-workers-spa.md#cross-tab-auth-multi-tab-session-sync).

### hCaptcha

When enabled: login, register, forgot-password, reset-password, and two-factor login require `h-captcha-response`. Server: `HcaptchaVerifier` + Form Request trait (`ValidatesHcaptcha`, merged into `ResetPasswordRequest` for parity with forgot-password). SPA: hCaptcha widget on auth views in `monolith-vue-workers`.

### Login throttle (identity-scoped, failure-only)

`POST /api/v1/auth/login` and `POST /api/v1/auth/two-factor/login` do **not** use route middleware `throttle:*` — enforcement lives in `App\Domain\Identity\Support\AuthLoginThrottle`, called from `AuthService::login()` and `AuthService::loginWithTwoFactorToken()`. Limits: `config/auth.php` → `login_throttle` (`max_attempts` 5, `decay_seconds` 60, `ip_backstop_max` 20).

Behavior contract:

- **Identity scope = email + client IP** (2FA scope = `2fa:` + sha1 of the challenge token). One user failing on a shared IP (NAT/CGNAT/office) never locks out other users on that IP — the previous `throttle:5,1` keyed every guest on `domain|IP` and caused exactly that false-positive.
- **Failure-only counting.** `RateLimiter::hit()` runs only after `InvalidCredentialsException` / `InvalidTwoFactorCodeException`. Validation rejects (422), CSRF failures, hCaptcha failures, and successful logins never consume quota.
- **Success resets.** Valid credentials clear the identity counter so a user who recovers the right password is not kept out by earlier typos.
- **IP backstop** (`ip_backstop_max`, default 20/min) still contains cross-email brute force from one address; it is never cleared by a successful login.
- **Locked response**: 429 JSON envelope with `Retry-After` / `X-RateLimit-*` headers (rendered in `bootstrap/app.php` `ThrottleRequestsException` handler).
- Register keeps middleware `throttle:5,1,auth-register` — its own key prefix, so registrations never share a counter with logins.

### Public URLs (`SPA_URL`)

`App\Support\SpaPublicUrl` is the single mint for browser-facing identity links when `config('services.spa.url')` is non-empty. Full minting matrix (password reset, email verify, Archivd `share_url`, iPaymu hosted return/cancel): [vue-workers-spa — Production URL minting](../../integration/vue-workers-spa.md#production-url-minting-spa_url).

## Auth API (`/api/v1/auth/*`)

Typical surface (all under `auth:api` where noted):

| Area | Endpoints (concept) |
|------|---------------------|
| Guest | register, login, forgot/reset password, 2FA login |
| Session | me, logout, refresh, locale |
| Email | verify, resend |
| 2FA setup | start, confirm, disable, QR |

JWT: Bearer header (API) or HttpOnly `__Host-spa_jwt` cookie (SPA via `AuthenticateWithSpaCookie`). Octane: no sticky claims; cleanup guards after token ops — [platform/octane](../../platform/octane.md).

**Register side-effect:** `AuthService::register` auto-activates every implicit Product domain declared in [`config/products.php`](../../../config/products.php) (e.g. Archivd) by inserting a `product_activations` row with `state = active` and `source = register_hook`. Failures are logged but do not abort registration — the `product.active` middleware lazy-creates a missing implicit activation on first product endpoint hit. See [Product Activations](../product-activations/README.md).

Roles seeded in `RoleSeeder` (Root, Admin, Moderator, Management, User) — all **`domain_kind = core`**. Example protected: `GET /api/v1/admin/ping` with permission middleware.

## Core vs product RBAC

| Field | On | Values | Purpose |
|-------|-----|--------|---------|
| `domain_kind` | `roles`, `permissions` | `core`, `product` | Catalog ownership (platform vs product modules) |
| `level` | `roles` only | unsigned int, nullable | Hierarchy **scoped per `domain_kind`** — unique `(domain_kind, level)` |

This is **not** the module registry in [`config/domains.php`](../../../config/domains.php) (`kind` + `locked` for `domain:list` / `domain:remove`). Same words (core/product) for taxonomy; different systems. See [platform/architecture.md](../../platform/architecture.md).

- **Spatie names stay globally unique** on `(name, guard_name)`. Product must not reuse core names (`Admin`, `view user`, …). Prefer e.g. `Archivd Admin`.
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

Query-mode `GET /api/v1/users` rows include `actions[]` (`App\Support\ListActions\Builders\UserQueryRowActions`): always `edit` → SPA `users.edit`; `delete` only when the actor has `delete user` and the row is not self. Contract: [list-display-modes.md](../../platform/list-display-modes.md#query-mode-actions-live-spa-contract). Paged query-mode without `search` uses SQL `LIMIT` (`UserRepository::paginateVisibleToActorLevel`); `search` still uses the PHP recursive matcher so hits stay unchanged.

**Sidebar:** **Administration → Access control → User management** (`users.index`), visible to Root, Admin, Moderator, and Management. Menu visibility is presentation only; API policy and H-RBAC remain authoritative.

Notifications on create/update: [notification domain](../notification/README.md).

## Lookups

Paginated select options: `GET /api/v1/lookups/*` — [platform/list-display-modes.md](../../platform/list-display-modes.md).

## Realtime

RBAC and auth mutations ping channels after a successful persist (gated on `BroadcastConnection::isConfigured()`), payloads ids + `action` only:

| Channel (permission) | Event | Fired by |
|----------------------|-------|----------|
| `rbac.roles` (`view user`) | `.rbac.role.list.changed`, `.rbac.role.permission.changed` | `RbacRealtime` — role CRUD, grant/revoke, assignment |
| `rbac.users` (`view user`) | `.rbac.user.list.changed` | `RbacRealtime` — admin user create/update/delete |
| `rbac` (`view user`, legacy) | `.rbac.role.permission.changed` | kept for older SPA builds |
| `App.Models.User.{id}` (self-only) | `.auth.state.changed`, `.auth.security.changed` | `AuthStateBroadcast` — login/logout, password change, 2FA enable/disable |

SPA: `useAdminRealtime.ts` (Users/role views refetch), `useAuthSelfRealtime.ts` (Password/TwoFactor views refetch own state).

## Connected accounts (OAuth providers)

SPA: `/apps/profile/connections` (`ConnectionsView` — one card section per `providers[]` row, with Connect / Sync / Disconnect on that section). Discord is the only `OAuthProvider` case today; the contract and UI do not assume it is the last.

API (Bearer, any authenticated user):

| Method | URI | Behavior |
|--------|-----|----------|
| GET | `/api/v1/profile/connections` | `{ providers: [...] }` — one row per `OAuthProvider` case. Stale Discord snapshots still auto-refresh here (`identity.discord.connections_snapshot_max_age_minutes`, default 60) and on vTual verify miss. |
| POST | `/api/v1/profile/connections/{oauth_provider}/sync` | Force-refresh that provider’s snapshot (bypasses TTL). Discord is the only case with `supports_sync: true`. Throttled `throttle:10,1`. Returns the same `{ providers }` payload as GET. Not connected / token refresh failed → existing `IdentityOAuthException` (422 / 401). |
| DELETE | `/api/v1/profile/connections/{oauth_provider}` | Disconnect that provider. |
| GET | `/api/v1/auth/discord/authorize-url` | Discord connect URL. |

The path parameter is `{oauth_provider}` constrained with `whereIn` `OAuthProvider::values()` (unknown values 404). Do **not** name it `{provider}` — Inference globally binds `{provider}` to `InferenceProvider` (`InferenceServiceProvider`), which would 404 `"Data not found."` for `discord`.

Each `providers[]` row: `key` (enum value), `connected`, `oauth_configured`, `supports_sync`, `synced_at` / `synced_at_formatted` / `synced_at_human`, `connections[]` (`type`, `id`, `name`, `verified`, `revoked`). `connections` is empty when disconnected.

OAuth **callback** stays Monolith web (`auth.discord.callback` at `/apps/auth/discord/callback`) — identity is the encrypted state, not a session cookie. SPA starts OAuth via the authorize-url endpoint, then `window.location` to Discord.

Scopes: `identify` + `connections`. Table `identity_oauth_accounts` stores envelope-encrypted tokens + **full** Discord connection snapshot (`connections_snapshot` JSON: all connection types, documented safe fields — no secrets).

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
