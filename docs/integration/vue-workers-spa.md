# External SPA port — Monolith-Vue-Workers

Separate Vue 3 SPA that consumes the Monolith `/api/v1/*` API. Lives in a sibling repository (`monolith-vue-workers`), not inside the Monolith codebase. This doc is the integration contract between the SPA and the API.

For the Blade admin + Vue islands frontend, see [web/blade-admin.md](../web/blade-admin.md).

## Architecture

| Piece | Role |
|-------|------|
| Vue 3 + Vite + TypeScript | SPA runtime, SFC components, type safety |
| Cloudflare Workers | Deployment target (`@cloudflare/vite-plugin`) |
| Token-based auth | JWT stored in memory + `localStorage` (shared across tabs); sent as `Authorization: Bearer` header (no HttpOnly cookie — that is Blade-only). Cross-tab sync via `storage` event + `BroadcastChannel` coordinates refresh so only one tab refreshes; `remember` flag only affects TTL, not storage location. |
| `fetch` wrapper (`src/api/client.ts`) | Builds URLs from `VITE_API_BASE_URL`, injects Bearer token, handles 401 refresh + retry, parses `{ status, message, data }` envelope |
| `vue-i18n` | Full locale system (`en`, `id`, `ja`) converted from Laravel PHP lang files |

Base URL: `VITE_API_BASE_URL` (e.g. `https://monolith-ph24.spnd.uk/api/v1`). All API call paths in composables are relative to this base.

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

### Response shape gotchas

| Endpoint | Returns | Gotcha |
|----------|---------|--------|
| `GET notifications/unread-count` | `{ unread: number }` | Field is `unread`, not `count` |
| `GET microstock/billing` | `balance_micro_usd`, `balance_usd`, `estimated_per_analysis_micro_usd`, `estimated_per_analysis_usd`, `daily_free: { enabled, limit, used, remaining, resets_at }` | Field names are `balance_usd` / `estimated_per_analysis_usd`, not `balance_formatted` / `estimate_formatted`. Daily free uses `used`/`limit`, not `slots_consumed_today`/`daily_limit`. |
| `GET admin/product-activations` (index) | Flat `ProductActivationResource[]` in `data` | No `{ data, pagination }` wrapper — `res.data` is the array directly |
| `GET admin/product-activations/products/{productKey}` | `{ summary, activations, pagination }` in `data` | This one DOES have a wrapper — opposite of index |
| `GET payment-channels?subtotal=` | `PaymentChannelResource[]` with flattened quote fields | Quote fields (`estimated_channel_fee`, `estimated_channel_fee_formatted`, `fee_label`, etc.) are at top level, NOT nested under `checkout_quote`. See [payment-gateway](../domains/payment-gateway/README.md#paymentchannelresource-checkout-quote-flattening). |

## Type alignment

TS types in `src/api/types.ts` must match Laravel API Resources exactly. No transformation layer.

### Conventions

- **snake_case everywhere** — Laravel returns snake_case, TS uses snake_case. No camelCase conversion.
- **API envelope** — every response is `{ status: 'success'|'error', message: string, data: T }`. The `data` field is the payload.
- **Date triplets** — date fields come as `{ key (ISO string), key_formatted (human-readable), key_human (relative) }`. All three are pre-formatted strings; never call `new Date(apiField)`.
- **Nullable fields** — Laravel may return `null` where a type expects a string. Use `string | null` in TS types.
- **UUIDs** — all model IDs are UUID v7 strings (see [platform/identifiers.md](../platform/identifiers.md)). Never type IDs as `number`.

### Pagination shapes

Three patterns exist in the API:

1. **Flat array** — `ProductActivationResource::collection(...)` returned directly. `data` is `T[]`. No pagination metadata.
2. **`{ items, pagination: { current_page, last_page, per_page, total, has_more } }`** — used by microstock billing ledger/usage-events, inference usage events.
3. **`{ summary, activations, pagination: { current_page, last_page, per_page, total } }`** — product activation per-product show endpoint. `has_more` is not returned here.

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

## Realtime (Laravel Echo + Pusher)

The SPA supports realtime updates via Laravel Echo and Pusher, with polling fallback.

- **Composable**: `src/composables/useEcho.ts` — singleton Echo instance, reads Pusher config from `VITE_PUSHER_*` env vars, handles JWT auth via `broadcasting/auth` endpoint.
- **Notification bell**: `src/components/AppNavbar.vue` — inits Echo on mount, subscribes to `App.Models.User.{id}` private channel for `.activity-notification.received` events. Polling (60s) starts as fallback if Echo fails to connect within 30s grace window. On each WS event, the bell also calls `fetchList(5)` to refresh its own recent-notification dropdown content (capped at 5 — the full list is fetched on dropdown open or navigation to `/notifications`).
- **File Hosting**: `src/composables/useFileHostingRealtime.ts` — singleton channel manager for `file-hosting.user.{userId}` (`.listing.changed`, `.quota.changed`, `.members.changed`, `.activity.appended`, `.collection.analysis.progress`, `.asset.metadata.progress`). Multiple views register handlers; the channel is subscribed once and torn down when the last handler unregisters. See [Pattern B](#pattern-b-direct-channel-subscription) below.
- **Microstock**: collection mutations (`lockCollection`, `analyzeCollection`, `fetchCollectionStatus`, `exportCollection`) live in `useMicrostock.ts`; realtime subscriptions go through `useFileHostingRealtime.ts` (same channel, Microstock events).
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
| `VITE_API_BASE_URL` | Monolith API base (e.g. `https://monolith-ph24.spnd.uk/api/v1`) — used for both API calls and the broadcast auth endpoint |
| `VITE_MONOLITH_WEB_URL` | Monolith web root (e.g. `https://monolith-ph24.spnd.uk`) — used for web OAuth redirects and Blade links |
| `VITE_PUSHER_APP_KEY` | Pusher app key (required for Echo — must match Monolith `PUSHER_APP_KEY`) |
| `VITE_PUSHER_APP_CLUSTER` | Pusher cluster (default `mt1`) |
| `VITE_PUSHER_HOST` | Self-hosted/Reverb host (omit for Pusher-hosted) |
| `VITE_PUSHER_PORT` | WebSocket port (default `443`) |
| `VITE_PUSHER_SCHEME` | `https` or `http` (default `https`) |
| `VITE_HCAPTCHA_SITE_KEY` | hCaptcha site key (optional) |

Values must mirror Monolith's `.env` `PUSHER_*` literals. `.env.local` may override `VITE_API_BASE_URL` for local development.

When `VITE_PUSHER_APP_KEY` is unset, Echo is not initialized and all realtime-dependent features fall back to polling.

### Auth

Echo private channel auth uses a dedicated API broadcast auth endpoint: `POST {VITE_API_BASE_URL}/broadcasting/auth` (i.e. `POST /api/v1/broadcasting/auth`). The request carries `Authorization: Bearer {jwt}` header with the JWT from the auth store's `token` ref via a `tokenProvider` callback.

**Why a dedicated API route?** Laravel's default `/broadcasting/auth` (auto-registered by `withRouting(channels:...)` in `bootstrap/app.php`) lives under the `web` middleware group — it requires CSRF + session, which is incompatible with a cross-origin Bearer-token SPA. The API route (`routes/api/v1/broadcasting.php`) mirrors the same `Broadcast::auth($request)` call but under `api` middleware (JWT guard, CORS-covered via `api/*`):

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

Wired into `CheckoutPanel.vue` with 15s polling fallback (down from previous 60s).

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
- **CheckoutPanel** (`src/components/CheckoutPanel.vue`) — invoice checkout with payment channel selection.

CSS for these components is in `src/assets/order-invoice-show.css`, ported from Monolith's `resources/css/payments/order-invoice-show.css`. Uses Bootstrap 5 CSS variables for dark mode compatibility.

## Service checkout flow

`ServiceShowView.vue` includes a checkout form that mirrors Monolith's `services/show.blade.php`:

- Currency select (populated from service prices)
- Quantity input (shown only for addon plan kind)
- Start checkout button (gated by `purchase service` or `initiate payment` permission)
- On submit, calls `POST services/{id}/checkout` API and redirects to the purchase detail page

`ServicePurchaseShowView.vue` renders the full purchase detail with:
- `PaymentStatusHero` with purchase status and payment status badges
- `PaymentSteps` indicator (current step based on invoice status)
- Grace banner alert when entitlement is in grace period
- Sidebar card with: quantity editor (addon only), change type, list/credit/display/settlement amounts, FX rate, period, entitlement status, stack quantity equation
- `CheckoutPanel` for invoice payment

