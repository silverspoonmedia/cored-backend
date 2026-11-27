# Security

Perimeter and audit posture for the Laravel API: a repeatable OWASP Top 10:2025 defensive audit, an explicit CORS allowlist for the credentialed cross-origin SPA, a Cloudflare Tunnel origin that is unreachable directly, and a per-viewer response contract.

Auth observability maps to Spatie activitylog (`log_name: auth`), and runtime failures are grouped in the ErrorLog domain (`error_logs`, admin-only `permission:view error log`) with redacted context while `storage/logs/` stays dual-write. The API is never published with a wildcard origin: `config/cors.php` `allowed_origins` is `APP_URL` + `SPA_URL` + `CORS_EXTRA_ORIGINS`, with `supports_credentials = true`, and the **allowlist** — not the `fruitcake/php-cors` single-origin shortcut — is what decides. `X-SPA-Origin` is an anti-noise marker and must never be treated as authentication or origin proof. Sensitive keys are gated in the JSON Resource or split into a dedicated one; "hide it in the SPA" while the JSON still carries it is a defect. Login throttling is identity-scoped (email + client IP, failure-only) with a separate per-IP backstop.

| | |
|---|---|
| Code | `vendor/silverspoonmedia/platform/src/Support/Http/AllowlistCorsService.php`, `vendor/silverspoonmedia/platform/src/Support/Http/StampApiCors.php`, `app/Domain/Identity/Support/AuthLoginThrottle.php`, `bootstrap/app.php` |
| Config | `config/cors.php`, `config/auth.php` (`login_throttle`) |
| Commands | `composer audit`, `npm audit`, `php artisan route:list` |
| Tests | `tests/Feature/Http/CorsOriginsTest.php`, `CorsBroadcastAuthTest.php`, `CorsOnAuthErrorProbeTest.php`, `Tests\Concerns\AssertsPayloadHygiene` |
| Pakem | [security.mdc](../../.cursor/rules/security.mdc) · [api-resources.mdc](../../.cursor/rules/api-resources.mdc) |
| Deep dive | [reference-security.md](reference-security.md) — audit procedure, secret hygiene and rotation runbook, CORS, Tunnel perimeter |
