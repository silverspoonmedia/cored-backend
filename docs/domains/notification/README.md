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

Update fires **two** notifications: personal `account_updated` (the edited user learns their account changed) **and** broadcast `user_updated` (drives the live list reload for admins watching `/users`). They are separate on purpose — the personal note must not reach admin bells.

```
Service → ActivityNotificationEvent → SendActivityNotification
        → NotificationService::emit() → resolveAudience() → create + optional websocket pings
```

| Concern | Owner |
|---------|-------|
| Type exists | `NotificationType` enum |
| When to fire | Service (not controller) |
| Message | `lang/{locale}/api/notifications.php` |
| Who receives | Seeder / `notification_type_settings` |
| Deep link | `actionRoute` + `actionParams` on dispatch |

Listener auto-discovered — **do not** double-register.

## Delivery

| Mode | Config |
|------|--------|
| Polling (default local) | `APP_NOTIFICATION_TYPE=polling` |
| Realtime | `realtime` + valid Pusher/Reverb |

Realtime: after persist, `UserActivityNotificationReceived` broadcasts `type`, `table_id`, and the recipient's absolute `unread_count` on private `App.Models.User.{id}`. The bell applies `unread_count` directly; a healthy WebSocket does **not** call `/notifications/unread-count`. Initial count is server-rendered in bell config. HTTP unread-count remains only for degraded polling, read actions, and one-shot reconciliation after reconnect. Recent-list copy still refetches over HTTP when the dropdown is open so localization stays server-owned.

Client (`resources/js/notifications/bell.js`) treats WebSocket as the primary path. While Pusher is `connected`, **no badge or DataTable polling runs**. A non-connected state starts a 30-second grace window; only if the socket is still down after that does the client emit `notification-realtime:degraded` and start 60-second polling. Reconnect cancels the grace/stops both polling intervals and performs one catch-up reload.

For Pusher-hosted WebSockets, `BroadcastConnection::clientConfig()` must expose `cluster` but leave `host` null. Laravel's server-side Pusher config defaults `host` to `api-{cluster}.pusher.com`, which is the REST API endpoint — passing it to Echo as `wsHost` silently prevents the socket from connecting and leaves the app permanently on polling. Explicit non-default hosts remain valid for self-hosted/Reverb-compatible sockets.

## Schema (essentials)

- `activity_notifications` — type, data JSON, action route/params, timestamps  
- Pivots: roles, target users, reads  
- `notification_type_settings` + role/user pivots, `notify_subject_user`

## Audience cache (`NotificationTypeSettingRepository`)

Forever key `notification_type_settings.snapshot`; seeder / `syncRoles` / `syncUsers` flush it. The snapshot stores **plain primitives** (`type → { notify_subject_user, roles[], users[] }`), **never hydrated Eloquent models**. With `CACHE_STORE=database` (or redis) cached values are serialized; caching model graphs there means a class/relation change or cross-host autoload mismatch deserializes into `__PHP_Incomplete_Class`, violating the `Collection` return type and throwing a `TypeError` on the next emit (observed on `account_updated` during `PUT /api/v1/users/{id}`). Model-returning methods (`findByType`/`read`/`listAll`) query the DB fresh; only `resolveAudience` reads the primitive snapshot.

## API / Web

Viewer endpoints under `/api/v1/notifications/*` (list, unread-count, mark read). Web bell + inbox proxy API. Composer injects bell config.

## Workflows (agents)

### A — New type (one PR)

1. Enum case + `icon()` / `defaultRoles()` fallback / `tableId()`  
2. Translations `en`/`id`/`ja` under `types.{value}`  
3. `NotificationTypeSettingSeeder` row + roles  
4. `ActivityNotificationEvent::dispatch` from owning **service**  
5. Tests  
6. Optional DataTable live reload (`tableId` + blade/controller)

### B — Audience only

Edit seeder / settings pivots; redeploy seed. No admin UI.

### C — Copy only

Lang files all locales.

### D — Wire existing type

Dispatch only from service.

### E — DataTable reload

Map `tableId()` to table id; ensure list page listens for bell ping. The WS ping is **per-recipient** (`recipientUserIds`), so a table reload only fires for users who are in that notification's audience. To reload a shared admin list on any row change, the reload signal must be a **role-broadcast** to the watchers (Root+Admin), not a personal note — see `user_created`/`user_updated`/`user_deleted`. Event-driven reload needs realtime; in polling mode only the active-tab flash reload (create/delete) applies.

## Types used by Service (personal)

Fired from `ServiceEntitlementLifecycleService` (scheduler), not controllers:

| Type | Audience | When |
|------|----------|------|
| `service_subscription_grace` | Subject user | Subscription enters grace after `ends_at` |
| `service_subscription_expired` | Subject user | Grace ends; free-tier purge applied |

Seeder: `NotificationTypeSettingSeeder` (`notify_subject_user` true). Copy under `lang/{locale}/api/notifications.php` → `types.*`.

## Role vs user (decision)

| Need | Mode |
|------|------|
| Anyone with role should see (including future assigns) | Role broadcast |
| Only specific people | Personal users |
| Edited user should know | `notify_subject_user` / personal to subject |

**Pitfalls:** dispatch from controller; hardcode audience in code instead of settings; forget seeder; double listener registration; mix up Spatie activitylog vs this bell; **relying on an empty type setting to keep a personal type personal** — a personal dispatch (`account_updated`) MUST pass `roles: []` explicitly. `resolveAudience` only skips the setting's roles when an override is given; a stray role pivot on the setting (data pollution from old manual/admin edits) otherwise broadcasts the personal note to those roles. Symptom: an admin who edits another user sees "Your account was updated" in their own bell (observed with khaled/admin). Fix = force `roles: []` at dispatch **and** `sync([])` in the seeder; in `bell.js`, **using any `config` field (e.g. `labels`) before the `const { ... } = config` destructure** — `const` TDZ throws `ReferenceError`, the caller's `try/catch` swallows it, and the whole bell goes inert (no poll, no websocket, no dropdown fetch). Destructure config first, then use it; caching hydrated Eloquent models in the audience snapshot (persistent store → `__PHP_Incomplete_Class` → `TypeError`) — cache primitives only.

## Bell not showing — debug checklist

Icon renders from Blade even when the JS never runs, so an empty/stale badge can look like "no notifications". Work top-down:

1. **Console** — any `Notification bell failed to initialize:` warn or `ReferenceError` on load? Init died (e.g. TDZ / bad JSON) → nothing polls. Rebuild assets (`npm run build`) if source and `public/build` are out of sync.
2. **Permission** — viewer holds `view notification`? No permission → no bell markup, API 403.
3. **Seeder** — `NotificationTypeSettingSeeder` run? Empty audience → row persists but nobody is a recipient.
4. **API** — `GET /api/v1/notifications` (JWT) returns the row? If not, it's a persistence/visibility issue, not the bell.
5. **Web proxy** — `GET /notifications/unread-count` + `/notifications/recent`. On API failure these silently return `0` / `[]`, masking the real error.
6. **Delivery mode** — `APP_NOTIFICATION_TYPE` vs `BroadcastConnection::usesRealtimeNotifications()`. Realtime needs a valid Pusher/Reverb; otherwise the client falls back to polling.
7. **Personal types** — `subject_user_id` (or explicit `users`) set at emit time? Missing target = nobody sees it.

## Related

- [platform/localization.md](../../platform/localization.md)
- [platform/crud.md](../../platform/crud.md)
- [activity-log](../activity-log/README.md) — separate audit trail
