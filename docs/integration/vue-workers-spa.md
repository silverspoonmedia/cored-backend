# Leading SPA frontend — Monolith-Vue-Workers

Monolith-Vue-Workers (`monolith-vue-workers` sibling) is the **leading SPA frontend**. Backend (the Laravel repo) and this SPA are separate projects; that split is the intended end state. It consumes the Monolith `/api/v1/*` API. This doc is the integration contract between the SPA and the API.

The Blade admin in Monolith stays as the in-repo frontend **until this port actually succeeds** — do not remove it; it is the reference for appearance and behavior. See [web/blade-admin.md](../web/blade-admin.md).

## Architecture

| Piece | Role |
|-------|------|
| Vue 3 + Vite + TypeScript | SPA runtime, SFC components, type safety |
| Cloudflare Workers | Deployment target (`@cloudflare/vite-plugin`). Also the `/api/v1` reverse proxy (hostname mask). |
| Token-based auth | JWT stored in memory + `localStorage` (shared across tabs); sent as `Authorization: Bearer` header (no HttpOnly cookie — that is Blade-only). Cross-tab sync via `storage` event + `BroadcastChannel` coordinates refresh so only one tab refreshes; `remember` flag only affects TTL, not storage location. |
| `fetch` wrapper (`src/api/client.ts`) | Same-origin `/api/v1/...` via `src/api/baseUrl.ts` (optional `VITE_API_BASE_URL` absolute override). Injects Bearer token, handles 401 refresh + retry, parses `{ status, message, data }` envelope. `getBlob()` is the non-JSON path (CSV / file download). |
| `vue-i18n` | Full locale system (`en`, `id`, `ja`) converted from Laravel PHP lang files |

Base URL: empty `VITE_API_BASE_URL` (default). Composable paths are relative (`auth/me`); the client prefixes `/api/v1`. The Worker forwards that to `{MONOLITH_API_ORIGIN}/api/v1/...`. Set `VITE_API_BASE_URL` to an absolute prefix (`https://monolith-ph24.spnd.uk/api/v1`) only as an emergency bypass — DevTools will then show the monolith host.

## API hostname mask

The browser calls **same-origin** `/api/v1/*`. DevTools Network shows the Vue-Workers origin, not the monolith host. `@cloudflare/vite-plugin` runs `server/index.ts` in `npm run dev` **and** on deploy — one proxy, both environments.

This is **hostname masking, not a control.** Paths stay `/api/v1/auth/login`. JWT stays in `localStorage`. The Worker **is** the public API. `MONOLITH_API_ORIGIN` lives in Wrangler `vars` / `.dev.vars` (not `VITE_`, not the JS bundle).

Still naked (browser talks to these hosts directly):

- Pusher / Reverb WebSockets (`VITE_PUSHER_*`)
- Discord OAuth + the Monolith web callback (`SPA_URL` return)
- File Hosting presigned / gateway byte URLs
- hCaptcha JS

Local gotcha: Vite env files stack — `.env.local` **overrides** `.env` (gitignored via `*.local`). Restarting `npm run dev` does nothing if `.env.local` still has `VITE_API_BASE_URL=https://…/api/v1`. Leave that key **empty in both files**. Put `MONOLITH_API_ORIGIN` in `.dev.vars` (copy `.dev.vars.example`). An absolute Vite API URL means the browser talks to the monolith host directly; the Worker never sees the request. After changing env files, restart Vite and hard-refresh the browser (env is baked at compile).

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
- **Nullable fields** — Laravel may return `null` where a type expects a string. Use `string | null` in TS types.
- **UUIDs** — all model IDs are UUID v7 strings (see [platform/identifiers.md](../platform/identifiers.md)). Never type IDs as `number`.

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
- `prompt()` / `confirm()` dialog text

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

### ConfirmModal

```vue
<ConfirmModal
  v-model="showConfirm"
  :title="t('title_key')"
  :confirm-label="t('confirm_key')"
  :confirm-variant="'danger'"
  @confirm="handleConfirm"
  @cancel="showConfirm = false"
>
  <p>{{ t('message_key') }}</p>
</ConfirmModal>
```

| Prop | Type | Notes |
|------|------|-------|
| `v-model` | `boolean` | Controls visibility. NOT `:show`. |
| `title` | `string` | |
| `message` | `string` | Rendered in `<p>` when no slot content |
| `confirm-label` | `string` | NOT `confirm-text` |
| `cancel-label` | `string` | |
| `confirm-variant` | `string` | Bare name: `'danger'`, `'success'`, `'primary'`, `'warning'`. NOT `'btn-danger'` — component prepends `btn-`. |

Default slot overrides `<p>` message when slot content is provided.

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

`src/api/client.ts` `get()` always sets `Accept: application/json` and `res.json()`. CSV and other attachments use `getBlob()` (same Bearer + 401 refresh, `Accept: */*`) plus `src/utils/download.ts` `saveBlob()`. Wired for `GET invoices/export` (invoices list and income page; income sends `status=paid` plus optional `paid_from`/`paid_to`) and `GET microstock/collections/{folder}/download`.

### OverlayScrollbars

Sidebar CSS is bundled in `src/assets/vendor.css`. JS init lives in `useSidebar.init()` (called from `AppLayout` on mount): desktop only (`max-width: 991.98px` skipped), target `.sidebar-wrapper`, `os-theme-dark` / `os-theme-light` from `data-bs-theme`, `autoHide: 'leave'`. Theme changes call `syncOverlayScrollbarTheme()`. Destroy on `AppLayout` unmount. Auth layout has no sidebar wrapper — skip.

### hCaptcha

Login, register, forgot-password, and 2FA challenge mount `HcaptchaWidget` (invisible). The widget is a no-op unless `VITE_HCAPTCHA_SITE_KEY` is set. When the key is present, `execute()` runs before submit and the token is sent as **`h-captcha-response`** on the same API FormRequests Blade uses (`ValidatesHcaptcha`).

The SPA cannot read `system_settings`. Treat `VITE_HCAPTCHA_SITE_KEY` as the enabled gate. If the API has `platform.hcaptcha.enabled` on and the SPA env key is missing, those endpoints 422 — same class of misconfig as Blade missing `HCAPTCHA_SITEKEY`.

### File Hosting Drive UX

Browse/trash/show use `.fh-manager` (CSS: `src/assets/file-hosting.css`, imported from `main.css`). Vue Router stays three routes — this is **not** the Blade single-island soft-nav shell. JWT composables (`useFileHosting`, `useFileHostingRealtime`) are unchanged.

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

SPA routes: `/payment/return` and `/payment/cancel` (`PaymentReturnView`, `requiresAuth`). Blade uses `/payment/ipaymu/return|cancel`. Paths do **not** need to match Blade — configure the payment gateway (and any SPA-origin env the API uses for return URLs) to the Vue-Workers origin. JWT in `localStorage` survives the gateway bounce; `beforeEach` keeps `redirect=fullPath`. Do not reopen these as guest routes unless a real gateway hit arrives without a token. These stay at the **site root**, not under `/apps`.

### `/apps` shell

Authenticated `AppLayout` routes are prefixed `/apps/*` (dashboard, users, settings, file-hosting, vtual, invoices, …). `/` redirects to `/apps/dashboard`. Route **names** stay unprefixed — sidebar `NavTree` / `AppNavbar` already `push` by name.

**Not prefixed** (same exceptions as Blade): auth (`/login`, `/register`, `/forgot-password`, `/reset-password`, `/two-factor-challenge`), public share (`/s/f/:id`, `/s/d/:id`), payment bounce (`/payment/return`, `/payment/cancel`).

Legacy bare paths (`/users` → `/apps/users`, …) redirect so bookmarks do not 404. Catch-all 404 stays last, inside `AppLayout`.

### Settings menu names

Every Settings screen is ported (`SettingsFormView` on `/apps/settings/:group`). MenuSeeder children are `settings.platform`, `settings.file-hosting`, `settings.microstock`, `settings.vtual`. The SPA registers those **as named routes** (`settings.platform.view`, …) pointing at the same SFC, **before** the parameterized `settings.group.view` (used for `identity` and any future registry group). Sidebar `hasRoute(node.route + '.view')` is what makes the click work — a redirect-only alias would lose `isActive`. Do **not** add `identity` to MenuSeeder.

Static vTual credential probe (`/apps/vtual/platform-credentials/probe`) is registered **before** `/apps/vtual/platform-credentials/:platformId` so `/probe` is not captured as a platform id.

### 404 catch-all

Last route: `path: '/:pathMatch(.*)*'` → `NotFoundView` inside `AppLayout`, `meta: { requiresAuth: true, titleKey: 'errors.not_found' }`. This is an admin SPA — unknown URLs for guests already hit `beforeEach` → login with `redirect`. After login they see 404 **inside** the sidebar, not a naked page. Catch-all stays below `/s/f/:id`, `/payment/return`, and the legacy `/users` → `/apps/users` redirects.

### Mimic doctrine

Mimic is **appearance + behavior + intent**, done well and safely — not a pixel clone of Blade DOM, routes, or libraries.

Monolith is API-first. One list endpoint, two presentations via `display_mode`:

- `query` — Eloquent-shaped JSON (SPA `DataTable.vue` / `useResource.ts`). Optional `page` / `per_page` / `search` → `{ items, pagination }`; omit them → legacy flat `T[]`.
- `datatable` — same rows, Yajra-shaped for Blade DataTables

The SPA fetches and parses **as-is**. Do not port Yajra JS. Do not invent a second list API.

### Accepted divergences (A / B / C)

These are the safe zone. Keep the current approaches.

**A — Platform.** JWT in `localStorage`, `POST /api/v1/broadcasting/auth`, no CSRF on Bearer, hCaptcha gated by `VITE_HCAPTCHA_SITE_KEY`, duplicate vue-i18n JSON, CF Workers origin, Worker `/api/v1` hostname mask, breadcrumb config map, guest cannot read `system_settings`. Mimic the session and realtime intent, not the cookie.

**B — Intentional consumer choices.** Vue `DataTable` + `display_mode=query` (server page of 10 when paging params are sent). Select2 only for server-side lookup (`MenuEditView` AJAX parent/roles/users). Navigation via `GET navigation/menus?layout=sidebar`. File Hosting three Vue routes vs Blade island; team as a page vs offcanvas; listing-only move picker; rate editor re-fetch instead of full reload. Mimic intent, not Blade DOM ids (`#fhTeamOffcanvas`, `#fhCreateWorkspaceModal`, `#fhMicrostockPanel`).

**C — Gray (optional later, not a product blocker).** Live-reload on 2 of 7 catalogs (`NotificationType::tableId()` only emits those two), local row-action chrome vs query-mode `actions[]` (deletes on users / inference / gateways / services are wired locally with Blade-equivalent permission + confirm; hierarchy still 403s from the API), Laravel plural pipe hack.

Do not treat a read-only show-page microstock card as “review is done.” Review is `FhMicrostockReviewModal` consuming `GET|PATCH microstock/assets/{file}/metadata`.

## Realtime (Laravel Echo + Pusher)

The SPA supports realtime updates via Laravel Echo and Pusher, with polling fallback.

- **Composable**: `src/composables/useEcho.ts` — singleton `Echo<'pusher'>` (`EchoOptions<'pusher'>`), reads Pusher config from `VITE_PUSHER_*` env vars, JWT auth via custom authorizer → `broadcasting/auth`. Connection flag uses `connector.onConnectionChange` / `connectionStatus()`. Subscribe via `getEcho()`; `useEcho()` exposes `init` / `disconnect` / `connected` only.
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
| `MONOLITH_API_ORIGIN` | Worker-only. Monolith origin (no `/api/v1` suffix, no trailing slash). Wrangler `vars` + `.dev.vars`. **Not** bundled into the browser. Empty → Worker 503. |
| `VITE_API_BASE_URL` | Optional absolute API prefix baked into the client. **Leave empty in `.env` and `.env.local`** so calls stay same-origin `/api/v1` (Worker mask). `.env.local` wins. Set only to bypass the proxy. |
| `VITE_PUSHER_APP_KEY` | Pusher app key (required for Echo — must match Monolith `PUSHER_APP_KEY`) |
| `VITE_PUSHER_APP_CLUSTER` | Pusher cluster (default `mt1`) |
| `VITE_PUSHER_HOST` | Self-hosted/Reverb host (omit for Pusher-hosted) |
| `VITE_PUSHER_PORT` | WebSocket port (default `443`) |
| `VITE_PUSHER_SCHEME` | `https` or `http` (default `https`) |
| `VITE_HCAPTCHA_SITE_KEY` | hCaptcha site key. When set, guest auth forms run the invisible widget and POST `h-captcha-response`. When unset, the widget is omitted (API must also have hCaptcha off or login 422s) |

Discord OAuth return is **not** a Vite var. Set `SPA_URL` on the Monolith API host to this SPA's public origin (Vite/Workers URL). Empty `SPA_URL` → `GET auth/discord/authorize-url` 503.

Values must mirror Monolith's `.env` `PUSHER_*` literals (and `HCAPTCHA_SITEKEY` when hCaptcha is on). `.env.local` overrides `.env` for every `VITE_*` key (that is why an old absolute `VITE_API_BASE_URL` survived Vite restarts). Worker origin is `.dev.vars` `MONOLITH_API_ORIGIN`, not a `VITE_` var.

When `VITE_PUSHER_APP_KEY` is unset, Echo is not initialized and all realtime-dependent features fall back to polling.

### Auth

Echo private channel auth uses a dedicated API broadcast auth endpoint: `POST /api/v1/broadcasting/auth` (same-origin Worker proxy; optional `VITE_API_BASE_URL` override). The request carries `Authorization: Bearer {jwt}` header with the JWT from the auth store's `token` ref via a `tokenProvider` callback.

**Why a dedicated API route?** Laravel's default `/broadcasting/auth` (auto-registered by `withRouting(channels:...)` in `bootstrap/app.php`) lives under the `web` middleware group — it requires CSRF + session, which is incompatible with a Bearer-token SPA. The API route (`routes/api/v1/broadcasting.php`) mirrors the same `Broadcast::auth($request)` call but under `api` middleware (JWT guard):

```php
Route::post('broadcasting/auth', function (Request $request) {
    return Broadcast::auth($request);
})->middleware(['auth:api'])->name('api.broadcasting.auth');
```

This delegates to the same channel authorization callbacks in `routes/channels.php` — no duplication of channel logic. Blade continues using the web-middleware `/broadcasting/auth` with cookie + CSRF; the API route is additive and does not affect Blade.

## Cross-tab auth (multi-tab session sync)

The SPA keeps a logged-in session alive across browser tabs without forcing re-login. Three mechanisms work together in `src/stores/auth.ts`:

1. **Always `localStorage`** — JWT is stored in `localStorage` (never `sessionStorage`). The `remember` flag only affects TTL (remember = longer refresh window via `jwt.remember_ttl`), not storage location. This eliminates the per-tab isolation that caused Tab B to start empty.

2. **`storage` event listener** — when Tab A writes a new token to `localStorage`, Tab B's `storage` event fires. Tab B updates its in-memory `token` / `user` / `expiresAt` / `remember` refs and reschedules its refresh timer. A `null` newValue (cleared by another tab) clears the local session.

3. **`BroadcastChannel('monolith-auth')` coordinated refresh** — when multiple tabs' refresh timers fire near-simultaneously, only one tab performs `POST auth/refresh`; the others wait for the `refresh-done` broadcast. This prevents the refresh race where two tabs each call refresh, one blacklists the other's token, and both tabs lose. Messages: `refresh-request` (tab asks if anyone is already refreshing), `refresh-response` (active refresher says "I'm on it"), `refresh-done` (new token or null on failure), `logout` (coordinated logout).

`clearAuth()` broadcasts `logout` so other tabs clear their in-memory state without each independently nuking `localStorage`. The `storage` event handles the actual key removal sync.

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
- Sidebar card with: quantity editor (addon only, same `purchase_max_quantity` cap), change type, list/credit/display/settlement amounts, FX rate, period, entitlement status, stack quantity equation
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

