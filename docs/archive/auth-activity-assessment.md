# Authentication activity assessment

Assessment of how **authentication and session lifecycle** events are observed in this application, and how they map to **Spatie activitylog** (`activity_log`, `log_name: auth`) versus other logging surfaces.

See also [JWT authentication & RBAC](../domains/identity/README.md), [Activity log](../domains/activity-log/README.md), and `SECURITY_AUDIT.md` (FINDING-019).

---

## Auth architecture (summary)

| Layer | Responsibility |
|-------|----------------|
| `AuthService` | Login, logout, register, refresh, 2FA challenge completion |
| `PasswordResetService` | Forgot/reset link, authenticated password change |
| `TwoFactorService` | TOTP setup, confirm, disable, verify at login |
| `UserRepository` | User persistence (register, password updates) |
| JWT guard (`api`) | Stateless bearer tokens; blacklist on logout |

Auth flows are **event-driven** for observability: services dispatch domain events; listeners handle side effects (file log + activity log).

---

## Observability surfaces

| Surface | Table / destination | Purpose |
|---------|---------------------|---------|
| **Spatie activitylog** | `activity_log` (`log_name: auth`) | Admin-visible audit trail (Activity log UI) |
| **Laravel log** | `storage/logs/laravel.log` | Ops / SIEM via `LogAuthenticationEvents` |
| **User model log** | `activity_log` (`log_name: user`) | Eloquent `created` on self-registration only |
| **Activity notifications** | `activity_notifications` | In-app bell — **not** auth sessions |

---

## Event coverage matrix

| Auth action | Domain event | Activity log (`auth`) | File log | Notes |
|-------------|--------------|----------------------|----------|-------|
| Login (password, no 2FA) | `LoginSuccessful` | `login` | Yes | `login_method: password` in properties |
| Login (after 2FA code) | `LoginSuccessful` | `login` | Yes | `login_method: two_factor` |
| Login (invalid credentials) | `LoginFailed` | `login_failed` | Yes | Subject linked when email matches a user |
| Logout | `UserLoggedOut` | `logout` | Yes | JWT blacklisted in `AuthService` |
| 2FA required (password OK) | `TwoFactorChallengeIssued` | `two_factor_challenge` | Yes | JWT from step 1 invalidated |
| 2FA failure (bad token/code) | `TwoFactorLoginFailed` | `two_factor_login_failed` | Yes | `reason`: `invalid_token` \| `invalid_code` |
| Password change (authenticated) | `PasswordChanged` | `password_changed` | Yes | `method: change` |
| Password reset (email link) | `PasswordChanged` | `password_changed` | Yes | `method: reset` |
| 2FA enabled (setup confirmed) | `TwoFactorEnabled` | `two_factor_enabled` | Yes | After valid TOTP on `confirmSetup` |
| 2FA disabled | `TwoFactorDisabled` | `two_factor_disabled` | Yes | After valid TOTP on `disable` |
| Self-registration | — (Eloquent) | `user` / `created` | — | Via `User` `HasActivity`; no JWT event |
| Token refresh | — | **Not logged** | — | See gaps below |
| 2FA setup started (`startSetup`) | — | **Not logged** | — | Not enabled until `confirmSetup` succeeds |
| Forgot-password link sent | — | **Not logged** | — | Intentional (no state change yet) |

### Activity entry shape (`log_name: auth`)

Common properties (never include secrets):

| Property | When present |
|----------|----------------|
| `ip_address` | All auth activity entries |
| `user_agent` | Login, failures, 2FA challenge |
| `login_method` | `login` — `password` or `two_factor` |
| `reason` | `login_failed`, `two_factor_login_failed` |
| `method` | `password_changed` — `change` or `reset` |
| `email` | Failed login / 2FA failure when user unknown or for display |

`causer` and `subject` are the authenticated `User` when known. Failed login with a matching email attaches `subject` to that user but leaves `causer` null.

---

## Implementation map

| Component | Path |
|-----------|------|
| Events | `app/Events/Auth/*` |
| File log listener | `app/Listeners/Auth/LogAuthenticationEvents.php` |
| Activity log listener | `app/Listeners/Auth/RecordAuthenticationActivity.php` |
| Recorder | `app/Support/ActivityLog/AuthActivityRecorder.php` |
| Registration | Auto-discovered via each listener's `subscribe()` method (do **not** also call `Event::subscribe()` in `AppServiceProvider` — that duplicates every auth row) |
| Dispatch sites | `AuthService`, `PasswordResetService`, `TwoFactorService` |

Both listeners run **synchronously** (not queued) so activity rows appear immediately without a queue worker. Use `QUEUE_CONNECTION=sync` or run `php artisan queue:listen` for other queued jobs in the app.

---

## Known gaps (not in activity log today)

| Gap | Risk | Recommendation |
|-----|------|----------------|
| **JWT refresh** (`POST /auth/refresh`) | Session extension invisible in admin audit | Add `TokenRefreshed` event if compliance requires it |
| **Forgot-password email** | Low — no credential change until reset completes | Keep out of activity log to reduce noise |
| **Register by admin** | Covered by `user` log + `role_assigned` | No extra auth event needed |

---

## Security notes

- Passwords, TOTP secrets, and recovery codes are **never** written to `activity_log` (global `default_except_attributes` + no password fields in auth properties).
- Failed login entries may include `email` in properties and optionally link `subject` — acceptable for admin audit; avoid exposing in public APIs beyond `view activity log`.
- Auth listeners are **synchronous** — they do not require a queue worker. Other app jobs may still use `QUEUE_CONNECTION=database`; run `php artisan queue:listen` (or `composer run dev`) when those must process.

---

## Related documentation

- [Activity log](../domains/activity-log/README.md) — viewer API, permissions, UI
- [JWT authentication & RBAC](../domains/identity/README.md) — endpoints and flows
- [Octane / Swoole readiness](../platform/octane.md) — `Auth::forgetGuards()` after JWT operations
