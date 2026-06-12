# Activity Notifications (per role + per user)

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
| Model | `app/Models/ActivityNotification.php` | `HasUuids`, `roles()` + `targetUsers()` + `readers()` |
| Type catalogue | `app/Notifications/Activity/NotificationType.php` | Enum: message key, icon, default broadcast roles per action |
| Event | `app/Events/ActivityNotificationEvent.php` | Generic "raise a notification" signal any feature dispatches |
| Listener | `app/Listeners/SendActivityNotification.php` | Persists + fans out (auto-discovered) |
| Contract | `app/Repositories/Contracts/NotificationRepositoryInterface.php` | Repository contract |
| Repository | `app/Repositories/Eloquent/NotificationRepository.php` | Role/user visibility, unread count, read state |
| Service | `app/Services/Notification/NotificationService.php` | `emit()` / `broadcastToRoles()` / `sendToUsers()` + viewer reads |
| Resource | `app/Http/Resources/Notification/NotificationResource.php` | JSON shape (localized `message`, `icon`, `is_read`) |
| Request | `app/Http/Requests/Notification/IndexNotificationRequest.php` | `limit` validation |
| API controller | `app/Http/Controllers/API/V1/NotificationController.php` | Thin HTTP layer |
| Web controller | `app/Http/Controllers/Web/NotificationController.php` | Pure API consumer (bell + page) |

The repository is bound in `App\Providers\AppServiceProvider`:

```php
$this->app->bind(NotificationRepositoryInterface::class, NotificationRepository::class);
```

The listener is **auto-discovered** (Laravel scans `app/Listeners`); do **not**
also register it with `Event::listen` or it fires twice.

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
storage or visibility directly. Choose the audience with `roles` (broadcast)
and/or `users` (personal):

```php
use App\Events\ActivityNotificationEvent;
use App\Notifications\Activity\NotificationType;

// Broadcast to roles (null roles = the type's defaultRoles()):
ActivityNotificationEvent::dispatch(
    NotificationType::UserCreated,
    ['name' => $user->name, 'email' => $user->email, 'actor' => $actor->name],
    actionRoute: 'users.edit',
    actionParams: ['user' => $user->id],
);

// Personal — to specific users only:
ActivityNotificationEvent::dispatch(
    NotificationType::AccountUpdated,
    ['name' => $user->name, 'actor' => $actor->name],
    roles: [],            // no broadcast
    users: [$user->id],   // personal audience
);
```

Or call `NotificationService` directly when not dispatching an event:

| Method | Audience |
|--------|----------|
| `emit($type, $data, $roles, $users, …)` | Full control (roles and/or users) |
| `broadcastToRoles($type, $data, $roles, …)` | Roles only (defaults when `$roles` null) |
| `sendToUsers($type, $users, $data, …)` | Specific users only |

### Reference wiring

| Trigger | Type | Audience | Where |
|---------|------|----------|-------|
| User created | `user_created` | **Broadcast** → Root + Admin | `UserManagementService::create()` |
| User account updated (by someone else) | `account_updated` | **Personal** → the edited user | `UserManagementService::update()` (skipped on self-edit) |

Because **both** the API and the Blade UI funnel user create/update through
`UserManagementService`, a single dispatch covers every entry point.

### Adding a new notification type

1. Add a case to `NotificationType` with its `icon()` and `defaultRoles()`
   (return `[]` for personal-only types).
2. Add the message template under `types.{value}` in
   `lang/en/api/notifications.php` **and** `lang/id/api/notifications.php`.
3. Dispatch `ActivityNotificationEvent` (or call the service) with the right
   `roles` / `users` audience.

No engine, controller, or migration change is required.

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

- **Navbar bell** — `resources/views/components/partials/navbar.blade.php`,
  shown `@can('view notification')` (every role). Polls the unread count every
  60s, loads recent items on dropdown open, marks an item read on click. Built
  on AdminLTE's native `navbar-badge` + `dropdown-header`/`dropdown-footer`.
- **Index page** — `resources/views/notifications/index.blade.php`, with a
  "mark all as read" action.

Sidebar entry: `MenuSeeder` adds a `Notifications` (`bi bi-bell`,
`notifications.index`) item visible to **all authenticated** users (no role gate,
since any user can receive a personal notification).

---

## Localization

User-facing copy lives in application-owned files (all locales):

| File | Use |
|------|-----|
| `lang/{locale}/api/notifications.php` | API messages + per-type `types.*` templates |
| `lang/{locale}/web/notifications.php` | Bell + page labels |
| `lang/{locale}/menu.php` | `notifications` static label |

Per the [Localization task rule](./localization.md#task-rule-required), every new
type message must be added to **both** `en` and `id` in the same change.

---

## Tests

`tests/Feature/Http/Controllers/API/V1/NotificationTest.php` covers: broadcast
fan-out on user creation, role-scoped visibility (Admin sees / Moderator with the
permission still sees nothing outside its broadcast), personal notifications
reaching only the targeted user, the `account_updated` personal trigger on update
(skipped on self-edit), unread count + mark-as-read, mark-all, and out-of-scope
403.

```bash
php artisan test --filter=NotificationTest
```

---

## Related documentation

- [User Management & H-RBAC](./user-management-hrbac.md) — the create/update flows that emit notifications
- [API Routing](./api-routing.md) — versioned route placement
- [CRUD Architecture & Repository Pattern](./crud-architecture.md) — dedicated repository rationale
- [Web frontend (Blade + AdminLTE 4)](./web-frontend.md) — API-client architecture, bell wiring
- [Localization](./localization.md) — message templates per locale
- [JWT Authentication & RBAC](./jwt-authentication.md) — guard, Spatie permissions
