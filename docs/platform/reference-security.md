# Security — reference

Detail behind [security.md](security.md): the audit procedure, auth activity coverage, response data minimization, the CORS contract, and the Cloudflare Tunnel perimeter.

## Audit procedure (OWASP Top 10:2025)

Repeatable defensive audit for this Laravel app. Frequency: quarterly, major releases, pre-prod, after incidents.

**In scope:** source, config, dependencies, routes/middleware, authz, queries, validation/encoding.
**Out of scope:** prod pen-test, third-party infra, social engineering. Non-destructive, local/staging only.

### When running an audit

1. Read the archived audit [`archive/security-audit-2026-06-16.md`](../archive/security-audit-2026-06-16.md) — the posture is a snapshot. All 20 findings are resolved (FINDING-007 pinned 2026-09-27), so there is **no open-findings tracker**; treat it as the baseline to compare against, and read its dated addendum for what a later re-run changed.
2. Audit against OWASP A01–A10 (access control, misconfig, supply chain, crypto, injection, insecure design, auth failures, integrity, logging, SSRF — use current checklist in agent prompt).
3. `composer audit`, `npm audit`, `php artisan route:list`. **Re-run `composer audit` on every pass even when the previous one was clean** — advisories are published against versions that were already installed (FINDING-008 was recorded clean on 2026-06-16 and 18 transitive advisories existed by 2026-08-03).
4. Mark findings NEW / RECURRING / STILL OPEN; patch CRITICAL/HIGH; update the archived audit file for the new date.

### Agent prompt skeleton

Ask for a defensive OWASP Top 10:2025 review, local only, write the findings into a dated file under `docs/archive/`, compare to the previous audit date, patch critical issues, verify with tests. Include categories A01–A10 with Laravel-specific checks (policies, mass assignment, `APP_DEBUG`, JWT/session config, raw SQL, XSS in Blade, rate limits, secrets in logs).

### Useful commands

```bash
composer audit
npm audit
php artisan route:list
# Search patterns as needed: raw DB::, {!!, env( outside config
```

Full long-form prompt and history templates lived in the pre-reorg security procedure; keep results in a dated file under [archive/](../archive/).

## Secret hygiene and rotation

### A tracked env file is a leak, not a template

`.env.example` and the `.env.{local,staging,production}.example` templates are the only env files that may be committed, and they must hold **empty or placeholder** values. Anything else is a leak the moment it is pushed: git history keeps it after the file is deleted, so untracking is not remediation on its own.

`.gitignore` therefore uses a blanket pattern rather than an explicit list:

```gitignore
.env
.env.*
!.env.example
!.env.local.example
!.env.production.example
!.env.staging.example
```

The explicit list it replaced (`.env`, `.env.backup`, `.env.profile`, `.env.local`, `.env.staging`, `.env.production`, `.env.*.local`) enumerated suffixes and missed `.env.pre-archivd-backup`, which was committed with a live `APP_KEY`, `PUSHER_APP_SECRET`, `HCAPTCHA_SECRET`, a real OCI KMS crypto endpoint and production webhook hosts. A new suffix must never be able to reintroduce that: the blanket pattern ignores every `.env*` at any depth and the `!` entries re-allow the templates only.

### Rotation runbook

Rotation is a **provider-first** procedure. Rotate at the provider, then update the active `.env`, then verify. Doing it in the other order leaves the app failing against a revoked credential while the leaked value is still valid.

| Secret | Where it is rotated | Notes |
|---|---|---|
| `APP_KEY` | `php artisan key:generate --force` | Appends the retired key to `APP_PREVIOUS_KEYS` so existing ciphertext still decrypts (`config/app.php` `previous_keys`). Do not hand-edit the two lines — `EnvironmentKeyWriter` owns the anchor pair, and `KeyGenerateCommandTest` covers the rotation. |
| `ENCRYPTION_BLIND_INDEX_PEPPER` | Manual | A changed pepper breaks every blind-index lookup; existing rows must be re-indexed, not just re-hashed. Treat as a migration, not a config edit. |
| `PUSHER_APP_ID` / `_KEY` / `_SECRET` | Pusher dashboard | The SPA's `VITE_PUSHER_APP_KEY` must move in the same change or realtime goes silent. |
| `HCAPTCHA_SECRET` / `HCAPTCHA_SITEKEY` | hCaptcha dashboard | The sitekey is public and lives in the SPA bundle; the secret is backend-only. |
| `OCI_KMS_*` | OCI IAM | Rotate the credential, then `php artisan oci:kms-verify` (exit `3` = IAM, `1` = config, `2` = auth, `4` = network, `5` = other). |
| Webhook credentials | Payment provider dashboards | `MIDTRANS_NOTIFY_URL` / `IPAYMU_NOTIFY_URL` point at a host; the signing keys live in the provider dashboard. |

After a rotation: `php artisan config:rebuild`, then `php artisan encryption:flush-decrypted-cache` when a key or secret that feeds decryption moved.

### Why a tracked-file leak is closed by rotation, not by history rewriting

Removing a file from the working tree does not remove it from git history, and rewriting history on a shared repository invalidates every existing clone, breaks open pull requests, and is not recoverable on the GitHub free plan. Rotating the leaked values is what makes the historical copy worthless — the committed string is then a dead credential. Treat the rewrite as unnecessary once rotation has happened, and record the incident rather than attempting surgery.

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

Never log passwords, TOTP secrets, or recovery codes. `ip_address`, `user_agent`, and the attempted `email` on these rows are stored as envelopes (see [PII at rest](../domains/activity-log/reference.md#pii-at-rest-envelope-encryption)).

## Response data minimization (mandatory)

An API JSON body is a **per-viewer contract**, not a serialization of the Eloquent model. Two viewers hitting the same URL (or a nested Resource on a different URL) can receive different keys. Precedent: `BillingUsageEventResource` vs `AdminBillingUsageEventResource`; `PublicHostedFileResource` vs `HostedFileResource`.

### Rules

1. Gate sensitive keys with `$this->when($request->user()?->can(...))` **or** split Resources. Do not "hide it in the SPA" while the JSON still contains it.
2. Guest / unauthenticated endpoints must not reuse owner Resources. Dedicated public variants only.
3. Never emit:
   - credential secrets / envelope plaintext
   - live accept or claim **tokens** to anyone except the token owner (create-invite response is the exception for workspace invites)
   - upstream provider cost / rate snapshots to customers
   - FX / settlement internals to buyers (`view invoice` staff only)
   - other users' emails except owner/manager/staff (or the member viewing themselves)
   - storage `path` / `disk` / `checksum` / `region` except storage owner or archivd staff
4. Activity log `properties` / `attribute_changes` go through `ErrorContextRedactor` (same keys as error-log context). Crypto rows still mask `kms_key_id`. PII keys (`ip_address`, `user_agent`, `email`) are **envelope-encrypted at rest** and decrypted only for `permission:view activity log` viewers — [activity-log](../domains/activity-log/reference.md#pii-at-rest-envelope-encryption).
5. Every new list/show endpoint needs: one **wrong-viewer 403** test and one **sensitive field absent** test. Helpers: `Tests\Concerns\AssertsPayloadHygiene`.

Authz (permission / policy) is the first wall. Field gating is the second — a nested Resource on an owner-scoped URL still leaks if it dumps staff columns.

## CORS

`config/cors.php` `allowed_origins` is `APP_URL` + `SPA_URL` + any comma-separated origins in `CORS_EXTRA_ORIGINS` (all trimmed, trailing-slash-normalized, unique, empty dropped). No `*`. Same-origin Vue-Workers `/api/*` traffic never hits Laravel CORS; this allowlist only matters if a browser talks to the Laravel host directly. `CORS_EXTRA_ORIGINS` covers Cloudflare Workers preview URLs, localhost dev, or any additional SPA origin without loosening the credential rule below.

`allowed_methods` is an explicit verb list (`GET, POST, PUT, PATCH, DELETE, OPTIONS`), not `*` — the API never serves other verbs, so they are not advertised to preflights. `allowed_headers` includes public `X-SPA-Origin`, which the SPA sends on direct versioned API calls. `exposed_headers` is `Content-Disposition` so the SPA's `getBlob()` can read the server-chosen filename for CSV/file downloads (the header is otherwise invisible cross-origin); it carries no session or token material. `supports_credentials = true` because the SPA authenticates cross-site with the HttpOnly `__Host-spa_jwt` cookie (`credentials: 'include'`). Credentialed CORS forbids a wildcard origin, which is why the origin list stays explicit. `max_age` is `600` so browsers cache preflights instead of OPTIONS-ing every credentialed GET. `Access-Control-Allow-Credentials: true` and the matched `Access-Control-Allow-Origin` are emitted on auth-error (401), CSRF-mismatch (419), and other API errors (including exception/`abort()` responses) so the browser surfaces the real status instead of a generic CORS error. `VerifySpaCsrf` returns a JSON 419 envelope rather than `abort()` so `HandleCors` can still stamp those headers. `MenuController::requiredActor()` and API 403s such as notification mark-as-read throw `HttpResponseException` with `ApiResponder::error(...)` instead of `abort()` for the same reason. Responses that still skip `HandleCors` are stamped in `bootstrap/app.php` `$exceptions->respond()` via `App\Support\Http\StampApiCors`. Probe: `tests/Feature/Http/CorsOnAuthErrorProbeTest.php` (401 on `auth/me` and cookie-less `auth/refresh`, 419 CSRF, guest `GET navigation/menus` 200, OPTIONS preflight, invalid JWT cookie on jwt.optional menus, missing `X-SPA-Origin` 403).

### The allowlist decides, not the shortcut

`App\Support\Http\AllowlistCorsService` (bound over `Fruitcake\Cors\CorsService` in `AppServiceProvider::register()`) is what `HandleCors` injects. The stock `fruitcake/php-cors` has a shortcut for a **single-origin** allowlist: `configureAllowedOrigin()` writes `allowedOrigins[0]` without ever comparing it to the request's `Origin` header. A one-origin allowlist is a normal deployment (`SPA_URL` unset, only `APP_URL` set), so a request from any foreign origin left with `Access-Control-Allow-Origin: <that one origin>` **and** `Access-Control-Allow-Credentials: true`, plus no `Vary: Origin`. The browser blocks the read — the value does not match the request origin — so it was not exploitable, but the allowlist is the thing that must decide, and a shared cache could replay the grant to another origin. The subclass re-runs `isOriginAllowed()` after both writers (actual response and preflight) and strips the grant, the credentials header and the exposed-headers list when the origin is not on the list, restoring `Vary: Origin`. Regression test: `tests/Feature/Http/CorsOriginsTest.php` — `test_foreign_origin_does_not_receive_allow_origin` and `test_foreign_origin_preflight_does_not_receive_allow_origin`. Both pass with an empty `.env` (the single-origin case) and with `SPA_URL` set (the multi-origin case).

### `broadcasting/auth` invariant

`paths` is `['api/*', 'broadcasting/auth']`. The second entry exists because `withRouting(channels: ...)` registers a **web-group twin** of `POST /broadcasting/auth` (session + CSRF). That twin is not matched by `api/*`, so before this entry it answered 403 with **no** `Access-Control-*` header — a response the browser can only report as a generic "CORS error", which made a wrong endpoint look like a broken allowlist. The SPA must never reach it: the versioned endpoint is defined once in `App\Support\Broadcasting\BroadcastAuthEndpoint` (`PATH`/`SUBPATH`/`url()`), used by `routes/api/v1/broadcasting.php` and by `BroadcastConnection::socketClientConfig()`'s `authEndpoint`; the SPA additionally normalizes `VITE_API_BASE_URL` to end with `/api/v1`. Invariant test: `tests/Feature/Http/CorsBroadcastAuthTest.php` enumerates every route whose URI ends in `broadcasting/auth` and fails if any is not matched by `cors.paths` — so a future alias or re-registration turns red instead of shipping an opaque CORS error.

SPA cookie auth hardening (see [../integration/vue-workers-spa.md](../integration/vue-workers-spa.md)): the JWT lives only in the HttpOnly `__Host-spa_jwt` cookie — `data.token` is stripped from the login/register/refresh/2FA JSON bodies so an XSS payload cannot exfiltrate it. The double-submit CSRF nonce rides the non-HttpOnly `__Host-spa_csrf` cookie echoed back in `X-CSRF-Token`; both cookies use the `__Host-` prefix (`Secure`, `Path=/`, no `Domain`). The nonce protects cookie-authenticated state-changing requests; session-establishing/session-ending endpoints (login, register, refresh, logout, forgot/reset password, two-factor login) skip it by design since they have no session to ride, and Bearer requests skip it as not cookie-ridable. Versioned API requests also require public `X-SPA-Origin: 1` as an anti-noise filter. This header is trivially forgeable and must never be treated as authentication or origin proof. `OPTIONS` preflights and provider webhook paths bypass this marker gate; webhook signatures/payload validation remain responsible for acceptance. `POST auth/reset-password` requires hCaptcha, at parity with `forgot-password`.

## Cloudflare Tunnel perimeter

This section describes the **intended production perimeter**. Nothing in this repository configures the Tunnel, firewall, DNS, or edge rules — no `cloudflared` config, no ingress config, no VM/OCI firewall or security-list rules, no Access policy, no AOP config, no Transform Rules. These are external deployment facts that ops must verify outside this codebase.

Production API traffic is intended to be published through Cloudflare Tunnel. VM and OCI inbound ports `80` and `443` stay closed; `cloudflared` creates outbound-only connections to Cloudflare, so the origin is not reachable through its public IP when firewall rules are correct. Allow only the egress required by `cloudflared` (normally Cloudflare Tunnel port `7844` plus DNS and operational update traffic), and bind the local web service to loopback where possible.

Tunnel ingress is a positive allowlist, not a wildcard router: expose only the approved SPA and API hostnames, finish the configuration with a catch-all `http_status:404`, and remove stale DNS records that point at the origin. Treat the tunnel token/credential as a Tier A secret; never commit it, expose it in logs, or reuse it across unrelated environments. Verify from outside OCI that direct IPv4, IPv6, and origin-IP requests fail while the public hostnames still work.

Authenticated Origin Pulls (AOP) is not an additional control for hostnames served through Cloudflare Tunnel. AOP expects Cloudflare to open an inbound mTLS connection to the origin; Tunnel uses an outbound connector instead. Do not describe AOP as protecting this deployment unless the hostname is moved off Tunnel and the origin is configured for AOP.

Cloudflare Access is appropriate for separate admin, debugging, monitoring, or other internal hostnames/paths. It authenticates users, devices, or service tokens at Cloudflare; it cannot prove that JavaScript came from the official SPA. Protecting the public cross-origin API with Access also introduces an Access cookie and preflight problem: browser `OPTIONS` requests do not carry the Access cookie, so Access must either bypass preflights to Laravel or answer them with matching CORS headers. Access service-token credentials must remain server-side; putting them in `VITE_*` exposes them. A transparent browser-to-API service-token flow therefore requires a Worker/BFF and changes the current zero-Worker-invocation architecture.

Credential login throttling is identity-scoped (email + client IP) and failure-only, enforced in `App\Domain\Identity\Support\AuthLoginThrottle` from `AuthService` — not by route middleware — so users behind shared egress IPs (NAT/CGNAT) never share quota, and pre-credential rejects (validation, CSRF, hCaptcha) never consume it. A separate per-IP backstop (default 20/min, `config/auth.php` `login_throttle`) contains cross-email brute force. See [domains/identity/README.md](../domains/identity/reference.md#login-throttle-identity-scoped-failure-only).

Cloudflare WAF and edge rate limits are defense in depth, not replacements for Laravel JWT authentication, RBAC, policies, CSRF, validation, throttles, and webhook signatures. A private edge header or transform rule, if added later, would attest only that traffic passed through the configured edge; it would not prove that a public browser request came from the SPA. The current `X-SPA-Origin` marker remains metadata only.

`bootstrap/app.php` trusts all proxies (`at: '*'`) and forwarded client-IP/host/port/protocol headers. This is only acceptable while direct origin access is proven impossible: if any network path reaches the origin outside the Tunnel, clients can spoof `X-Forwarded-*` and poison IP-based throttling, HTTPS detection, generated URLs, and audit data. Deployment verification must confirm client-supplied forwarded headers cannot bypass rate-limit identity, and `trustProxies` should be narrowed if that proof is ever lost. Do not rely on `X-Forwarded-*` values as authentication.

Historical assessment detail: [archive/auth-activity-assessment.md](../archive/auth-activity-assessment.md).

## Related

- [security.md](security.md) — brief
- [api-resources.mdc](../../.cursor/rules/api-resources.mdc) — envelope and response minimization rule
- [domains/identity/README.md](../domains/identity/README.md)
- [domains/activity-log/README.md](../domains/activity-log/README.md)
- [domains/error-log/README.md](../domains/error-log/README.md)
- [database-safety.md](database-safety.md)
