# Activity Notifications (role-targeted)

A generic, **role-targeted** notification engine: when an action happens (e.g. a
new user is created), one notification record is created and fanned out to whole
**roles**. Any current — or future — holder of a target role sees it. Per-user
read state is tracked separately.

This is **not** Laravel's database-channel `Notifiable` feature (the
`notifications` table). It is an application-domain surface with its own RBAC
visibility, stored under `activity_notifications` so it never shadows the
framework table.

All IDs are **UUID v7** end to end (see [User primary keys (UUID v7)](./user-uuid-v7.md)).
The feature uses a **dedicated repository** because visibility is a role-overlap
query, not plain CRUD. See [CRUD Architecture](./crud-architecture.md).

---

## Why role-targeted (not per-user copies)

A notification is stored **once** and linked to target **roles** via a pivot. A
viewer sees it when they hold at least one of those roles. Compared with fanning
out a row per user at creation time:

- A user promoted to `Admin` tomorrow immediately sees past notifications.
- No duplicate rows; one write regardless of audience size.
- Read state is the only per-user data (`activity_notification_reads.read_at`).

---

## Layers

| Layer | File | Responsibility |
|-------|------|----------------|
| Migration | `database/migrations/2026_06_12_000001_create_activity_notifications_tables.php` | `activity_notifications` + `activity_notification_roles` + `activity_notification_reads` |
| Model | `app/Models/ActivityNotification.php` | `HasUuids`, `roles()` + `readers()` relations |
| Type catalogue | `app/Notifications/Activity/NotificationType.php` | Enum: message key, icon, default target roles per action |
| Event | `app/Events/ActivityNotificationEvent.php` | Generic "raise a notification" signal any feature dispatches |
| Listener | `app/Listeners/SendActivityNotification.php` | Persists + fans out (auto-discovered) |
| Contract | `app/Repositories/Contracts/NotificationRepositoryInterface.php` | Repository contract |
| Repository | `app/Repositories/Eloquent/NotificationRepository.php` | Role-overlap visibility, unread count, read state |
| Service | `app/Services/Notification/NotificationService.php` | `emit()` + viewer reads |
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

- `activity_notification_roles` — (`notification_id`, `role_id`), composite PK, both FKs cascade. The target audience.
- `activity_notification_reads` — (`notification_id`, `user_id`, `read_at`), composite PK, both FKs cascade. A present row = the user has read it.

---

## Visibility rules

A notification is visible to a viewer when **the viewer holds at least one of the
notification's target roles** (api guard). No target roles ⇒ visible to nobody.
This is enforced in `NotificationRepository` (`listForViewer`, `isVisibleTo`,
`unreadCountForViewer`).

The **API surface** is additionally gated by the `view notification` permission
(`RoleSeeder` grants it to **Root** and **Admin**). The permission gates *access
to the endpoints*; the per-row role overlap is the finer rule.

> Keep the two aligned: a role with `view notification` should normally be among
> some notification's target roles, or it sees an empty list. The default
> `user_created` type targets `Root` + `Admin`, matching the permission grant.

---

## API

Base prefix `/api/v1`, route name prefix `api.v1.notifications.`. All routes use
`auth:api` + `permission:view notification`.

| Method | URI | Name | Action |
|--------|-----|------|--------|
| GET | `/api/v1/notifications` | `…index` | Visible notifications, newest first (`?limit=` 1–100, default 50) |
| GET | `/api/v1/notifications/unread-count` | `…unread-count` | `{ unread: N }` for the badge |
| POST | `/api/v1/notifications/read-all` | `…read-all` | Mark all visible as read (`{ marked: N }`) |
| POST | `/api/v1/notifications/{notification}/read` | `…read` | Mark one read; **403** if out of role scope |

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
storage or visibility directly:

```php
use App\Events\ActivityNotificationEvent;
use App\Notifications\Activity\NotificationType;

ActivityNotificationEvent::dispatch(
    NotificationType::UserCreated,
    ['name' => $user->name, 'email' => $user->email, 'actor' => $actor->name],
    actionRoute: 'users.edit',
    actionParams: ['user' => $user->id],
);
```

Omitting `roles` uses the type's `defaultRoles()`. Pass an explicit
`roles: ['Root']` to override per dispatch.

### Reference wiring: "new user created"

`UserManagementService::create()` dispatches `ActivityNotificationEvent` after
assigning the role. Because **both** the API (`POST /api/v1/users`,
`routes/api/v1.php`) and the Blade UI (`GET /users/create` → `POST /users`,
`routes/web.php`) funnel through this one service, a single dispatch covers every
entry point. Default audience: **Root** + **Admin**.

### Adding a new notification type

1. Add a case to `NotificationType` with its `icon()` and `defaultRoles()`.
2. Add the message template under `types.{value}` in
   `lang/en/api/notifications.php` **and** `lang/id/api/notifications.php`.
3. Dispatch `ActivityNotificationEvent` from wherever the action happens.

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
  shown only `@can('view notification')`. Polls the unread count every 60s,
  loads recent items on dropdown open, marks an item read on click.
- **Index page** — `resources/views/notifications/index.blade.php`, with a
  "mark all as read" action.

Sidebar entry: `MenuSeeder` adds a `Notifications` (`bi bi-bell`,
`notifications.index`) item role-gated to **Root** + **Admin**.

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

`tests/Feature/Http/Controllers/API/V1/NotificationTest.php` covers: fan-out on
user creation, Admin sees / Moderator forbidden, unread count + mark-as-read,
mark-all, and out-of-scope 403.

```bash
php artisan test --filter=NotificationTest
```

---

## Related documentation

- [User Management & H-RBAC](./user-management-hrbac.md) — the create flow that emits `user_created`
- [API Routing](./api-routing.md) — versioned route placement
- [CRUD Architecture & Repository Pattern](./crud-architecture.md) — dedicated repository rationale
- [Web frontend (Blade + AdminLTE 4)](./web-frontend.md) — API-client architecture, bell wiring
- [Localization](./localization.md) — message templates per locale
- [JWT Authentication & RBAC](./jwt-authentication.md) — guard, Spatie permissions
