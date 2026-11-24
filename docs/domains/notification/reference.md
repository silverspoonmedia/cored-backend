# Notification — deep dive

Mental model, delivery contract, schema, workflows, type inventory, verification, debug checklist. Invariants: [.cursor/rules/notification.mdc](../../../.cursor/rules/notification.mdc). Brief: [README.md](README.md).

## Mental model

| Mode | Audience | Use |
|------|----------|-----|
| **Broadcast (role)** | Role pivots — any current/future role holder | "User created" → Root+Admin |
| **Personal (user)** | User pivots | "Your account was updated" |

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

Tunable in **Administration → Settings → Platform** — see [configuration-policy.md](../../platform/configuration-policy.md). The backend still stores `polling` vs `realtime`; **the SPA ignores polling**. Browser delivery is Echo/Pusher only. Reconnect recovery is `onEchoResync` (re-pull unread count, recent list, registered listings), not a timer HTTP fallback. Blade `bell.js` polling is gone — [blade-admin.md](../../web/blade-admin.md).

| Mode | Setting | SPA transport |
|------|---------|---------------|
| Polling | `platform.notifications.delivery` = `polling` | No live updates (manual reload). The SPA does not poll unread-count or listings. |
| Realtime | `platform.notifications.delivery` = `realtime` + valid Pusher/Sockudo | Private WebSocket via `useEcho.ts`. Empty `VITE_PUSHER_APP_KEY` disables Echo (same as polling: no live updates). |

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
- `table_id` tells a matching list page which registered listing to reload (SPA Pattern A via `useListingReload`); it is `null` for types without a list screen.
- `unread_count` is the recipient's **absolute** unread count after the new notification is persisted. The bell applies it directly; it does not increment locally and does not call HTTP on each WS event.

**Not this bus:**

- A product's live listing uses its own private channels and domain events — no `NotificationType`, no `table_id`. A product registers its channels through `ChannelRegistrarRegistry`, so this file never names one.
- Payment Gateway invoice checkout uses private `payments.invoice.{invoiceId}` + `InvoiceStatusChanged` / `PaymentRealtime` — no `NotificationType`, independent of platform notification delivery mode. See [payment-gateway/reference.md](../payment-gateway/reference.md).

Those features share the SPA Echo singleton (`useEcho.ts` / `getEcho()`); they do not ride the activity-notification event.

**SPA client.** `AppNavbar` inits Echo (`useEcho.ts`) **after** `useShellHydrate.hydrateShell()` **and** `whenCsrfCookieQuiet()` so `POST /broadcasting/auth` does not race `POST /batch` or the `__Host-spa_csrf` write it triggers. Private-channel auth is `POST /api/v1/broadcasting/auth` through `client.post` (`authorizeBroadcastChannel`): cookie + `X-CSRF-Token`, 419 refresh+retry like `/batch`. Private-channel lifecycles are owned by `src/composables/echo/channelRegistry.ts` (ref-counted per channel name, attach deferred until Echo exists) — composables do not call `echo.private()` themselves. Fallback `GET /api/v1/notifications` / unread-count / `navigation/menus` run only when the batch did not seed that widget and the sub-entry was not a terminal 401/403/429. Shell batch seeds unread count and the recent list (limit 5); dropdown open or `/apps/notifications` fetches the full list. See [vue-workers-spa.md — Realtime](../../integration/reference-vue-workers-spa.md#realtime).

**Sidebar:** **Workspace → Notifications** (`notifications.index`) has no menu-role pivot and is available to authenticated viewers who pass the navigation audience rules. API access still requires `view notification`. This page entry is distinct from the navbar activity bell: both use Notification-domain data, but the page is the full history surface while the bell is a compact delivery control.

### Expected Network behavior

| Situation | `GET /api/v1/notifications/unread-count` | Listing HTTP (`users-table`, etc.) |
|-----------|------------------------------------------|-------------------------------------|
| Shell hydrate | One read inside `POST /api/v1/batch` (or navbar fallback if the batch did not seed) | Initial list load from the view |
| Notification arrives over healthy WS | No request; use payload `unread_count` | One reload only when payload `table_id` matches a registered listing |
| Browser focus while WS healthy | No request | No request |
| WS down | No polling. Navbar shows reconnecting; events in the gap are lost | No polling |
| WS reconnect | One unread-count + recent-list pull via `onEchoResync` | One catch-up via `triggerAllListingsReload` |
| Single-read / mark-all | Response updates local badge; no extra unread-count fetch required | None |

Periodic unread-count or listing requests while Echo is connected are a regression. There is no 30-second degraded HTTP fallback.

### Pusher host rule

For Pusher-hosted WebSockets, `BroadcastConnection::clientConfig()` exposes `cluster` but leaves `host` null. Laravel's server-side Pusher config defaults `host` to `api-{cluster}.pusher.com`, which is the REST API endpoint — passing it to Echo as `wsHost` silently prevents the socket from connecting. SPA `useEcho.ts` `readConfig()` rejects that REST-host pattern. Explicit non-default hosts remain valid for self-hosted/Sockudo sockets.

### Read-state UI and tab synchronization

Mark-all and single-read are in-place API mutations (`POST /api/v1/notifications/read-all` and `…/{id}/read`). The JSON carries `{ marked, unread: 0 }` (or the updated item); the SPA updates badge and list locally with no redirect.

`useNotifications.ts` opens a per-user `BroadcastChannel('activity-notifications.{userId}')`:

- `read-all` propagates mark-all to other tabs.
- `read` + `id` decrements unread and marks the matching item.

If `BroadcastChannel` is unavailable, the acting tab still updates immediately; other tabs reconcile on their next WS event or Echo reconnect. See [vue-workers-spa — cross-tab notification read-state sync](../../integration/reference-vue-workers-spa.md#cross-tab-notification-read-state-sync).

## Schema (essentials)

- `activity_notifications` — type, data JSON, action route/params, timestamps
- Pivots: roles, target users, reads
- `notification_type_settings` + role/user pivots, `notify_subject_user`

## Audience cache (`NotificationTypeSettingRepository`)

Forever key `notification_type_settings.snapshot`; seeder / `syncRoles` / `syncUsers` flush it. The snapshot stores **plain primitives** (`type → { notify_subject_user, roles[], users[] }`), **never hydrated Eloquent models**. With `CACHE_STORE=database` (or redis) cached values are serialized; caching model graphs there means a class/relation change or cross-host autoload mismatch deserializes into `__PHP_Incomplete_Class`, violating the `Collection` return type and throwing a `TypeError` on the next emit (observed on `account_updated` during `PUT /api/v1/users/{id}`). Model-returning methods (`findByType`/`read`/`listAll`) query the DB fresh; only `resolveAudience` reads the primitive snapshot.

## Vue-Workers SPA consumer

The sibling Vue-Workers SPA uses `useNotificationsHydrate` for notification-audience edit, batching the audience and assignable-role reads. Shell hydration also seeds the sidebar/navbar-adjacent recent notification list and unread count; failed entries retain the bell's individual loaders. See [vue-workers-spa.md — invocation budget controls](../../integration/reference-vue-workers-spa.md#invocation-budget-controls).

API payloads keep `action_route` + `action_params` (`users.edit`, `{ user }`). The SPA does not invent a second action map: `src/utils/notificationTarget.ts` resolves `${route}.view` and remaps `user` / `modelCard` → Vue `:id`. Bell and `/apps/notifications` navigate there; unknown names fall back to the notifications list. See [vue-workers-spa — notification deep links](../../integration/reference-vue-workers-spa.md#notification-deep-links).

## API

Viewer API endpoints:

| API endpoint | Purpose |
|--------------|---------|
| `GET /api/v1/notifications` | Visible notifications with viewer-specific `is_read` |
| `GET /api/v1/notifications/unread-count` | Absolute count (`{ unread: number }`). Seeded by shell `POST /api/v1/batch`; otherwise an explicit fetch. Not a polling loop. |
| `POST /api/v1/notifications/{id}/read` | Idempotent single-read |
| `POST /api/v1/notifications/read-all` | Returns `{ marked, unread: 0 }` |

Bell UI, inbox (`/apps/notifications`), and audience admin live in Monolith-Vue-Workers. Blade `NotificationBellComposer` and web proxies are removed — see [blade-admin.md](../../web/blade-admin.md).

## Implementation map

| Layer | File | Responsibility |
|-------|------|----------------|
| Type catalogue | `packages/notification/src/Notifications/NotificationType.php` | Type value, icon, role fallback, listing registry key |
| Emit service | `packages/notification/src/Services/NotificationService.php` | Persist audience, calculate per-recipient unread count, dispatch realtime event |
| Realtime event | `packages/notification/src/Events/UserActivityNotificationReceived.php` | Private channel/name and `type`/`table_id`/`unread_count` payload |
| Audience settings | `NotificationTypeSettingRepository` + seeder | Resolve role/user/subject audience; primitive cached snapshot |
| SPA bell / inbox | `monolith-vue-workers` (`useNotificationsHydrate`, Echo) | Badge, list, WS, mark-read |
| Users list live reload | SPA `UsersView` | Register `users-table` via Pattern A |

## Workflows (agents)

### A — New type (one PR)

1. Enum case + `icon()` / `defaultRoles()` fallback / `tableId()`
2. Translations `en`/`id`/`ja`/`ko`/`zh_CN`/`ar`/`ru` under `types.{value}`
3. `NotificationTypeSettingSeeder` row + roles
4. `ActivityNotificationEvent::dispatch` from owning **service**
5. Tests
6. Optional listing live reload (`tableId` + SPA listing registry)

### B — Audience only

Edit **Administration → Settings → Notification audiences** (`/apps/notification-audiences`) or seeder pivots. Seeder remains the bootstrap baseline. List `items[]` emit query-mode `actions[]` (`edit` → `notification-audiences.edit` when the actor can `manage settings`); the labeled Edit column maps that key.

### C — Copy only

Lang files, all locales.

### D — Wire existing type

Dispatch only from service.

### E — Listing reload (Pattern A)

Map `tableId()` to the SPA listing registry key (`useListingReload`). The bell forwards `.activity-notification.received` `table_id` to registered views. The WS event is **per-recipient**, so reload only fires for users in that notification's audience. To reload a shared admin list on any row change, the reload signal must be a **role-broadcast** to the watchers (Root+Admin), not a personal note — see `user_created`/`user_updated`/`user_deleted`. There is no DataTable `ajax.reload` or `notification-realtime:*` window events; reconnect catch-up is `onEchoResync`.

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

Deep link: `inference.model-cards.show`. Listing registry reload: `inference-model-cards-table`. Dedup uses forever key `inference:unregistered_model_notify:{slug}` (`Cache::add` with null TTL); cleared when classification completes. See [cache-policy](../../platform/cache-policy.md).

## Role vs user (decision)

| Need | Mode |
|------|------|
| Anyone with role should see (including future assigns) | Role broadcast |
| Only specific people | Personal users |
| Edited user should know | `notify_subject_user` / personal to subject |

**Pitfalls:** dispatch from controller; hardcode audience in code instead of settings; forget seeder; double listener registration; mix up Spatie activitylog vs this bell; **relying on an empty type setting to keep a personal type personal** — a personal dispatch (`account_updated`) MUST pass `roles: []` explicitly. `resolveAudience` only skips the setting's roles when an override is given; a stray role pivot on the setting (data pollution from old manual/admin edits) otherwise broadcasts the personal note to those roles. Symptom: an admin who edits another user sees "Your account was updated" in their own bell. Fix = force `roles: []` at dispatch **and** `sync([])` in the seeder; caching hydrated Eloquent models in the audience snapshot (persistent store → `__PHP_Incomplete_Class` → `TypeError`) — cache primitives only.

## Verification matrix

Run focused backend coverage after changing notification delivery:

```bash
php artisan test packages/notification/tests
php artisan test tests/Unit/Support/Broadcasting/BroadcastConnectionTest.php
```

SPA: `npm test` in `monolith-vue-workers` (includes `tests/echo.test.ts` and `tests/client.test.ts` CSRF/419/settle cases).

Minimum manual browser verification:

1. Logged-out → login → dashboard: `POST /api/v1/broadcasting/auth` 200 (not CORS), `POST /api/v1/batch` 200, no `POST /api/v1/auth/refresh` on that navigation. Hard reload still 200.
2. Confirm a `wss://...pusher.com/...` (or Sockudo) connection. Keep Network open: no periodic unread-count or listing HTTP while Echo is connected.
3. Create/update/delete a user in another tab. Confirm WS payload, badge count update, and one `users-table` reload.
4. Disable network/WS. Confirm no polling. Restore connection: one `onEchoResync` catch-up (unread + listings), then no periodic HTTP.
5. Trigger mark-all from navbar and `/apps/notifications`; confirm current and sibling tabs update without redirect/reload.

## Bell not showing — debug checklist

Work top-down. Blade composer / `bell-*.js` / `window.notificationRealtime` are gone — [blade-admin.md](../../web/blade-admin.md).

1. **Permission** — viewer holds `view notification`? API 403 otherwise.
2. **Seeder** — `NotificationTypeSettingSeeder` run? Empty audience → row persists but nobody is a recipient.
3. **API** — `GET /api/v1/notifications` returns the row? If not, persistence/visibility, not the bell.
4. **Shell hydrate** — `POST /api/v1/batch` should seed unread + recent list. `AppNavbar` awaits that before fallback GETs. A first-login CORS on `/navigation/menus` or a `POST /auth/refresh` 401 is the cookie-settle race, not a missing CORS allowlist — [vue-workers-spa.md](../../integration/reference-vue-workers-spa.md#cross-tab-auth-multi-tab-session-sync).
5. **WebSocket** — Network should show a live `wss://...` connection. For hosted Pusher, `VITE_PUSHER_HOST` must be empty; `api-{cluster}.pusher.com` is REST, never `wsHost`.
6. **Private-channel auth** — `POST /api/v1/broadcasting/auth` must return 2xx (cookie + `X-CSRF-Token` via `client.ts`). A 401/403 means subscribe failed even if the socket is open. A first-login CORS error on this path is either (a) the CSRF cookie-settle race the authorizer gate + `client.ts` retry already cover, or (b) the URL missing `/api/v1`, which lands on the framework's CSRF-guarded **web twin** and answers 403 without CORS headers. Check the request URL in Network first; if it is correct, `curl -i -X POST …/api/v1/broadcasting/auth -H "Origin: <spa-origin>"` returning 401 **with** `Access-Control-Allow-Origin` proves the allowlist is fine. Backend invariant: `tests/Feature/Http/CorsBroadcastAuthTest.php`; [reference-security.md — broadcasting/auth invariant](../../platform/reference-security.md#broadcastingauth-invariant).
7. **Payload** — inspect `.activity-notification.received`: expected `type`, `table_id`, and numeric `unread_count`.
8. **Delivery setting** — **Settings → Platform** `platform.notifications.delivery` plus `BroadcastConnection::usesRealtimeNotifications()`. Stale cache: `php artisan settings:rebuild-cache`. Empty `VITE_PUSHER_APP_KEY` disables Echo regardless of the setting.
9. **Personal types** — `subject_user_id` (or explicit `users`) set at emit time? Missing target = nobody sees it.
