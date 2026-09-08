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

## Response data minimization (mandatory)

An API JSON body is a **per-viewer contract**, not a serialization of the Eloquent model. Two viewers hitting the same URL (or a nested Resource on a different URL) can receive different keys. Precedent: `BillingUsageEventResource` vs `AdminBillingUsageEventResource`; `PublicHostedFileResource` vs `HostedFileResource`.

### Rules

1. Gate sensitive keys with `$this->when($request->user()?->can(...))` **or** split Resources. Do not “hide it in the SPA” while the JSON still contains it.
2. Guest / unauthenticated endpoints must not reuse owner Resources. Dedicated public variants only.
3. Never emit:
   - credential secrets / envelope plaintext
   - live accept or claim **tokens** to anyone except the token owner (create-invite response is the exception for workspace invites)
   - upstream provider cost / rate snapshots to customers
   - FX / settlement internals to buyers (`view invoice` staff only)
   - other users’ emails except owner/manager/staff (or the member viewing themselves)
   - storage `path` / `disk` / `checksum` / `region` except storage owner or archivd staff
4. Activity log `properties` / `attribute_changes` go through `ErrorContextRedactor` (same keys as error-log context). Crypto rows still mask `kms_key_id`.
5. Every new list/show endpoint needs: one **wrong-viewer 403** test and one **sensitive field absent** test. Helpers: `Tests\Concerns\AssertsPayloadHygiene`.

Authz (permission / policy) is the first wall. Field gating is the second — a nested Resource on an owner-scoped URL still leaks if it dumps staff columns.

## CORS

`config/cors.php` `allowed_origins` is `APP_URL` + `SPA_URL` + any comma-separated origins in `CORS_EXTRA_ORIGINS` (all trimmed, trailing-slash-normalized, unique, empty dropped). No `*`. Same-origin Vue-Workers `/api/*` traffic never hits Laravel CORS; this allowlist only matters if a browser talks to the Laravel host directly. `CORS_EXTRA_ORIGINS` covers Cloudflare Workers preview URLs, localhost dev, or any additional SPA origin without loosening the credential rule below.

`allowed_methods` is an explicit verb list (`GET, POST, PUT, PATCH, DELETE, OPTIONS`), not `*` — the API never serves other verbs, so they are not advertised to preflights. `allowed_headers` includes public `X-SPA-Origin`, which the SPA sends on direct versioned API calls. `supports_credentials = true` because the SPA authenticates cross-site with the HttpOnly `__Host-spa_jwt` cookie (`credentials: 'include'`). Credentialed CORS forbids a wildcard origin, which is why the origin list stays explicit. `max_age` is `600` so browsers cache preflights instead of OPTIONS-ing every credentialed GET. `Access-Control-Allow-Credentials: true` and the matched `Access-Control-Allow-Origin` are emitted on auth-error (401), CSRF-mismatch (419), and other API errors (including exception/`abort()` responses) so the browser surfaces the real status instead of a generic CORS error. `VerifySpaCsrf` returns a JSON 419 envelope rather than `abort()` so `HandleCors` can still stamp those headers. `MenuController::requiredActor()` and API 403s such as notification mark-as-read throw `HttpResponseException` with `ApiResponder::error(...)` instead of `abort()` for the same reason. Responses that still skip `HandleCors` are stamped in `bootstrap/app.php` `$exceptions->respond()` via `App\Support\Http\StampApiCors`. Probe: `tests/Feature/Http/CorsOnAuthErrorProbeTest.php` (401 on `auth/me` and cookie-less `auth/refresh`, 419 CSRF, guest `GET navigation/menus` 200, OPTIONS preflight, invalid JWT cookie on jwt.optional menus, missing `X-SPA-Origin` 403).

SPA cookie auth hardening (see [../integration/vue-workers-spa.md](../integration/vue-workers-spa.md)): the JWT lives only in the HttpOnly `__Host-spa_jwt` cookie — `data.token` is stripped from the login/register/refresh/2FA JSON bodies so an XSS payload cannot exfiltrate it. The double-submit CSRF nonce rides the non-HttpOnly `__Host-spa_csrf` cookie echoed back in `X-CSRF-Token`; both cookies use the `__Host-` prefix (`Secure`, `Path=/`, no `Domain`). The nonce protects cookie-authenticated state-changing requests; session-establishing/session-ending endpoints (login, register, refresh, logout, forgot/reset password, two-factor login) skip it by design since they have no session to ride, and Bearer requests skip it as not cookie-ridable. Versioned API requests also require public `X-SPA-Origin: 1` as an anti-noise filter. This header is trivially forgeable and must never be treated as authentication or origin proof. `OPTIONS` preflights and provider webhook paths bypass this marker gate; webhook signatures/payload validation remain responsible for acceptance. `POST auth/reset-password` requires hCaptcha, at parity with `forgot-password`.

## Cloudflare Tunnel perimeter

This section describes the **intended production perimeter**. Nothing in this repository configures the Tunnel, firewall, DNS, or edge rules — no `cloudflared` config, no ingress config, no VM/OCI firewall or security-list rules, no Access policy, no AOP config, no Transform Rules. These are external deployment facts that ops must verify outside this codebase.

Production API traffic is intended to be published through Cloudflare Tunnel. VM and OCI inbound ports `80` and `443` stay closed; `cloudflared` creates outbound-only connections to Cloudflare, so the origin is not reachable through its public IP when firewall rules are correct. Allow only the egress required by `cloudflared` (normally Cloudflare Tunnel port `7844` plus DNS and operational update traffic), and bind the local web service to loopback where possible.

Tunnel ingress is a positive allowlist, not a wildcard router: expose only the approved SPA and API hostnames, finish the configuration with a catch-all `http_status:404`, and remove stale DNS records that point at the origin. Treat the tunnel token/credential as a Tier A secret; never commit it, expose it in logs, or reuse it across unrelated environments. Verify from outside OCI that direct IPv4, IPv6, and origin-IP requests fail while the public hostnames still work.

Authenticated Origin Pulls (AOP) is not an additional control for hostnames served through Cloudflare Tunnel. AOP expects Cloudflare to open an inbound mTLS connection to the origin; Tunnel uses an outbound connector instead. Do not describe AOP as protecting this deployment unless the hostname is moved off Tunnel and the origin is configured for AOP.

Cloudflare Access is appropriate for separate admin, debugging, monitoring, or other internal hostnames/paths. It authenticates users, devices, or service tokens at Cloudflare; it cannot prove that JavaScript came from the official SPA. Protecting the public cross-origin API with Access also introduces an Access cookie and preflight problem: browser `OPTIONS` requests do not carry the Access cookie, so Access must either bypass preflights to Laravel or answer them with matching CORS headers. Access service-token credentials must remain server-side; putting them in `VITE_*` exposes them. A transparent browser-to-API service-token flow therefore requires a Worker/BFF and changes the current zero-Worker-invocation architecture.

Credential login throttling is identity-scoped (email + client IP) and failure-only, enforced in `App\Domain\Identity\Support\AuthLoginThrottle` from `AuthService` — not by route middleware — so users behind shared egress IPs (NAT/CGNAT) never share quota, and pre-credential rejects (validation, CSRF, hCaptcha) never consume it. A separate per-IP backstop (default 20/min, `config/auth.php` `login_throttle`) contains cross-email brute force. See [domains/identity/README.md](../domains/identity/README.md#login-throttle-identity-scoped-failure-only).

Cloudflare WAF and edge rate limits are defense in depth, not replacements for Laravel JWT authentication, RBAC, policies, CSRF, validation, throttles, and webhook signatures. A private edge header or transform rule, if added later, would attest only that traffic passed through the configured edge; it would not prove that a public browser request came from the SPA. The current `X-SPA-Origin` marker remains metadata only.

`bootstrap/app.php` trusts all proxies (`at: '*'`) and forwarded client-IP/host/port/protocol headers. This is only acceptable while direct origin access is proven impossible: if any network path reaches the origin outside the Tunnel, clients can spoof `X-Forwarded-*` and poison IP-based throttling, HTTPS detection, generated URLs, and audit data. Deployment verification must confirm client-supplied forwarded headers cannot bypass rate-limit identity, and `trustProxies` should be narrowed if that proof is ever lost. Do not rely on `X-Forwarded-*` values as authentication.

Historical assessment detail: [archive/auth-activity-assessment.md](../archive/auth-activity-assessment.md).

## Related

- [domains/identity/README.md](../domains/identity/README.md)
- [domains/activity-log/README.md](../domains/activity-log/README.md)
- [domains/error-log/README.md](../domains/error-log/README.md)
- [database-safety.md](database-safety.md)
