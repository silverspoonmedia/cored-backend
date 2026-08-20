# Leading SPA frontend — Monolith-Vue-Workers

Monolith-Vue-Workers (`monolith-vue-workers` sibling) is the **leading SPA frontend**. Backend (the Laravel repo) and this SPA are separate projects; that split is the intended end state. It consumes the Monolith `/api/v1/*` API. This doc is the integration contract between the SPA and the API.

The Blade admin in Monolith stays as the in-repo frontend **until this port actually succeeds** — do not remove it; it is the reference for appearance and behavior. See [web/blade-admin.md](../web/blade-admin.md).

## Architecture

| Piece | Role |
|-------|------|
| Vue 3 + Vite + TypeScript | SPA runtime, SFC components, type safety |
| Cloudflare Workers | Deployment target (`@cloudflare/vite-plugin`). Also the `/api/v1` reverse proxy (hostname mask) and BFF: HttpOnly `spa_jwt` cookie → `Authorization: Bearer` to Laravel; strips `data.token` from login/register/refresh/2FA-login JSON. |
| Session auth | HttpOnly `spa_jwt` on the Vue-Workers origin. JS never stores the JWT. `GET auth/me` hydrates `user`. Cross-tab: the cookie is shared; `BroadcastChannel` only coordinates refresh/logout (no token in the messages). `remember` affects cookie `Max-Age` via API `expires_in`. |
| `fetch` wrapper (`src/api/client.ts`) | Same-origin `/api/v1/...` via `src/api/baseUrl.ts` (`credentials: 'same-origin'`, `X-Requested-With: XMLHttpRequest`). No `Authorization` header — the Worker attaches Bearer from the cookie. 401 refresh + retry. `getBlob()` is the non-JSON path (CSV / file download). |
| `vue-i18n` | Full locale system (`en`, `id`, `ja`) converted from Laravel PHP lang files |

Base URL: empty `VITE_API_BASE_URL` (required). Composable paths are relative (`auth/me`); the client prefixes `/api/v1`. The Worker forwards that to `{MONOLITH_API_ORIGIN}/api/v1/...` and owns the session cookie. An absolute `VITE_API_BASE_URL` bypasses the Worker — DevTools shows the monolith host **and** the HttpOnly BFF cookie never reaches Laravel. Leave it empty.

## API hostname mask

The browser calls **same-origin** `/api/v1/*`. DevTools Network shows the Vue-Workers origin, not the monolith host. `@cloudflare/vite-plugin` runs `server/index.ts` in `npm run dev` **and** on deploy — one proxy, both environments.

This is **hostname masking plus an origin gate.** Paths stay `/api/v1/auth/login`. The Worker **is** the public API and the BFF: it sets HttpOnly `spa_jwt` (not Blade `auth_token`) and never forwards `Cookie` to Laravel. The Worker stamps every brokered request with `X-SPA-Origin: 1`; Monolith's `RequireSpaOrigin` middleware (API group) audits direct hits to the `spa_audit` log channel and, **outside `local`/`testing`, rejects them with 403** (`api/common.spa_origin_required`). Local and testing stay open so PHPUnit, tinker, and tooling keep working; OPTIONS preflights always pass because browsers never attach custom headers there. The `/whatsup` health route is not in the API group and is unaffected. `MONOLITH_API_ORIGIN` lives in Wrangler `vars` / `.dev.vars` (not `VITE_`, not the JS bundle). **`MONOLITH_API_ORIGIN` must equal the `APP_URL` origin** — signed email-verify HMAC is bound to the API host. XSS can still session-ride; it cannot read the JWT. Mutating requests with a cookie require `X-Requested-With: XMLHttpRequest` (`SameSite=Strict` plus that header).

Still naked (browser talks to these hosts directly):

- Pusher / Reverb WebSockets (`VITE_PUSHER_*`)
- Discord OAuth + the Monolith web callback (`SPA_URL` return)
- File Hosting presigned / gateway byte URLs
- hCaptcha JS

Local gotcha: Vite env files stack — `.env.local` **overrides** `.env` (gitignored via `*.local`). Restarting `npm run dev` does nothing if `.env.local` still has `VITE_API_BASE_URL=https://…/api/v1`. Leave that key **empty in both files** (required for hostname mask **and** the BFF cookie). Put `MONOLITH_API_ORIGIN` in `.dev.vars` (copy `.dev.vars.example`). An absolute Vite API URL means the browser talks to the monolith host directly; the Worker never sees the request. After changing env files, restart Vite and hard-refresh the browser (env is baked at compile).

## Worker invocation vs static assets

HTML, JS, CSS, and SPA fallback do **not** invoke the isolate. Wrangler `assets.run_worker_first` is only `["/api/*"]` — see `monolith-vue-workers/wrangler.jsonc`. Cloudflare’s free-tier **100,000 requests/day** is function invocations, not static views.

One browser `fetch` to the SPA origin `/api/*` is **one** invocation. The Worker’s `fetch` to Laravel is a subrequest (per-invocation cap), not a second daily request. `npm run dev` runs the same `server/index.ts` locally and is **not** billed. A 401 that refresh-retries can be three invocations (original + `POST auth/refresh` + retry). Pusher, S3/gateway byte URLs, Discord OAuth, and hCaptcha are not Worker requests.

`/api/*` reverse-proxy + HttpOnly `spa_jwt` is **deemed necessary**. Keep that script small (`fetch` only). Stamp `X-Content-Type-Options`, `Referrer-Policy`, and `Permissions-Policy` on Worker JSON responses (`server/bff.ts`). Static HTML/JS get CSP / frame-deny from `public/_headers` (Cloudflare Assets). Do not add cron, queues, Durable Objects, extra `run_worker_first` paths, or domain logic in the Worker without an explicit reason in this guide. Business work stays on Monolith; the SPA fetches and parses.

## Invocation budget controls

Because every `/api/*` call is one Worker invocation, the stack has explicit quota savers:

- **Batch fan-in** — `POST /api/v1/batch` (`routes/api/v1/batch.php`, `BatchController`) dispatches up to **10** read-only (`GET`/`HEAD`) sub-requests through the kernel in one HTTP call, each keeping its own auth/permission middleware. SPA wrapper: `batch()` in `src/api/client.ts`; `BatchSubRequest` rejects mutation methods at the client type level. Throttled `30,1`; mutations are rejected (`422` per entry). Use it for multi-widget hydrates instead of N parallel fetches.
- **Shell hydrate** — `useShellHydrate.hydrateShell()` (called from `AppLayout` and awaited by the auth route guard) batches five reads: `auth/me`, sidebar menus, navbar menus, `notifications/unread-count`, and `notifications?display_mode=query&limit=5`. It seeds `setUser`, `seedTree`, `seedUnreadCount`, and `seedList`; `AppNavbar` uses the seeded recent list for its bell dropdown. Each entry is checked independently. A failed entry leaves its owning lazy loader as fallback (`AppNavbar` skips navigation/list/count fetches only when the corresponding seed exists). Dashboard reads `auth/me` from the seeded store — no extra fetch. `resetShellHydration()` exists for re-hydration after logout.
- **File Hosting hydrate** — `useFhHydrate.hydrateFileHosting()` batches the four independent FH reads (`file-hosting/browse`, `file-hosting/quota`, `file-hosting/workspaces`, `microstock/slots`) into one invocation from `FileHostingView.loadAll()`. `hydrateWorkspaceDetail(null)` batches the workspace list; `hydrateWorkspaceDetail(workspaceId)` batches workspaces, members, and up to 50 activity rows for `FileHostingWorkspacesView`. Failed subs return `null` and fall back to `fetchWorkspaces`, `fetchMembers`, `fetchActivity`, or the owning loader. The dependent stage (`microstock/billing`, collection status) still runs after — it gates on `browse.workspace.kind === 'microstock'`. Realtime (`.listing.changed` / `.quota.changed`) and polling fallback are unchanged — batching only trims initial hydrate.
- **Worker cache headers** — `server/bff.ts` stamps `cache-control` on reference-data GET responses (`navigation/menus`, `services/options`, `currencies*`): `private, max-age=60/300` when a `spa_jwt` session is present, `public` when not. The guest-safe vTual public API (`/api/v1/vtual/public/*` — directory, contents, profile, plus `landing`/`stats`/`live`/`schedule`) is also `public, max-age=60` so landing-page repeats skip the Worker. The browser serves repeats from disk cache without touching the Worker.
- **JWT TTL 240** — `config/jwt.php` `'ttl' => 240` (Tier B; runtime `setting_config('jwt.ttl')` / Settings → Platform `platform.jwt.ttl`). A 401 refresh round-trip costs 3 invocations; longer TTL makes that rare. Refresh rotation + blacklist still bound token theft.
- **Polling backoff** — `useListingLiveUpdate` (base 90s) and the `AppNavbar` bell (base 120s) double their interval after 5 unchanged polls (cap 5 min), reset on any change. Echo events remain the primary channel; polling is the degraded fallback.
- **Per-domain batch hydrate** — Current coverage: `useNavigationHydrate` (menu edit plus parent/role/include/exclude lookups), `useNotificationsHydrate` (audience edit plus assignable roles), `useUsersHydrate` (user, assignable roles, activity logs), `useServicesHydrate` (service/purchase plus options), `usePaymentsHydrate` (income overview/invoices), `useInferenceHydrate` (usage overview/events/model cards and provider/card detail), `useMicrostockHydrate` (billing/ledger, usage events, admin overview/events), and `useVtualHydrate` (profile, credentials, orphan edit, claim). Every helper uses `bodyOf()` and returns `null` for a failed entry so callers retain individual-loader fallback. `src/api/batch.ts` owns the unwrap contract: `bodyOf()` accepts only a status-200 `success` envelope; `dataOf()` returns its `data` or `null`. Batch transport failure rejects the call and callers fall back to their normal loaders. Only `GET`/`HEAD` subrequests are supported; maximum remains 10.
- **Project-wide realtime** — Dedicated `ShouldBroadcastNow` events per domain: Navigation (`menu.tree.changed`), Service (`service.catalog.changed`, `service.purchase.changed`), Inference (`inference.catalog.changed`), Settings (`settings.group.changed`), ErrorLog (`log.error.recorded`), ProductActivation (`product.activation.changed`), DomainAccess (`domain.visibility.changed`), RBAC (`rbac.role.permission.changed`), PaymentGateway admin (`invoice.list.changed`), Microstock billing (`microstock.billing.changed` rides the FH user channel). Each SPA view subscribes via a `use{Domain}Realtime` or `useAdminRealtime` composable; `useListingLiveUpdate` polling stays as degraded fallback when Echo is not connected.

Do **not** add a client fallback that naked-fetches the monolith host when the budget is tight — see the warning above.

Vite `server.proxy` is a **local Node** reverse proxy. It does not run on Cloudflare Static Assets. It can mask a host in `npm run dev` when nothing else intercepts `/api`, but it cannot mint `spa_jwt` or replace this Worker while BFF is deemed necessary (`SameSite=Strict` on the SPA origin — every authenticated call must hit that origin). `@cloudflare/vite-plugin` already sends `/api/*` through `server/index.ts` in `npm run dev`; a second Vite proxy for the same path is dead or breaks login. Do not treat Vite proxy as a production “no-function mask.”

Do not add a client fallback that naked-fetches the monolith host when the function cap is hit: the cookie would not be sent (different origin), JS has no JWT, and locking Laravel to the Worker would block that path anyway. The Worker **is** the public API; hostname masking is not a control.

## Local dogfood checklist

Daily driver is `npm run dev` at `http://localhost:5173`. Blade stays at `APP_URL` as the reference. Set Monolith `SPA_URL="http://localhost:5173"`. Empty `VITE_API_BASE_URL` in `.env` and `.env.local`.

Pass/fail (compare Blade when unsure):

- Network: `/api/v1/...` on `localhost:5173`, not `monolith-ph24`. Login JSON has **no** `token`. Request has Cookie `spa_jwt` + `X-Requested-With`, not `Authorization`.
- Application: `spa_jwt` HttpOnly; no `mw_auth` in localStorage.
- Hard refresh still logged in; logout clears the cookie; two tabs, one logout, the other dies on the next API call / `BroadcastChannel` logout.
- Login / remember / 2FA / refresh near TTL.
- FH browse + upload, invoice CSV, Discord connect (`SPA_URL`), Echo bell or FH live event (or poll fallback).
- Sidebar **DMCA Blocklist** (`file-hosting.blocklist.index` → SPA `file-hosting.blocklist.index.view`) navigates.

## Production URL minting (`SPA_URL`)

`App\Support\SpaPublicUrl` prefixes browser-facing links onto `config('services.spa.url')` when that origin is non-empty. Empty `SPA_URL` keeps Blade / signed-API URLs (local Blade dogfood). CORS `allowed_origins` is `APP_URL` + `SPA_URL` (no `*`).

| Surface | Empty `SPA_URL` | Set `SPA_URL` |
|---------|-----------------|---------------|
| Password reset email | Blade `password.reset` (`/reset-password/{token}?email=`) | `{SPA_URL}/reset-password?token=&email=` |
| Email verify email | Signed `GET /api/v1/auth/email/verify/{id}/{hash}` | `{SPA_URL}/verify-email/{id}/{hash}?expires=&signature=` — SPA then GETs the signed API path through the Worker. HMAC stays on `APP_URL`. |
| File Hosting `share_url` | Blade `route()` `/s/f/{id}` / `/s/d/{id}` | Same paths on `SPA_URL` |
| iPaymu hosted return/cancel defaults | Blade `/payment/ipaymu/return\|cancel` | `{SPA_URL}/payment/return` and `/payment/cancel` |

Channel checkout still sends empty `ureturn` (on-page QR/VA). Guest verify route is **not** `guestOnly` so a logged-in unverified user can land on it.

## Notification deep links

API `action_route` + `action_params` are Blade web names (`users.edit`, `{ user: uuid }`). SPA `src/utils/notificationTarget.ts` resolves `${route}.view` and maps `user` / `modelCard` → Vue `:id`. Bell and `/apps/notifications` navigate there; unknown names fall back to the notifications list.

## Production checklist

Ops after merge (not code):

1. Monolith `SPA_URL` = public Vue-Workers origin (no trailing slash). Empty keeps Blade/API URLs.
2. Worker `MONOLITH_API_ORIGIN` **equals** Monolith `APP_URL` origin (signed email-verify HMAC).
3. `VITE_API_BASE_URL` empty in the SPA build. `npm run deploy` from `monolith-vue-workers`.
4. hCaptcha: Settings on **and** `VITE_HCAPTCHA_SITE_KEY` set, or both off.
5. File Hosting bucket / Gateway CORS includes the SPA origin and **exposes `ETag`**.
6. Pusher or Sockudo: Laravel `PUSHER_*` + matching `VITE_PUSHER_*` in the SPA build.
7. Cloudflare custom domain on the Worker. Do not invent a second Wrangler env unless a second API origin exists.

`X-SPA-Origin` is an audit stamp, not auth. JWT is auth. Do not widen `run_worker_first` past `/api/*`.

Fonts and Bootstrap Icons are self-hosted (`@fontsource/source-sans-3`, `bootstrap-icons`). `public/robots.txt` disallows `/apps` and auth paths; `/s/*` is `noindex`. Vue DevTools is Vite `serve` only.

## CI

`monolith-vue-workers/.github/workflows/ci.yml`: `npm ci` → `type-check` → `check:i18n` → `vitest` (`tests/bff.test.ts`, `tests/notificationTarget.test.ts`) → `build-only`. No Playwright / ESLint-everywhere in this gate.

## API contract reference

The SPA consumes the same `/api/v1/*` endpoints as the Blade admin. Per-domain API docs:

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
| `useConnections.fetchConnections` | `GET profile/connections` | Not under `auth/`. Any authenticated user. |
| `useConnections.authorizeUrl` | `GET auth/discord/authorize-url` | Query `intent` + `return_path` (must start `/apps/`). Returns `{ url }` — navigate the browser there. Discord still callbacks to Monolith web. |

### Response shape gotchas

| Endpoint | Returns | Gotcha |
|----------|---------|--------|
| `GET notifications/unread-count` | `{ unread: number }` | Field is `unread`, not `count` |
| `GET microstock/billing` | `balance_micro_usd`, `balance_usd`, `estimated_per_analysis_micro_usd`, `estimated_per_analysis_usd`, `daily_free: { enabled, limit, used, remaining, resets_at }` | Field names are `balance_usd` / `estimated_per_analysis_usd`, not `balance_formatted` / `estimate_formatted`. Daily free uses `used`/`limit`, not `slots_consumed_today`/`daily_limit`. |
| `GET admin/product-activations` (index) | Flat `ProductActivationResource[]` in `data` | No `{ data, pagination }` wrapper — `res.data` is the array directly |
| `GET admin/product-activations/products/{productKey}` | `{ summary, activations, pagination }` in `data` | This one DOES have a wrapper — opposite of index |
| `GET payment-channels?subtotal=` | `PaymentChannelResource[]` with flattened quote fields | Quote fields (`estimated_channel_fee`, `estimated_channel_fee_formatted`, `fee_label`, etc.) are at top level, NOT nested under `checkout_quote`. See [payment-gateway](../domains/payment-gateway/README.md#paymentchannelresource-checkout-quote-flattening). |
| `GET services/options` | `{ billing_intervals, plan_kinds, statuses, purchase_max_quantity }` | `purchase_max_quantity` is the live `platform.service.purchase_max_quantity` cap. SPA checkout/purchase steppers parse it. Fallback `99` only when the call fails (options requires `view service`; purchase owners may lack it). |
| `GET profile/connections` | `{ discord_connected, discord_oauth_configured, discord_synced_at, discord_synced_at_formatted, discord_synced_at_human, connections[] }` | `connections[]` items are `{ type, id, name, verified, revoked }`. Stale Discord snapshots refresh on this GET (same as Blade). |
| `GET auth/discord/authorize-url` | `{ url }` | Requires `SPA_URL` on the API host. `return_path` is a path, not an origin — the API prefixes `SPA_URL`. |

## Type alignment

TS types in `src/api/types.ts` must match Laravel API Resources exactly. No transformation layer. `DiscordConnection` / `ProfileConnections` match `GET /api/v1/profile/connections`: `connections[]` with nullable `id` / `name` (see `ProfileConnectionsController::presentConnections`). One interface — do not redeclare `DiscordConnection`.

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
- Empty / null status → label `—`, color = `fallback` (default `secondary`). Mapped color `light` also gets `border` (Blade vTual unknown/disconnected).
- Do **not** add a per-view `statusClass()`. Do **not** add a generic Blade `x-adminlte4::status-badge` — [blade-admin.md](../web/blade-admin.md) anti-pattern “cross-domain status badges”. Blade keeps `x-adminlte4::vtual.status-badge`.

Leave specialized chrome alone: `PaymentStatusHero` (`ois-status-*` + `data-payment-badge`), FH `fh-badge` / `fh-ms-badge`, keyword chips, navbar unread count, product-activation count chips, type/kind/role/code chips.

## i18n porting notes

The SPA uses `vue-i18n` with JSON locale files converted from Laravel PHP lang files.

### Contextual localization policy

English remains the reference locale. Indonesian and Japanese translations must be contextual and natural for the surface where they appear; literal word-for-word translations are not accepted. Review button labels, headings, table labels, status badges, toasts, validation, errors, explanatory copy, domain terminology, audience, and Japanese/Indonesian register separately. Keep machine-contract prompts and technical identifiers unchanged when they are not user-facing. Split keys when one source phrase serves different contexts.

Translation workflow:

1. Add or adjust the key in all three locale files in one change.
2. Preserve Vue interpolation tokens, plural behavior, and API/DB identifier semantics.
3. Use `computed()` or the current i18n translator for labels that must react to runtime locale changes; do not freeze translated arrays at module scope.
4. Run `npm run check:i18n` after every translation batch. Treat English-duplicate output as review input, not automatic failure; allow technical terms, brands, codes, and machine-contract strings.
5. Run `npm run type-check` and `npm run build` before handoff.

### SPA localization guards

`npm run check:i18n` checks key parity across `en`, `id`, and `ja`, Vue placeholder shape, malformed interpolation braces, suspicious Indonesian/Japanese values identical to English, and suspicious hardcoded user-facing fallback copy under `src/`. API fallback errors must use locale keys, with server messages preferred when present.

Runtime locale switching updates `document.documentElement[lang]`, persisted preference, reactive labels, and locale-aware number/date formatting. Startup resolution is separate from backend `SetLocale`: the SPA checks supported `localStorage.locale`, then the first two characters of supported `navigator.language`, then falls back to `en`. `setLocale()` persists the choice and applies the HTML `lang` attribute; `window` and `document` access is guarded for SSR/tooling contexts. Backend requests still resolve authenticated user preference, then cookie, then `config('app.locale')`; do not assume both clients choose the same locale before synchronization.

Use `Intl.NumberFormat` / `Intl.DateTimeFormat` with active locale; never call `toLocaleString()` without the application locale for user-facing values. API/DB labels must come from localized backend data or explicit SPA key mappings. Show raw codes only when they are technical identifiers. Machine prompts remain English only when English is part of the provider contract; user-facing labels and messages remain localized.

`src/i18n/fallbacks.ts` owns generic fallback keys for validation failure, request failure, session expiry, confirmation copy, cancel, and save. `src/api/client.ts` calls `translateFallback(i18n.global.t, ...)` only when the API response has no usable message; server-provided messages always win. This module-level path must use the shared `i18n` instance, not `useI18n()`.

### Conversion rules

| Laravel PHP | vue-i18n JSON |
|-------------|---------------|
| `lang/en/web/auth.php` | `src/i18n/locales/en.json` under `web.auth.*` |
| `lang/en/ui.php` | `src/i18n/locales/en.json` under `ui.*` |
| `trans('web/auth.title')` | `t('web.auth.title')` — use `.` separator, not `/` |
| `:variable` placeholder | `{variable}` placeholder |
| `trans_choice('key', count)` | `t('key', { count })` or custom pluralization helper |

### Usage pattern

Every `.vue` file using translations must have:

```vue
<script setup lang="ts">
import { useI18n } from 'vue-i18n'
const { t } = useI18n()
// or for message arrays (settings fields/hints):
// const { t, tm } = useI18n()
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
- **i18n** — shared labels live at `ui.swal.*` and `ui.dialog.*`; confirm bodies use their domain keys. All three locales must stay in parity.
- **Layering** — `.swal2-container` is pinned to `z-index: 1090` in `src/assets/monolith-overrides.css`, above Bootstrap modals (1050/1055) and custom layers like `.fh-context-menu` / `.monolith-toast-container` (1080). Never lower it; any new fixed layer must stay below 1090.
- **Toast/modal serialization** — swal2 removes the `swal2-toast-shown` body class unconditionally when a modal closes, which makes any still-open toast container fall back to full-viewport width. To prevent the race, `showErrorDialog()` and `confirmDialog()` call `closeActiveToast()` first (closes an open toast before firing the modal). Never fire a raw `Swal.fire` modal while a toast may be open — go through `src/lib/swal.ts`.
- **Body classes** — never assign `document.body.className = ''`. Swal (and Bootstrap/OverlayScrollbars) store runtime classes on `<body>` (`swal2-toast-shown`, `swal2-shown`, `modal-open`). Layout classes (`login-page`, `layout-fixed`, `sidebar-expand-lg`, …) are toggled in `App.vue` via `classList.remove`/`add` only. Toast container sizing is also pinned in CSS (`.swal2-container:has(.swal2-popup.swal2-toast)`) so a missing body class cannot balloon the toast to full viewport.
- **Redirect safety** — `useAlert().error(...)` returns a Promise. In flows that navigate away (`window.location.href = ...`, e.g. Discord/OAuth connect, file download), always `await showError(...)` inside the `catch` so the error dialog renders and is read before any unload instead of dying with the page.
- **Uncaught errors** — `app.config.errorHandler`, `unhandledrejection`, and `router.onError` in `main.ts` call `showErrorDialog(i18n.global.t('ui.ajax.unexpected_error'))`. No Sentry unless a DSN is added later (Cloudflare `observability.enabled` is already on in wrangler).

`useI18n()` is restricted to Vue component `setup()` (including composables called synchronously from setup). Router guards, Pinia-style stores, shell hydration, realtime callbacks, module-level services, and other ordinary functions may run without an active component instance. Those paths must import the shared `src/i18n` instance and call `i18n.global.t(...)`; they must not call `useI18n()`. This prevents `Must be called at the top of a setup function` from aborting initial router navigation. `main.ts` registers `router.onError()` for diagnostic reporting without hiding navigation failures, then mounts only after `router.isReady()` resolves. The auth guard performs shell hydration before protected route components mount.

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
- `src/i18n/index.ts` — `createI18n()` instance, `setLocale()` helper, `applyHtmlLang()`

Locale parity: all three files must have the same key set. Laravel `:variable` placeholders must be converted to `{variable}` in all locales (not just `en`).

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

### FormInput

Supports `maxlength?: number` prop, passed through to underlying `<input>`.

### FormSelect2

Wraps jQuery Select2. Handles plain and AJAX modes. See [web/ui-patterns.md](../web/ui-patterns.md) for Select2 usage patterns.

## Breadcrumb system

The SPA mirrors Monolith's `diglactic/laravel-breadcrumbs` trail system via a config-based composable.

- **Composable**: `src/composables/useBreadcrumbs.ts` — config-based breadcrumb map keyed by Vue Router route name.
- **Consumer**: `src/layouts/AppLayout.vue` — renders breadcrumb trail in the content header.
- **Config shape**: each entry has `labelKey` (i18n key), optional `parent` (route name chain), and optional `routeName` (for clickable links).

The map mirrors `routes/breadcrumbs.php` from Monolith. Parent chains resolve recursively: e.g. `users.edit.view` → `users.index.view` → `dashboard.view`. The last item in the trail is always non-clickable (active).

To add a new route's breadcrumb, add an entry to `BREADCRUMB_MAP` in `useBreadcrumbs.ts` with the correct `parent` chain and `labelKey` (must exist in locale JSON files).

## Page titles and admin chrome

Authenticated routes set `meta.titleKey` (and guest auth routes also `meta.headingKey`) to the same i18n keys Blade uses on `layouts.app` / `layouts.auth`. Do **not** hardcode English in router `props.title`.

- **Composable**: `src/composables/usePageTitle.ts` — resolves the title from `route.matched` meta; special-cases settings groups (`web.settings.groups.*` from `route.params.group` **or** MenuSeeder names `settings.platform.view` / `settings.file-hosting.view` / `settings.microstock.view` / `settings.vtual.view`) and product-activation show (`product-activations.product_title` with `{product}`).
- **Content header**: `AppLayout.vue` renders `pageTitle` in the `h3`.
- **Auth heading**: `AuthLayout.vue` renders `heading` as `login-box-msg`.
- **Tab title**: `App.vue` watches `documentTitle` and sets `document.title` to `{page} | Monolith` (or `Monolith` when there is no title). Recomputes on locale change.
- **DataTable chrome**: `DataTable.vue` uses `ui.datatable.*` (`search_placeholder`, `empty_title`, `info`, `per_page`) and `ui.labels.actions`. Catalog dumps (`useResource` + DataTable) send `display_mode=query&page&per_page=10` (and `search` when the box is used) and parse `{ items, pagination }` **or** a legacy flat `T[]`. Default page size is **10** (Blade DataTables). Menus tree stays a full dump + client slice. Do not port Yajra JS. Do not invent a second list URL.
- **Row actions**: icon-only (`RowActions.vue`) — `aria-label` + `title` from the label, icon `aria-hidden`, `btn-group btn-group-sm`. Toolbar primaries stay icon + text (`Add user`). File Hosting `.fh-action-group` is already icon-only — leave it.

### Blob / CSV downloads

`src/api/client.ts` `get()` always sets `Accept: application/json` and `res.json()`. CSV and other attachments use `getBlob()` (same cookie session + 401 refresh, `Accept: */*`) plus `src/utils/download.ts` `saveBlob()`. Wired for `GET invoices/export` (invoices list and income page; income sends `status=paid` plus optional `paid_from`/`paid_to`) and `GET microstock/collections/{folder}/download`.

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

**Microstock collection lifecycle** (`FileHostingView` when workspace `kind === 'microstock'`): toolbar follows `collection_status` — draft/failed → Lock & Describe (`FhCollectionDescribeModal`, chip compose in `src/utils/fhCollectionDescribe.ts` → `POST microstock/collections/{folder}/lock`); locked → Start analysis (`POST …/analyze`; API `assertCanAnalyze` is the only gate — toast the API `message` on 422; do **not** invent client credit math); processing → status + progress (`GET …/status` + Echo `.collection.analysis.progress`); review → Review + Export; exported → Download CSV (`getBlob` on `GET …/download`). Review is `FhMicrostockReviewModal` (signed preview from `GET file-hosting/files/{id}/download?preview=1`, stage strip, PATCH title/keywords, solo `POST microstock/assets/{file}/analyze`). Show page opens the same modal. Create system workspace is `POST microstock/workspaces` — not `POST file-hosting/workspaces` (team). Switcher CTAs parse quota (team slots) and `GET microstock/slots` (used/limit/`can_create`); full/unactivated links to Services.

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

SPA routes: `/payment/return` and `/payment/cancel` (`PaymentReturnView`, `requiresAuth`). Blade uses `/payment/ipaymu/return|cancel`. When `SPA_URL` is set, `SpaPublicUrl::paymentReturn/Cancel` mints the SPA paths as iPaymu hosted redirect defaults; empty `SPA_URL` keeps Blade. Channel checkout still sends empty `ureturn` (on-page QR/VA). HttpOnly `spa_jwt` survives the gateway bounce (`SameSite=Strict` still sends the cookie on same-origin XHR after the SPA loads); `beforeEach` hydrates via `GET auth/me` and keeps `redirect=fullPath`. Do not reopen these as guest routes unless a real gateway hit arrives without a session. These stay at the **site root**, not under `/apps`.

### `/apps` shell

Authenticated `AppLayout` routes are prefixed `/apps/*` (dashboard, users, settings, file-hosting, vtual, invoices, …). `/` redirects to `/apps/dashboard`. Route **names** stay unprefixed — sidebar `NavTree` / `AppNavbar` already `push` by name.

**Not prefixed** (same exceptions as Blade, plus the verify hop): auth (`/login`, `/register`, `/forgot-password`, `/reset-password`, `/two-factor-challenge`, `/verify-email/:id/:hash`), public share (`/s/f/:id`, `/s/d/:id`), payment bounce (`/payment/return`, `/payment/cancel`), **vTual public** (`/vtual`, `/vtual/vtubers/:slug`). Verify-email uses `meta.authChrome` (not `guestOnly`) so a logged-in unverified user can land.

Legacy bare paths (`/users` → `/apps/users`, …) redirect so bookmarks do not 404. Catch-all 404 stays last, inside `AppLayout` — except public vTuber misses, which stay in `VkStr1Layout`.

### vTual public surface

Registered **before** `LEGACY_APP_ROOTS` (`vtual` is in that set — unmatched `/vtual/*` rewrites to `/apps/vtual/*`). Explicit children of `VkStr1Layout`:

| Path | Name | Data |
|------|------|------|
| `/vtual` | `vtual.public` | Landing: Swiper hero slider (`VkStr1HeroSlider`, 3 static promo slides: hub intro `hero.one.*`, claim profile `hero.two.*`, events `hero.three.*` — no live data) + post-hero band (`pt-12 md:pt-16`: platform stats `VkStr1PlatformStats`) + unified live directory (`#vtubers`, `VkStr1VtuberLiveDirectory` — self-fetching directory grid with search/pagination/skeleton/hover-prefetch plus a `liveItems` prop fed from `landing.live_now`; live cards show a danger-ringed avatar, LIVE badge, up to 3 stream rows + `+N more`) + content feed (`#contents`) + upcoming schedule events (`landing.schedule`, i18n fallback) + claim banner (`VkStr1ChannelBanner`). One `fetchLanding` call feeds the live directory/stats/events. `VkStr1LiveNow` / `VkStr1VtuberDirectory` are kept unchanged as references. Content cards (`VkStr1VideoCard`) carry a channel-avatar left column, a `:title` tooltip on the truncated title, an aspect-ratio thumbnail container with `absolute inset-0 object-cover`, and a permanent underlay placeholder on thumbnail error/null. |
| `/vtual/vtubers/:slug` | `vtual.public.vtuber` | Two-column guest profile via `useVtualPublic().fetchProfile(slug, page)` (banner overlay + gradient fallback, `VkStr1ProfileSidebar` with `VkStr1DetailsList` + `VkStr1ChannelCard`, featured live, **paginated** video grid with Prev/Next pager — `contents: { items, pagination }`, featured live only on page 1, styled empty-state). 404 renders **inside** the Valkivid3 layout, not `AppLayout`. |

`meta.public` + `meta.template: 'valkivid3'`. Isolation: `body.vk3-page`; `html.dark` via `str1-color-theme` (class `dark:` variant, not `prefers-color-scheme`), restored pre-paint by an inline `index.html` head script so dark-mode guests never see a light FOUC; Tailwind utilities nested under `.vk3-root` (`@tailwind utilities` inside that selector — not `important(.vk3-root)`, which Tailwind 4.3 leaves as invalid `@media important(...)`); no preflight. **Scoping caveat:** nesting `@tailwind utilities` under `.vk3-root` makes every utility a descendant selector (`.vk3-root .bg-white`). Utilities placed ON the `.vk3-root` element itself never match — page background, root text color, and min-height must live in `@layer base` on `.vk3-root` / `.dark .vk3-root`, not as utility classes on the layout div. Leaving `/vtual` still `classList.remove('dark')`. AdminLTE keeps `data-bs-theme` / `lte-theme`. Theme toggle (sun/moon sprite icons, `fill-accent stroke-accent`) lives in `VkStr1Header` + `VkStr1MobileMenu`; track has explicit `bg-gray-150 border-gray-500/60 dark:bg-white/10 dark:border-white/50` so the pill stays visible on the always-dark header.

**Forked steel-slate palette (light/dark).** The str1 skin uses a steel-slate silver identity (not the original Valkivid violet/cyan). One accent value cannot serve both backgrounds, so every brand var forks per mode in `@layer base`: `--color-primary` / `--color-accent` are `#475569` (slate-600) light / `#94a3b8` (slate-400) dark; `--color-primary-500` (hover) is `#334155` (slate-700) light / `#cbd5e1` (slate-300) dark; root body text forks `#7a809b` (gray-500) light / `#a3a9c1` (gray) dark. A dedicated `--color-accent-500` (slate-700 light / slate-200 dark) backs accent hover because dark `--color-primary-500` == dark `--color-accent` (both slate-300), so `hover:bg-primary-500` on an accent button would be a no-op in dark mode. Do NOT collapse these to a single value — light mode reusing the dark accent/body gray was the 2.3:1 washout bug.

**Button conventions.** Because accent/primary fork dark-in-light / bright-in-dark, button labels fork the opposite way: `bg-accent` and `bg-primary` CTAs/badges/avatars use `text-white dark:text-gray-900`. Hover forks per variant: primary uses `hover:bg-primary-500`, accent uses `hover:bg-accent-500`. Outline (ring) buttons no longer invert white-to-gray-900 on hover (17.87:1); they soft-fill `hover:bg-slate-500` light / `dark:hover:bg-slate-400` dark, keeping base ring + label. Any element that fills with `hover:bg-accent` / `hover:bg-primary` must fork its label `hover:text-white dark:hover:text-gray-900` (light fill is a dark slate so the label goes white; dark fill is a bright slate so the label goes gray-900) — a bare `hover:text-gray-900` on a dark hover fill was the pager/ChannelCard bug.

**Unlayered `!important` wrappers in `valkivid3.css`.** Bootstrap/AdminLTE ship unlayered utilities that load after the chunk and outrank all layered Tailwind regardless of specificity. Scoped unlayered `!important` wrappers exist for `.bg-primary`, `hover:bg-primary-500`, `hover:bg-accent(-500)`, `hover:bg-slate-400/500`, `dark:text-gray-900`, `hover:text-white`, `dark:hover:text-gray-900`, and `dark:bg-gray-700`. Rule: any `dark:bg-*` fork on a class that has a Bootstrap `!important` counterpart (`.bg-white`, `.bg-black`, `.bg-primary`, `.bg-danger`, ...) needs a scoped `!important` wrapper — otherwise the layered fork loses and a white bg stays white in dark mode, the ChannelCard/MobileMenu white-on-white bug. Non-`!important` Bootstrap names with no counterpart (`bg-gray-150`, `bg-gray-800`) never collide.

**Adaptive hero controls.** The str1 hero (Swiper core + `Autoplay`/`EffectFade`/`Navigation`/`Pagination`, fade, 3 slides, staggered entrance with `vv-slide-played`, vertical bar pagination `.vv-hero-swiper-pagination`, width `1320px`) forks its nav arrows + pagination per ACTIVE-SLIDE tone at ≥992px, not per global mode — slides 2/3 stay `bg-gray-900` in both modes, so a light-mode-only fork would hide the arrow on those dark slides. `VkStr1HeroSlider` resolves the active slide's effective tone into `data-tone` on the hero container (`light` only for slide-1, `dark` otherwise) via Swiper `slideChange`. `valkivid3.css` then forks: arrows default white and only go `gray-900` under `html:not(.dark) .vk3-root [data-tone='light']` (the `html:` prefix is required — a bare `:not(.dark)` matches any non-dark ancestor like body); the pagination track goes bright (`white/25`) on dark slides vs darker (`gray-500/35`) on slide-1 light; the active bullet re-asserts the solid accent on slide-1 light but uses slate-300 on dark slides in light mode. All unlayered scoped `!important`. The controls hang `-right-16` (64px outside) only at `2xl` so they don't dangle off-canvas below 1360px; do NOT add `2xl:px-16` to compensate (that shrinks the hero). Below `xl` the `bg-gray-900` chip keeps the arrow white regardless.

**FROZEN — HomeView (`/vtual` landing).** `VkStr1VtualHomeView.vue` and its exclusive components (`VkStr1HeroSlider`, `VkStr1LiveNow`, `VkStr1PlatformStats`, `VkStr1VtuberDirectory`, `VkStr1VtuberLiveDirectory`, `VkStr1ContentFeed`, `VkStr1SectionEvents`, `VkStr1SectionCountdown`, `VkStr1ChannelBanner`) are frozen — declared production-ready. Any plan that edits them requires explicit user permission first and MUST be diffed against the visual baseline afterward. Baseline lives in `src/valkivid3/.baseline/`: `home-light.jpg` + `home-dark.jpg` (full-page 1440px screenshots) and `home-baseline.json` (computed styles of key elements). Re-capture only after an approved, verified change.

**Error-panel pattern.** Async fetch failures render a retryable panel, not bare `text-danger` text — wrapper `bg-gray-150 ring-1 ring-gray-500/20 dark:bg-gray-800 px-6 py-10 text-center`, a `text-gray-600 dark:text-gray-300` message, and a `bg-accent hover:bg-accent-500 text-white dark:text-gray-900` retry button that re-invokes the loader. Used by `VkStr1VtualHomeView` (landing) and `VkStr1VtuberShowView` (profile); i18n key `<scope>.retry`.

See SPA README.

Claim stays authenticated at `/apps/vtual/vtubers/:slug/claim`. Public page only advertises it: guest + `is_claimable` → `/login?redirect=/apps/vtual/vtubers/{slug}/claim`; authed + `is_claimable` → the claim route. No guest `POST /claims`. Tokens stay auth-only.

Blade and API `profile_url` / `claim_process_url` mint **singular** `/apps/vtual/vtuber/{slug}` (hub is the param-less `/apps/vtual/vtuber`). SPA canonical show/claim stay **plural** `/apps/vtual/vtubers/:slug` so they do not collide with that hub. Singular `/apps/vtual/vtuber/:slug` and `/claim` redirect onto the plural named routes. Singular `/vtual/vtuber/:slug` (no `/apps`) also redirects into `/apps` — it must not sit in `VkStr1Layout`. Public guest profile remains `/vtual/vtubers/:slug` only. Claim CTA on the auth show page is a named `router-link`, not the Blade `claim_process_url`. UUID in the path is replaced with the canonical slug after fetch (Blade 301 equivalent, client-side).

Login **and** register honor `?redirect=` through `src/lib/safeRedirect.ts` (`safeInternalRedirect`): must start with `/`, reject `//`, `\`, `://`, `/http:`. Cross-links between login and register preserve the query. Default fallback `/apps/dashboard`.

### vTual orphan staff admin

Authenticated `/apps/vtual/orphans` mimics Blade staff seed (appearance + behavior + intent). Vue `DataTable` + `display_mode=query` — do not port Yajra. Public `/vtual` is a different surface.

| Step | SPA contract |
|------|----------------|
| List | Subtitle `web.vtual-orphans.subtitle`. Row actions: edit + archive. Archive is `DELETE /api/v1/vtual/creators/{id}` after `confirmDialog` (`datatable.confirm_delete`); refetch the list. Claimed rows already omitted by `GET creators/orphans`. |
| Create | `POST /api/v1/vtual/creators/orphan` → `vtual.orphans.edit.view` with the new `id`. An orphan without channels is not claimable in practice. |
| Save | `PATCH /api/v1/vtual/creators/{id}` stays on edit. Cancel/back go to index. |
| Channels | Staff `POST /api/v1/vtual/creators/{id}/channels` is `registerPendingForStaff` (pending channel, in-place append, skip duplicate `id`). Not a claim — do not treat `attempt_count` as `channel_claim_started` on this page. YouTube/Twitch placeholders + 5-col table (avatar / platform / profile_input\|\|external_id / handle / status). |

Create and edit share `VtualOrphanEditView`. `AppLayout` is a bare `<router-view />` (no `:key`), so create→edit does **not** remount. Watch `route.params.id` / `isEdit`: reset the form, hydrate on edit, skip hydrate on create.

### Settings menu names

Every Settings screen is ported (`SettingsFormView` on `/apps/settings/:group`). MenuSeeder children are `settings.platform`, `settings.file-hosting`, `settings.microstock`, `settings.vtual`. The SPA registers those **as named routes** (`settings.platform.view`, …) pointing at the same SFC, **before** the parameterized `settings.group.view` (used for `identity` and any future registry group). Sidebar `hasRoute(node.route + '.view')` is what makes the click work — a redirect-only alias would lose `isActive`. Do **not** add `identity` to MenuSeeder.

Static vTual credential probe (`/apps/vtual/platform-credentials/probe`) is registered **before** `/apps/vtual/platform-credentials/:platformId` so `/probe` is not captured as a platform id.

### 404 catch-all

Last route: `path: '/:pathMatch(.*)*'` → `NotFoundView` inside `AppLayout`, `meta: { requiresAuth: true, titleKey: 'errors.not_found' }`. This is an admin SPA — unknown URLs for guests already hit `beforeEach` → login with `redirect`. After login they see 404 **inside** the sidebar, not a naked page. Catch-all stays below `/s/f/:id`, `/verify-email/:id/:hash`, `/payment/return`, `/vtual`, `/vtual/vtubers/:slug`, Blade-compat `/vtual/vtuber/:slug` (redirects into `/apps`), and the legacy `/users` → `/apps/users` redirects. A missing public vTuber slug is **not** this catch-all — `VkStr1VtuberShowView` renders an in-layout 404.

### Mimic doctrine

Mimic is **appearance + behavior + intent**, done well and safely — not a pixel clone of Blade DOM, routes, or libraries.

Monolith is API-first. One list endpoint, two presentations via `display_mode`:

- `query` — Eloquent-shaped JSON (SPA `DataTable.vue` / `useResource.ts`). Optional `page` / `per_page` / `search` → `{ items, pagination }`; omit them → legacy flat `T[]`.
- `datatable` — same rows, Yajra-shaped for Blade DataTables

The SPA fetches and parses **as-is**. Do not port Yajra JS. Do not invent a second list API.

### Accepted divergences (A / B / C)

These are the safe zone. Keep the current approaches.

**A — Platform.** HttpOnly `spa_jwt` on the Vue-Workers origin (Worker BFF; JS does not store the JWT). Blade remains HttpOnly `auth_token` on `APP_URL`. Direct Monolith `/api/v1` still returns Bearer JSON. `POST /api/v1/broadcasting/auth` (cookie → Worker Bearer), Worker CSRF header on mutating cookie requests, hCaptcha gated by `VITE_HCAPTCHA_SITE_KEY`, duplicate vue-i18n JSON, CF Workers origin, Worker `/api/v1` hostname mask, breadcrumb config map, guest cannot read `system_settings`, invocation budget controls (`/api/v1/batch`, Worker cache headers, JWT TTL 240, polling backoff). Mimic the session and realtime intent; two cookies, two origins.

**B — Intentional consumer choices.** Vue `DataTable` + `display_mode=query` (server page of 10 when paging params are sent). Select2 only for server-side lookup (`MenuEditView` AJAX parent/roles/users). Navigation via `GET navigation/menus?layout=sidebar`. File Hosting three Vue routes vs Blade island; team as a page vs offcanvas; listing-only move picker; rate editor re-fetch instead of full reload. Mimic intent, not Blade DOM ids (`#fhTeamOffcanvas`, `#fhCreateWorkspaceModal`, `#fhMicrostockPanel`).

**C — Gray (optional later, not a product blocker).** Live-reload on 2 of 7 catalogs (`NotificationType::tableId()` only emits those two), local row-action chrome vs query-mode `actions[]` (deletes on users / inference / gateways / services are wired locally with Blade-equivalent permission + confirm; hierarchy still 403s from the API), Laravel plural pipe hack.

Do not treat a read-only show-page microstock card as “review is done.” Review is `FhMicrostockReviewModal` consuming `GET|PATCH microstock/assets/{file}/metadata`.

## Realtime (Laravel Echo + Pusher)

The SPA supports realtime updates via Laravel Echo and Pusher, with polling fallback.

- **Composable**: `src/composables/useEcho.ts` — singleton `Echo<'pusher'>` (`EchoOptions<'pusher'>`), reads Pusher config from `VITE_PUSHER_*` env vars. Authorizer `fetch` is same-origin + `X-Requested-With` (no Bearer); the Worker attaches JWT from `spa_jwt`. Connection flag uses `connector.onConnectionChange` / `connectionStatus()`. Subscribe via `getEcho()`; `useEcho()` exposes `init` / `disconnect` / `connected` only.
- **Notification bell**: `src/components/AppNavbar.vue` — inits Echo on mount, subscribes to `App.Models.User.{id}` private channel for `.activity-notification.received` events. Polling (60s) starts as fallback if Echo fails to connect within 30s grace window. On each WS event, the bell also calls `fetchList(5)` to refresh its own recent-notification dropdown content (capped at 5 — the full list is fetched on dropdown open or navigation to `/notifications`).
- **File Hosting**: `src/composables/useFileHostingRealtime.ts` — singleton channel manager for `file-hosting.user.{userId}` (`.listing.changed`, `.quota.changed`, `.members.changed`, `.activity.appended`, `.collection.analysis.progress`, `.asset.metadata.progress`). Multiple views register handlers; the channel is subscribed once and torn down when the last handler unregisters. See [Pattern B](#pattern-b-direct-channel-subscription) below.
- **Microstock**: collection mutations (`lockCollection`, `analyzeCollection`, `fetchCollectionStatus`, `exportCollection`, `downloadCollectionCsv`, asset metadata GET/PATCH, `analyzeAsset`, `fetchSlots`, `createWorkspace`) live in `useMicrostock.ts` and are wired from `FileHostingView` / `FhMicrostockReviewModal`. Realtime subscriptions go through `useFileHostingRealtime.ts` (same channel, Microstock events).
- **vTual claim**: `src/views/VtualVtuberClaimView.vue` — subscribes to `vtual.claim.{claimId}` private channel for `.claim.progress` events. Polling (5s) stays active as fallback alongside Echo.

#### Cross-tab notification read-state sync

`useNotifications.startRealtime()` opens a per-user `BroadcastChannel('activity-notifications.{userId}')` so mark-as-read actions propagate across tabs without each tab needing an extra HTTP request. This mirrors Blade's `bell.js` tab-sync pattern (see [notification — Read-state UI and tab synchronization](../domains/notification/README.md#read-state-ui-and-tab-synchronization)).

Messages:

| Message | Trigger | Effect on receiving tab |
|---------|---------|--------------------------|
| `{ type: 'read-all' }` | `markAllRead()` | Zero `unreadCount`, mark all `items` as read |
| `{ type: 'read', id }` | `markAsRead(id)` | Decrement `unreadCount`, mark matching item as read |

If `BroadcastChannel` is unavailable, the acting tab still updates immediately; other tabs reconcile on their next WS event, reconnect, or fallback poll.

### Env vars

Echo reads Pusher config from Vite env vars. The SPA **requires a `.env` file** in the project root (`monolith-vue-workers/.env`) — without it, `VITE_PUSHER_APP_KEY` is `undefined`, `readConfig()` returns `null`, and Echo never initializes. The websocket visible in the browser network tab would then be Vite HMR only, not Pusher.

| Var | Purpose |
|-----|---------|
| `MONOLITH_API_ORIGIN` | Worker-only. Monolith origin (no `/api/v1` suffix, no trailing slash). Wrangler `vars` + `.dev.vars`. **Not** bundled into the browser. Empty → Worker 503. Must equal `APP_URL` origin or signed email-verify HMAC fails. |
| `VITE_API_BASE_URL` | Must stay **empty** so calls stay same-origin `/api/v1` (Worker mask + BFF cookie). `.env.local` wins. An absolute prefix bypasses the Worker — hostname leak **and** no `spa_jwt`. |
| `VITE_PUSHER_APP_KEY` | Pusher app key (required for Echo — must match Monolith `PUSHER_APP_KEY`) |
| `VITE_PUSHER_APP_CLUSTER` | Pusher cluster (default `mt1`) |
| `VITE_PUSHER_HOST` | Self-hosted/Reverb host (omit for Pusher-hosted) |
| `VITE_PUSHER_PORT` | WebSocket port (default `443`) |
| `VITE_PUSHER_SCHEME` | `https` or `http` (default `https`) |
| `VITE_HCAPTCHA_SITE_KEY` | hCaptcha site key. When set, guest auth forms run the invisible widget and POST `h-captcha-response`. When unset, the widget is omitted (API must also have hCaptcha off or login 422s) |

Discord OAuth return is **not** a Vite var. Set `SPA_URL` on the Monolith API host to this SPA's public origin (Vite/Workers URL). Empty `SPA_URL` → `GET auth/discord/authorize-url` 503.

Values must mirror Monolith's `.env` `PUSHER_*` literals (and `HCAPTCHA_SITEKEY` when hCaptcha is on). `.env.local` overrides `.env` for every `VITE_*` key (that is why an old absolute `VITE_API_BASE_URL` survived Vite restarts). Worker origin is `.dev.vars` `MONOLITH_API_ORIGIN`, not a `VITE_` var.

When `VITE_PUSHER_APP_KEY` is unset, Echo is not initialized and all realtime-dependent features fall back to polling.

### Self-hosted websocket (Sockudo)

Production realtime targets **Sockudo** (Pusher-compatible WS). No code change is needed on either side — the SPA (`useEcho.ts`, broadcaster `pusher`) and Monolith (`pusher/pusher-php-server`, `BROADCAST_CONNECTION=pusher`) speak the Pusher protocol. Migration is env-only: point `PUSHER_HOST` / `PUSHER_PORT` / `PUSHER_SCHEME` (and the `VITE_PUSHER_*` mirrors) at the Sockudo host. Development stays on Pusher-hosted (`PUSHER_HOST` empty → `ws-{cluster}.pusher.com`).

### Auth

Echo private channel auth uses a dedicated API broadcast auth endpoint: `POST /api/v1/broadcasting/auth` (same-origin Worker proxy). The SPA sends cookies + `X-Requested-With`; the Worker attaches `Authorization: Bearer` from `spa_jwt`. Empty `VITE_API_BASE_URL` is required — an absolute override skips the BFF.

**Why a dedicated API route?** Laravel's default `/broadcasting/auth` (auto-registered by `withRouting(channels:...)` in `bootstrap/app.php`) lives under the `web` middleware group — it requires CSRF + session, which is incompatible with a Bearer-token SPA. The API route (`routes/api/v1/broadcasting.php`) mirrors the same `Broadcast::auth($request)` call but under `api` middleware (JWT guard):

```php
Route::post('broadcasting/auth', function (Request $request) {
    return Broadcast::auth($request);
})->middleware(['auth:api'])->name('api.broadcasting.auth');
```

This delegates to the same channel authorization callbacks in `routes/channels.php` — no duplication of channel logic. Blade continues using the web-middleware `/broadcasting/auth` with cookie + CSRF; the API route is additive and does not affect Blade.

## Cross-tab auth (multi-tab session sync)

The SPA keeps a logged-in session alive across browser tabs without forcing re-login.

1. **HttpOnly `spa_jwt`** — the browser shares the cookie across tabs on the Vue-Workers origin. JS does not persist the JWT (`mw_auth` leftover keys are cleared on hydrate). `remember` only affects `Max-Age` via API `expires_in`.
2. **`GET auth/me` on hydrate** — each tab learns `user` from the cookie, not from `localStorage`.
3. **`BroadcastChannel('monolith-auth')` coordinated refresh** — when multiple tabs' refresh timers fire near-simultaneously, only one tab performs `POST auth/refresh`; the others wait for `refresh-done`. This prevents the refresh race where two tabs each call refresh, one blacklists the other's token, and both tabs lose. Messages: `refresh-request`, `refresh-response`, `refresh-done` (`ok` + `user` / expiry — **no JWT**), `logout`.

`clearAuth()` broadcasts `logout` so other tabs drop in-memory `user`. The Worker expires `spa_jwt` on `POST auth/logout`.

### Backend `remember` preservation

`AuthService::refreshFromToken` preserves the `remember` flag across refresh so a remember-me session does not silently downgrade to a short-TTL session on every refresh. The flag is resolved with priority:

1. `remember` in the refresh request body (SPA opt-in per refresh).
2. `rm` custom claim on the original token (set at login, persisted across refreshes via `jwt.persistent_claims`).
3. Fallback `false`.

## Live-update patterns

The SPA uses two distinct live-update mechanisms, mirroring Monolith Blade's architecture:

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

**Reusable hook:** `src/composables/useListingLiveUpdate.ts` — encapsulates registration + polling fallback in one call:

```ts
import { useListingLiveUpdate } from '@/composables/useListingLiveUpdate'

// Inside <script setup>:
useListingLiveUpdate('users-table', () => fetchList())
```

This registers on mount, unregisters on unmount, and starts a 60s visibility-gated polling fallback when Echo is not connected. The poll self-stops once Echo connects.

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
import { useFileHostingRealtime } from '@/composables/useFileHostingRealtime'

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
import { usePaymentRealtime } from '@/composables/usePaymentRealtime'

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

Wired into `CheckoutPanel.vue` with 15s polling fallback (down from previous 60s). Sync-status provider is parsed from `checkoutResult.gateway` or `invoice.payment_gateway.name`. If neither is present, skip the call — do **not** default to `midtrans`. Blade resolves the provider in the web hop (`InvoiceCheckoutGatewayResolver`); the SPA has no `defaultForInvoice()` equivalent.

#### vTual claim — inline in `VtualVtuberClaimView.vue`

Subscribes to `private-vtual.claim.{claimId}` for `.claim.progress` events. Handles phase transitions (`started`, `verify_queued`, `verify_processing`, `verify_failed`, `verified`, `cancelled`, `max_attempts_blocked`). 5s polling stays active as fallback alongside Echo.

### Channel/event reference

| Domain | Channel | Event | Composable |
|--------|---------|-------|------------|
| Notification bell | `App.Models.User.{userId}` | `.activity-notification.received` | `useNotifications.ts` |
| File Hosting | `file-hosting.user.{userId}` | `.listing.changed`, `.quota.changed`, `.members.changed`, `.activity.appended`, `.collection.analysis.progress`, `.asset.metadata.progress` | `useFileHostingRealtime.ts` |
| Payments invoice | `payments.invoice.{invoiceId}` | `.invoice.status.changed` | `usePaymentRealtime.ts` |
| Payments routing | `payments.routing` | `.routing.catalog.changed` | `usePaymentRealtime.ts` |
| vTual claim | `vtual.claim.{claimId}` | `.claim.progress` | `VtualVtuberClaimView.vue` (inline) |

### Polling fallback convention

All realtime surfaces follow the same degraded-mode pattern:

1. On mount, attempt Echo subscription.
2. If Echo not connected (`echoConnected === false`), start visibility-gated polling (interval varies: 15s for payments, 30s for file hosting, 60s for bell-driven listings).
3. Poll callback checks `echoConnected` — if Echo connects mid-poll, the poll self-stops.
4. On unmount, unsubscribe from Echo + clear poll timer.

## Payment UI components

The SPA ports Monolith's payment Blade components to Vue:

- **PaymentStatusHero** (`src/components/PaymentStatusHero.vue`) — hero banner with eyebrow, title, amount, and status badges. Uses `ois-hero` CSS classes.
- **PaymentSteps** (`src/components/PaymentSteps.vue`) — 3-step progress indicator (Choose method → Pay → Confirmation). Uses `ois-steps` CSS classes.
- **CheckoutPanel** (`src/components/CheckoutPanel.vue`) — invoice checkout with payment channel selection. Sync-status uses the gateway from the checkout payload or the invoice resource; unknown provider → skip.

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
| Discord verify on the **creator claim** page is always painted | SPA cannot read `vtual.claim` Discord-verify settings | API 422s; bio verify still works. Profile manage **does** hide Kick and shows Connect vs Verify from `GET profile/connections`. |
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
| Payment channel client search; menus client DataTable | Accepted B | |
| Blade JS `purchase-pricing-preview.js` fee re-quote | Blade also does this | Baseline mimic, not a new SPA crime |
| SPA cannot call `PaymentGateway::defaultForInvoice()` | Blade web hop resolves a missing gateway; SPA skips sync when provider is unknown | Skip is safe; poll resumes after checkout returns `gateway` |

**Correct consumer pattern (keep):** `quota.can_create_workspace`, `slots.can_create`, `claim.can_verify`, flattened channel quotes, invoice fee snapshots, `*_formatted`.

