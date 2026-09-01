# Leading SPA frontend — Monolith-Vue-Workers

Monolith-Vue-Workers (`monolith-vue-workers` sibling) is the **sole browser UI**. Backend (the Laravel repo) and this SPA are separate projects; that split is the intended end state. It consumes the Monolith `/api/v1/*` API. This doc is the integration contract between the SPA and the API.

The in-repo Blade admin has been **removed** from Monolith; `docs/web/blade-admin.md` records what server `web` routes remain.

## Architecture

| Piece | Role |
|-------|------|
| Vue 3 + Vite + TypeScript | SPA runtime, SFC components, type safety |
| Cloudflare Workers | Deployment target (`@cloudflare/vite-plugin`). Static assets plus SPA fallback only; current Worker does not proxy API or auth requests. All browser API traffic goes direct to Laravel. |
| Session auth | Cross-site HttpOnly `__Host-spa_jwt` on the **API origin** (minted by Laravel, `SameSite=None; Secure`). JS never reads it; the browser attaches it to direct API calls via `credentials: 'include'`. `GET auth/me` hydrates `user`. Cross-tab: `BroadcastChannel` only coordinates refresh/logout (no token in the messages). `remember` affects cookie `Max-Age` via API `expires_in`. |
| `fetch` wrapper (`src/api/client.ts`) | All calls go **direct to the Laravel API origin** (`VITE_API_BASE_URL`) with `credentials: 'include'`; each request carries public `X-SPA-Origin: 1` for Laravel's anti-noise gate, and cross-origin mutations send the double-submit `X-CSRF-Token` nonce (`src/api/csrf.ts`) except auth-session paths (login/register/refresh/logout/forgot-reset/2FA login), which skip `ensureCsrf()` to match the server skip. Other credentialed fetches wait until an in-flight CSRF nonce `Set-Cookie` settles (`whenCsrfCookieQuiet`). 401: `GET auth/me` and `GET navigation/menus` do **not** `POST /auth/refresh`. For a few seconds after `applyAuth` (`markSessionIssued`), a 401 **or** browser TypeError (`Failed to fetch` / `Load failed` / `NetworkError` — DevTools often labels this CORS) retries the original URI with backoff (50/150/400ms) until the settle window ends; it does **not** refresh. Later 401s refresh + retry. 419 CSRF refresh + retry. `getBlob()` uses the same gate and settle retries. |
| `vue-i18n` | Full locale system (`en`, `id`, `ja`, `ko`, `zh-CN`, `ar`, `ru`) converted from Laravel PHP lang files. Arabic sets `html[dir="rtl"]`; other supported locales use `ltr`. `vue-i18n` and `@intlify/unplugin-vue-i18n` must stay on the **same major** (both v11): the unplugin precompiles locale JSON to a minified AST whose node-type enum is read by the `vue-i18n` runtime formatter. A major mismatch (e.g. unplugin v11 emitting linked-modifier node type 9 + runtime v9) makes `t()` throw `SyntaxError: unhandled minifier node type` for every message that uses that node, which previously blanked page titles and the error dialog. |

### Source layout

AdminLTE app code is **type-then-domain**: `src/views/{domain}/`, domain-specific `src/components/{domain}/` and `src/composables/{domain}/`. Shared chrome/forms/infra stay at the type root (`AppNavbar`, `DataTable`, `useEcho`, `useShellHydrate`, …). Domain folder names match backend kebab (`identity`, `file-hosting`, `payment-gateway`, `vtual`, …). Vue Router `path` / `name` are unchanged. `src/router/index.ts` keeps history, scroll, and guards; route records live in `src/router/routes/*.ts` assembled in the original order. **Valkivid3** stays under `src/valkivid3/` (do not mix with AdminLTE folders).

Base URL: set `VITE_API_BASE_URL` to the Laravel API origin (e.g. `https://api.example.com/api/v1`). Composable paths are relative (`auth/me`); `src/api/baseUrl.ts` sends every path direct to that origin. Leave it empty only for local same-origin development.

## Direct API

The browser sends every `/api/v1/*` request directly to the Laravel API origin (`VITE_API_BASE_URL`). The Cloudflare Worker serves static assets and SPA fallback only; it is not an auth or data proxy. In the intended deployment the API hostname is published through Cloudflare Tunnel with VM and OCI inbound ports closed, so the browser reaches Laravel through Cloudflare Edge and the outbound-only tunnel connector — neither repo contains the Tunnel/firewall/DNS configuration, so that perimeter is an external ops fact to verify, not an implementation claim. This removes a Worker proxy trust boundary and keeps API behavior visible in browser network diagnostics.

The direct path authenticates with the **cross-site HttpOnly `__Host-spa_jwt` cookie** that Laravel mints on the API origin (`SameSite=None; Secure`, `__Host-` prefix). JS never reads it; the browser attaches it via `credentials: 'include'`. Every versioned API request also carries public `X-SPA-Origin: 1`; `App\Http\Middleware\RequireSpaRequestHeader` rejects missing or invalid markers before API-group work. This marker is replayable client metadata, not authentication. Static assets receive `public/_headers` CSP with no `unsafe-eval`, `object-src 'none'`, frame denial, HSTS, and restricted feature policy; dynamic URLs from API payloads pass through `src/utils/safeUrl.ts` before navigation or rendering. `App\Http\Middleware\AuthenticateWithSpaCookie` (API group) binds the JWT cookie to the `api` guard, so `auth:api` routes, Spatie RBAC, and policies work unchanged. Because a cross-origin cookie can be CSRF'd, cookie-authenticated state-changing requests additionally send a **double-submit nonce**: `GET /api/v1/csrf` mints a random token on a NON-HttpOnly `__Host-spa_csrf` cookie, the SPA echoes it in the `X-CSRF-Token` header, and `App\Http\Middleware\VerifySpaCsrf` (API group) accepts only when header and cookie match (`419` on mismatch). Session-establishing/session-ending endpoints (login, register, refresh, logout, forgot/reset password, two-factor login) skip the check by design — there is no session cookie to ride yet, and hCaptcha/throttles guard those paths. Bearer requests skip the CSRF check (not cookie-ridable).

Tunnel is the network perimeter, not an SPA identity check. Keep tunnel ingress limited to the intended SPA/API hostnames with a final `http_status:404` catch-all, protect the connector credential, bind the local service to loopback where possible, and verify from outside that direct IPv4/IPv6 and origin-IP access fail — these are ops verification steps, not backend controls in this repo. Authenticated Origin Pulls does not apply to Tunnel hostnames. Cloudflare Access is suitable for separate internal/admin/debug surfaces; it authenticates users, devices, or server-side service tokens, but cannot prove that public JavaScript came from this SPA. Do not place Access service-token secrets in `VITE_*`. Protecting this public cross-origin API with Access also requires Access-specific CORS/preflight configuration; hiding a service token from the browser would require a Worker/BFF, which changes the current no-proxy and zero-Worker-invocation architecture.

CORS: `config/cors.php` has `supports_credentials => true` with an explicit origin allowlist (`APP_URL` + `SPA_URL`) — a wildcard origin cannot be combined with credentials. `X-SPA-Origin` is in the allowed request-header list. Preflight `max_age` is `600` so credentialed GETs are not OPTIONS'd on every call. `RequireSpaRequestHeader` gates `/api/v1/*`; missing/invalid markers return JSON `403`, while `OPTIONS` preflights and provider webhooks bypass the marker gate. `RequireSpaOrigin` remains **audit-only** everywhere: it logs direct hits to `spa_audit` but never 403s. The marker is public anti-noise metadata, not auth. CORS controls browser response access; it does not stop `curl` or server-to-server callers. Auth-error (401), CSRF-mismatch (419), and other API errors still carry `Access-Control-Allow-Origin` + `Allow-Credentials` so the SPA can read the real status and retry (`refreshCsrf` on 419) instead of seeing a generic CORS failure. `VerifySpaCsrf` returns a JSON 419 envelope rather than `abort()`, so `HandleCors` stamps those headers. Exception/`abort()` responses that skip `HandleCors` are stamped in `bootstrap/app.php` `$exceptions->respond()` via `App\Support\Http\StampApiCors` (same allowlist). Backend probe: `tests/Feature/Http/CorsOnAuthErrorProbeTest.php` (401/419 CORS headers, cookie-less `POST /auth/refresh` 401, guest `GET navigation/menus` 200, OPTIONS preflight, invalid JWT cookie still 200 on jwt.optional menus, missing `X-SPA-Origin` 403). If Cloudflare Access is placed on the API hostname, Access must either bypass `OPTIONS` to Laravel or answer preflights itself with the same allowlist, methods, and headers; an Access cookie is not sent on browser preflights.

`MONOLITH_API_ORIGIN` is legacy local configuration and is not read by the current Worker. `VITE_API_BASE_URL` is validated at runtime before URL construction: production builds accept only credential-free `https:` API bases without query strings or fragments; development builds also accept `http:` for localhost workflows. Invalid or unsafe values fall back to same-origin `/api/v1`. Signed email-verify HMAC remains bound to the Laravel `APP_URL` origin.

> **Verify before production cutover.** The direct data path depends on browsers accepting the cross-site `SameSite=None; Secure` cookie. Confirm on the deployed HTTPS origin that `__Host-spa_jwt` is set after login and attached to a direct data call in Chrome/Edge/Firefox/Safari. If a target browser blocks third-party cookies (ITP/ETP strict), the SPA needs a separately implemented Bearer fallback (token in JS memory, `Authorization: Bearer` direct) — the current SPA source does not enable that fallback. Laravel's `VerifySpaCsrf` skips Bearer requests, but that server capability does not create a client-side fallback by itself.

Still naked (browser talks to these hosts directly):

- Pusher / Reverb WebSockets (`VITE_PUSHER_*`)
- Discord OAuth + the Monolith web callback (`SPA_URL` return)
- File Hosting presigned / gateway byte URLs
- hCaptcha JS

Local gotcha: Vite env files stack — `.env.local` **overrides** `.env` (gitignored via `*.local`). For local `npm run dev` against a same-origin API, leave `VITE_API_BASE_URL` empty; for deployed direct traffic, set it to the Laravel API origin plus `/api/v1`. `MONOLITH_API_ORIGIN` is legacy and is not needed by the current Worker. After changing env files, restart Vite and hard-refresh the browser (env is baked at compile).

## Worker invocation vs static assets

HTML, JS, CSS, and SPA fallback are served as static assets. The current Worker handler only returns hardened 404 responses for stray `/api/*` requests; it does not proxy auth or data. Laravel API requests target the API hostname, not this Worker route, so they do not execute this Worker handler. Cloudflare billing and invocation treatment for static asset delivery remains dependent on the deployed Cloudflare product/account configuration; verify it in deployment metrics rather than treating “static” as an unconditional billing guarantee.

The API is intended to be protected at the network edge by Cloudflare Tunnel, not by the static SPA Worker. In the verified deployment, inbound VM/OCI ports stay closed and Tunnel exposes only explicitly configured hostnames, ending with a `http_status:404` catch-all — confirm this in the live account since the config lives outside both repos. Tunnel credential compromise is an origin-perimeter incident, so store and rotate it as infrastructure secret. Cloudflare Access may protect separate internal/admin hostnames, but an Access policy cannot identify official public JavaScript. A service token cannot be shipped in this bundle; using one without exposing it requires a server-side proxy/BFF and therefore changes Worker invocation and billing behavior.

The Worker stays small (`fetch` guard only). It stamps `Content-Security-Policy: default-src 'none'; frame-ancestors 'none'`, `Strict-Transport-Security`, `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, and `Permissions-Policy` on its own JSON/404 responses (`server/index.ts`). Static HTML/JS get CSP / frame-deny / HSTS from `public/_headers` (Cloudflare Assets). External API-provided URLs rendered by the SPA pass through `src/utils/safeUrl.ts`; production HTTP navigation/download/payment URLs require HTTPS, unsafe schemes, credential-bearing URLs, arbitrary image data, executable SVG markup, and untrusted OAuth destinations are rejected. Presigned upload PUT URLs are validated separately before `XMLHttpRequest` opens. Do not add cron, queues, Durable Objects, API proxy paths, or domain logic in the Worker without an explicit reason in this guide. Business work stays on Monolith; the SPA fetches and parses.

## Invocation budget controls

Data traffic does not target this Worker because SPA API calls go direct to the Laravel API hostname. The remaining client-side batching and cache controls still matter for responsiveness and for trimming Laravel/API load. Confirm any Cloudflare billing assumptions against the deployed account metrics:

- **Batch fan-in** — `POST /api/v1/batch` (`routes/api/v1/batch.php`, `BatchController`) dispatches up to **10** read-only (`GET`/`HEAD`) sub-requests through the kernel in one HTTP call, each keeping its own auth/permission middleware. SPA wrapper: `batch()` in `src/api/client.ts`; `BatchSubRequest` rejects mutation methods at the client type level. Throttled `30,1`; mutations are rejected (`422` per entry). Use it for multi-widget hydrates instead of N parallel fetches. Aggregation rules (standalone-first, SPA-only, backend fan-in, gate condition): [api-aggregation.md](../platform/api-aggregation.md).
- **Shell hydrate** — The route guard's `useAuth.hydrateShell()` cold-boots with **`GET auth/me`** (safe method, no CSRF). Only when that succeeds does it call `useShellHydrate.hydrateShell()`, which batches five reads: `auth/me`, sidebar menus, navbar menus, `notifications/unread-count`, and `notifications?display_mode=query&limit=5`. Guests never `POST /batch` — that mutation would mint `__Host-spa_csrf` before the JWT cookie exists and race the first authenticated batch after login. It seeds `setUser`, `seedTree`, `seedUnreadCount`, and `seedList`; `AppNavbar` uses the seeded recent list for its bell dropdown. `AppNavbar` **awaits** `hydrateShell()` then runs fallbacks and **then** `initEcho()`; `AppLayout` awaits the same inflight and GET-fallbacks sidebar only when it is unseeded **and** the sub-entry was not a terminal 401/403/429 (`isShellFallbackBlocked`). During `markSessionIssued`, a failed batch is retried with settle backoff instead of immediately GET-ing `navigation/menus`. Echo `POST /broadcasting/auth` must not overlap the batch CSRF cookie write — a collision shows in DevTools as CORS on whatever request lost the race (often GET menus). Dashboard reads `auth/me` from the seeded store — no extra fetch. `resetShellHydration()` exists for re-hydration after logout.
- **File Hosting hydrate** -- `useFhHydrate.hydrateFileHosting()` batches the four independent FH reads (`file-hosting/browse`, `file-hosting/quota`, `file-hosting/workspaces`, `microstock/slots`) into one invocation from `FileHostingView.loadAll()`. `hydrateWorkspaceDetail(null)` batches the workspace list; `hydrateWorkspaceDetail(workspaceId)` batches workspaces, members, and up to 50 activity rows for `FileHostingWorkspacesView`. Failed subs return `null` and fall back to `fetchWorkspaces`, `fetchMembers`, `fetchActivity`, or the owning loader. The dependent stage (`microstock/billing`, collection status) still runs after -- it gates on `browse.workspace.kind === 'microstock'`. Realtime (`.listing.changed` / `.quota.changed`) is unchanged -- batching only trims initial hydrate.
- **JWT TTL 240** — `config/jwt.php` `'ttl' => 240` (Tier B; runtime `setting_config('jwt.ttl')` / Settings → Platform `platform.jwt.ttl`). A 401 refresh round-trip costs 3 invocations; longer TTL makes that rare. Refresh rotation + blacklist still bound token theft.
- **Realtime only (no polling)** -- the SPA relies on Echo/Pusher alone for live updates. Pusher auto-reconnect is the recovery path; there is no timer-based polling fallback anywhere. On reconnect after an outage the SPA fires a **resync bus** (`onEchoResync` in `useEcho.ts`): `AppNavbar` re-pulls the unread count + recent list and reloads every registered listing (`triggerAllListingsReload`), because events during the gap are lost. A "reconnecting" indicator shows in the navbar while the socket is down.
- **Per-domain batch hydrate** -- Current coverage: `useNavigationHydrate` (menu edit plus parent/role/include/exclude lookups), `useNotificationsHydrate` (audience edit plus assignable roles), `useUsersHydrate` (user, assignable roles, activity logs), `useServicesHydrate` (service show, purchase show, and service edit -- each with options), `usePaymentsHydrate` (income overview/invoices), `useInferenceHydrate` (usage overview/events/model cards and provider/card detail), `useMicrostockHydrate` (billing/ledger, admin overview/events, microstock review asset metadata + preview download), and `useVtualHydrate` (profile, credentials, orphan edit, claim). Every helper unwraps via `bodyOfOrThrow()` and returns `null` for a transient entry failure so callers retain individual-loader fallback. `src/api/batch.ts` owns the unwrap contract: `bodyOf()` accepts only a status-200 `success` envelope; `dataOf()` returns its `data` or `null`. A **terminal** entry (`401`/`403`/`429`) makes `bodyOfOrThrow()` throw `TerminalBatchError` instead of returning `null`, so callers skip the standalone fallback for that entry (re-firing would repeat the rejection, and a 401 would trip the global force-logout). Batch transport failure rejects the call and callers fall back to their normal loaders. Only `GET`/`HEAD` subrequests are supported; the server cap is 10 per call, and the client **chunks** a larger fan-out into sequential 10-entry calls (positional results re-joined, nothing dropped). Aggregation rules: [api-aggregation.md](../platform/api-aggregation.md).
- **Project-wide realtime** -- Dedicated `ShouldBroadcastNow` events per domain: Navigation (`menu.tree.changed`), Service (`service.catalog.changed`, `service.purchase.changed`), Inference (`inference.catalog.changed`), Settings (`settings.group.changed`), ErrorLog (`log.error.recorded`), ProductActivation (`product.activation.changed`), DomainAccess (`domain.visibility.changed`), RBAC (`rbac.role.permission.changed`), PaymentGateway admin (`invoice.list.changed`), Microstock billing (`microstock.billing.changed` rides the FH user channel), vTual platform credentials (`platform.credential.changed`). Each SPA view subscribes via a `use{Domain}Realtime` or `useAdminRealtime` composable; `useListingLiveUpdate` only registers the listing's reload fn on the Echo bus (no polling).




## Local dogfood checklist

Daily driver is `npm run dev` at `http://localhost:5173`. Set Monolith `SPA_URL="http://localhost:5173"`. For local same-origin dev leave `VITE_API_BASE_URL` empty; for deployed direct API traffic set it to the Laravel API origin plus `/api/v1`.

Pass/fail:

- Network: every API call goes direct to the configured API origin. Login JSON has **no** `token`. Cookie-authenticated mutations carry the `__Host-spa_jwt` cookie + `X-CSRF-Token`, not `Authorization`.
- Application: `__Host-spa_jwt` HttpOnly on the API origin; no `mw_auth` in localStorage.
- Hard refresh still logged in; logout clears the cookie; two tabs, one logout, the other dies on the next API call / `BroadcastChannel` logout.
- Login / remember / 2FA / refresh near TTL.
- Login lockout: credential endpoints (`auth/login`, `auth/two-factor/login`) reject with a **429 envelope** (`{status: "error", message}`) plus `Retry-After` when the identity (email+IP) exhausts its failure quota — identity-scoped and failure-only, enforced server-side in `AuthLoginThrottle`, so shared-IP users are never cross-locked and validation/hCaptcha rejects never consume quota. Surface `message` directly; do not auto-retry before `Retry-After`. Backend contract: [domains/identity/README.md — Login throttle](../domains/identity/README.md#login-throttle-identity-scoped-failure-only).
- FH browse + upload, invoice CSV, Discord connect (`SPA_URL`), Echo bell or FH live event.
- Sidebar **DMCA Blocklist** (`file-hosting.blocklist.index` → SPA `file-hosting.blocklist.index.view`) navigates.

## Production URL minting (`SPA_URL`)

`App\Support\SpaPublicUrl` prefixes browser-facing links onto `config('services.spa.url')` when that origin is non-empty; otherwise it falls back to `APP_URL` and the surviving server endpoints. CORS `allowed_origins` is `APP_URL` + `SPA_URL` + `CORS_EXTRA_ORIGINS` (no `*`).

| Surface | Empty `SPA_URL` | Set `SPA_URL` |
|---------|-----------------|---------------|
| Password reset email | `{APP_URL}/reset-password?token=&email=` — same SPA query shape, Monolith origin (there is no server reset screen any more) | `{SPA_URL}/reset-password?token=&email=` |
| Email verify email | Signed `GET /api/v1/auth/email/verify/{id}/{hash}` | `{SPA_URL}/verify-email/{id}/{hash}?expires=&signature=` — SPA then GETs the signed API path directly from the API origin. HMAC stays on `APP_URL`. |
| File Hosting `share_url` | Server public-share endpoints `{APP_URL}/s/f/{id}` / `/s/d/{id}` ([`routes/web/file-hosting-public.php`](../../routes/web/file-hosting-public.php)) | Same paths on `SPA_URL` |
| iPaymu hosted return/cancel defaults | Server endpoints `{APP_URL}/payment/ipaymu/return\|cancel` | `{SPA_URL}/payment/return` and `/payment/cancel` |

Channel checkout still sends empty `ureturn` (on-page QR/VA). Guest verify route is **not** `guestOnly` so a logged-in unverified user can land on it.

## Notification deep links

API `action_route` + `action_params` carry symbolic route names inherited from the Blade era (`users.edit`, `{ user: uuid }`) — the same vocabulary as `config/spa-routes.php`, not registered Laravel routes. SPA `src/utils/notificationTarget.ts` resolves `${route}.view` and maps `user` / `modelCard` → Vue `:id`. Bell and `/apps/notifications` navigate there; unknown names fall back to the notifications list.

## Production checklist

Ops after merge (not code):

1. Monolith `SPA_URL` = public Vue-Workers origin for that backend environment (production `SPA_URL` → `monolith-spa-prod` origin, sandbox `SPA_URL` → `monolith-spa-sandbox` origin; no trailing slash). Empty falls back to `APP_URL` + server endpoints.
2. `VITE_API_BASE_URL` set to the matching Laravel API origin plus `/api/v1` in the SPA build. This value lives in the **tracked** per-environment env files: `monolith-vue-workers/.env.production` and `.env.sandbox` (loaded via `vite build --mode <env>`; the mode file wins over `.env.local` during builds — verified on Vite 8). `wrangler.jsonc` `vars` are runtime isolate env and never reach the browser bundle — putting `VITE_*` there silently produces a same-origin-fallback build that 404s every API call.
3. Deploy: `npm run deploy:production` or `npm run deploy:sandbox` from `monolith-vue-workers` — or push to `main` / `sandbox`, which auto-deploys the same environments through GitHub Actions (`.github/workflows/deploy.yml`; operational guide: `monolith-vue-workers/docs/deployment.md`). Each path pairs `vite build --mode <env>` with `wrangler deploy --env <env>` **and sets `CLOUDFLARE_ENV=<env>` on the build** — `@cloudflare/vite-plugin` bakes the environment's worker name into the redirected deploy config; without it a sandbox deploy would silently overwrite the production Worker, and wrangler hard-fails any `--env` that mismatches the built `targetEnvironment`. Never mix a mode with a different Wrangler env.
4. hCaptcha: Settings on **and** `VITE_HCAPTCHA_SITE_KEY` set, or both off.
5. File Hosting bucket / Gateway CORS includes the SPA origin and **exposes `ETag`**.
6. Pusher or Sockudo: Laravel `PUSHER_*` + matching `VITE_PUSHER_*` in the SPA build.
7. Two official Wrangler environments exist: `production` → worker `monolith-spa-prod`, `sandbox` → worker `monolith-spa-sandbox`. Both publish a default `<name>.<account>.workers.dev` URL; attach Cloudflare custom domains per Worker when domains are decided. Do not add further Wrangler envs unless another API origin exists.
8. API hostname is served through Cloudflare Tunnel with inbound VM/OCI ports closed. Verify tunnel ingress is an explicit allowlist with a final `http_status:404` catch-all; verify direct origin IPv4/IPv6 access fails.
9. If Cloudflare Access is used, reserve it for internal/admin/debug surfaces unless an intentional user-facing Access login and CORS/preflight design exists. Never put Access service-token credentials in the SPA bundle.

`X-SPA-Origin: 1` is a public anti-noise marker, not auth or proof of SPA origin. JWT is auth. Versioned API calls need the marker; `OPTIONS` and provider webhooks are exceptions. Keep API traffic direct; do not turn the static Worker into an API proxy. Adding this browser header does not add Worker invocations or BFF billing.

Fonts and Bootstrap Icons are self-hosted (`@fontsource/source-sans-3`, `bootstrap-icons`). `public/robots.txt` disallows `/apps` and auth paths; `/s/*` is `noindex`. Vue DevTools is Vite `serve` only.

## CI

`monolith-vue-workers/.github/workflows/ci.yml`: `npm ci` → `type-check` → `check:i18n` → `vitest` (`tests/baseUrl.test.ts`, `tests/client.test.ts`, `tests/csrf.test.ts`, `tests/echo.test.ts`, `tests/notificationTarget.test.ts`, `tests/requestHeaders.test.ts`, `tests/safeRedirect.test.ts`, `tests/safeUrl.test.ts`, `tests/shellHydrate.test.ts`, `tests/vkStr1Theme.test.ts`) → `build-only`. No Playwright / ESLint-everywhere in this gate.

## API contract reference

The SPA consumes the same `/api/v1/*` endpoints Monolith exposes. Per-domain API docs:

- [payment-gateway](../domains/payment-gateway/README.md)
- [microstock](../domains/microstock/README.md)
- [notification](../domains/notification/README.md)
- [product-activations](../domains/product-activations/README.md)
- [identity](../domains/identity/README.md)
- [navigation](../domains/navigation/README.md)
- [file-hosting](../domains/file-hosting/README.md)
- [inference](../domains/inference/README.md)
- [service](../domains/service/README.md)
- [vtual](../domains/vtual/README.md)

### Endpoint path gotchas

These paths tripped up the SPA port — documented here to prevent regression:

| SPA composable | Correct API path | Common mistake |
|----------------|------------------|----------------|
| `usePaymentsIncome.fetchOverview` | `GET payments/income` | `payments/income/overview` (route name is `payments.income.overview` but URI has no `/overview` suffix) |
| `useMicrostockBilling.fetchBilling` | `GET microstock/billing` | `file-hosting/microstock/billing` (extra `file-hosting/` prefix — that is the web route, not API) |
| `useMicrostockBilling.fetchLedger` | `GET microstock/billing/ledger` | `file-hosting/microstock/billing/ledger` |
| `useMicrostockBilling.fetchUsageEvents` | `GET microstock/billing/usage-events` | `file-hosting/microstock/billing/usage-events` |
| `useMicrostockBilling.fetchAdminOverview` | `GET admin/microstock/billing/overview` | `file-hosting/microstock/billing/admin/overview` |
| `useMicrostockBilling.fetchAdminUsageEvents` | `GET admin/microstock/billing/usage-events` | `file-hosting/microstock/billing/admin/usage-events` |
| `usePaymentChannels.syncPolicy` | `POST payment-channels/sync-policy` | No API route existed previously; added in `routes/api/v1/payment-gateway.php` |
| `useRatePlanAdmin.saveRates` | `PUT inference/providers/{id}/rates` | Body `{ rates: [...] }` is a **full replace** (same as Blade). Gated by `manage inference catalog`. |
| `useConnections.fetchConnections` | `GET profile/connections` | Not under `auth/`. Any authenticated user. Returns `{ providers: [...] }`. |
| `useConnections.syncProvider` | `POST profile/connections/{oauth_provider}/sync` | Force-refresh; returns the same `{ providers }` payload as GET. Path param is `{oauth_provider}` (not `{provider}` — Inference binds that name). |
| `useConnections.disconnectProvider` | `DELETE profile/connections/{oauth_provider}` | Same `{oauth_provider}` constraint as sync. |
| `useConnections.authorizeUrl` | `GET auth/discord/authorize-url` | Query `intent` + `return_path` (must start `/apps/`). Returns `{ url }` — navigate the browser there. Discord still callbacks to Monolith web. Discord-only until a second vendor exists. |

### Response shape gotchas

| Endpoint | Returns | Gotcha |
|----------|---------|--------|
| `GET notifications/unread-count` | `{ unread: number }` | Field is `unread`, not `count` |
| `GET microstock/billing` | `balance_micro_usd`, `balance_usd`, `estimated_per_analysis_micro_usd`, `estimated_per_analysis_usd`, `daily_free: { enabled, limit, used, remaining, resets_at }` | Field names are `balance_usd` / `estimated_per_analysis_usd`, not `balance_formatted` / `estimate_formatted`. Daily free uses `used`/`limit`, not `slots_consumed_today`/`daily_limit`. |
| `GET admin/product-activations` (index) | Flat `ProductActivationResource[]` in `data` | No `{ data, pagination }` wrapper — `res.data` is the array directly |
| `GET admin/product-activations/products/{productKey}` | `{ summary, activations, pagination }` in `data` | This one DOES have a wrapper — opposite of index |
| `GET payment-channels?subtotal=` | `PaymentChannelResource[]` with flattened quote fields | Quote fields (`estimated_channel_fee`, `estimated_channel_fee_formatted`, `fee_label`, etc.) are at top level, NOT nested under `checkout_quote`. See [payment-gateway](../domains/payment-gateway/README.md#paymentchannelresource-checkout-quote-flattening). |
| `GET services/options` | `{ billing_intervals, plan_kinds, statuses, purchase_max_quantity }` | `purchase_max_quantity` is the live `platform.service.purchase_max_quantity` cap. SPA checkout/purchase steppers parse it. Fallback `99` only when the call fails (options requires `view service`; purchase owners may lack it). |
| `GET profile/connections` | `{ providers: [{ key, connected, oauth_configured, supports_sync, synced_at, synced_at_formatted, synced_at_human, connections[] }] }` | One row per `OAuthProvider`. `connections[]` items are `{ type, id, name, verified, revoked }` (empty when disconnected). Stale Discord snapshots still TTL-refresh on this GET. No top-level `discord_*` fields. |
| `POST profile/connections/{oauth_provider}/sync` | Same `{ providers }` as GET | Always hits Discord for `discord` (bypasses TTL). Unknown provider 404. Parameter name is `oauth_provider`. |
| `DELETE profile/connections/{oauth_provider}` | `null` data | Disconnect. Replaces `DELETE …/discord`. |
| `GET auth/discord/authorize-url` | `{ url }` | Requires `SPA_URL` on the API host. `return_path` is a path, not an origin — the API prefixes `SPA_URL`. |

## Type alignment

TS types in `src/api/types.ts` must match Laravel API Resources exactly. No transformation layer. `ProfileConnections.providers` is `OAuthProviderConnection[]` (`key`, `connected`, `oauth_configured`, `supports_sync`, synced-at triplet, `connections[]`). Linked-platform rows stay `DiscordConnection` (`type`, nullable `id` / `name`, `verified`, `revoked`) — see `ProfileConnectionsController::presentConnections`. Use `providerByKey(snapshot, 'discord')` instead of a flat `discord_connected` field. One interface — do not redeclare `DiscordConnection`.

### Conventions

- **snake_case everywhere** — Laravel returns snake_case, TS uses snake_case. No camelCase conversion.
- **API envelope** — every response is `{ status: 'success'|'error', message: string, data: T }`. The `data` field is the payload.
- **Date triplets** — date fields come as `{ key (ISO string), key_formatted (human-readable), key_human (relative) }`. All three are pre-formatted strings; never call `new Date(apiField)`.
- **Nullable / omitted fields** — Laravel may return `null` where a type expects a string, **or omit the key** when the viewer is not allowed to see it (per-viewer Resources). Use `string | null` and `?` optional keys. Never assume staff-only fields exist on buyer/guest payloads.
- **UUIDs** — all model IDs are UUID v7 strings (see [platform/identifiers.md](../platform/identifiers.md)). Never type IDs as `number`.

### Per-viewer payloads

API Resources are a **per-viewer** contract, not a dump of the model. Guest/public endpoints use dedicated Resources (`PublicHostedFileResource`). Staff-only fields (`view invoice`, storage topology, claim tokens, member emails, upstream AI cost, FX/settlement) are optional in `src/api/types.ts`. Backend rules: [platform/security.md](../platform/security.md#response-data-minimization-mandatory).

### Pagination shapes

Three patterns exist in the API:

1. **Flat array** — `ProductActivationResource::collection(...)` returned directly. `data` is `T[]`. No pagination metadata. Query-mode catalog indexes **without** `page` / `per_page` / `search` also stay this shape (legacy clients / tests).
2. **`{ items, pagination: { current_page, last_page, per_page, total, has_more } }`** — microstock billing ledger/usage-events, inference usage events, **and** query-mode catalog indexes when the SPA (or any client) sends `page` / `per_page` / `search`. Default `per_page` is 10 (max 100). Same list URL — not a second API.
3. **`{ summary, activations, pagination: { current_page, last_page, per_page, total } }`** — product activation per-product show endpoint. `has_more` is not returned here.

## Status badges

Lifecycle status in the SPA is one renderer, many maps — not one semantic mega-badge.

- Component: `src/components/StatusBadge.vue` (`status`, optional `label`, `mapping`, `fallback`, `extraClass`).
- Maps: `src/utils/statusBadge.ts` (`INVOICE_STATUS_BADGE`, `SERVICE_STATUS_BADGE`, `SUBSCRIPTION_STATUS_BADGE`, `ERROR_LOG_STATUS_BADGE`, `ERROR_LOG_LEVEL_BADGE`, `PRODUCT_ACTIVATION_STATE_BADGE`, `VTUAL_STATUS_BADGE`). Domain maps **merge over** defaults. `expired` is secondary on invoices and danger on product activations — that is why a single default map is forbidden.
- Empty / null status → label `—`, color = `fallback` (default `secondary`). Mapped color `light` also gets `border` (vTual unknown/disconnected).
- Do **not** add a per-view `statusClass()`, and do **not** collapse the domain maps into one generic cross-domain badge. That was the Blade era's mistake and the reason `VTUAL_STATUS_BADGE` stays a separate map.

Leave specialized chrome alone: `PaymentStatusHero` (`ois-status-*` + `data-payment-badge`), FH `fh-badge` / `fh-ms-badge`, keyword chips, navbar unread count, product-activation count chips, type/kind/role/code chips.

## i18n porting notes

The SPA uses `vue-i18n` with JSON locale files converted from Laravel PHP lang files.

### Contextual localization policy

English remains the reference locale. Indonesian and Japanese translations must be contextual and natural for the surface where they appear; literal word-for-word translations are not accepted. Review button labels, headings, table labels, status badges, toasts, validation, errors, explanatory copy, domain terminology, audience, and Japanese/Indonesian register separately. Keep machine-contract prompts and technical identifiers unchanged when they are not user-facing. Split keys when one source phrase serves different contexts.

Translation workflow:

1. Add or adjust the key in all supported locale files in one change.
2. Preserve Vue interpolation tokens, plural behavior, and API/DB identifier semantics.
3. Use `computed()` or the current i18n translator for labels that must react to runtime locale changes; do not freeze translated arrays at module scope.
4. Run `npm run check:i18n` after every translation batch. Exact English duplicates fail unless key appears in explicit `scripts/check-i18n.mjs` allowlist. Allowlist is key-specific and limited to brands, provider/code identifiers, `QRIS`, `Discord`, `vTual`, `Microstock`, machine-contract text, and **locale loanwords** — keys whose accepted term in the locale IS the English word (Indonesian IT vocabulary like `Status`, `Filter`, `Model`, `Slug`, `Avatar`, `Channel`; Chinese tech convention `Slug`). Prefer translating when a natural native term exists; allowlist only when the loanword is what a native user would read in a real product. `node scripts/i18n-duplicate-report.mjs` writes `i18n-duplicate-report.json` with locale/namespace/key-level inventory, classifications, placeholder tokens, actionable totals, and stale-allowlist detection. The checker prints per-locale/two-segment-namespace duplicate counts for file-by-file translation waves; generated inventory never authorizes untranslated user-facing copy.
5. Keep layout direction logical and scope RTL-only rules to `html[dir="rtl"]`; use explicit RTL icon transforms when an arrow's meaning changes. RTL vendor setup: `src/main.ts` awaits either `src/assets/main-ltr.css` or `src/assets/main-rtl.css` (chosen from `isRtl()` before mount). `main-rtl.css` imports `src/assets/vendor-rtl.css`, a sibling of `vendor.css` whose only difference is the two vendor lines use the official RTL dist builds (`bootstrap.rtl.min.css`, `adminlte.rtl.min.css`); keep both vendor files' import lists in sync. The swap is a separate CSS entry — NOT a Vite `resolveId` alias — because Vite processes CSS `@import` via postcss, not the JS resolve pipeline, so a resolver-based swap silently no-ops in production builds. `setLocale()` triggers a full `location.reload()` when the direction changes so the other stylesheet loads — never try to hot-swap direction without reload. The reload must run only AFTER `auth/locale` persists: `setLocale(locale, { reload: false })` skips the reload, and `AppNavbar.switchLocale()` captures the prior locale, awaits `switchLocaleApi()` + `refetchNavigation()`, then calls `reloadForDirection(prev, next)` last. Reloading synchronously inside `setLocale()` aborts the in-flight `POST auth/locale`, leaving `users.locale` and the `locale` cookie stale — the dynamic menu then stays on the old locale (this was the Arabic menu-stale bug). On the backend, `SetLocale` resolves `users.locale` → `X-SPA-Locale` header → `locale` cookie → default, so the always-sent header beats a stale cookie even if a persist is interrupted. Directional icon convention: back-arrow semantics use the `html[dir='rtl'] .bi-arrow-left::before` glyph swap in `monolith-overrides.css`; the custom `.fh-breadcrumb__sep` separator uses `transform: scaleX(-1)`; list-movement chevrons (pagination, modal prev/next) bind via `isRtl()` from `src/i18n/index.ts` so "previous" always moves backward in the list. Do NOT add a custom transform for AdminLTE's `.nav-arrow` — the vendor RTL build already mirrors it with `translateY(-50%) rotate(180deg)` and a custom `scaleX(-1)` would clobber that vertical centering (this was the sidebar icon/text collision bug).
6. Run `npm run type-check` and `npm run build` before handoff.

### SPA localization guards

`npm run check:i18n` checks key parity across `en`, `id`, `ja`, `ko`, `zh-CN`, `ar`, and `ru`, Vue placeholder shape, malformed interpolation braces, suspicious values identical to English, and suspicious hardcoded user-facing fallback copy under `src/`. API fallback errors must use locale keys, with server messages preferred when present.

Runtime locale switching updates `document.documentElement[lang]`, persisted preference, reactive labels, and locale-aware number/date formatting. Startup resolution is separate from backend `SetLocale`: the SPA checks supported `localStorage.locale`, then the first two characters of supported `navigator.language`, then falls back to `en`. `setLocale()` persists the choice and applies the HTML `lang` attribute; `window` and `document` access is guarded for SSR/tooling contexts. Backend requests still resolve authenticated user preference, then cookie, then `config('app.locale')`; do not assume both clients choose the same locale before synchronization.

Use `Intl.NumberFormat` / `Intl.DateTimeFormat` with active locale; never call `toLocaleString()` without the application locale for user-facing values. API/DB labels must come from localized backend data or explicit SPA key mappings. Show raw codes only when they are technical identifiers. Machine prompts remain English only when English is part of the provider contract; user-facing labels and messages remain localized.

`src/i18n/fallbacks.ts` owns generic fallback keys for validation failure, request failure, session expiry, confirmation copy, cancel, and save. `src/api/client.ts` calls `translateFallback(i18n.global.t, ...)` only when the API response has no usable message; server-provided messages always win. This module-level path must use the shared `i18n` instance, not `useI18n()`.

### Conversion rules

| Laravel PHP | vue-i18n JSON |
|-------------|---------------|
| `lang/{locale}/web/settings.php` | `src/i18n/locales/{en,id,ja,ko,zh-CN,ar,ru}.json` under `web.settings.*` (`zh_CN` ↔ `zh-CN` at API/browser boundary) |
| `lang/{locale}/ui.php` | `src/i18n/locales/{en,id,ja,ko,zh-CN,ar,ru}.json` under `ui.*` |
| `trans('web/settings.title')` | `t('web.settings.title')` — use `.` separator, not `/` |
| `:variable` placeholder | `{variable}` placeholder |
| `trans_choice('key', count)` | `t('key', { count })` or custom pluralization helper |

### Usage pattern

Every `.vue` file using translations must have:

```vue
<script setup lang="ts">
import { useI18n } from 'vue-i18n'
const { t } = useI18n()
// or for settings fields/hints (literal dotted JSON keys):
// const { t, tm, rt } = useI18n()
</script>
```

All user-visible strings must be wrapped in `t()`. This includes:
- Text nodes in templates: `{{ t('key') }}`
- Component prop labels: `:title="t('key')"` not `title="Hardcoded"`
- Placeholders: `:placeholder="t('key')"`
- Toast/error messages in catch blocks: `showError(t('key'))`
- `confirmDialog()` / Swal dialog copy (native `alert()` / `confirm()` are forbidden)

## Notifications & confirmations (Sweetalert2)

All user-facing notifications and confirmations go through Sweetalert2 via the shared helper `src/lib/swal.ts`; native `alert()`/`confirm()` and ad-hoc Bootstrap `alert` blocks are not allowed:

- **Notifications** — keep calling `useAlert()` (`success`/`error`/`info`). Internally it renders Swal toasts (`success`/`info`, `top-end`, 4s auto-dismiss) and an error dialog (`error`, `ui.dialog.error_title` title, `ui.swal.ok` button). The API is unchanged so call sites need no updates.
- **Confirmations** — use `confirmDialog({ title, text | html, confirmText, cancelText, icon, danger })` which resolves `true` on confirm. Never add a `ConfirmModal`-style `v-model` component for simple confirmations; the one remaining `ConfirmModal.vue` usage is the file-hosting rename form (an input form, not a confirmation) and stays until it gets a dedicated dialog.
- **Text input prompts** — use `inputDialog({ title, required?, confirmText?, danger? })` (resolves the trimmed string or `null` on cancel; `required: true` blocks an empty confirm). Native `prompt()` is forbidden; the product-activation reject/suspend reason prompts use this helper.
- **i18n** — shared labels live at `ui.swal.*` and `ui.dialog.*`; confirm bodies use their domain keys. All supported locales (`en`, `id`, `ja`, `ko`, `zh-CN`, `ar`, `ru`) must stay in parity. Arabic sets `html[dir="rtl"]`; other locales use `ltr`.
- **Layering** — `.swal2-container` is pinned to `z-index: 1090` in `src/assets/monolith-overrides.css`, above Bootstrap modals (1050/1055) and custom layers like `.fh-context-menu` / `.monolith-toast-container` (1080). Never lower it; any new fixed layer must stay below 1090.
- **Toast/modal serialization** — swal2 removes the `swal2-toast-shown` body class unconditionally when a modal closes, which makes any still-open toast container fall back to full-viewport width. To prevent the race, `showErrorDialog()` and `confirmDialog()` call `closeActiveToast()` first (closes an open toast before firing the modal). Never fire a raw `Swal.fire` modal while a toast may be open — go through `src/lib/swal.ts`.
- **Body classes** — never assign `document.body.className = ''`. Swal (and Bootstrap/OverlayScrollbars) store runtime classes on `<body>` (`swal2-toast-shown`, `swal2-shown`, `modal-open`). Layout classes (`login-page`, `layout-fixed`, `sidebar-expand-lg`, …) are toggled in `App.vue` via `classList.remove`/`add` only. Toast container sizing is also pinned in CSS (`.swal2-container:has(.swal2-popup.swal2-toast)`) so a missing body class cannot balloon the toast to full viewport.
- **Redirect safety** — `useAlert().error(...)` returns a Promise. In flows that navigate away (`window.location.href = ...`, e.g. Discord/OAuth connect, file download), always `await showError(...)` inside the `catch` so the error dialog renders and is read before any unload instead of dying with the page.
- **Uncaught errors** — `app.config.errorHandler`, `unhandledrejection`, and `router.onError` in `main.ts` call `showErrorDialog(i18n.global.t('ui.ajax.unexpected_error'))`. No Sentry unless a DSN is added later (Cloudflare `observability.enabled` is already on in wrangler).

`useI18n()` is restricted to Vue component `setup()` (including composables called synchronously from setup). Router guards, Pinia-style stores, shell hydration, realtime callbacks, module-level services, and other ordinary functions may run without an active component instance. Those paths must import the shared `src/i18n` instance and call `i18n.global.t(...)`; they must not call `useI18n()`. This prevents `Must be called at the top of a setup function` from aborting initial router navigation. `main.ts` registers `router.onError()` for diagnostic reporting and mounts the app immediately, so a failed initial navigation cannot leave a blank document. The auth guard still performs shell hydration before protected route components mount.

```ts
const message = i18n.global.t('web.notifications.load_failed')
```

Options arrays that contain labels should be `computed()` so they re-render on locale change:

```ts
const basisOptions = computed(() => [
  { value: 'paid_at', label: t('web.payments.income.basis_paid_at') },
  { value: 'withdrawable_at', label: t('web.payments.income.basis_withdrawable_at') },
])
```

### Locale files

- `src/i18n/locales/en.json` — reference locale
- `src/i18n/locales/id.json` — Indonesian
- `src/i18n/locales/ja.json` — Japanese
- `src/i18n/locales/ko.json` — Korean
- `src/i18n/locales/zh-CN.json` — Simplified Chinese (browser code)
- `src/i18n/locales/ar.json` — Arabic (RTL)
- `src/i18n/locales/ru.json` — Russian
- `src/i18n/index.ts` — `createI18n()` instance, `setLocale()` helper, `applyHtmlLang()`

Locale parity: all seven files must have the same key set. Laravel `:variable` placeholders must be converted to `{variable}` in all locales (not just `en`). `zh_CN` remains backend-only; SPA storage, `html[lang]`, and browser `Intl` use `zh-CN`.

## Component conventions

### confirmDialog

```ts
import { confirmDialog } from '@/lib/swal'

async function onDelete(): Promise<void> {
  const confirmed = await confirmDialog({
    title: t('ui.datatable.delete'),
    text: t('web.payments.gateways.confirm_delete'),
    confirmText: t('ui.actions.delete'),
  })
  if (!confirmed) return
  await destroy()
}
```

Custom bodies that used to be ConfirmModal slots go in `html:` (escape any interpolated user data). `danger` defaults to true (red confirm); pass `danger: false` for a primary confirm button.

`ConfirmModal.vue` is **not** the confirmation API. The only remaining use is the file-hosting rename form on `FileHostingShowView` (an input dialog, not a yes/no confirm). Do not add new `v-model` confirm components.

### SubmitButton

```vue
<SubmitButton :loading="saving" :label="t('save_key')" variant="danger" />
```

Uses `variant` prop (not `confirm-class`). Values: `'primary'`, `'danger'`, `'success'`, `'warning'`.

A submit that sits in the last column of a labeled form row (same height as `FormInput` / `FormSelect`) uses a spacer `form-label` plus `d-grid` so the button fills the column on every breakpoint — see `VtualOrphanEditView` channel add and `VtualProfileShowView` channel add / merge / links.

### FormInput

Supports `maxlength?: number` prop, passed through to underlying `<input>`.

### FormSelect2

Wraps jQuery Select2 (`src/utils/jquery-setup`, `src/utils/select2-lookup`). Handles plain and AJAX modes; the AJAX mode consumes the paginated `GET /api/v1/lookups/*` contract — see [platform/list-display-modes.md](../platform/list-display-modes.md).

### Loading & error states (skeleton + retry)

Every data-loading view renders three explicit states in priority order: skeleton while loading → error with retry on failure → empty/data. No view goes blank on fetch failure. Bootstrap 5 classes only (no Tailwind in the AdminLTE namespace).

- `src/components/SkeletonBlock.vue` — placeholder block; props `variant` (`line|block|avatar|button`), `width`, `height`, `lines`. Compose into layout-shaped skeletons per view.
- `src/components/ErrorState.vue` — sibling of `EmptyState.vue`; props `icon` (default `bi-exclamation-triangle`), `title`, `message`, `retryLabel`; emits `retry` (retry button always renders). i18n keys `ui.error_state.{title,message,retry}`.
- `src/components/EmptyState.vue` — icon + title (+ optional message). Default icon `bi-inbox`. Tabular lists pass a domain-relatable Bootstrap icon (orphans: `bi-person-plus`).
- `src/components/TableEmptyRow.vue` — one `<tr>` / colspan `<td>` wrapping `EmptyState`. Use this whenever a custom `<table>` cannot use `DataTable.vue` (keep `<thead>` visible).
- `src/composables/useDetailLoader.ts` — standardizes detail/show views: `{ data, loading, error, load }`, auto-loads on mount, `load()` is the retry handler.
- `DataTable.vue` — `error?: string | null` prop + `retry` emit; body order is loading skeleton rows → error (ErrorState full-colspan) → empty (`EmptyState` with `empty-icon` / `empty-title`, default icon `bi-inbox`) → rows. List views pass `useResource.error` and `@retry="fetchList()"` plus a domain `empty-icon`. Card + dump lists that used a raw table now use `DataTable` the same way. Custom tables that must stay raw (Menus hierarchy, load-more event/ledger tables, inline-edit nested tables) use `TableEmptyRow` instead of plain text.
- Router chunk-load failures (`router.onError` in `main.ts`) flip a global `chunkLoadFailed` flag (`src/composables/useChunkLoadError.ts`); `App.vue` renders a full-page `ErrorState` + reload button instead of a blank document.
- `showError` (SweetAlert2) remains the surface for action failures (create/delete/checkout), not initial load.

The valkivid3 public template keeps its own retry-panel pattern and is intentionally untouched.

## Breadcrumb system

Breadcrumbs are entirely SPA-side via a config-based composable. Monolith's `diglactic/laravel-breadcrumbs` trails were removed with Blade; there is no server breadcrumb registry to mirror any more.

- **Composable**: `src/composables/useBreadcrumbs.ts` — config-based breadcrumb map keyed by Vue Router route name.
- **Consumer**: `src/layouts/AppLayout.vue` — renders breadcrumb trail in the content header.
- **Config shape**: each entry has `labelKey` (i18n key), optional `parent` (route name chain), and optional `routeName` (for clickable links).

The map was ported from Monolith's former `routes/breadcrumbs.php` and is now the only definition. Parent chains resolve recursively: e.g. `users.edit.view` → `users.index.view` → `dashboard.view`. The last item in the trail is always non-clickable (active).

To add a new route's breadcrumb, add an entry to `BREADCRUMB_MAP` in `useBreadcrumbs.ts` with the correct `parent` chain and `labelKey` (must exist in locale JSON files).

## Page titles and admin chrome

Authenticated routes set `meta.titleKey` (and guest auth routes also `meta.headingKey`) to the same key names ported from the former Blade layouts. Do **not** hardcode English in router `props.title`.

- **Composable**: `src/composables/usePageTitle.ts` — resolves the title from `route.matched` meta; special-cases settings groups (`web.settings.groups.*` from `route.params.group` **or** MenuSeeder names `settings.platform.view` / `settings.file-hosting.view` / `settings.microstock.view` / `settings.vtual.view`) and product-activation show (`product-activations.product_title` with `{product}`).
- **Content header**: `AppLayout.vue` renders `pageTitle` in the `h3`.
- **Auth heading**: `AuthLayout.vue` renders `heading` as `login-box-msg`.
- **Tab title**: `App.vue` watches `documentTitle` and sets `document.title` to `{page} | Monolith` (or `Monolith` when there is no title). Recomputes on locale change.
- **DataTable chrome**: `DataTable.vue` uses `ui.datatable.*` (`search_placeholder`, `empty_title`, `info`, `per_page`) and `ui.labels.actions`. Catalog dumps (`useResource` + DataTable) send `display_mode=query&page&per_page=10` (and `search` when the box is used) and parse `{ items, pagination }` **or** a legacy flat `T[]`. Default page size is **10** (Blade DataTables). Menus tree stays a full dump + client slice (no server paging). Do not port Yajra JS. Do not invent a second list URL. `MenusView` opts out of `DataTable.vue` for a plain `<table>` + client-side `searchQuery` filter (no pagination, no sort) so the `h`/`p`/`c` hierarchy is never split across pages; empty still uses in-table `TableEmptyRow` (`bi-list-nested`). `MenusTrashedView` still uses `DataTable.vue`. Card grids, file-hosting browsers, and overview mini-tables are not datatables — they keep their own empty UI.
- **Row actions**: Monolith query-mode `row.actions[]` is the source of keys, destinations, RBAC visibility, and mutate confirm copy. SPA `mapQueryRowActions` / `findQueryRowAction` in `src/utils/mapQueryRowActions.ts` only maps and executes — views must not invent keys. Icon-only lists keep `RowActions.vue` (`aria-label` + `title` from the API label, icon `aria-hidden`, `btn-group btn-group-sm`). Nav → `route` + `.view`; external → `href`/`target` (sanitized with `safeHttpUrl`); mutate → existing composable + `confirmDialog` using API `confirm`. Labeled columns (currencies activate/settlement, audiences Edit, vTual platform Manage, blocklist remove) stay labeled and still read `row.actions`. `@row-click` uses the same nav action already on the row. Toolbar primaries stay icon + text (`Add user`). File Hosting browse `.fh-action-group` is out of scope. Yajra `display_mode=datatable` `actions[]` has no SPA consumer — do not extend it.
- **Navigation buttons vs action buttons**: Any button whose only effect is `router.push(...)` MUST be a `<router-link :to="...">` so the destination URL is visible on hover (browser status bar / address bar tooltip). Real actions (delete, save, export, probe, restore) stay `<button>` — they have side effects and no meaningful URL. `router.back()` buttons stay `<button>` too (destination is history-dependent, not resolvable). Disabled nav (e.g. cancel while saving) uses `<router-link>` + Bootstrap `disabled` class + `aria-disabled` + `tabindex="-1"` instead of the native `:disabled` attribute (which only works on `<button>`).

### AdminLTE page grid

Authenticated `AppLayout` content already sits in `container-fluid`. A lone `col-lg-8` / `col-lg-6` therefore leaves a hole on the right. Page-level Bootstrap columns (not nested form/dl/KPI tiles) follow:

1. **One page-level card** — put `Card` at the content root (same as Settings / Menu edit). Do not wrap it in a partial column. A `.row > .col-12` wrapper is redundant; skip it.
2. **Two page-level cards** — use a `.row` and a split that matches content. Default **`col-lg-8` + `col-lg-4`** (main + sidebar). Use **`col-md-8` + `col-md-4`** when both columns should sit side by side earlier (claim steps, vTuber profile). Pair both sides on the **same** breakpoint. Optional `order-*` for sidebar-first on mobile stays allowed (purchase/invoice show).
3. **Conditional sibling** — the main cell is `col-12` when the counterpart is not rendered, and gains the split class only while the sibling exists (`:class="isEdit ? 'col-lg-8' : 'col-12'"`). Do not leave `col-lg-8` standing alone on create. This applies to **main + sidebar shells**, not to equal-width KPI/card tiles that drop one tile.

Valkivid3 public layouts are out of scope. Nested field grids (`col-md-6` form pairs, `col-sm-3`/`col-sm-9` definition lists) are not page shell.

### Blob / CSV downloads

`src/api/client.ts` `get()` always sets `Accept: application/json` and `res.json()`. CSV and other attachments use `getBlob()` (same cookie-write gate, settle TypeError/401 backoff, then later-401 refresh as `get()`, `Accept: */*`) plus `src/utils/download.ts` `saveBlob()`. Wired for `GET invoices/export` (invoices list and income page; income sends `status=paid` plus optional `paid_from`/`paid_to`) and `GET microstock/collections/{folder}/download`.

### OverlayScrollbars

Sidebar CSS is bundled in `src/assets/vendor.css`. JS init lives in `useSidebar.init()` (called from `AppLayout` on mount): desktop only (`max-width: 991.98px` skipped), target `.sidebar-wrapper`, `os-theme-dark` / `os-theme-light` from `data-bs-theme`, `autoHide: 'leave'`. Theme changes call `syncOverlayScrollbarTheme()`. Destroy on `AppLayout` unmount. Auth layout has no sidebar wrapper — skip.

### hCaptcha

Login, register, forgot-password, and 2FA challenge mount `HcaptchaWidget` (invisible). The widget is a no-op unless `VITE_HCAPTCHA_SITE_KEY` is set. When the key is present, `execute()` runs before submit and the token is sent as **`h-captcha-response`** on the same API FormRequests Blade uses (`ValidatesHcaptcha`).

The SPA cannot read `system_settings`. Treat `VITE_HCAPTCHA_SITE_KEY` as the enabled gate. If the API has `platform.hcaptcha.enabled` on and the SPA env key is missing, those endpoints 422 — same class of misconfig as Blade missing `HCAPTCHA_SITEKEY`.

### File Hosting Drive UX

Browse/trash/show use `.fh-manager` (CSS: `src/assets/file-hosting.css`, imported from `main.css`). Vue Router stays three routes — this is **not** the Blade single-island soft-nav shell. `useFileHosting` / `useFileHostingRealtime` go through the shared cookie client (`src/api/client.ts`); they do not attach a Bearer token in JS.

**Browse (`FileHostingView.vue`)** matches Blade Drive-lite:

| Gesture | Behavior |
|---------|----------|
| Click row/card/name/icon | Select only (ignores checkbox/actions) |
| Ctrl/Cmd+click | Toggle |
| Shift+click | Inclusive range over visible folder+file order (`useFhSelection.ts`) |
| Dblclick | Open: folder → `file-hosting.index.folder`; file → `file-hosting.show.view` |
| Right-click | Select if needed + permission-gated context menu |
| Drag onto folder / breadcrumb / parent `...` | Move (`useFhDragMove.ts`); refuse self-drop |
| Escape | Clear selection + close context + close search |

Search is a **debounced API overlay** (`GET file-hosting/search`). It does **not** filter or hide the listing. Overlay results are files only and navigate to show.

Folder navigation: root is `file-hosting.index.view`; nested is `file-hosting.index.folder` with `params.folderId`. `workspace_id` / `user_id` stay query params and are watched (reload browse/quota/workspaces). Echo stays user-scoped (`file-hosting.user.{id}`) — do not rebind on workspace switch.

New Folder is gated by `create file hosting folder`. Dropzone still mounts when a microstock collection is locked; add/drop toasts `web.file-hosting.microstock.error_collection_locked` / `error_requires_folder`. Microstock row badges when `browse.workspace.kind === 'microstock'`.

**Microstock collection lifecycle** (`FileHostingView` when workspace `kind === 'microstock'`): toolbar follows `collection_status` — draft/failed → Lock & Describe (`FhCollectionDescribeModal`, chip compose in `src/utils/file-hosting/fhCollectionDescribe.ts` → `POST microstock/collections/{folder}/lock`); locked → Start analysis (`POST …/analyze`; API `assertCanAnalyze` is the only gate — toast the API `message` on 422; do **not** invent client credit math); processing → status + progress (`GET …/status` + Echo `.collection.analysis.progress`); review → Review + Export; exported → Download CSV (`getBlob` on `GET …/download`). Review is `FhMicrostockReviewModal` (signed preview from `GET file-hosting/files/{id}/download?preview=1`, stage strip, PATCH title/keywords, solo `POST microstock/assets/{file}/analyze`). Show page opens the same modal. Create system workspace is `POST microstock/workspaces` — not `POST file-hosting/workspaces` (team). Switcher CTAs parse quota (team slots) and `GET microstock/slots` (used/limit/`can_create`); full/unactivated links to Services.

Share modal: visibility select (`private` / `anyone_with_link`) + readonly URL. Move modal: root + current listing folders (not a tree picker). Bulk bar: Move / share / unshare / delete.

**Trash / show / workspaces / blocklist:** browse Drive gestures stay on the listing only. Workspaces and hash blocklist use `.fh-manager.fh-admin-page` intro chrome (header + lead), not a nested Card dump. Show uses `FhShareModal` and `FhMicrostockReviewModal`.

**Public share** (`/s/d/:id`, `/s/f/:id`): `PublicLayout` (no sidebar, no auth Drive gestures). `PublicFolderView` maps `breadcrumb` ids to `public.folder` and file rows to `public.file`. Reloads when `route.params.id` changes.

View mode persists in `localStorage` key `fh-view-mode` (default table).

### Inference provider rate editor

`InferenceProviderShowView` wires `useRatePlanAdmin`. After `fetchOne`, `initRates(provider.model_rates)`. Model-card options come from `useInferenceModelCards().fetchList` (`display_name` on the select, not slug-only). `canManage` (`manage inference catalog`) gates Add / Save / Remove and makes rate inputs `readonly` when false.

`saveRates()` `PUT inference/providers/{id}/rates` with `{ rates: [...] }` — **full replace**, same contract as Blade `rate-plans-admin.js`. On success the view re-fetches the provider and re-inits rows; it does not full-reload the SPA.

### Payment channels admin chrome

`.pc-admin` CSS lives in `src/assets/channels-admin.css` (imported from `main.css`). `PaymentChannelsView` ports Blade intro / 3 steps / stats / kind icons (`qris` / `va` / `ewallet`) / ranked `.pc-route-*` rows. Channel and route toggle / priority / sync-policy handlers are unchanged.

### Inference usage chrome

`InferenceUsageEventsView` wraps filters/totals with `.inf-usage-admin` header + subtitle (`web.inference.usage.list_title` / `.subtitle`). Same chrome move as `.pc-admin`. Filters and totals were already wired.

### Payment return URLs

SPA routes: `/payment/return` and `/payment/cancel` (`PaymentReturnView`, `requiresAuth`). Monolith keeps `/payment/ipaymu/return|cancel` as **server endpoints** that redirect onward. When `SPA_URL` is set, `SpaPublicUrl::paymentReturn/Cancel` mints the SPA paths as iPaymu hosted redirect defaults; empty `SPA_URL` keeps those server endpoints on `APP_URL`. Channel checkout still sends empty `ureturn` (on-page QR/VA). The API-origin HttpOnly `__Host-spa_jwt` uses `SameSite=None; Secure`; after the SPA loads, `beforeEach` hydrates through direct `GET auth/me` and keeps `redirect=fullPath`. Browser privacy policy can still block or partition cross-site cookies, so verify the deployed payment bounce and session behavior. Do not reopen these as guest routes unless a real gateway hit arrives without a session. These stay at the **site root**, not under `/apps`.

### `/apps` shell

Authenticated `AppLayout` routes are prefixed `/apps/*` (dashboard, users, settings, file-hosting, vtual, invoices, …). `/` redirects to `/apps/dashboard`. Route **names** stay unprefixed — sidebar `NavTree` / `AppNavbar` `push` by name on click; `NavTree` leaf `href` is resolved via `router.resolve` so the URL is visible on hover (parent `href` stays `#` while it has visible children).

**Not prefixed** (same exceptions as Blade, plus the verify hop): auth (`/login`, `/register`, `/forgot-password`, `/reset-password`, `/two-factor-challenge`, `/verify-email/:id/:hash`), public share (`/s/f/:id`, `/s/d/:id`), payment bounce (`/payment/return`, `/payment/cancel`), **vTual public** (`/vtual`, `/vtual/vtubers/:slug`). Verify-email uses `meta.authChrome` (not `guestOnly`) so a logged-in unverified user can land.

Legacy bare paths (`/users` → `/apps/users`, …) redirect so bookmarks do not 404. Catch-all 404 stays last, inside `AppLayout` — except public vTuber misses, which stay in `VkStr1Layout`.

### vTual public surface

Registered **before** `LEGACY_APP_ROOTS` (`vtual` is in that set — unmatched `/vtual/*` rewrites to `/apps/vtual/*`). Explicit children of `VkStr1Layout`:

| Path | Name | Data |
|------|------|------|
| `/vtual` | `vtual.public` | Landing: Swiper hero slider (`VkStr1HeroSlider`, 3 static promo slides: hub intro `hero.one.*`, claim profile `hero.two.*`, events `hero.three.*` — no live data) + post-hero band (`pt-12 md:pt-16`: platform stats `VkStr1PlatformStats`) + unified live directory (`#vtubers`, `VkStr1VtuberLiveDirectory` — self-fetching directory grid with search/pagination/skeleton/hover-prefetch plus a `liveItems` prop fed from `landing.live_now`; live cards show a danger-ringed avatar, LIVE badge, up to 3 stream rows + `+N more`) + content feed (`#contents`) + upcoming schedule events (`landing.schedule`, i18n fallback) + claim banner (`VkStr1ChannelBanner`). One `fetchLanding` call feeds the live directory/stats/events. `VkStr1LiveNow` / `VkStr1VtuberDirectory` are kept unchanged as references. Content cards (`VkStr1VideoCard`) carry a channel-avatar left column, a `:title` tooltip on the truncated title, an aspect-ratio thumbnail container with `absolute inset-0 object-cover`, and a permanent underlay placeholder on thumbnail error/null. |
| `/vtual/vtubers/:slug` | `vtual.public.vtuber` | Two-column guest profile via `useVtualPublic().fetchProfile(slug, page)` (banner overlay + gradient fallback, `VkStr1ProfileSidebar` with `VkStr1DetailsList` + `VkStr1ChannelCard`, featured live, **paginated** video grid with Prev/Next pager — `contents: { items, pagination }`, featured live only on page 1, styled empty-state). 404 renders **inside** the Valkivid3 layout, not `AppLayout`. |

`meta.public` + `meta.template: 'valkivid3'`. Isolation: `body.vk3-page`; `html.dark` via `str1-color-theme` (class `dark:` variant, not `prefers-color-scheme`), restored before app mount by `initTheme()` so dark-mode guests avoid a light FOUC; Tailwind utilities nested under `.vk3-root` (`@tailwind utilities` inside that selector — not `important(.vk3-root)`, which Tailwind 4.3 leaves as invalid `@media important(...)`); no preflight. **Scoping caveat:** nesting `@tailwind utilities` under `.vk3-root` makes every utility a descendant selector (`.vk3-root .bg-white`). Utilities placed ON the `.vk3-root` element itself never match — page background, root text color, and min-height must live in `@layer base` on `.vk3-root` / `.dark .vk3-root`, not as utility classes on the layout div. Leaving `/vtual` still `classList.remove('dark')`. AdminLTE keeps `data-bs-theme` / `lte-theme`. Theme toggle (sun/moon sprite icons, `fill-accent stroke-accent`) lives in `VkStr1Header` + `VkStr1MobileMenu`; track has explicit `bg-gray-150 border-gray-500/60 dark:bg-white/10 dark:border-white/50` so the pill stays visible on the always-dark header.

**Forked steel-slate palette (light/dark).** The str1 skin uses a steel-slate silver identity (not the original Valkivid violet/cyan). One accent value cannot serve both backgrounds, so every brand var forks per mode in `@layer base`: `--color-primary` / `--color-accent` are `#475569` (slate-600) light / `#94a3b8` (slate-400) dark; `--color-primary-500` (hover) is `#334155` (slate-700) light / `#cbd5e1` (slate-300) dark; root body text forks `#7a809b` (gray-500) light / `#a3a9c1` (gray) dark. A dedicated `--color-accent-500` (slate-700 light / slate-200 dark) backs accent hover because dark `--color-primary-500` == dark `--color-accent` (both slate-300), so `hover:bg-primary-500` on an accent button would be a no-op in dark mode. Do NOT collapse these to a single value — light mode reusing the dark accent/body gray was the 2.3:1 washout bug.

**Button conventions.** Because accent/primary fork dark-in-light / bright-in-dark, button labels fork the opposite way: `bg-accent` and `bg-primary` CTAs/badges/avatars use `text-white dark:text-gray-900`. Hover forks per variant: primary uses `hover:bg-primary-500`, accent uses `hover:bg-accent-500`. Outline (ring) buttons no longer invert white-to-gray-900 on hover (17.87:1); they soft-fill `hover:bg-slate-500` light / `dark:hover:bg-slate-400` dark, keeping base ring + label. Any element that fills with `hover:bg-accent` / `hover:bg-primary` must fork its label `hover:text-white dark:hover:text-gray-900` (light fill is a dark slate so the label goes white; dark fill is a bright slate so the label goes gray-900) — a bare `hover:text-gray-900` on a dark hover fill was the pager/ChannelCard bug.

**Unlayered `!important` wrappers in `valkivid3.css`.** Bootstrap/AdminLTE ship unlayered utilities that load after the chunk and outrank all layered Tailwind regardless of specificity. Scoped unlayered `!important` wrappers exist for `.bg-primary`, `hover:bg-primary-500`, `hover:bg-accent(-500)`, `hover:bg-slate-400/500`, `dark:text-gray-900`, `hover:text-white`, `dark:hover:text-gray-900`, and `dark:bg-gray-700`. Rule: any `dark:bg-*` fork on a class that has a Bootstrap `!important` counterpart (`.bg-white`, `.bg-black`, `.bg-primary`, `.bg-danger`, ...) needs a scoped `!important` wrapper — otherwise the layered fork loses and a white bg stays white in dark mode, the ChannelCard/MobileMenu white-on-white bug. Non-`!important` Bootstrap names with no counterpart (`bg-gray-150`, `bg-gray-800`) never collide.

**Adaptive hero controls.** The str1 hero (Swiper core + `Autoplay`/`EffectFade`/`Navigation`/`Pagination`, fade, 3 slides, staggered entrance with `vv-slide-played`, vertical bar pagination `.vv-hero-swiper-pagination`, width `1320px`) forks its nav arrows + pagination per ACTIVE-SLIDE tone at ≥992px, not per global mode — slides 2/3 stay `bg-gray-900` in both modes, so a light-mode-only fork would hide the arrow on those dark slides. `VkStr1HeroSlider` resolves the active slide's effective tone into `data-tone` on the hero container (`light` only for slide-1, `dark` otherwise) via Swiper `slideChange`. `valkivid3.css` then forks: arrows default white and only go `gray-900` under `html:not(.dark) .vk3-root [data-tone='light']` (the `html:` prefix is required — a bare `:not(.dark)` matches any non-dark ancestor like body); the pagination track goes bright (`white/25`) on dark slides vs darker (`gray-500/35`) on slide-1 light; the active bullet re-asserts the solid accent on slide-1 light but uses slate-300 on dark slides in light mode. All unlayered scoped `!important`. The controls hang `-right-16` (64px outside) only at `2xl` so they don't dangle off-canvas below 1360px; do NOT add `2xl:px-16` to compensate (that shrinks the hero). Below `xl` the `bg-gray-900` chip keeps the arrow white regardless.

**FROZEN — HomeView (`/vtual` landing).** `VkStr1VtualHomeView.vue` and its exclusive components (`VkStr1HeroSlider`, `VkStr1LiveNow`, `VkStr1PlatformStats`, `VkStr1VtuberDirectory`, `VkStr1VtuberLiveDirectory`, `VkStr1ContentFeed`, `VkStr1SectionEvents`, `VkStr1SectionCountdown`, `VkStr1ChannelBanner`) are frozen — declared production-ready. Any plan that edits them requires explicit user permission first and MUST be diffed against the visual baseline afterward. Baseline lives in `src/valkivid3/.baseline/`: `home-light.jpg` + `home-dark.jpg` (full-page 1440px screenshots) and `home-baseline.json` (computed styles of key elements). Re-capture only after an approved, verified change.

**Error-panel pattern.** Async fetch failures render a retryable panel, not bare `text-danger` text — wrapper `bg-gray-150 ring-1 ring-gray-500/20 dark:bg-gray-800 px-6 py-10 text-center`, a `text-gray-600 dark:text-gray-300` message, and a `bg-accent hover:bg-accent-500 text-white dark:text-gray-900` retry button that re-invokes the loader. Used by `VkStr1VtualHomeView` (landing) and `VkStr1VtuberShowView` (profile); i18n key `<scope>.retry`.

See SPA README.

Claim stays authenticated at `/apps/vtual/vtubers/:slug/claim`. Public page only advertises it: guest + `is_claimable` → `/login?redirect=/apps/vtual/vtubers/{slug}/claim`; authed + `is_claimable` → the claim route. No guest `POST /claims`. Tokens stay auth-only.

API / `SpaPublicUrl` `profile_url` / `claim_process_url` mint **singular** `/apps/vtual/vtuber/{slug}` (hub is the param-less `/apps/vtual/vtuber`). SPA canonical show/claim stay **plural** `/apps/vtual/vtubers/:slug` so they do not collide with that hub. Singular `/apps/vtual/vtuber/:slug` and `/claim` redirect onto the plural named routes. Singular `/vtual/vtuber/:slug` (no `/apps`) also redirects into `/apps` — it must not sit in `VkStr1Layout`. Public guest profile remains `/vtual/vtubers/:slug` only. Claim CTA on the auth show page is a named `router-link`. UUID in the path is replaced with the canonical slug after fetch (server 301 equivalent, client-side).

Login **and** register honor `?redirect=` through `src/lib/safeRedirect.ts` (`safeInternalRedirect`): must start with `/`, reject `//`, `\`, `://`, `/http:`. Cross-links between login and register preserve the query. Default fallback `/apps/dashboard`.

### vTual orphan staff admin

Authenticated `/apps/vtual/orphans` mimics Blade staff seed (appearance + behavior + intent). Vue `DataTable` + `display_mode=query` — do not port Yajra. Public `/vtual` is a different surface.

| Step | SPA contract |
|------|----------------|
| List | Subtitle `web.vtual-orphans.subtitle`. Query-mode `actions[]`: `edit` → `vtual.orphans.edit`; `delete` archive → `DELETE /api/v1/vtual/creators/{id}` after `confirmDialog` using API confirm (`web/vtual-orphans.datatable.confirm_delete`). SPA maps keys; refetch the list. Claimed rows already omitted by `GET creators/orphans`. |
| Create | `POST /api/v1/vtual/creators/orphan` → `vtual.orphans.edit.view` with the new `id`. An orphan without channels is not claimable in practice. |
| Save | `PATCH /api/v1/vtual/creators/{id}` stays on edit. Cancel/back go to index. |
| Channels | Staff `POST /api/v1/vtual/creators/{id}/channels` is `registerPendingForStaff` (pending channel, in-place append, skip duplicate `id`). Not a claim — do not treat `attempt_count` as `channel_claim_started` on this page. YouTube/Twitch placeholders + 5-col table (avatar / platform / profile_input\|\|external_id / handle / status). |

Create and edit share `VtualOrphanEditView`. `AppLayout` is a bare `<router-view />` (no `:key`), so create→edit does **not** remount. Watch `route.params.id` / `isEdit`: reset the form, hydrate on edit, skip hydrate on create.

### vTual owned profile manage

Authenticated `/apps/vtual/profiles` (`VtualProfilesView`) lists owned creators. Query-mode `actions[]`: `edit` → SPA `vtual.profiles.show` + `id`; `view` → `href` `profile_url`, `_blank`. SPA maps those keys into `RowActions`. Row-click follows the `edit` nav action (`/apps/vtual/profiles/:id`).

Authenticated `/apps/vtual/profiles/:id` (`VtualProfileShowView`). Full-width cards (no `col-lg-8` + sidebar). Identity is one `Card` (stacked meta fields). Channels are a **separate** `Card` (help + 3-column add form + 5-column table: avatar / platform / `profile_input||external_id` / handle / status, plus an Actions column). As an owner/editor **member** (including Admin who is also a vTuber on that profile), submit starts a **channel claim** — the token and verify / Discord / cancel chrome sit **above** the table (`attempt_count` in the response). Staff without membership still queues a pending channel row. Leftover pending rows use **Delete** (`DELETE /api/v1/vtual/channels/{id}`); bound channels use Archive; private Discord-discovered channels use Promote. Archived channels are a second table in the same section with Restore. External links and merge are separate full-width cards below. There is **no** per-channel snapshot Refresh button. Display name, avatar, banner, description, and YouTube subscriber count update via scheduled `vtual:flush-channel-pool --resync` (hourly; flush also runs every minute when the pool already has pending ids). `POST /api/v1/vtual/channels/{id}/refresh` remains for ops/API only — `useVtual` does not wrap it.

### Settings menu names

Every Settings screen is ported (`SettingsFormView` on `/apps/settings/:group`). Fields use `FormInput` / `FormSelect` / `FormTextarea` / `FormCheckbox` (`switch`); registry key and source sit in the field hint, not inside `form-check`. `PUT admin/settings/{group}` sends **underscore** field names (`platform_jwt_ttl`), not the dotted GET keys — Laravel treats dots as nested paths, which used to 422 `settings.empty_payload` for every save. The FormRequest aliases a dotted body onto the underscore names when needed. MenuSeeder children are `settings.platform`, `settings.file-hosting`, `settings.microstock`, `settings.vtual`. The SPA registers those **as named routes** (`settings.platform.view`, …) pointing at the same SFC, **before** the parameterized `settings.group.view` (used for `identity` and any future registry group). Sidebar `hasRoute(node.route + '.view')` is what makes the click work — a redirect-only alias would lose `isActive`. Do **not** add `identity` to MenuSeeder.

Static vTual credential probe (`/apps/vtual/platform-credentials/probe`) is registered **before** `/apps/vtual/platform-credentials/:platformId` so `/probe` is not captured as a platform id.

### 404 catch-all

Last route: `path: '/:pathMatch(.*)*'` → `NotFoundView` inside `AppLayout`, `meta: { requiresAuth: true, titleKey: 'errors.not_found' }`. This is an admin SPA — unknown URLs for guests already hit `beforeEach` → login with `redirect`. After login they see 404 **inside** the sidebar, not a naked page. Catch-all stays below `/s/f/:id`, `/verify-email/:id/:hash`, `/payment/return`, `/vtual`, `/vtual/vtubers/:slug`, Blade-compat `/vtual/vtuber/:slug` (redirects into `/apps`), and the legacy `/users` → `/apps/users` redirects. A missing public vTuber slug is **not** this catch-all — `VkStr1VtuberShowView` renders an in-layout 404.

### Valkivid3 dynamic navigation

The Valkivid3 public template renders a **dynamic** navigation tree (no hardcoded links) sourced from the same backend as the admin shell. `useVkNavigation` (`src/valkivid3/composables/useVkNavigation.ts`) fetches `GET navigation/menus?layout=vtual` with a 60s SWR-style in-tab cache (stale data renders instantly, background revalidate on miss). `VkStr1Header` renders flat top-level nodes as `router-link`s; a parent with children becomes a dropdown. `VkStr1MobileMenu` renders the same tree with recursive expand/collapse accordions. `VkStr1Footer` renders the leaf nodes as a flat link list. `resolveRoute(node)` maps `node.route` (`vtual.public` → `vtual.public.view`) and appends `node.hash` (`#vtubers` / `#contents`) so in-page anchors ride on `router.scrollBehavior`. Active-state marking uses the hash-aware `isVkNavActive` helper: because Home/Directory/Contents share the `vtual.public` route, a node with a `hash` is marked active only when `route.hash` matches, and a node without one only when the route has no hash — so the marker bar (`after:scale-x-100`) + `text-accent` + `aria-current="page"` lands on exactly one top-level item at a time. Guest access works because the index endpoint is `jwt.optional`. The admin shell (`useNavigation`) was refactored from hardcoded `sidebar`/`navbar` refs to a map-based generic (`Record<string, NavigationTreeNode[]>`) so the same composable backs any layout; `sidebarTree`/`navbarTree` remain as computed aliases for existing consumers.

### Mimic doctrine

Mimic is **appearance + behavior + intent**, done well and safely — not a pixel clone of Blade DOM, routes, or libraries.

Monolith is API-first. One list endpoint, two presentations via `display_mode`:

- `query` — Eloquent-shaped JSON (SPA `DataTable.vue` / `useResource.ts`). Optional `page` / `per_page` / `search` → `{ items, pagination }`; omit them → legacy flat `T[]`.
- `datatable` — same rows, Yajra-shaped. Built for the removed Blade DataTables and **currently unused by any client** — see [platform/list-display-modes.md](../platform/list-display-modes.md).

The SPA fetches and parses **as-is**, always with `display_mode=query`. Do not port Yajra JS. Do not invent a second list API.

### Accepted divergences (A / B / C)

These are the safe zone. Keep the current approaches.

**A - Platform.** Cross-site HttpOnly `__Host-spa_jwt` on the API origin (Laravel-minted; JS does not store the JWT). The Blade-era `auth_token` cookie bridge on `APP_URL` is gone — there is one browser session cookie now. Direct Monolith `/api/v1` traffic uses the cross-site cookie (or Bearer); `POST /api/v1/broadcasting/auth` goes direct, hCaptcha gated by `VITE_HCAPTCHA_SITE_KEY`, duplicate vue-i18n JSON, CF Workers static origin, breadcrumb config map, guest cannot read `system_settings`, budget controls (`/api/v1/batch`, JWT TTL 240).

**B — Intentional consumer choices.** Vue `DataTable` + `display_mode=query` (server page of 10 when paging params are sent). Select2 only for server-side lookup (`MenuEditView` AJAX parent/roles/users). Navigation via `GET navigation/menus?layout={sidebar|navbar|vtual}`; admin layout select options come from `GET navigation/menus/layouts`. File Hosting three Vue routes instead of the removed single-island shell; team as a page rather than an offcanvas; listing-only move picker; rate editor re-fetch instead of full reload.

**C — Gray (optional later, not a product blocker).** Live-reload on 2 of 7 catalogs (`NotificationType::tableId()` only emits those two), Laravel plural pipe hack. Query-mode `actions[]` is the live row-button source — closed (see [list-display-modes.md](../platform/list-display-modes.md#query-mode-actions-live-spa-contract)). Hierarchy still 403s from the API on mutate.

Do not treat a read-only show-page microstock card as “review is done.” Review is `FhMicrostockReviewModal` consuming `GET|PATCH microstock/assets/{file}/metadata`.

## Realtime (Laravel Echo + Pusher)

The SPA supports realtime updates via Laravel Echo and Pusher. There is no polling fallback -- Echo/Pusher auto-reconnect is the recovery path.

- **Composable**: `src/composables/useEcho.ts` — singleton `Echo<'pusher'>` (`EchoOptions<'pusher'>`), reads Pusher config from `VITE_PUSHER_*` env vars. Subscribe via `getEcho()`; `useEcho()` exposes `init` / `disconnect` / `connected` only. Connection flag uses `connector.onConnectionChange` / `connectionStatus()`. Laravel authenticates the API-origin HttpOnly cookie directly; the Worker does not attach JWTs. Private-channel auth, CSRF mint before `initEcho()`, and 419 retry: [Auth](#auth). `AppNavbar` starts Echo **after** `hydrateShell()` so `POST /broadcasting/auth` does not race `POST /batch`. Post-login cookie-settle: [Cross-tab auth](#cross-tab-auth-multi-tab-session-sync).
- **Notification bell**: `src/components/AppNavbar.vue` -- inits Echo on mount, subscribes to `App.Models.User.{id}` private channel for `.activity-notification.received` events. On each WS event, the bell also calls `fetchList(5)` to refresh its own recent-notification dropdown content (capped at 5 -- the full list is fetched on dropdown open or navigation to `/notifications`).
- **File Hosting**: `src/composables/file-hosting/useFileHostingRealtime.ts` — singleton channel manager for `file-hosting.user.{userId}` (`.listing.changed`, `.quota.changed`, `.members.changed`, `.activity.appended`, `.collection.analysis.progress`, `.asset.metadata.progress`). Multiple views register handlers; the channel is subscribed once and torn down when the last handler unregisters. See [Pattern B](#pattern-b-direct-channel-subscription) below.
- **Microstock**: collection mutations (`lockCollection`, `analyzeCollection`, `fetchCollectionStatus`, `exportCollection`, `downloadCollectionCsv`, asset metadata GET/PATCH, `analyzeAsset`, `fetchSlots`, `createWorkspace`) live in `useMicrostock.ts` and are wired from `FileHostingView` / `FhMicrostockReviewModal`. Realtime subscriptions go through `useFileHostingRealtime.ts` (same channel, Microstock events).
- **vTual claim**: `src/views/vtual/VtualVtuberClaimView.vue` -- subscribes to `vtual.claim.{claimId}` private channel for `.claim.progress` events. If Echo is unavailable, a pending claim gets no live update until manual reload.
- **vTual platform credentials**: `useVtualRealtime.ts` -- `private-vtual.platform-credentials` / `.platform.credential.changed`. Platform list silent-refetches when `platform_id` matches; index view refreshes active/pending counts. Acting tab still applies `data.credential` from `POST .../test` so status updates without waiting on the socket.

#### Cross-tab notification read-state sync

`useNotifications.startRealtime()` opens a per-user `BroadcastChannel('activity-notifications.{userId}')` so mark-as-read actions propagate across tabs without each tab needing an extra HTTP request. Same contract as [notification — Read-state UI and tab synchronization](../domains/notification/README.md#read-state-ui-and-tab-synchronization).

Messages:

| Message | Trigger | Effect on receiving tab |
|---------|---------|--------------------------|
| `{ type: 'read-all' }` | `markAllRead()` | Zero `unreadCount`, mark all `items` as read |
| `{ type: 'read', id }` | `markAsRead(id)` | Decrement `unreadCount`, mark matching item as read |

If `BroadcastChannel` is unavailable, the acting tab still updates immediately; other tabs reconcile on their next WS event or reconnect.

### Env vars

Echo reads Pusher config from Vite env vars. The SPA **requires a `.env` file** in the project root (`monolith-vue-workers/.env`) — without it, `VITE_PUSHER_APP_KEY` is `undefined`, `readConfig()` returns `null`, and Echo never initializes. The websocket visible in the browser network tab would then be Vite HMR only, not Pusher.

| Var | Purpose |
|-----|---------|
| `MONOLITH_API_ORIGIN` | Legacy local compatibility variable. Current Worker does not read it. **Not** bundled into the browser. |
| `VITE_API_BASE_URL` | Set to the **Laravel API origin plus `/api/v1`**; every SPA API call goes direct. Local: `.env.local` wins over `.env`. Deployed builds: the tracked `.env.production` / `.env.sandbox` are authoritative — during `vite build --mode <env>` the mode file wins over `.env.local` (verified on Vite 8), so any machine bakes the same origin. Empty only for local same-origin development. |
| `VITE_PUSHER_APP_KEY` | Pusher app key (required for Echo — must match Monolith `PUSHER_APP_KEY`) |
| `VITE_PUSHER_APP_CLUSTER` | Pusher cluster (default `mt1`) |
| `VITE_PUSHER_HOST` | Self-hosted/Reverb host (omit for Pusher-hosted) |
| `VITE_PUSHER_PORT` | WebSocket port (default `443`) |
| `VITE_PUSHER_SCHEME` | `https` or `http` (default `https`) |
| `VITE_HCAPTCHA_SITE_KEY` | hCaptcha site key. When set, guest auth forms run the invisible widget and POST `h-captcha-response`. When unset, the widget is omitted (API must also have hCaptcha off or login 422s) |

Discord OAuth return is **not** a Vite var. Set `SPA_URL` on the Monolith API host to this SPA's public origin (Vite/Workers URL). Empty `SPA_URL` → `GET auth/discord/authorize-url` 503.

Values must mirror Monolith's `.env` `PUSHER_*` literals (and `HCAPTCHA_SITEKEY` when hCaptcha is on). `.env.local` overrides `.env` for every `VITE_*` key in local dev (that is why an old absolute `VITE_API_BASE_URL` survived Vite restarts); during `vite build --mode production|sandbox` the tracked mode file wins over `.env.local` (verified on Vite 8), so deploy builds stay reproducible. Worker origin serves static assets only; API origin is the bundled `VITE_API_BASE_URL`.

When `VITE_PUSHER_APP_KEY` is unset, Echo is not initialized and realtime-dependent features simply receive no live updates (manual reload required).

### Self-hosted websocket (Sockudo)

Production realtime targets **Sockudo** (Pusher-compatible WS). No code change is needed on either side — the SPA (`useEcho.ts`, broadcaster `pusher`) and Monolith (`pusher/pusher-php-server`, `BROADCAST_CONNECTION=pusher`) speak the Pusher protocol. Migration is env-only: point `PUSHER_HOST` / `PUSHER_PORT` / `PUSHER_SCHEME` (and the `VITE_PUSHER_*` mirrors) at the Sockudo host. Development stays on Pusher-hosted (`PUSHER_HOST` empty → `ws-{cluster}.pusher.com`).

### Auth

Echo private channel auth uses a dedicated API broadcast auth endpoint: `POST /api/v1/broadcasting/auth` (direct to the API origin, cross-site cookie + `X-CSRF-Token`). The SPA authorizer goes through `client.ts` (`post('broadcasting/auth')`), not a private `fetch`, so a post-login CSRF cookie/header race (419) retries once like `/batch` instead of showing as a CORS error. `AppNavbar` awaits `hydrateShell()` (and `ensureCsrf()` only if the nonce is still missing) **before** `initEcho()`, so the first subscribe does not POST with an empty nonce and does not overlap the shell batch cookie write.

**Why a dedicated API route?** Laravel's default `/broadcasting/auth` (auto-registered by `withRouting(channels:...)` in `bootstrap/app.php`) lives under the `web` middleware group — it requires CSRF + session, which is incompatible with a Bearer-token SPA. The API route (`routes/api/v1/broadcasting.php`) mirrors the same `Broadcast::auth($request)` call but under `api` middleware (JWT guard):

```php
Route::post('broadcasting/auth', function (Request $request) {
    return Broadcast::auth($request);
})->middleware(['auth:api'])->name('api.broadcasting.auth');
```

This delegates to the same channel authorization callbacks in `routes/channels.php` — no duplication of channel logic. Laravel's framework-registered web `/broadcasting/auth` route still exists but has no browser client; the SPA uses the API route above.

## Cross-tab auth (multi-tab session sync)

The SPA keeps a logged-in session alive across browser tabs without forcing re-login.

1. **HttpOnly `__Host-spa_jwt`** - the browser shares the cookie across tabs on the API origin. JS does not persist the JWT (`mw_auth` leftover keys are cleared on hydrate). `remember` only affects `Max-Age` via API `expires_in`.
2. **`GET auth/me` on hydrate** — one GET per cold-boot (`hydrateShell` in `beforeEach`); the guard does not `fetchMe()` again on that same navigation. Each tab learns `user` from the cookie, not from `localStorage`.
3. **`BroadcastChannel('monolith-auth')` coordinated refresh** — when multiple tabs' refresh timers fire near-simultaneously, only one tab performs `POST auth/refresh`; the others wait for `refresh-done`. This prevents the refresh race where two tabs each call refresh, one blacklists the other's token, and both tabs lose. Messages: `refresh-request`, `refresh-response`, `refresh-done` (`ok` + `user` / expiry — **no JWT**), `logout`.

`clearAuth()` broadcasts `logout` so other tabs drop in-memory `user`. It also resets the full shell state for a clean re-login cycle: `resetShellHydration()` (via dynamic import) plus `resetRouterHydration()` (the route guard's one-shot cold-boot flag), so the next login re-bootstraps instead of reusing a stale `hydrated` flag. The SPA sends `POST auth/logout` directly to Laravel; Laravel invalidates the session and clears the API-origin `__Host-spa_jwt` cookie. The Worker does not receive or expire this cookie.

**Post-login cookie-settle serialization.** After a successful login, register, or 2FA verify the SPA (1) mints a fresh CSRF nonce (`refreshCsrf()`) now that `__Host-spa_jwt` is in the cookie jar, then (2) confirms the cookie with `confirmSession()` (`GET auth/me`, retry once) before navigating (`LoginView.handleSubmit`, `RegisterView.handleSubmit`, `TwoFactorChallengeView.handleSubmit`). A failed confirm does **not** navigate. Auth-session POSTs (`login` / `register` / `refresh` / `logout` / forgot-reset / 2FA login) do **not** call `ensureCsrf()` — the server skips CSRF there, and fetching a nonce before the JWT exists creates a duplicate `__Host-spa_csrf` that races the first authenticated `POST /batch` and `POST /broadcasting/auth`. `applyAuth` calls `markSessionIssued()` so `client.ts` treats a 401 **or** `fetch` TypeError in the next few seconds as cookie-settle: backoff-retry the original URI, wait for any CSRF `Set-Cookie` (`whenCsrfCookieQuiet`), **do not** `POST /auth/refresh` (no cookie → 401 “token not provided”, and `unauthorizedHandler` must not `clearAuth()` in that window). `GET auth/me` and `GET navigation/menus` 401 never refresh. Canonical chrome order after navigate: one `POST /batch` (Navbar starts / Layout joins) → seed stores → fallback GET only if unseeded **and** not terminal → **then** Echo. `AppLayout` must not `GET navigation/menus` or `startNavRealtime` in parallel with that batch. Echo `broadcasting/auth` CSRF/419 retry: [Auth](#auth). Guest cold-boot is one `GET auth/me` (`hydrateShell` in `beforeEach`); the guard does **not** `fetchMe()` again on that same navigation (opening `/` or a protected URL as a guest used to fire two 401s before redirecting to login). Re-probe with `fetchMe()` only on a later `requiresAuth` navigation where in-memory `user` is still null (cookie from another tab). Post-login settle is `confirmSession` before navigate, not a duplicate guard GET. A guest 401 is not treated as session death: `unauthorizedHandler` only `clearAuth()` when in-memory `user` was set **and** the session is not settling.

### Cross-tab CSRF nonce sync

The double-submit `__Host-spa_csrf` nonce has the same shared-cookie / per-tab-memory split as the JWT, and needs its own sync. Each tab caches the nonce in module memory (`src/api/csrf.ts`), but the cookie is shared per origin. Without coordination, a tab that fetches a fresh nonce rotates the shared cookie and strands every other tab on its stale in-memory value — their next mutation sends `header=old` vs `cookie=new` and gets a `419`.

`src/api/csrf.ts` therefore mirrors the JWT pattern with a `BroadcastChannel('monolith-csrf')`: whichever tab fetches a nonce (`ensureCsrf` / `refreshCsrf`) broadcasts it (`{ type: 'csrf', token }`); every other tab adopts it on `onmessage`. All CSRF consumers (`client.ts` mutations, `useEcho.ts` `authorizeBroadcastChannel`) read through `csrf.ts`, so one channel keeps every tab on the same nonce. Covered by `tests/csrf.test.ts` (fetch+broadcast, adoption, malformed-message guard, single-flight, header application, `whenCsrfCookieQuiet`), `tests/client.test.ts` (auth-session POSTs skip the nonce fetch; `POST /batch` and `POST /broadcasting/auth` 419 refresh and retry; `GET auth/me` and `GET navigation/menus` 401 do not `POST /auth/refresh`; settle-window 401 and TypeError retry the original URI; CSRF cookie-write gate), `tests/shellHydrate.test.ts` (settle batch retry, terminal sub-entry blocks GET fallback), and `tests/echo.test.ts` (authorizer calls `client.post('broadcasting/auth')`).

On logout the nonce dies with the session. `clearCsrf()` drops the in-memory copy and broadcasts `{ type: 'csrf-clear' }` so every tab stops sending the dead nonce; `useAuth.clearAuth()` calls it, and each tab also clears on the `monolith-auth` `logout` message.

### Backend `remember` preservation

`AuthService::refreshFromToken` preserves the `remember` flag across refresh so a remember-me session does not silently downgrade to a short-TTL session on every refresh. The flag is resolved with priority:

1. `remember` in the refresh request body (SPA opt-in per refresh).
2. `rm` custom claim on the original token (set at login, persisted across refreshes via `jwt.persistent_claims`).
3. Fallback `false`.

## Live-update patterns

The SPA uses two distinct live-update mechanisms, implementing the same backend broadcast/API contract:

```mermaid
flowchart TB
    subgraph Bell["Pattern A: Bell-driven listing reload"]
        Mut["Backend mutation<br>(create/update/delete)"] -->|dispatches| Event["UserActivityNotificationReceived<br>on App.Models.User.{viewerId}"]
        Event -->|payload: table_id| Bell["useNotifications.startRealtime<br>AppNavbar.vue"]
        Bell -->|triggerListingReload| Registry["useListingReload registry"]
        Registry -->|calls fn| View["Listing view<br>fetchList()"]
    end
    subgraph Direct["Pattern B: Direct channel subscription"]
        DomainMut["Domain mutation<br>(file upload, payment sync)"] -->|dispatches| DomainEvent["Domain broadcast event<br>e.g. InvoiceStatusChanged"]
        DomainEvent -->|private channel| Echo["useEcho.private(channel)"]
        Echo -->|listen event| Handler["View-specific handler<br>patches reactive state"]
    end
```

### Pattern A: Bell-driven listing reload

Backend mutations (user create/update/delete, inference model unregistered, etc.) dispatch `UserActivityNotificationReceived` on each recipient's private channel `App.Models.User.{viewerId}`. The payload carries `{ type, table_id, unread_count }` — `table_id` identifies which listing should refetch (set by `NotificationType::tableId()` on the backend, e.g. `'users-table'`).

1. The notification bell (`useNotifications.startRealtime()` in `AppNavbar.vue`) owns the single subscription to `App.Models.User.{viewerId}` and listens for `.activity-notification.received`.
2. On receipt, the bell forwards `table_id` to `triggerListingReload(tableId)` from `useListingReload.ts`.
3. Any listing view that registered for that `table_id` gets its refetch callback invoked.
4. The bell also calls `fetchList(5)` to refresh its own recent-notification dropdown content (capped at 5 — the full list is fetched on dropdown open or navigation to `/notifications`).

**Registry:** `src/composables/useListingReload.ts` — module-level singleton mapping `tableId -> Set<() => void>`. No reactive state.

**Reusable hook:** `src/composables/useListingLiveUpdate.ts` -- registers a listing's reload fn on the Echo bus in one call:

```ts
import { useListingLiveUpdate } from '@/composables/useListingLiveUpdate'

// Inside <script setup>:
useListingLiveUpdate('users-table', () => fetchList())
```

This registers the listing's reload fn on mount and unregisters on unmount. Live updates arrive only over the websocket; there is no polling fallback.

**Active listing views with bell-driven live-update:**

| View | table_id | Backend NotificationType |
|------|----------|--------------------------|
| `UsersView.vue` | `users-table` | `UserCreated`, `UserUpdated`, `UserDeleted` |
| `InferenceModelCardsView.vue` | `inference-model-cards-table` | `InferenceUnregisteredModel` |

### Pattern B: Direct channel subscription

Domain-specific realtime uses dedicated private channels with direct Echo subscriptions. Each domain has its own composable managing the subscription lifecycle.

#### File Hosting — `useFileHostingRealtime.ts`

Singleton channel manager for `private-file-hosting.user.{userId}`. Multiple views register handlers with the same singleton; the channel is subscribed once and torn down when the last handler unregisters.

```ts
import { useFileHostingRealtime } from '@/composables/file-hosting/useFileHostingRealtime'

const { subscribe, unsubscribe } = useFileHostingRealtime()
const handlers = {
  onListingChanged: () => loadBrowse(),
  onQuotaChanged: () => loadQuota(),
  onMembersChanged: () => loadMembers(),
  onActivityAppended: () => loadActivity(),
  onCollectionAnalysisProgress: () => loadBrowse(),
  onAssetMetadataProgress: () => loadBrowse(),
}

onMounted(() => subscribe(handlers))
onUnmounted(() => unsubscribe(handlers))
```

**Events handled:**

| Event | UI update |
|------|-----------|
| `.listing.changed` | Refetch browse/trash/file listing |
| `.quota.changed` | Refetch quota bar |
| `.members.changed` | Refetch workspace members |
| `.activity.appended` | Refetch workspace activity feed |
| `.collection.analysis.progress` | Refetch browse (collection status) |
| `.asset.metadata.progress` | Refetch browse (metadata status) |

**Views wired:** `FileHostingView.vue`, `FileHostingTrashView.vue`, `FileHostingShowView.vue`, `FileHostingWorkspacesView.vue`, `FileHostingMicrostockUsageView.vue`.

**Why a singleton?** `useFileHosting.ts` and `useMicrostock.ts` both previously had their own `subscribeRealtime()` methods on the same channel — they would clobber each other's subscription via a shared `activeChannel` variable. The singleton eliminates this: one channel, multiple handler sets, single subscription.

#### Payments checkout — `usePaymentRealtime.ts`

Two channel subscriptions for the checkout experience:

```ts
import { usePaymentRealtime } from '@/composables/payment-gateway/usePaymentRealtime'

const { subscribeInvoice, unsubscribeInvoice, subscribeRoutingCatalog, unsubscribeRoutingCatalog } = usePaymentRealtime()

onMounted(() => {
  subscribeInvoice(invoiceId, handleStatusChanged)
  subscribeRoutingCatalog(refreshChannels)
})
onUnmounted(() => {
  unsubscribeInvoice()
  unsubscribeRoutingCatalog()
})
```

| Channel | Event | UI update |
|---------|-------|-----------|
| `private-payments.invoice.{invoiceId}` | `.invoice.status.changed` | Patch status badge, steps, sidebar; emit `status-updated` + `invoice-updated` |
| `private-payments.routing` | `.routing.catalog.changed` | Re-fetch checkout channels, update picker |

Wired into `CheckoutPanel.vue` (Echo-driven, no polling). Sync-status provider is parsed from `checkoutResult.gateway` or `invoice.payment_gateway.name`. If neither is present, skip the call -- do **not** default to `midtrans`. Server-side, `InvoiceCheckoutGatewayResolver` can fall back to `PaymentGateway::defaultForInvoice()`; the SPA has no equivalent and must not guess.

#### vTual claim — inline in `VtualVtuberClaimView.vue`

Subscribes to `private-vtual.claim.{claimId}` for `.claim.progress` events. Handles phase transitions (`started`, `verify_queued`, `verify_processing`, `verify_failed`, `verified`, `cancelled`, `max_attempts_blocked`). No polling fallback; Echo only.

#### vTual platform credentials — `useVtualRealtime.ts`

Staff catalog channel for platform-credential status. Same singleton pattern as Inference (`handlerSet` + one `private` subscribe).

```ts
import { useVtualRealtime } from '@/composables/vtual/useVtualRealtime'

const { subscribe, unsubscribe } = useVtualRealtime()
const handlers = {
  onCredentialChanged: (payload) => {
    if (payload.platform_id === platformId) void loadCredentials(true)
  },
}

onMounted(() => subscribe(handlers))
onUnmounted(() => unsubscribe(handlers))
```

| Event | UI update |
|------|-----------|
| `.platform.credential.changed` | Silent refetch of the matching platform credential list; index view refreshes active/pending counts |

**Views wired:** `VtualPlatformCredentialPlatformView.vue`, `VtualPlatformCredentialsView.vue`. Both also register `useListingLiveUpdate` for Echo reconnect catch-up. Payload is `platform_id`, `credential_id`, `action` (`created` \| `updated` \| `tested` \| `test_failed` \| `revoked` \| `deleted`), `status` (null on delete). No secrets on the wire.

### Channel/event reference

| Domain | Channel | Event | Composable |
|--------|---------|-------|------------|
| Notification bell | `App.Models.User.{userId}` | `.activity-notification.received` | `useNotifications.ts` |
| File Hosting | `file-hosting.user.{userId}` | `.listing.changed`, `.quota.changed`, `.members.changed`, `.activity.appended`, `.collection.analysis.progress`, `.asset.metadata.progress` | `useFileHostingRealtime.ts` |
| Payments invoice | `payments.invoice.{invoiceId}` | `.invoice.status.changed` | `usePaymentRealtime.ts` |
| Payments routing | `payments.routing` | `.routing.catalog.changed` | `usePaymentRealtime.ts` |
| vTual claim | `vtual.claim.{claimId}` | `.claim.progress` | `VtualVtuberClaimView.vue` (inline) |
| vTual credentials | `vtual.platform-credentials` | `.platform.credential.changed` | `useVtualRealtime.ts` |

### Realtime connection convention

All realtime surfaces follow the same websocket-only pattern:

1. On mount, attempt Echo subscription.
2. Live updates arrive only over the websocket. Echo/Pusher auto-reconnect is the recovery path; there is no timer-based polling fallback.
3. On unmount, unsubscribe from Echo.

## Payment UI components

Checkout UI in the SPA (`CheckoutPanel.vue`, `PaymentStatusHero.vue`, etc.) implements the same backend invoice/realtime contract:

- **PaymentStatusHero** (`src/components/payment-gateway/PaymentStatusHero.vue`) — hero banner with eyebrow, title, amount, and status badges. Uses `ois-hero` CSS classes.
- **PaymentSteps** (`src/components/payment-gateway/PaymentSteps.vue`) — 3-step progress indicator (Choose method → Pay → Confirmation). Uses `ois-steps` CSS classes.
- **CheckoutPanel** (`src/components/payment-gateway/CheckoutPanel.vue`) — invoice checkout with payment channel selection. Sync-status uses the gateway from the checkout payload or the invoice resource; unknown provider → skip. On mount the panel restores an in-flight checkout from `invoice.checkout_session` (typed `CheckoutResult` in `src/api/types.ts`) into its local `checkoutResult` — instructions and the Confirm-paid button render only from that state — so a hard reload keeps the chosen method's VA/QRIS/ewallet instructions on screen; without a session it falls back to the plain channel picker.

CSS for these components is in `src/assets/order-invoice-show.css`, ported from Monolith's `resources/css/payments/order-invoice-show.css`. Uses Bootstrap 5 CSS variables for dark mode compatibility.

## Service checkout flow

`ServiceShowView.vue` includes a checkout form that mirrors Monolith's `services/show.blade.php`:

- Currency select (populated from service prices)
- Quantity input (shown only for addon plan kind); `:max` from `GET services/options` `purchase_max_quantity` (last-resort 99 only if that call fails)
- Start checkout button (gated by `purchase service` or `initiate payment` permission)
- On submit, calls `POST services/{id}/checkout` API and redirects to the purchase detail page

`ServicePurchaseShowView.vue` renders the full purchase detail with:
- `PaymentStatusHero` with purchase status and payment status badges
- `PaymentSteps` indicator (current step based on invoice status)
- Grace banner alert when entitlement is in grace period
- Sidebar card with: quantity editor (addon only, same `purchase_max_quantity` cap), change type, list/credit/display amounts, period, entitlement status, stack quantity equation. Settlement + FX rows only when those keys are present (`view invoice` staff) — buyer JSON omits them; see [Per-viewer payloads](#per-viewer-payloads).
- `CheckoutPanel` for invoice payment

## Non-blocking consumer decision findings

Assessment of whether Vue-Workers invents business rules that belong in Monolith. **Blocking** cases (client `canAffordAnalysis` math, sync-status default `'midtrans'`, hardcoded purchase qty `99`) were moved to the API / parse-as-is in the same task. The rows below are still SPA (or Blade-JS) decisions, but they do **not** stop a flow the API would complete. Leave them for a later discussion — do not “fix” them as part of this contract.

| Finding | Why it is decision-making | Why not blocking |
|---------|---------------------------|------------------|
| Accept Anyway always painted on the claim page | SPA cannot read `vtual.claim.accept_anyway_enabled` | Blade claim page also shows it to any logged-in user when the setting is on; API 422s when off. Profile-page staff-gate is a **different** surface (channel claims). Extra button ≠ blocked verify. |
| Discord verify settings on the **creator claim** page are not read | SPA cannot read `vtual.claim` Discord-verify flags | Connect hint vs **Verify with Discord** is gated by `GET profile/connections` (`providerByKey(..., 'discord')?.connected`). The verify button can still 422 when those settings are off; bio verify still works. |
| `collection_status` → CTA map | Local state machine in `FileHostingView` | API rejects illegal transitions; documented mimic of Blade toolbar |
| Invoice/purchase `status` → PaymentSteps / hero | Chrome | Display only |
| `plan_kind === 'addon'` qty editor | Chrome from a field | API rejects non-addon qty |
| JWT `hasPermission` hide-buttons | Stale until refresh | API still 403s |
| `analysisEstimateUsd` display multiply | Preview math in `FhCollectionDescribeModal` | Does not gate analyze (`assertCanAnalyze` is the only gate) |
| Describe chips + min-10 | Port of Blade `collection-describe.js` | Mimic, not new money/lifecycle math |
| `useMicrostock.createWorkspace` invents `is_owner` / `Owner` | Optimistic shape vs resource | Local list until refetch |
| `ROLE_RANK`, kind icons, chip catalogs, settings slug list, invite roles, subscription cycle literals | Presentation / form enums | Wrong sort or extra option; API validates |
| `projectedStackQuantity` | Display preview | Not sent as the charge |
| Local `formatBytes` vs `*_human` | Presentation | Prefer API fields later |
| Payment channel client search; menus client plain table (no pagination) | Accepted B | |
| Blade JS `purchase-pricing-preview.js` fee re-quote | Blade also does this | Baseline mimic, not a new SPA crime |
| SPA cannot call `PaymentGateway::defaultForInvoice()` | The server resolver can fall back to a default gateway; the SPA skips sync when the provider is unknown | Skip is safe; sync resumes after checkout returns `gateway` |

**Correct consumer pattern (keep):** `quota.can_create_workspace`, `slots.can_create`, `claim.can_verify`, flattened channel quotes, invoice fee snapshots, `*_formatted`.

