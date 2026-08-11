# Notification — activity bell (role + user)

Hybrid engine: **types/triggers in code**, **audience in DB** (`notification_type_settings` + seeder). Not Laravel’s `notifications` table.

Code: `app/Domain/Notification/`.

## Mental model

| Mode | Audience | Use |
|------|----------|-----|
| **Broadcast (role)** | Role pivots — any current/future role holder | “User created” → Root+Admin |
| **Personal (user)** | User pivots | “Your account was updated” |

One row + pivots; per-user read state in `activity_notification_reads`. Viewer sees if role match **OR** user target.

### User CRUD types

| Type | Mode | Audience | `tableId()` | Fired on |
|------|------|----------|-------------|----------|
| `user_created` | Broadcast | Root+Admin | `users-table` | create |
| `user_updated` | Broadcast | Root+Admin | `users-table` | update |
| `user_deleted` | Broadcast | Root+Admin | `users-table` | delete |
| `account_updated` | Personal | Subject user only | `null` | update (subject ≠ actor) |

Update fires **two** notifications: personal `account_updated` (the edited user learns their account changed) **and** broadcast `user_updated` (drives the live list reload for admins watching `/apps/users`). They are separate on purpose — the personal note must not reach admin bells.

```
Service → ActivityNotificationEvent → SendActivityNotification
        → NotificationService::emit() → resolveAudience() → create + optional websocket pings
```

| Concern | Owner |
|---------|-------|
| Type exists | `NotificationType` enum |
| When to fire | Service (not controller) |
| Message | `lang/{locale}/api/notifications.php` |
| Who receives | Admin UI (`/apps/notification-audiences`) or `notification_type_settings` seeder |
| Deep link | `actionRoute` + `actionParams` on dispatch |

Listener auto-discovered — **do not** double-register.

## Delivery

Tunable in **Administration → Settings → Platform** — see [configuration-policy.md](../../platform/configuration-policy.md).

| Mode | Setting | Primary transport | Fallback |
|------|---------|-------------------|----------|
| Polling | `platform.notifications.delivery` = `polling` | HTTP every `platform.notifications.polling_interval` seconds | None |
| Realtime | `platform.notifications.delivery` = `realtime` + valid Pusher/Reverb | Private WebSocket channel | HTTP after 30 seconds degraded |

### Realtime event contract

After persistence, `NotificationService` resolves every recipient and broadcasts `UserActivityNotificationReceived` on private channel `App.Models.User.{userId}` with event name `.activity-notification.received`:

```json
{
  "type": "user_updated",
  "table_id": "users-table",
  "unread_count": 4
}
```

- `type` identifies the notification.
- `table_id` tells a matching list page which registered DataTable to reload; it is `null` for types without a list screen.
- `unread_count` is the recipient's **absolute** unread count after the new notification is persisted. The bell applies it directly; it does not increment locally and does not call HTTP on each WS event.

**Not this bus:**

- File Hosting live listing uses dedicated private channels (`file-hosting.user.*` / `file-hosting.workspace.*`) and domain events under `App\Domain\FileHosting\Events\` — no `NotificationType`, no `table_id`. See [file-hosting/README.md — Realtime / live listing](../file-hosting/README.md#realtime--live-listing).
- Payment Gateway invoice checkout uses private `payments.invoice.{invoiceId}` + `InvoiceStatusChanged` / `PaymentRealtime` — no `NotificationType`, independent of platform notification delivery mode. See [payment-gateway/README.md — Realtime invoice status](../payment-gateway/README.md#realtime-invoice-status-no-hard-reload).

Both still may share the browser Echo instance (`window.__monolithEcho` / `createNotificationEcho`) when credentials are configured; they do not ride the activity-notification event.

Initial unread count is server-rendered by `NotificationBellComposer` as `initialUnreadCount`. Recent-list copy still refetches `/apps/notifications/recent` when the dropdown is open so localization and deep links remain server-owned. Web proxies live under `prefix('apps')` (route names unprefixed: `notifications.*`); composer builds URLs via `route()`.

**Sidebar:** **Workspace → Notifications** (`notifications.index`) has no menu-role pivot and is available to authenticated viewers who pass the navigation audience rules. API access still requires `view notification`. This page entry is distinct from the navbar activity bell: both use Notification-domain data, but the page is the full history surface while the bell is a compact delivery control.

### Browser health state machine (`bell.js`)

`bell.js` owns the Echo/Pusher connection and exposes current state as:

```js
window.notificationRealtime = {
    enabled: true,
    connected: true,
    degraded: false,
};
```

It also emits window events consumed by list pages:

| Event | Meaning | Client action |
|-------|---------|---------------|
| `notification-realtime:connected` | WS connected/reconnected | Stop badge + DataTable polling; one catch-up reload on reconnect |
| `notification-realtime:degraded` | WS stayed non-connected for 30 seconds | Start badge + registered-page fallback polling every 60 seconds |
| `notification-realtime:polling` | Pure polling mode or failed realtime config (no WS attempt) | Start DataTable fallback polling immediately (badge already polling) |

`connected: false, degraded: false` means connecting or inside the 30-second grace period — **do not poll yet**. While connected, badge and DataTable polling are both off. Reconnect cancels the grace timer, clears fallback intervals, and restores pure WS delivery.

**Script order:** `bell.js` loads from `assets-scripts.blade.php` immediately after `app.js` (gated by `@can('view notification')`), **before** page `$scripts`. List pages (e.g. `/apps/users`) may snapshot `window.notificationRealtime` on init and also listen for the events above. Do not `@push` `bell.js` onto `@stack('scripts')` — that runs after `$scripts` and breaks the pure-polling DataTable fallback race.

### Expected Network behavior

| Situation | `/apps/notifications/unread-count` | `/apps/users/datatable` |
|-----------|---------------------------------|--------------------|
| Initial page with healthy WS | No request; count came from rendered config | Initial load; one connection catch-up reload is possible depending on init order |
| Notification arrives over healthy WS | No request; use payload `unread_count` | One reload only when payload `table_id` is `users-table` |
| Browser focus while WS healthy | No request | No request |
| WS down for less than 30 seconds | No request | No request |
| WS degraded for at least 30 seconds | Poll every 60 seconds | Poll every 60 seconds while `/apps/users` is visible |
| WS reconnect | One count reconciliation | One catch-up reload; polling then stops |
| Single-read action | One count reconciliation | None |
| Mark-all action | No count refetch; response already says `unread: 0` | None |

Seeing periodic unread-count or DataTable requests while `window.notificationRealtime.connected === true` is a regression.

### Pusher host rule

For Pusher-hosted WebSockets, `BroadcastConnection::clientConfig()` exposes `cluster` but leaves `host` null. Laravel's server-side Pusher config defaults `host` to `api-{cluster}.pusher.com`, which is the REST API endpoint — passing it to Echo as `wsHost` silently prevents the socket from connecting and leaves the app permanently on polling. `echo.js` also rejects that REST-host pattern defensively. Explicit non-default hosts remain valid for self-hosted/Reverb-compatible sockets.

### Read-state UI and tab synchronization

`Mark all as read` is an in-place AJAX mutation in both the navbar and `/apps/notifications`. The web response carries `{ marked, unread: 0 }` without a redirect. Shared AJAX infrastructure emits `ajax-form:success`; notification UI then updates the badge, dropdown font weight, page rows/status badges, and button state immediately.

The bell opens a per-user, same-origin `BroadcastChannel` named `activity-notifications.{userId}`:

- `ping` mirrors WS payloads to other tabs so badge and DataTable state stay synchronized without each tab needing an extra HTTP request.
- `read-all` propagates mark-all state to other tabs.

No hard reload, timer hack, or unread-count refetch is required for mark-all. If `BroadcastChannel` is unavailable, the current tab still updates immediately; other tabs reconcile on their next WS event, reconnect, or fallback poll.

## Schema (essentials)

- `activity_notifications` — type, data JSON, action route/params, timestamps  
- Pivots: roles, target users, reads  
- `notification_type_settings` + role/user pivots, `notify_subject_user`

## Audience cache (`NotificationTypeSettingRepository`)

Forever key `notification_type_settings.snapshot`; seeder / `syncRoles` / `syncUsers` flush it. The snapshot stores **plain primitives** (`type → { notify_subject_user, roles[], users[] }`), **never hydrated Eloquent models**. With `CACHE_STORE=database` (or redis) cached values are serialized; caching model graphs there means a class/relation change or cross-host autoload mismatch deserializes into `__PHP_Incomplete_Class`, violating the `Collection` return type and throwing a `TypeError` on the next emit (observed on `account_updated` during `PUT /api/v1/users/{id}`). Model-returning methods (`findByType`/`read`/`listAll`) query the DB fresh; only `resolveAudience` reads the primitive snapshot.

## API / Web

Viewer API endpoints:

| API endpoint | Purpose |
|--------------|---------|
| `GET /api/v1/notifications` | Visible notifications with viewer-specific `is_read` |
| `GET /api/v1/notifications/unread-count` | Absolute count (`{ unread: number }`); polling/reconciliation fallback |
| `POST /api/v1/notifications/{id}/read` | Idempotent single-read |
| `POST /api/v1/notifications/read-all` | Returns `{ marked, unread: 0 }` |

Web routes proxy the API through `ConsumesApi` under `/apps/*`. `NotificationBellComposer` injects URLs, labels, delivery config, safe Pusher client config, viewer ID, and `initialUnreadCount` into `data-notification-config`. The `/apps/notifications` page renders up to 100 visible items and mutates read-state markup in place after mark-all.

## Implementation map

| Layer | File | Responsibility |
|-------|------|----------------|
| Type catalogue | `app/Domain/Notification/Notifications/NotificationType.php` | Type value, icon, role fallback, DataTable ID |
| Emit service | `app/Domain/Notification/Services/NotificationService.php` | Persist audience, calculate per-recipient unread count, dispatch realtime event |
| Realtime event | `app/Domain/Notification/Events/UserActivityNotificationReceived.php` | Private channel/name and `type`/`table_id`/`unread_count` payload |
| Audience settings | `NotificationTypeSettingRepository` + seeder | Resolve role/user/subject audience; primitive cached snapshot |
| Browser config | `NotificationBellComposer` | Initial count, URLs, labels, delivery mode, safe Pusher config |
| Echo client | `resources/js/notifications/echo.js` | Pusher client, private-channel authorizer, hosted/self-hosted endpoint selection |
| Bell state | `resources/js/notifications/bell.js` | Badge/list UI, WS health state machine, fallback polling, DataTable reload, tab sync |
| Users list | `resources/views/adminlte4/users/index.blade.php` | Register `users-table`; health-gated fallback reload |
| Inbox UI | `resources/views/adminlte4/notifications/index.blade.php` | In-place read-all row/status mutation |
| AJAX infrastructure | `resources/js/infra/api-response-handler.js` (via `app.js`) | Emit `ajax-form:success` after successful no-redirect mutation |

## Workflows (agents)

### A — New type (one PR)

1. Enum case + `icon()` / `defaultRoles()` fallback / `tableId()`  
2. Translations `en`/`id`/`ja` under `types.{value}`  
3. `NotificationTypeSettingSeeder` row + roles  
4. `ActivityNotificationEvent::dispatch` from owning **service**  
5. Tests  
6. Optional DataTable live reload (`tableId` + blade/controller)

### B — Audience only

Edit **Administration → Settings → Notification audiences** (`/apps/notification-audiences`) or seeder pivots. Seeder remains the bootstrap baseline.

### C — Copy only

Lang files all locales.

### D — Wire existing type

Dispatch only from service.

### E — DataTable reload

Map `tableId()` to the key used by `window.dataTables`; `bell.js` calls `ajax.reload(null, false)` to preserve paging. The WS event is **per-recipient** (`recipientUserIds`), so reload only fires for users in that notification's audience. To reload a shared admin list on any row change, the reload signal must be a **role-broadcast** to the watchers (Root+Admin), not a personal note — see `user_created`/`user_updated`/`user_deleted`.

List pages that require resilience subscribe to `notification-realtime:connected` / `notification-realtime:degraded` / `notification-realtime:polling`. `/apps/users` stops its fallback timer while connected, starts a visible-tab-only 60-second `ajax.reload` after degradation or pure polling, and performs one catch-up reload on reconnect. Create/delete flash reload remains a same-tab redirect baseline.

## Types used by Service (personal)

Fired from `ServiceEntitlementLifecycleService` (scheduler), not controllers:

| Type | Audience | When |
|------|----------|------|
| `service_subscription_grace` | Subject user | Subscription enters grace after `ends_at` |
| `service_subscription_expired` | Subject user | Grace ends; free-tier purge applied |

Seeder: `NotificationTypeSettingSeeder` (`notify_subject_user` true). Copy under `lang/{locale}/api/notifications.php` → `types.*`.

## Types used by Inference (broadcast)

| Type | Audience | When |
|------|----------|------|
| `inference_unregistered_model` | Root, Admin, Management | First sighting of a resolved model with no provider catalog rate; provisional billing active |

Deep link: `inference.model-cards.show`. DataTable reload: `inference-model-cards-table`. Dedup uses forever key `inference:unregistered_model_notify:{slug}` (`Cache::add` with null TTL); cleared when classification completes. See [cache-policy](../../platform/cache-policy.md).

## Role vs user (decision)

| Need | Mode |
|------|------|
| Anyone with role should see (including future assigns) | Role broadcast |
| Only specific people | Personal users |
| Edited user should know | `notify_subject_user` / personal to subject |

**Pitfalls:** dispatch from controller; hardcode audience in code instead of settings; forget seeder; double listener registration; mix up Spatie activitylog vs this bell; **relying on an empty type setting to keep a personal type personal** — a personal dispatch (`account_updated`) MUST pass `roles: []` explicitly. `resolveAudience` only skips the setting's roles when an override is given; a stray role pivot on the setting (data pollution from old manual/admin edits) otherwise broadcasts the personal note to those roles. Symptom: an admin who edits another user sees "Your account was updated" in their own bell (observed with khaled/admin). Fix = force `roles: []` at dispatch **and** `sync([])` in the seeder; in `bell.js`, **using any `config` field (e.g. `labels`) before the `const { ... } = config` destructure** — `const` TDZ throws `ReferenceError`, the caller's `try/catch` swallows it, and the whole bell goes inert (no poll, no websocket, no dropdown fetch). Destructure config first, then use it; caching hydrated Eloquent models in the audience snapshot (persistent store → `__PHP_Incomplete_Class` → `TypeError`) — cache primitives only.

## Verification matrix

Run focused backend coverage after changing notification delivery:

```bash
php artisan test tests/Domain/Notification
php artisan test tests/Unit/Support/Broadcasting/BroadcastConnectionTest.php
php artisan test --filter=UserDatatable
npm run build
```

Minimum manual browser verification:

1. Hard-refresh once and confirm a `wss://...pusher.com/...` connection plus successful `/broadcasting/auth`.
2. Keep Network open for more than 60 seconds. With `connected:true`, there must be no periodic unread-count or DataTable requests.
3. Create/update/delete a user in another tab. Confirm WS payload, direct badge count update, and one `users-table` reload.
4. Disable network/WS. Before 30 seconds, confirm no polling; after 30 seconds, confirm 60-second fallback polling.
5. Restore connection. Confirm one reconciliation/catch-up and no later periodic HTTP.
6. Trigger mark-all from navbar and `/apps/notifications`; confirm both current and sibling tabs update without redirect/reload.

## Bell not showing — debug checklist

Icon renders from Blade even when the JS never runs, so an empty/stale badge can look like "no notifications". Work top-down:

1. **Console** — any `Notification bell failed to initialize:` warn or `ReferenceError` on load? Init died (e.g. TDZ / bad JSON) → nothing polls. Rebuild assets (`npm run build`) if source and `public/build` are out of sync.
2. **Permission** — viewer holds `view notification`? No permission → no bell markup, API 403.
3. **Seeder** — `NotificationTypeSettingSeeder` run? Empty audience → row persists but nobody is a recipient.
4. **API** — `GET /api/v1/notifications` (JWT) returns the row? If not, it's a persistence/visibility issue, not the bell.
5. **Runtime health** — inspect `window.notificationRealtime` in Console. `connected:true` means periodic HTTP is a bug; `degraded:true` means the 30-second grace expired and polling is expected.
6. **WebSocket request** — Network should show a live `wss://...pusher.com/...` connection. For hosted Pusher, rendered config must have `cluster` and `host:null`; `api-{cluster}.pusher.com` is REST, never `wsHost`.
7. **Private-channel auth** — `POST /broadcasting/auth` must return 2xx after socket connect. A 401/403 means channel subscription failed even if the socket itself is open.
8. **Payload** — inspect `.activity-notification.received`: expected `type`, `table_id`, and numeric `unread_count`. Missing count causes stale badge; wrong/null table ID prevents list reload.
9. **HTTP fallback** — `/apps/notifications/unread-count` may appear for degraded polling, single-read reconciliation, or reconnect catch-up. `/apps/notifications/recent` is expected when dropdown opens. On API failure the web proxies return `0` / `[]`, which can mask the source error.
10. **Delivery mode** — compare **Settings → Platform** `platform.notifications.delivery` with `BroadcastConnection::usesRealtimeNotifications()`. If the cache snapshot looks stale, run `php artisan settings:rebuild-cache`.
11. **Built asset** — manifest must point to the current `bell-*.js`, and that file must exist. Run `npm run build`, then hard-refresh once after the hash changes.
12. **Personal types** — `subject_user_id` (or explicit `users`) set at emit time? Missing target = nobody sees it.

## Related

- [platform/localization.md](../../platform/localization.md)
- [platform/crud.md](../../platform/crud.md)
- [activity-log](../activity-log/README.md) — separate audit trail
