# Security

## Audit procedure (OWASP Top 10:2025)

Repeatable defensive audit for this Laravel app. Frequency: quarterly, major releases, pre-prod, after incidents.

**In scope:** source, config, dependencies, routes/middleware, authz, queries, validation/encoding.  
**Out of scope:** prod pen-test, third-party infra, social engineering. Non-destructive, local/staging only.

### When running an audit

1. Read root `SECURITY_AUDIT.md` (history, open findings).
2. Audit against OWASP A01–A10 (access control, misconfig, supply chain, crypto, injection, insecure design, auth failures, integrity, logging, SSRF — use current checklist in agent prompt).
3. `composer audit`, `npm audit`, `php artisan route:list`.
4. Mark findings NEW / RECURRING / STILL OPEN; patch CRITICAL/HIGH; update `SECURITY_AUDIT.md`.

### Agent prompt skeleton

Ask for a defensive OWASP Top 10:2025 review, local only, update `SECURITY_AUDIT.md`, compare to previous audit date, patch critical issues, verify with tests. Include categories A01–A10 with Laravel-specific checks (policies, mass assignment, `APP_DEBUG`, JWT/session config, raw SQL, XSS in Blade, rate limits, secrets in logs).

### Useful commands

```bash
composer audit
npm audit
php artisan route:list
# Search patterns as needed: raw DB::, {!!, env( outside config
```

Full long-form prompt and history templates lived in the pre-reorg security procedure; keep results in `SECURITY_AUDIT.md`.

## Authentication activity coverage

Auth observability maps to Spatie activitylog (`log_name: auth`) plus Laravel log. See [activity-log](../domains/activity-log/README.md) for viewer UI.

## Application error log (persistent)

Runtime failures (reportable exceptions + `Log::error` and above) are stored in the **ErrorLog** domain (`error_logs`), grouped by fingerprint. Admin-only: `permission:view error log`. Context is redacted (passwords, tokens, etc.). File logs under `storage/logs/` remain dual-write. See [error-log](../domains/error-log/README.md).

| Auth action | Activity (`auth`) | Notes |
|-------------|-------------------|-------|
| Login success | `login` | `login_method`: password \| two_factor |
| Login failed | `login_failed` | Subject linked if email matches user |
| Logout | `logout` | JWT blacklisted |
| 2FA challenge / fail | `two_factor_challenge` / `two_factor_login_failed` | |
| Password change/reset | `password_changed` | `method`: change \| reset |
| 2FA enable/disable | `two_factor_enabled` / `two_factor_disabled` | |
| Self-registration | `user`/`created` | Eloquent activity, not auth event |
| Token refresh | **not logged** | Optional if compliance needs it |
| Forgot-password email | **not logged** | Noise; reset completion is logged |

Implementation: `app/Events/Auth/*`, listeners `LogAuthenticationEvents`, `RecordAuthenticationActivity`, `AuthActivityRecorder`. Listeners are **synchronous** (auto-discovered `subscribe()` — do not double-register).

Never log passwords, TOTP secrets, or recovery codes.

Historical assessment detail: [archive/auth-activity-assessment.md](../archive/auth-activity-assessment.md).

## Related

- [domains/identity/README.md](../domains/identity/README.md)
- [domains/activity-log/README.md](../domains/activity-log/README.md)
- [domains/error-log/README.md](../domains/error-log/README.md)
- [database-safety.md](database-safety.md)
