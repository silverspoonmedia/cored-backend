# Activity Notifications (per role + per user)

> **Implementing or changing a notification?** Use the step-by-step playbook: [Activity Notifications — Implementation Guide](./notifications-implementation-guide.md) (for AI agents and developers). This document covers architecture and runtime behaviour.

A generic activity-notification engine with **two targeting modes**:

- **Per role (broadcast)** — linked to whole roles; any current or future holder
  of a target role sees it. Good for "a new user was created" → Root + Admin.
- **Per user (personal)** — linked to specific users; only they see it. Good for
  "your account was updated".

A single notification may use **either or both** audiences. A viewer sees it when
they hold one of its target roles **OR** are one of its target users. Per-user
read state is tracked separately.

This is **not** Laravel's database-channel `Notifiable` feature (the
`notifications` table). It is an application-domain surface with its own RBAC
visibility, stored under `activity_notifications` so it never shadows the
framework table.

All IDs are **UUID v7** end to end (see [User primary keys (UUID v7)](./user-uuid-v7.md)).
The feature uses a **dedicated repository** because visibility is a role-overlap
/ user-target query, not plain CRUD. See [CRUD Architecture](./crud-architecture.md).

---

## Why this shape

A notification is stored **once** and linked to its audience via pivots, instead
of fanning out a copy per recipient:

- A user promoted to `Admin` tomorrow immediately sees past broadcasts.
- No duplicate rows; one write regardless of audience size.
- Read state is the only per-user data (`activity_notification_reads.read_at`).
- Personal and broadcast notifications share one model, one API, one bell.

---

## Layers

| Layer | File | Responsibility |
|-------|------|----------------|
| Migration | `database/migrations/2026_06_12_000001_create_activity_notifications_tables.php` | `activity_notifications` + `_roles` + `_users` + `_reads` |
| Migration | `database/migrations/2026_06_22_000001_create_notification_type_settings_tables.php` | `notification_type_settings` + role/user pivots (dynamic audience config) |
| Model | `app/Models/ActivityNotification.php` | `HasUuids`, `roles()` + `targetUsers()` + `readers()` |
| Model | `app/Models/NotificationTypeSetting.php` | Per-type audience config (`roles`, `targetUsers`, `notify_subject_user`) |
| Type catalogue | `app/Notifications/Activity/NotificationType.php` | Enum: message key, icon, DataTable mapping; enum `defaultRoles()` is a **fallback only** |
| Event | `app/Events/ActivityNotificationEvent.php` | Generic "raise a notification" signal any feature dispatches |
| Broadcast ping | `app/Events/UserActivityNotificationReceived.php` | Private-channel websocket ping when realtime delivery is enabled |
| Listener | `app/Listeners/SendActivityNotification.php` | Persists + fans out (auto-discovered) |
| Broadcast gate | `app/Support/Broadcasting/BroadcastConnection.php` | `APP_NOTIFICATION_TYPE=realtime` + valid Pusher config → realtime; otherwise polling |
| Broadcast config | `config/broadcasting.php` | Pusher / Reverb connection definitions (`BROADCAST_CONNECTION`, `PUSHER_*`) |
| Delivery config | `config/notifications.php` | Maps `APP_NOTIFICATION_TYPE` (`polling` \| `realtime`, default `polling`) and `APP_NOTIFICATION_POLLING_INTERVAL` (seconds, default `60`) |
| Channel auth | `routes/channels.php` | Private channel `App.Models.User.{id}` (UUID string match) |
| View composer | `app/View/Composers/NotificationBellComposer.php` | Injects `$notificationBellConfig` for the navbar bell (bound to the hashed navbar view — see below) |
| Contract | `app/Repositories/Contracts/NotificationRepositoryInterface.php` | Repository contract |
| Repository | `app/Repositories/Eloquent/NotificationRepository.php` | Role/user visibility, unread count, read state, `recipientUserIds()` |
| Service | `app/Services/Notification/NotificationService.php` | `emit()` / `broadcastToRoles()` / `sendToUsers()` + viewer reads |
| Contract | `app/Repositories/Contracts/NotificationTypeSettingRepositoryInterface.php` | Settings snapshot + `resolveAudience()` |
| Repository | `app/Repositories/Eloquent/NotificationTypeSettingRepository.php` | Cached settings, pivot sync, audience resolution |
| Seeder | `database/seeders/NotificationTypeSettingSeeder.php` | Default audiences (replaces former hardcoded enum defaults) |
| Resource | `app/Http/Resources/Notification/NotificationResource.php` | JSON shape (localized `message`, `icon`, `is_read`) |
| Request | `app/Http/Requests/Notification/IndexNotificationRequest.php` | `limit` validation |
| API controller | `app/Http/Controllers/API/V1/NotificationController.php` | Thin HTTP layer (viewer reads) |
| Web controller | `app/Http/Controllers/Web/NotificationController.php` | Pure API consumer (bell + inbox page) |
| Vite — bell | `resources/js/notifications/bell.js` | Badge/list UI, polling interval, Echo subscription, multi-tab sync |
| Vite — echo | `resources/js/notifications/echo.js` | Laravel Echo + Pusher factory (`VITE_PUSHER_*` + server overrides) |

The repository is bound in `App\Providers\AppServiceProvider`:

```php
$this->app->bind(NotificationRepositoryInterface::class, NotificationRepository::class);
$this->app->bind(NotificationTypeSettingRepositoryInterface::class, NotificationTypeSettingRepository::class);
```

The listener is **auto-discovered** (Laravel scans `app/Listeners`); do **not**
also register it with `Event::listen` or it fires twice.

`bootstrap/app.php` registers `routes/channels.php` via `withRouting(…, channels: …)`.
Channel authorization is exposed at `POST /broadcasting/auth` on the web stack.

---

## Realtime delivery flow (production)

When `BroadcastConnection::usesRealtimeNotifications()` is true, a persisted
notification also fans out a lightweight websocket ping:

```
CRUD / service
    └── ActivityNotificationEvent
            └── SendActivityNotification listener
                    └── NotificationService::emit()
                            ├── NotificationRepository::create()   (DB + pivots)
                            └── foreach recipientUserIds()
                                    └── event(UserActivityNotificationReceived)
                                            └── ShouldBroadcastNow
                                                    └── private App.Models.User.{id}
                                                            └── activity-notification.received
```

The browser bell (`bell.js`) listens on that private channel, then **refetches**
`GET /notifications/unread-count` (and recent items when the dropdown is open)
so localized copy still comes from `NotificationResource` over HTTP.

---

## Database schema

All keys are UUID. `roles` and `users` exist before this migration, so every FK
is constrained in one migration.

### `activity_notifications`

| Column | Type | Notes |
|--------|------|-------|
| `id` | uuid PK | |
| `type` | string | Action key (e.g. `user_created`); maps to `NotificationType` |
| `data` | json, nullable | Message replacements + render context (`name`, `email`, `actor`, …) |
| `action_route` | string, nullable | A **registered route name** for the deep link (not a URL) |
| `action_params` | json, nullable | Route params for `action_route` |
| `created_at` / `updated_at` | timestamps | |

### Pivots

- `activity_notification_roles` — (`notification_id`, `role_id`), composite PK, both FKs cascade. **Broadcast** audience.
- `activity_notification_users` — (`notification_id`, `user_id`), composite PK, both FKs cascade. **Personal** audience.
- `activity_notification_reads` — (`notification_id`, `user_id`, `read_at`), composite PK, both FKs cascade. A present row = the user has read it.

### Type settings (dynamic audience)

Per-type **who receives** a notification when it is emitted is stored in the database
and maintained via **`NotificationTypeSettingSeeder`** (or a one-off migration), not
hardcoded in dispatch code. There is **no admin UI** for audience configuration —
types and triggers stay in code; audience defaults are changed in a PR like any
other config. See [Implementation Guide — Workflow B](./notifications-implementation-guide.md#workflow-b-change-audience-seeder--pr).

#### `notification_type_settings`

| Column | Type | Notes |
|--------|------|-------|
| `id` | uuid PK | |
| `type` | string, unique | `NotificationType` value (e.g. `user_created`) |
| `notify_subject_user` | bool | When `true`, adds `data.subject_user_id` to the personal audience |
| `created_at` / `updated_at` | timestamps | |

#### Settings pivots

- `notification_type_setting_roles` — (`setting_id`, `role_id`), composite PK. **Broadcast** roles for this type.
- `notification_type_setting_users` — (`setting_id`, `user_id`), composite PK. **Additional** personal targets always notified for this type.

`NotificationTypeSettingRepository::resolveAudience()` merges, in order:

1. Explicit `$roles` / `$users` passed by the emitter (override; rare).
2. Roles / users from the cached settings row.
3. When `notify_subject_user` is `true`, `data['subject_user_id']` (contextual personal target).
4. If no settings row exists yet, `NotificationType::defaultRoles()` as a fallback.

`NotificationTypeSettingSeeder` runs after `RoleSeeder` and seeds defaults equivalent to the former hardcoded values (`user_created` → Root + Admin; `account_updated` → `notify_subject_user = true`).

---

## Visibility rules

A notification is visible to a viewer when **either**:

1. the viewer holds at least one of the notification's **target roles**, **or**
2. the viewer is one of the notification's **target users**.

A notification with no roles and no users is visible to nobody. This is enforced
in `NotificationRepository::scopeVisibleTo()`, used by `listForViewer`,
`isVisibleTo`, `unreadCountForViewer`, and `markAllAsRead`.

The **API surface** is gated by the `view notification` permission. Because a
**personal** notification can reach any user regardless of role, `RoleSeeder`
grants `view notification` to **every** role (Root, Admin, Moderator, Management,
User). The permission gates *access to the endpoints*; the per-row role/user
match is the finer rule that decides what each viewer actually sees.

---

## API

Base prefix `/api/v1`, route name prefix `api.v1.notifications.`. All routes use
`auth:api` + `permission:view notification`.

| Method | URI | Name | Action |
|--------|-----|------|--------|
| GET | `/api/v1/notifications` | `…index` | Visible notifications, newest first (`?limit=` 1–100, default 50) |
| GET | `/api/v1/notifications/unread-count` | `…unread-count` | `{ unread: N }` for the badge |
| POST | `/api/v1/notifications/read-all` | `…read-all` | Mark all visible as read (`{ marked: N }`) |
| POST | `/api/v1/notifications/{notification}/read` | `…read` | Mark one read; **403** if out of scope |

`{notification}` is a UUID (`whereUuid`). Responses use the shared `ApiResponse`
envelope; `message` strings come from `lang/{locale}/api/notifications.php`.

**Index row shape** (`NotificationResource`):

```json
{
  "id": "019e…",
  "type": "user_created",
  "message": "A new user Jane Doe (jane@example.com) was added by Admin User.",
  "icon": "bi bi-person-plus",
  "action_route": "users.edit",
  "action_params": { "user": "019e…" },
  "is_read": false,
  "created_at": "2026-06-12T04:00:00+00:00"
}
```

`message` is resolved for the **active locale** by interpolating `data` into the
type's translation key (`api/notifications.types.{type}`).

---

## Emitting a notification (generic engine)

Any feature raises a notification by dispatching the event — it never touches
storage directly. **Audience targeting** (roles + users) is resolved from
`notification_type_settings` unless the emitter passes an explicit override.

```php
use App\Events\ActivityNotificationEvent;
use App\Notifications\Activity\NotificationType;

// Audience comes from DB settings for user_created (broadcast roles):
ActivityNotificationEvent::dispatch(
    NotificationType::UserCreated,
    ['name' => $user->name, 'email' => $user->email, 'actor' => $actor->name],
    actionRoute: 'users.edit',
    actionParams: ['user' => $user->id],
);

// Audience comes from settings (notify_subject_user + optional extra users/roles):
ActivityNotificationEvent::dispatch(
    NotificationType::AccountUpdated,
    [
        'name' => $user->name,
        'actor' => $actor->name,
        'subject_user_id' => $user->id,
    ],
);
```

Or call `NotificationService` directly when not dispatching an event:

| Method | Audience |
|--------|----------|
| `emit($type, $data, $roles, $users, …)` | Full control; `null` `$roles` → DB settings |
| `broadcastToRoles($type, $data, $roles, …)` | Roles only (`null` → DB settings) |
| `sendToUsers($type, $users, $data, …)` | Passes explicit users; still merges DB settings |

### Reference wiring

| Trigger | Type | Audience (default settings) | Where |
|---------|------|-------------------------------|-------|
| User created | `user_created` | **Broadcast** → Root + Admin (configurable) | `UserManagementService::create()` |
| User account updated (by someone else) | `account_updated` | **Personal** → subject user via `notify_subject_user` | `UserManagementService::update()` (skipped on self-edit) |

Because **both** the API and the Blade UI funnel user create/update through
`UserManagementService`, a single dispatch covers every entry point.

### Adding a new notification type

See **[Implementation Guide — Workflow A](./notifications-implementation-guide.md#workflow-a-add-a-new-notification-type)** for the full checklist, templates, and test requirements. Summary:

1. Add a case to `NotificationType` with its `icon()` and `tableId()` as needed.
2. Add the message template under `types.{value}` in
   `lang/{locale}/api/notifications.php` for every supported locale (`en`, `id`, `ja`).
3. Add a row in `NotificationTypeSettingSeeder` with the default audience.
4. Dispatch `ActivityNotificationEvent` from wherever the action happens.

No activity-notification engine or migration change is required beyond seeding the new type's settings row.

---

## Web frontend (API client)

The Blade admin is a **pure consumer** (see [Web frontend](./web-frontend.md)).
`App\Http\Controllers\Web\NotificationController` forwards to the API and never
holds visibility logic.

| Web route | Forwards to |
|-----------|-------------|
| `GET /notifications` | `GET /api/v1/notifications?limit=100` (full page) |
| `GET /notifications/unread-count` | `GET /api/v1/notifications/unread-count` (bell badge poll) |
| `GET /notifications/recent` | `GET /api/v1/notifications?limit=10` (bell dropdown) |
| `POST /notifications/read-all` | `POST /api/v1/notifications/read-all` |
| `POST /notifications/{id}/read` | `POST /api/v1/notifications/{id}/read` |

The web layer resolves each notification's `action_route` + `action_params` into
a clickable URL (`Route::has()` guard; unregistered routes degrade to `null`).

- **Navbar bell** — `resources/views/adminlte4/components/partials/navbar.blade.php`,
  shown `@can('view notification')` (every role). Delivery mode is chosen at
  runtime (see [Realtime vs polling](#realtime-vs-polling) below). Config is
  injected by `NotificationBellComposer` as JSON on `#notification-bell`
  (`data-notification-config`); the Vite entry `resources/js/notifications/bell.js`
  is loaded with `@vite`. Loads recent items on dropdown open, marks an item read
  on click. Built on AdminLTE's native `navbar-badge` + `dropdown-header`/`dropdown-footer`.
- **Index page** — `resources/views/adminlte4/notifications/index.blade.php`, with a
  "mark all as read" action.

`NotificationBellComposer` is registered on the **hashed** navbar view name from
`WebLayout::navbarComposerView()` (same target as `NavigationComposer`). Anonymous
components (`<x-adminlte4::partials.navbar>`) do **not** use the readable
`adminlte4::…` view name — bind composers to the hashed name or chain them in
`AppServiceProvider` alongside `NavigationComposer`.

### Realtime vs polling

The bell supports two delivery modes. `App\Support\Broadcasting\BroadcastConnection`
picks between them based on `APP_NOTIFICATION_TYPE` (via `config/notifications.php`)
and whether the broadcast connection has valid credentials.

| Mode | When | Client behaviour |
|------|------|------------------|
| **Polling** | `APP_NOTIFICATION_TYPE=polling` (default), **or** `realtime` with missing / invalid broadcast config (`BROADCAST_CONNECTION` unset, `null`, `log`, or Pusher credentials empty) | Fetches unread count on load and every `APP_NOTIFICATION_POLLING_INTERVAL` seconds (default 60); recent list on dropdown open |
| **Realtime** | `APP_NOTIFICATION_TYPE=realtime` **and** Pusher (or Socket-compatible) credentials are present | Subscribes via Laravel Echo + Pusher to private channel `App.Models.User.{id}`; on `activity-notification.received` refetches badge (and recent list when the dropdown is open). Multiple tabs stay in sync without a full page reload (see below). |

**Typical configurations**

| `APP_NOTIFICATION_TYPE` | Pusher credentials | Bell behaviour |
|-------------------------|--------------------|----------------|
| `polling` (default) | any | **Polling** — unread count on load + every `APP_NOTIFICATION_POLLING_INTERVAL` s (default 60) |
| `realtime` | filled | **Realtime** — websocket push + HTTP refetch |
| `realtime` | empty / invalid | **Polling** fallback — bell still works |

**Why HTTP refetch on realtime pings?** Message copy is locale-aware and resolved
server-side (`NotificationResource`). The websocket event is intentionally empty —
it only signals "something changed" so each viewer refetches through the existing
web → API routes.

**Realtime setup** — set in `.env`:

| Variable | Notes |
|----------|--------|
| `APP_NOTIFICATION_TYPE` | `realtime` (default is `polling`) |
| `APP_NOTIFICATION_POLLING_INTERVAL` | Badge poll interval in seconds when in polling mode (default `60`, minimum `1`) |
| `BROADCAST_CONNECTION` | `pusher` (default in `.env.example`) |
| `PUSHER_APP_ID`, `PUSHER_APP_KEY`, `PUSHER_APP_SECRET` | From Pusher or a compatible host (e.g. Soketi) |
| `PUSHER_APP_CLUSTER` | Pusher cluster (ignored when `PUSHER_HOST` points at self-hosted) |
| `PUSHER_HOST` | Optional; set for Socket-compatible self-hosted websocket |
| `PUSHER_PORT` / `PUSHER_SCHEME` | Usually `443` / `https` (or your host's port/scheme) |
| `VITE_PUSHER_*` | Mirrored for the Vite bundle (`resources/js/notifications/echo.js` reads `import.meta.env.VITE_PUSHER_*`; server-injected config may override host/port at runtime) |

**Frontend assets** — the bell ships as a Vite entry (`resources/js/notifications/bell.js`),
loaded via `@vite` in the navbar when the viewer holds `view notification`. Run
`npm install` and `npm run build` before deploying (or `composer run dev` /
`npm run dev` for HMR). Echo and Pusher are **npm** dependencies (`laravel-echo`,
`pusher-js`), not CDN scripts.

| File | Role |
|------|------|
| `resources/js/notifications/bell.js` | Entry: reads `data-notification-config` (`pollingIntervalSeconds`, realtime flag), dropdown UX |
| `resources/js/notifications/echo.js` | Builds Echo client; reads `import.meta.env.VITE_PUSHER_*` |
| `vite.config.js` | Declares `bell.js` (and `app.js`) as Vite inputs |
| `package.json` | `laravel-echo`, `pusher-js` dependencies |

Channel authorization uses `POST /broadcasting/auth` on the **web** stack (JWT
cookie → `api` guard). `routes/channels.php` authorizes
`App.Models.User.{id}` when the authenticated user's id matches (UUID string
comparison — not integer).

**Multiple browser tabs** — each tab opens its own websocket. Background tabs are
often throttled by the browser and may miss a push. The Vite bell bundle keeps
every open tab consistent **without a manual page refresh**:

1. **BroadcastChannel** (`activity-notifications.{userId}`) — when one tab receives
   `activity-notification.received`, it posts a ping to sibling tabs for the same
   user; those tabs refetch the badge immediately.
2. **Page visibility / window focus** — when a tab becomes visible again, it
   refetches the badge (and recent list if the dropdown is open) and reconnects
   Pusher when the socket dropped while idle.

**Manual test (realtime)** — open two tabs as the same user (e.g. dashboard idle
in tab A, CRUD that emits a notification in tab B). Tab B should update instantly;
tab A should update without reload via BroadcastChannel (or on focus if the
browser suspended the background tab entirely).

When a notification is persisted, `NotificationService::emit()` fans out
`UserActivityNotificationReceived` to every recipient user id (role holders +
explicit targets) **only** in realtime mode.

Sidebar entry: `MenuSeeder` adds a `Notifications` (`bi bi-bell`,
`notifications.index`) item visible to **all authenticated** users (no role gate,
since any user can receive a personal notification).

---

## DataTable live reload (shipped)

**Status: in production.** List screens with DataTables (e.g. `#users-table`) now
**automatically reload** when relevant notifications fire (e.g. `user_created`),
using a **hybrid approach** that handles both cross-tab updates (websocket) and
active-tab updates (flash session).

| Component | Implementation |
|-----------|----------------|
| Type → table mapping | `NotificationType::tableId()` returns DataTable ID (e.g. `user_created` → `users-table`) |
| Websocket payload | `UserActivityNotificationReceived::broadcastWith()` includes `type` + `table_id` |
| DataTable exposure | Init scripts store API reference on `window.dataTables[tableId]` |
| Cross-tab reload | `bell.js` receives websocket → calls `table.ajax.reload()` → `BroadcastChannel` syncs sibling tabs |
| Active-tab reload | Controller sets flash session → Blade detects flag → retries until DataTable ready |
| Background tab retry | `bell.js` retry queue (exponential backoff) + visibility/focus handlers |

### How it works (hybrid architecture)

**Scenario A: Cross-tab update (Tab A idle, Tab B creates user)**

1. Tab B submits create form → redirect starts
2. `NotificationService` broadcasts `UserActivityNotificationReceived` with `{ type, table_id }`
3. Tab A (idle) receives websocket via `bell.js` → calls `reloadDataTable('users-table')`
4. Tab A's DataTable reloads instantly via `table.ajax.reload()`
5. `BroadcastChannel` propagates to other sibling tabs

**Scenario B: Active-tab update (Tab B creates user, then views list)**

1. Tab B submits create form
2. Controller redirects with `->with('datatable_reload', 'users-table')`
3. Index page loads, DataTable initializes
4. Blade detects flash session → triggers retry loop
5. Retry loop waits for DataTable to be ready (up to 10 attempts × 200ms)
6. Once ready, calls `table.ajax.reload(null, false)`

**Why hybrid?** Websocket events during navigation have unpredictable timing (may arrive
before DataTable initializes). Flash-based reload for the active tab is reliable because
it waits for the table to be ready. Cross-tab updates use websocket because those tabs
are already loaded and stable.

### Adding a new table reload

When adding a notification type that should trigger a list reload:

1. **Backend - NotificationType**: Add the type with `tableId()` method:
   ```php
   case OrderCreated = 'order_created';
   
   public function tableId(): ?string
   {
       return match ($this) {
           self::UserCreated => 'users-table',
           self::OrderCreated => 'orders-table',
           self::AccountUpdated => null, // personal, no table
       };
   }
   ```

2. **Backend - Controller**: Add flash flag on redirect after create/delete:
   ```php
   return redirect()->route('orders.index')
       ->with('success', __('api/orders.created'))
       ->with('datatable_reload', 'orders-table');
   ```

3. **Frontend - Blade index view**: Expose DataTable API (3 lines only):
   ```javascript
   const table = $('#orders-table').DataTable({ /* config */ });
   window.dataTables = window.dataTables || {};
   window.dataTables['orders-table'] = table;
   ```

**That's it.** No per-page retry logic needed. Flash reload is handled centrally
in `bell.js` (reads meta tag from layout, triggers retry until table ready).
Cross-tab reload via websocket is also centralized in `bell.js`.

**DRY principle:** Each DataTable page only needs 3 lines. All reload intelligence
(flash detection, websocket handling, retry logic, cross-tab sync) lives in one
place: `bell.js` + `assets-head.blade.php` meta tag.

---

## Localization

User-facing copy lives in application-owned files (all locales):

| File | Use |
|------|-----|
| `lang/{locale}/api/notifications.php` | API messages + per-type `types.*` templates |
| `lang/{locale}/web/notifications.php` | Bell + inbox page labels |
| `lang/{locale}/menu.php` | `notifications` static label |

Per the [Localization task rule](./localization.md#task-rule-required), every new
type message must be added to **all** supported locales (`en`, `id`, `ja`) in the
same change.

---

## Tests

`tests/Feature/Http/Controllers/API/V1/NotificationTest.php` covers: broadcast
fan-out on user creation, role-scoped visibility (Admin sees / Moderator with the
permission still sees nothing outside its broadcast), personal notifications
reaching only the targeted user, the `account_updated` personal trigger on update
(skipped on self-edit), unread count + mark-as-read, mark-all, out-of-scope
403, and realtime websocket fan-out when `APP_NOTIFICATION_TYPE=realtime` with valid Pusher
config (polling-only when delivery is `polling`).

`tests/Unit/Repositories/Eloquent/NotificationTypeSettingRepositoryTest.php`
covers `resolveAudience()` merging roles, static users, subject user, and overrides.

`tests/Unit/Support/Broadcasting/BroadcastConnectionTest.php` covers the
notification-type + credential gate for realtime delivery.

`tests/Unit/Config/NotificationsConfigTest.php` covers the default and override for
`APP_NOTIFICATION_POLLING_INTERVAL`.

```bash
php artisan test --filter=NotificationTest
php artisan test --filter=NotificationTypeSettingRepositoryTest
php artisan test --filter=BroadcastConnectionTest
php artisan test --filter=NotificationsConfigTest
```

---

## Related documentation

- [Activity Notifications — Implementation Guide](./notifications-implementation-guide.md) — step-by-step tasks for new types, audience changes, dispatch, tests
- [User Management & H-RBAC](./user-management-hrbac.md) — the create/update flows that emit notifications
- [API Routing](./api-routing.md) — versioned route placement
- [CRUD Architecture & Repository Pattern](./crud-architecture.md) — dedicated repository rationale
- [Web frontend (Blade + AdminLTE 4)](./web-frontend.md) — API-client architecture, bell wiring
- [Localization](./localization.md) — message templates per locale
- [JWT Authentication & RBAC](./jwt-authentication.md) — guard, Spatie permissions
