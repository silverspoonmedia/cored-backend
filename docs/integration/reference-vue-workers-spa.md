# SPA integration contract - reference

Deep dive for the cross-repo contract. Brief: [vue-workers-spa.md](vue-workers-spa.md). Orientation: [project-overview.md](project-overview.md).

## Architecture

Browser → SPA origin (static Worker) → **direct** `/api/v1/*` on the Laravel API origin. JWT is the HttpOnly `__Host-spa_jwt` cookie (`SameSite=None; Secure`). JS never stores the JWT. Mutations send `X-CSRF-Token` (double-submit `__Host-spa_csrf`). Every versioned API call sends public `X-SPA-Origin: 1` (`RequireSpaRequestHeader`; not auth). `RequireSpaOrigin` is audit-only.

```
Browser → Cored Frontend SPA → /api/v1/* (cookie + CSRF + marker)
Cored web stack → server-only redirects (share, payment return, OAuth callback)
```

## Direct API

`AuthenticateWithSpaCookie` binds the cookie to the `api` guard. `VerifySpaCsrf` accepts header==cookie (`419` JSON envelope on mismatch). Auth-session paths skip CSRF: login, register, refresh, logout, forgot/reset password, two-factor login. Bearer requests skip CSRF. CORS: `config/cors.php` `supports_credentials` + allowlist `APP_URL` + `SPA_URL` + `CORS_EXTRA_ORIGINS` (no `*`); `exposed_headers` is `Content-Disposition` so SPA blob downloads can read the server filename. Preflight `max_age` 600. `OPTIONS` and provider webhooks bypass the SPA marker. Auth-error responses still get CORS stamps (`StampApiCors`), and `AllowlistCorsService` (bound over `Fruitcake\Cors\CorsService`) makes the allowlist — not the library's single-origin shortcut — decide whether an allow-origin header is emitted. Probe: `tests/Feature/Http/CorsOnAuthErrorProbeTest.php`, regression `tests/Feature/Http/CorsOriginsTest.php`. Full perimeter notes: [security.md — CORS](../platform/security.md).

Cross-origin requests can also fail client-side with a bare `TypeError` (a cross-tab `Set-Cookie` collision on the shared CSRF nonce, which the browser reports as a CORS error) — that is a SPA retry concern, not a CORS misconfiguration; policy: sibling `cored-frontend/docs/api-client.md#cross-tab-cookie-race-retry`.

Still naked (browser talks to these hosts directly): Pusher/Reverb, Discord OAuth + Cored web callback, a product's presigned/gateway URLs, hCaptcha JS.

Local: Vite `.env.local` overrides `.env`. Empty `VITE_API_BASE_URL` = same-origin dev. Discord return is **not** a Vite var — set the backend `SPA_URL`.

## Worker invocation vs static assets

The SPA Worker serves HTML/JS/CSS and a hardened 404 for stray `/api/*`. It does **not** proxy API or auth. Do not add cron, queues, Durable Objects, or API proxy paths. Details: sibling [worker.md](../../../cored-frontend/docs/worker.md).

## Invocation budget controls

Data traffic does not hit this Worker. Remaining controls trim Laravel load:

- **`POST /api/v1/batch`** — up to 10 read-only (`GET`/`HEAD`) sub-requests; throttle `30,1`; mutations `422` per entry. SPA `batch()` in `src/api/client.ts`. Rules: [api-aggregation.md](../platform/api-aggregation.md).
- **Shell hydrate** — `GET auth/me` then, on success, one batch (me, sidebar, navbar, unread-count, recent notifications). Guests never `POST /batch`. Echo starts **after** that batch. Sibling [auth-session.md](../../../cored-frontend/docs/auth-session.md).
- **Per-domain hydrates** — navigation/notification/user/service/payment/inference edit screens (a product adds its own hydrate). Failed subs `null` → individual loader; terminal `401`/`403`/`429` skip fallback (`TerminalBatchError`).
- **JWT TTL 240** — `setting_config('jwt.ttl')`. Realtime only (no polling); reconnect fires a resync bus.

## Local dogfood checklist

Daily driver: SPA `npm run dev` at `http://localhost:5173`, backend `SPA_URL="http://localhost:5173"`. Login JSON has **no** `token`. Cookie-authenticated mutations carry `__Host-spa_jwt` + `X-CSRF-Token`. Two tabs, one logout → the other dies. Login lockout: identity-scoped **429** envelope + `Retry-After` — [identity — Login throttle](../domains/identity/reference.md#login-throttle-identity-scoped-failure-only).

## Production URL minting (`SPA_URL`)

`App\Support\SpaPublicUrl` prefixes browser-facing links onto `config('services.spa.url')` when set; otherwise `APP_URL` + surviving server endpoints. CORS origins: `APP_URL` + `SPA_URL` + `CORS_EXTRA_ORIGINS`.

| Surface | Empty `SPA_URL` | Set `SPA_URL` |
|---------|-----------------|---------------|
| Password reset email | `{APP_URL}/reset-password?token=&email=` | `{SPA_URL}/reset-password?token=&email=` |
| Email verify email | Signed `GET /api/v1/auth/email/verify/{id}/{hash}` | `{SPA_URL}/verify-email/{id}/{hash}?expires=&signature=` — SPA GETs the signed API path. HMAC stays on `APP_URL`. |
| iPaymu hosted return/cancel | `{APP_URL}/payment/ipaymu/return\|cancel` | `{SPA_URL}/payment/return` and `/payment/cancel` |

OAuth `return_to` allowlist is that origin plus `APP_URL`; empty `SPA_URL` disables SPA OAuth return. Channel checkout still sends empty `ureturn`. Guest verify is **not** `guestOnly`.

## Notification deep links

API `action_route` + `action_params` use symbolic names from `config/spa-routes.php`. SPA `src/utils/notificationTarget.ts` resolves `${route}.view` and maps `user` / `modelCard` → Vue `:id`. Unknown names fall back to the notifications list.

## Production checklist

Ops (not code): pair `SPA_URL` with the matching Worker origin (production / staging / sandbox — one `SPA_URL` per backend instance); bake `VITE_API_BASE_URL` from tracked `.env.production` / `.env.sandbox` / `.env.staging` (never `wrangler.jsonc` `vars`); hCaptcha on both sides or both off; a product's Gateway CORS exposes `ETag`; Pusher/Sockudo literals match; Tunnel allowlist + 404 catch-all. Full list: sibling [README.md](../../../cored-frontend/README.md) + [deployment.md](../../../cored-frontend/docs/deployment.md). The mode-file values are guarded by the sibling `npm run check:env` (fails a build on a placeholder, a missing key, a shared origin, or production/staging pointing at a sandbox host) — see sibling [deployment.md — Mode file values](../../../cored-frontend/docs/deployment.md#mode-file-values).

`X-SPA-Origin: 1` is a public anti-noise marker, not auth.

## CI

Sibling `.github/workflows/ci.yml`: `type-check` → `check:i18n` → `check:docs` → `check:env` → `vitest` → `build-only`. No Playwright in this gate.

## API contract reference

Per-domain APIs: [payment-gateway](../domains/payment-gateway/README.md), [notification](../domains/notification/README.md), [product-activations](../domains/product-activations/README.md), [identity](../domains/identity/README.md), [navigation](../domains/navigation/README.md), [inference](../domains/inference/README.md), [ai](../domains/ai/README.md), [service](../domains/service/README.md). A product's endpoints are its own brief.

### Endpoint path gotchas

| SPA composable | Correct API path | Common mistake |
|----------------|------------------|----------------|
| `usePaymentsIncome.fetchOverview` | `GET payments/income` | `payments/income/overview` |
| `usePaymentChannels.syncPolicy` | `POST payment-channels/sync-policy` | missing API route (exists in `payment-gateway.php`) |
| `useRatePlanAdmin.saveRates` | `PUT inference/providers/{id}/rates` | Body `{ rates: [...] }` is a **full replace**. `manage inference catalog`. |
| `useConnections.fetchConnections` | `GET profile/connections` | Not under `auth/` |
| `useConnections.syncProvider` | `POST profile/connections/{oauth_provider}/sync` | Param is `{oauth_provider}` |
| `useConnections.disconnectProvider` | `DELETE profile/connections/{oauth_provider}` | Same constraint |
| `useConnections.authorizeUrl` | `GET auth/discord/authorize-url` | Query `intent` + `return_path` (must start `/apps/`) |

### Response shape gotchas

Canonical envelope: [api-envelope.md](../platform/api-envelope.md). Deviations:

| Endpoint | Returns | Gotcha |
|----------|---------|--------|
| `GET notifications/unread-count` | `{ unread: number }` | Field is `unread`, not `count` |
| `GET admin/product-activations` (index) | Flat `ProductActivationResource[]` in `data` | No `{ data, pagination }` wrapper |
| `GET admin/product-activations/products/{productKey}` | `{ summary, activations, pagination }` in `data` | Opposite of index |
| `GET payment-channels?subtotal=` | Flattened quote fields at top level | Not nested under `checkout_quote` |
| `GET services/options` | `{ billing_intervals, plan_kinds, statuses, purchase_max_quantity }` | Fallback `99` only when the call fails |
| `GET profile/connections` | `{ providers: [...] }` | No top-level `discord_*` fields |
| `POST profile/connections/{oauth_provider}/sync` | Same `{ providers }` as GET | Unknown provider 404 |
| `DELETE profile/connections/{oauth_provider}` | `null` data | Replaces `DELETE …/discord` |
| `GET auth/discord/authorize-url` | `{ url }` | Requires `SPA_URL`. `return_path` is a path |

## Type alignment

TS types in `src/api/types.ts` must match Laravel API Resources exactly. No transformation layer. `ProfileConnections.providers` is `OAuthProviderConnection[]`. Linked-platform rows stay `DiscordConnection`. Use `providerByKey(snapshot, 'discord')`. One interface — do not redeclare `DiscordConnection`.

### Conventions

- **snake_case everywhere** — no camelCase conversion.
- **API envelope** — `{ status: 'success'|'error', message: string, data: T }`.
- **Date triplets** — `{ key, key_formatted, key_human }` as strings; never `new Date(apiField)`.
- **Nullable / omitted** — `string | null` and `?` optional keys for per-viewer Resources.
- **UUIDs** — UUID v7 strings ([identifiers.md](../platform/identifiers.md)). Never `number`.

### Per-viewer payloads

Guest/public endpoints use dedicated Resources. Staff-only fields are optional in `src/api/types.ts`. Backend: [reference-security.md](../platform/reference-security.md#response-data-minimization-mandatory).

### Pagination shapes

1. **Flat array** — `data` is `T[]`. Query-mode indexes **without** `page` / `per_page` / `search`.
2. **`{ items, pagination: { current_page, last_page, per_page, total, has_more } }`** — when the client sends `page` / `per_page` / `search`. Default `per_page` 10 (max 100).
3. **`{ summary, activations, pagination }`** — product activation per-product show. No `has_more`.

## Status badges

One renderer, many maps: SPA `StatusBadge.vue` + `src/utils/statusBadge.ts`. Domain maps merge over defaults. Do not add per-view `statusClass()` or one mega-badge. Details: sibling [ui-patterns.md](../../../cored-frontend/docs/ui-patterns.md). AI connection statuses map through `AI_CONNECTION_STATUS_BADGE` (`active`/`pending`/`disabled`).

## i18n porting notes

Runtime, RTL, lazy chunks, `useI18n()` vs `i18n.global.t`: sibling [i18n.md](../../../cored-frontend/docs/i18n.md). Backend locales: [localization.md](../platform/localization.md). **Agents: never `Read` whole locale JSON files** — Grep the key, patch every supported locale, run `npm run check:i18n`.

Conversion: PHP `trans('web/settings.title')` → `t('web.settings.title')`; `:variable` → `{variable}`. `zh_CN` backend ↔ `zh-CN` SPA.

Translatable **payload** maps (menu `name`/`description`, locale-keyed `Record<string, string>`) must be keyed by the backend code (`zh_CN`), never the SPA code (`zh-CN`) — the backend rejects unknown locale keys. `FormTranslatableInput` converts both directions through `src/i18n`: `translatableValue()` reads a backend-keyed prefill for the row rendered under the SPA code, `normalizeTranslatableMap()` writes backend keys and drops aliases; `MenuEditView` re-normalizes before submit as defense in depth.

## Notifications & confirmations (Sweetalert2)

Swal-only via SPA `src/lib/swal.ts`. Sibling [ui-patterns.md](../../../cored-frontend/docs/ui-patterns.md). Shared keys `ui.swal.*` / `ui.dialog.*`.

## Component conventions

SPA components (`confirmDialog`, `SubmitButton`, `FormInput`, loading/error/empty, breadcrumbs, page titles, DataTable, row actions) are documented in sibling [ui-patterns.md](../../../cored-frontend/docs/ui-patterns.md). Query-mode `actions[]`: [list-display-modes.md](../platform/list-display-modes.md).

### FormSelect2

Wraps jQuery Select2 AJAX against paginated lookups — identity `GET /api/v1/lookups/{users,roles}` and navigation `GET /api/v1/navigation/lookups/menus` — [list-display-modes.md](../platform/list-display-modes.md).

**Multi-value `ids[]` shape:** the SPA must send repeated params (`ids[]=a&ids[]=b`, i.e. pass the array to `apiUrl()`), not a comma-joined string — `ids.* => uuid` rejects the latter with 422. Batch sub-request failures are **not** uniformly silent; the menu-edit hydrate in particular splits three ways (transport fallback to the standalone loaders / `TerminalBatchError` on 401/403/429, never retried standalone / a recorded `failedLookups` key on any other non-200 that warns the admin and leaves the stored pivots untouched). For ajax-backed selects the pre-selected rows arrive **after** mount, so `FormSelect2` appends late `selectedOptions` in place and re-applies the value (no select2 re-init, which would drop the ajax adapter state), and `applyValue()` suppresses its own `change.select2` echo so a select whose `<option>` rows are not hydrated yet can never clear its parent. Both behaviors are specified in [ui-patterns.md — Select2 values must survive a failed hydrate](../../../cored-frontend/docs/ui-patterns.md).

Nested tree selects: ajax `navigation/lookups/menus` (Dynamic Menu CRUD — parent & reference in `MenuEditView`) returns **nested trees, one node per top-level root menu**. Roots are paginated (lazy load = next root group; a group is never split across pages); each root carries its entire subtree as recursive `children`, with `level` per node (0 = root) for indentation. Select2 renders only one `<optgroup>` level natively, so the SPA flattens the tree via `flattenTreeForSelect2()` (`src/utils/select2-lookup.ts`): each root becomes an optgroup whose first selectable leaf is the root itself, followed by descendants indented by `level`. Search (`q`) returns matching branches with their ancestors retained. The hydrate path (`ids[]` for pre-selection) still returns flat rows with `group`/`parent_text`/`level` metadata — see [ui-patterns.md](../../../cored-frontend/docs/ui-patterns.md).

### Blob / CSV downloads


### hCaptcha

`HcaptchaWidget` no-op unless `VITE_HCAPTCHA_SITE_KEY` is set. Token field `h-captcha-response`. Pair with `platform.hcaptcha.enabled`.

## Payment return URLs

SPA `/payment/return` and `/payment/cancel` (`requiresAuth`, site root, not under `/apps`). The backend keeps `/payment/ipaymu/return|cancel` as **server** redirects. `SpaPublicUrl::paymentReturn/Cancel` mints the SPA paths when `SPA_URL` is set.

## `/apps` shell

Authenticated `AppLayout` routes are prefixed `/apps/*`. Route **names** stay unprefixed. **Not prefixed:** auth screens, public share `/s/*`, payment bounce. Verify-email uses `meta.authChrome` (not `guestOnly`). Legacy bare paths redirect. Catch-all 404 is last, inside `AppLayout`.

## Domain SPA surfaces

Do not load this contract for screen-level UX. Use the domain README (Agent entry first) plus SPA views:

- A product's own screens (its feature pack, its realtime channels) ship with the product; this boilerplate has none.
- Inference rate editor: `PUT inference/providers/{id}/rates` full replace (`manage inference catalog`).
- AI connections: SPA `/apps/ai/connections` (`ai.connections.index` / `.create` / `.edit`, sibling of Inference under Administration — not Monitoring). API `/api/v1/ai/connections` CRUD + `POST {id}/test` + `POST {id}/default` (`view`/`manage ai connections`); response carries `secret_hint` + `has_secret` only — never the key. PATCH is partial: blank text = keep, blank `api_key` = keep, explicit `null` on the numeric/header overrides = clear. Detail: [domains/ai README](../domains/ai/README.md).
- Settings: `PUT admin/settings/{group}` sends **underscore** field names (`platform_jwt_ttl`). MenuSeeder children (`settings.platform` plus each product group) are named SPA routes **before** parameterized `settings.group.view`.
- Navigation menus: `POST/PATCH navigation/menus` send `audience` (`all` | `auth` | `role` | `guest`, default `auth` when omitted) and `roles` **only** with `audience=role`; rows return `audience` + `role_ids`. `is_authenticate` / `is_guest_only` were removed — breaking for older SPA builds, so both repos deploy together. Admin surface: [navigation README](../domains/navigation/README.md).
## Mimic doctrine

Mimic is **appearance + behavior + intent**, not a pixel clone of Blade. List endpoints use `display_mode=query` — [list-display-modes.md](../platform/list-display-modes.md). Parse as-is. Do not invent a second list API or client-side money/lifecycle math. Non-blocking SPA decisions: [archive/spa-consumer-decision-findings.md](../archive/spa-consumer-decision-findings.md) — a dated snapshot whose decisions are still binding guidance.

## Realtime

No polling. Echo auto-reconnect is recovery; reconnect fires a resync bus. SPA private channels are owned by `src/composables/echo/channelRegistry.ts` (ref-counted per channel name, attach deferred until Echo exists) through the declarative `createRealtimeChannel` / `createKeyedRealtimeChannel` wrappers — domain `use*Realtime` files are bindings over it, never raw `echo.private()` callers. Outage refetch is `onEchoResync` (one line per view). Sibling [auth-session.md](../../../cored-frontend/docs/auth-session.md), [api-client.md](../../../cored-frontend/docs/api-client.md).

### Env vars

| Var | Purpose |
|-----|---------|
| `MONOLITH_API_ORIGIN` | Legacy. Current Worker does not read it. Not bundled. |
| `VITE_API_BASE_URL` | Laravel API origin plus `/api/v1`. Empty only for local same-origin. |
| `VITE_PUSHER_*` | Echo; must match the backend `PUSHER_*` literals. Unset key → no live updates. |
| `VITE_HCAPTCHA_SITE_KEY` | Public sitekey; empty = no widget. |

Discord return is the backend `SPA_URL`, not a Vite var. Tracked mode files (`.env.production` / `.env.sandbox` / `.env.staging`) win over `.env.local` during `vite build --mode`. Sibling [README.md](../../../cored-frontend/README.md).

### Self-hosted websocket (Sockudo)

Env-only: point `PUSHER_HOST` / `PORT` / `SCHEME` (and `VITE_PUSHER_*`) at Sockudo. Dev stays Pusher-hosted when host is empty.

### Auth

`POST /api/v1/broadcasting/auth` under `auth:api` plus a dedicated `throttle:broadcast` limiter (120/min per user, `bootstrap/app.php`) — one authorize per channel on connect would otherwise compete with regular traffic in the global 60/min limiter. The SPA side holds the burst down too: `useEcho.ts` serializes authorizers (max 1 in-flight) and 419 refreshes are single-flighted (`refreshCsrfOnce()`). SPA authorizer uses `client.post('broadcasting/auth')` so 419 retries. `AppNavbar` awaits `hydrateShell()` **and** the CSRF cookie-settle gate (`whenCsrfCookieQuiet()`) before `initEcho()`; `authorizeBroadcastChannel()` awaits the same gate before each POST, so the first authorize cannot overlap the `__Host-spa_csrf` write from the shell batch. Channel callbacks stay in `routes/channels.php`.

The path is defined once, in `App\Support\Broadcasting\BroadcastAuthEndpoint` (`SUBPATH` for the route file inside the `api/v1` group, `url()` for server-issued client config). `BroadcastConnection::socketClientConfig()` uses it for Echo's `authEndpoint` — a literal `url('/broadcasting/auth')` there pointed at the framework's **web-group twin**, which is CSRF-guarded and (before `config/cors.php` listed `broadcasting/auth`) answered 403 with no CORS headers, i.e. an opaque browser "CORS error". Invariant test: `tests/Feature/Http/CorsBroadcastAuthTest.php`. SPA side: `apiOrigin()` guarantees a `/api/v1` suffix so `resolveApiUrl('broadcasting/auth')` can never fall back to the twin either.

Private-channel lifecycle on the SPA side goes through `src/composables/echo/channelRegistry.ts` (`acquireChannel`, plus the declarative `createRealtimeChannel` / `createKeyedRealtimeChannel`): ref-counted per channel name, attach deferred until Echo exists, dynamic names released on `retarget()`. Composables must not call `echo.private()` / `echo.leave()` directly — `tests/echoConventions.test.ts` enforces it.

## Cross-tab auth (multi-tab session sync)

SPA implementation: sibling [auth-session.md](../../../cored-frontend/docs/auth-session.md). Messages never carry JWT or CSRF nonce.

### Cross-tab notification read-state sync

`useNotifications.startRealtime()` opens `BroadcastChannel('activity-notifications.{userId}')` so mark-as-read propagates without extra HTTP. `{ type: 'read-all' }` zeros unread; `{ type: 'read', id }` decrements. If `BroadcastChannel` is unavailable, other tabs reconcile on the next WS event. Same contract: [notification README](../domains/notification/reference.md#read-state-ui-and-tab-synchronization).

### Backend `remember` preservation

`AuthService::refreshFromToken` preserves `remember`: request body → `rm` JWT claim (`jwt.persistent_claims`) → fallback `false`.

## Live-update patterns

- **Pattern A** — `UserActivityNotificationReceived` on `App.Models.User.{id}` with `table_id` → bell → `useListingReload`. Active: `UsersView` (`users-table`), `InferenceModelCardsView` (`inference-model-cards-table`).
- **Pattern B** — domain private channels (table below).

### Channel/event reference

| Domain | Channel | Event | Composable |
|--------|---------|-------|------------|
| Notification bell | `App.Models.User.{userId}` | `.activity-notification.received` | `useNotifications.ts` |
| Auth self | `App.Models.User.{userId}` | `.auth.state.changed`, `.auth.security.changed` | `useAuthSelfRealtime.ts` |
| RBAC admin lists | `rbac.roles`, `rbac.users` (legacy: `rbac`) | `.rbac.role.list.changed`, `.rbac.user.list.changed`, `.rbac.role.permission.changed` | `useAdminRealtime.ts` |
| Activity log | `logs.activity` | `.log.activity.appended` | `useActivityLogRealtime.ts` |
| Error log | `logs.errors` | `.log.error.recorded` | `useLogsRealtime.ts` |
| Payments invoice | `payments.invoice.{invoiceId}` | `.invoice.status.changed` | `usePaymentRealtime.ts` |
| Payments admin | `payments.admin` | `.invoice.list.changed`, `.payments.gateway.changed`, `.payments.channel.changed`, `.payments.subscription.changed` | `usePaymentRealtime.ts` |
| Payments routing | `payments.routing` | `.routing.catalog.changed` | `usePaymentRealtime.ts` |
| Currencies | `settings.currencies` | `.service.currency.changed` | `useCurrenciesRealtime.ts` |
| Settings | `settings.{group}` | `.settings.group.changed` | `useSettingsRealtime.ts` |
| Services catalog | `services.catalog` | `.service.catalog.changed`, `.service.purchase.changed` | `useServicesRealtime.ts` |
| Inference | `inference.catalog` | `.inference.catalog.changed` | `useInferenceRealtime.ts` |
| AI connections | `ai.connections` | `.ai.connection.changed` | `useAiConnectionsRealtime.ts` |
| Navigation menus | `navigation.menus` | `.menu.tree.changed` | `useMenusRealtime.ts` |
| Navigation (targeted) | user channel | `.menu.tree.changed` + `target_user_id` | `useNavigation.ts` |
| Product activations / domain access | `product-activations`, `domain-access` | `.product.activation.changed`, `.domain.visibility.changed` | `useAdminRealtime.ts` |

All channel callbacks live in `routes/channels.php` (Spatie permission or ownership/access service; admin-facing channels gate on the matching `view *` permission). Payloads are invalidation pings (ids + `action`) — never model snapshots.

## Payment UI / service checkout

Checkout chrome is SPA-only (`CheckoutPanel`, `PaymentStatusHero`). Parse `invoice.checkout_session`; unknown gateway → skip sync (do **not** default to `midtrans`). `POST services/{id}/checkout` then purchase show. Quantity cap from `GET services/options` `purchase_max_quantity`. Settlement/FX rows only when present (`view invoice`).

## Realtime connection convention

Mount → subscribe; updates websocket-only; unmount → unsubscribe.

**Mandatory SPA patterns** (enforced by shared primitives in the sibling repo — do not reimplement per composable):

1. **Composables acquire channels through `src/composables/echo/channelRegistry.ts`** — never `echo.private()`, `echo.leave()`, or a bare `if (!getEcho()) return`. Echo initializes asynchronously in `AppNavbar` (after the shell batch), so views mount before it exists; the registry attaches immediately when Echo is ready and defers via the ready bus otherwise. Declarative wrappers: `createRealtimeChannel<H>({ channel, events })` for a single (possibly dynamic) name, `createKeyedRealtimeChannel<K, H>({ channel: (key) => name, events })` for per-entity families. Guard: `tests/echoConventions.test.ts` + `tests/channelRegistry.test.ts`.
2. **Views register outage refetch via `useEchoResync(fn)`** — one line mirroring the view's realtime-handler loader. Websocket-only realtime means events during a disconnect are lost; the resync bus fires once on reconnect. (Exception: `AppNavbar` registers `onEchoResync` manually because it is outside a setup context.)
3. **Shared channels are ref-counted by the registry, never left unilaterally** — `acquireChannel()` counts holders per channel name and the channel leaves only when the last one releases:
   - `App.Models.User.{id}` — `listenPersonalChannel` (notification bell + `useAuthSelfRealtime`).
   - `navigation.menus` — `useMenusChannel` (`useNavigation` shell + `useMenusRealtime` admin views); leaving a Menus view must not kill the shell's live nav.
