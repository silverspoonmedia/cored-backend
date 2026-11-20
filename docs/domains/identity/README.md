# Identity — auth, RBAC, users

Core domain that owns authentication (JWT + cookie, 2FA, email verification, hCaptcha, throttling), the role/permission catalog, user management, lookups, and connected OAuth accounts. Everything a request needs to know *who* the caller is comes from here, and the browser never holds the token — the SPA authenticates with an HttpOnly `__Host-spa_jwt` cookie that the JSON body never echoes back. Code: `app/Domain/Identity/`. Invariants: [.cursor/rules/identity.mdc](../../../.cursor/rules/identity.mdc).

| Need | Path |
|------|------|
| Code | `packages/platform/src/Domain/Identity/` — `AuthService`, `UserManagementService`, `RoleService`, `TwoFactorService`, `UserOAuthAccountService` |
| Config | [`config/auth.php`](../../../config/auth.php) · [`config/jwt.php`](../../../config/jwt.php) · [`config/permission.php`](../../../config/permission.php) · Settings → Platform (`platform.jwt.*`, `platform.auth.*`, `platform.two_factor.*`) |
| API | [`packages/platform/routes/identity.php`](../../../packages/platform/routes/identity.php) — `/api/v1/auth/*`, `/api/v1/users*`, `/api/v1/lookups/*`, `/api/v1/profile/connections*` |
| Web route | [`packages/platform/routes/web-identity-oauth-callback.php`](../../../packages/platform/routes/web-identity-oauth-callback.php) — `auth.discord.callback` (the only identity web route) |
| SPA | Vue Router `auth.*`, `users.*`, `roles.*`, `profile.connections` |
| Realtime | `rbac.roles` / `rbac.users` / `rbac` (permission `view user`) · self-only `App.Models.User.{id}` |
| Translations | `lang/*/auth.php`, `lang/*/passwords.php` |
| Tests | `tests/Domain/Identity/` (`composer test -- --filter=Identity`) |
| Pakem | [.cursor/rules/identity.mdc](../../../.cursor/rules/identity.mdc) |
| Deep dive | [reference.md](reference.md) — packages, settings, session lifecycle, throttle contract, H-RBAC, connected accounts |
| Related | [activity-log](../activity-log/README.md) (`auth` log name) · [product-activations](../product-activations/README.md) (register hook) · [platform/security.md](../../platform/security.md) · [integration/vue-workers-spa.md](../../integration/vue-workers-spa.md) |
