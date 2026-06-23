# Activity Notifications — Implementation Guide (for AI agents)

**Purpose:** Step-by-step playbook for adding or changing activity notifications in this project. Mention this file (with [`notifications.md`](./notifications.md) for architecture depth) when assigning notification work to an AI agent.

**Model:** **Hybrid** — notification **types** and **triggers** live in code; **audience** (who receives each type) is stored in `notification_type_settings` and maintained via **`NotificationTypeSettingSeeder`** in the same PR. There is **no admin UI** for audience or type catalogue. Do **not** build a fully dynamic type catalogue unless explicitly requested.

**Language:** All user-facing strings must be translated in **`en`**, **`id`**, and **`ja`** in the same task. See [Localization](./localization.md#task-rule-required).

---

## Read this first

| Question | Read |
|----------|------|
| How does the engine work (schema, bell, websocket, visibility)? | [`notifications.md`](./notifications.md) |
| How do I implement or change a notification? | **This file** |
| Where should dispatch live (service vs controller)? | [CRUD architecture](./crud-architecture.md) — business actions belong in **services** |
| Full-stack rules (API + web + tests + docs)? | [`README.md`](../README.md) |

---

## Architecture in one minute

```
Business service (after successful action)
    └── ActivityNotificationEvent::dispatch(NotificationType::…, $data, …)
            └── SendActivityNotification (listener, auto-discovered)
                    └── NotificationService::emit()
                            ├── NotificationTypeSettingRepository::resolveAudience()  ← DB settings
                            └── NotificationRepository::create()  → activity_notifications + pivots
```

| Concern | Owner | Configurable without deploy? |
|---------|--------|------------------------------|
| **Type exists** (`user_created`, …) | `NotificationType` enum | No |
| **When** to fire | Service / domain code | No |
| **Message** template | `lang/{locale}/api/notifications.php` | No (code change) |
| **Icon**, DataTable mapping | `NotificationType` enum methods | No |
| **Who** receives (roles / users / subject user) | `notification_type_settings` + seeder | PR / seeder change |
| **Deep link** | `actionRoute` + `actionParams` on dispatch | No |

---

## Task router — which workflow?

| Goal | Workflow | Code required? |
|------|----------|----------------|
| New business event should notify users | [A — Add a new notification type](#workflow-a-add-a-new-notification-type) | Yes |
| Change who receives an **existing** type | [B — Change audience (seeder)](#workflow-b-change-audience-seeder--pr) | Yes (seeder / PR) |
| Fix / change notification wording | [C — Change message copy](#workflow-c-change-message-copy-only) | Yes (lang files) |
| Type already in enum; wire a new trigger | [D — Wire dispatch only](#workflow-d-wire-dispatch-to-an-existing-type) | Yes (service only) |
| List screen should auto-refresh on notify | [E — DataTable live reload](#workflow-e-enable-datatable-live-reload) | Yes (enum + controller redirect + blade) |

---

## Workflow A — Add a new notification type

Use when a **new business action** needs a new kind of notification (new `type` string).

### Checklist (complete every item in one PR)

- [ ] **1. Enum case** — `app/Notifications/Activity/NotificationType.php`
- [ ] **2. Enum methods** — add arms to `icon()`, `defaultRoles()` (fallback only), `tableId()` (`null` if no list reload)
- [ ] **3. Translations** — `lang/en/api/notifications.php`, `lang/id/...`, `lang/ja/...` under `types.{type_value}`
- [ ] **4. Settings row** — extend `database/seeders/NotificationTypeSettingSeeder.php` with default roles/users/`notify_subject_user`
- [ ] **5. Dispatch** — `ActivityNotificationEvent::dispatch(...)` from the **service** that owns the action (not controller)
- [ ] **6. Tests** — feature test: emit + correct audience; extend `NotificationTest` or repository unit test
- [ ] **7. DataTable reload** (optional) — [Workflow E](#workflow-e-enable-datatable-live-reload)
- [ ] **8. Docs** — update this file’s [catalogue table](#current-type-catalogue) if you maintain it; no need to duplicate `notifications.md` unless architecture changes

### 1–2. Enum template

```php
// app/Notifications/Activity/NotificationType.php

case OrderCreated = 'order_created';

public function icon(): string
{
    return match ($this) {
        // …
        self::OrderCreated => 'bi bi-cart-plus',
    };
}

public function defaultRoles(): array
{
    return match ($this) {
        // …
        self::OrderCreated => ['Root', 'Admin'], // fallback if DB row missing
    };
}

public function tableId(): ?string
{
    return match ($this) {
        // …
        self::OrderCreated => 'orders-table', // or null
    };
}
```

**Naming:** `type` value = `snake_case` string, stable forever (stored in DB rows).

### 3. Translation template

Placeholders in templates must match keys in the `data` array passed to `dispatch()`.

```php
// lang/en/api/notifications.php  (+ id, ja)
'types' => [
    'order_created' => 'Order :number was created by :actor.',
],
```

### 4. Seeder template

```php
// database/seeders/NotificationTypeSettingSeeder.php

$orderCreated = NotificationTypeSetting::query()->firstOrCreate(
    ['type' => NotificationType::OrderCreated->value],
    ['notify_subject_user' => false],
);

$orderCreated->roles()->sync(
    Role::query()
        ->where('guard_name', 'api')
        ->whereIn('name', ['Root', 'Admin'])
        ->pluck('id')
        ->all(),
);
```

After deploy: `php artisan db:seed --class=NotificationTypeSettingSeeder`

### 5. Dispatch templates

**Broadcast (audience from DB settings — preferred):**

```php
use App\Events\ActivityNotificationEvent;
use App\Notifications\Activity\NotificationType;

ActivityNotificationEvent::dispatch(
    NotificationType::OrderCreated,
    [
        'number' => $order->number,
        'actor' => $actor->name,
    ],
    actionRoute: 'orders.show',           // registered web route name
    actionParams: ['order' => $order->id],
);
```

Do **not** pass `roles:` unless you intentionally override DB settings. `null` roles → resolved from `notification_type_settings`.

**Personal / subject user (audience partly from settings):**

```php
ActivityNotificationEvent::dispatch(
    NotificationType::AccountUpdated,
    [
        'name' => $user->name,
        'actor' => $actor->name,
        'subject_user_id' => $user->id,   // required when notify_subject_user = true
    ],
);
```

Set `notify_subject_user => true` in the seeder when the type should include `data['subject_user_id']` in the personal audience.

**Explicit override (rare — avoid unless documented):**

```php
ActivityNotificationEvent::dispatch(
    NotificationType::UserCreated,
    $data,
    roles: ['Admin'],       // overrides DB roles for this emit only
    users: [$someUserId],   // merged with DB users + subject
);
```

### 6. Test template

Use `Tests\Concerns\SeedsRolesAndPermissions` (seeds roles **and** `NotificationTypeSettingSeeder`).

```php
// Trigger the business action, then assert:
$this->assertDatabaseHas('activity_notifications', ['type' => 'order_created']);
$notification = ActivityNotification::query()->where('type', 'order_created')->firstOrFail();
$this->assertSame(['Admin', 'Root'], $notification->roles->pluck('name')->sort()->values()->all());
```

Run:

```bash
php artisan test --filter=Notification
```

---

## Workflow B — Change audience (seeder / PR)

Use when only **who receives** an existing type should change.

1. Edit `database/seeders/NotificationTypeSettingSeeder.php` — adjust `roles()->sync(...)`, `targetUsers`, or `notify_subject_user`.
2. For **already-deployed** databases, add a one-off migration or data patch that updates `notification_type_setting_roles` / `_users` (re-running the full seeder is not always safe on production).
3. Run tests: `php artisan test --filter=Notification`.

There is **no** admin UI or API for runtime audience edits.

---

## Workflow C — Change message copy only

Edit `types.{type}` in all three files:

- `lang/en/api/notifications.php`
- `lang/id/api/notifications.php`
- `lang/ja/api/notifications.php`

Do not change the `type` string in the enum or DB. Placeholders (`:name`, `:actor`, …) must still exist in emitted `data`.

---

## Workflow D — Wire dispatch to an existing type

Type already exists in `NotificationType` but a new code path should fire it.

1. Find the **single service** both API and web use (same pattern as `UserManagementService`).
2. Add `ActivityNotificationEvent::dispatch(...)` after the action succeeds (inside transaction only if notification should roll back with failure — today notifications run **after** commit via listener; keep dispatch **after** persistence succeeds).
3. Add / extend a feature test for that code path.

Do **not** duplicate dispatch in both API controller and web controller.

---

## Workflow E — Enable DataTable live reload

When a list screen should refresh when a notification fires (see [`notifications.md` — DataTable live reload](./notifications.md#datatable-live-reload-shipped)):

1. **`NotificationType::tableId()`** — return the table DOM id (e.g. `'orders-table'`).
2. **Web controller redirect** after create/delete:
   ```php
   return redirect()->route('orders.index')
       ->with('success', __('api/orders.created'))
       ->with('datatable_reload', 'orders-table');
   ```
3. **Blade index view** — expose DataTable API:
   ```javascript
   const table = $('#orders-table').DataTable({ /* … */ });
   window.dataTables = window.dataTables || {};
   window.dataTables['orders-table'] = table;
   ```

Reload logic is centralized in `resources/js/notifications/bell.js` — do not copy retry logic per page.

---

## Audience resolution (for agents debugging “wrong recipient”)

`NotificationTypeSettingRepository::resolveAudience()` builds the final audience:

1. If `$roles` argument is **not** `null` → use it for broadcast (emitter override).
2. Else → roles from `notification_type_setting_roles` for that type.
3. Else (no DB row) → `NotificationType::defaultRoles()`.
4. Users = explicit `$users` + `notification_type_setting_users` + (`notify_subject_user` ? `data['subject_user_id']` :).

---

## Current type catalogue

| Type | Trigger | Dispatch location | Default audience (seeder) | `notify_subject_user` |
|------|---------|-------------------|---------------------------|------------------------|
| `user_created` | User created | `UserManagementService::create()` | Broadcast: Root, Admin | `false` |
| `account_updated` | User updated by someone else | `UserManagementService::update()` | None (personal via subject) | `true` |

**Update this table** when adding a new type in the same PR.

---

## File map (quick reference)

| Task | Files |
|------|--------|
| New type | `NotificationType.php`, `lang/*/api/notifications.php`, `NotificationTypeSettingSeeder.php`, owning **service**, tests |
| Dispatch only | Owning **service**, tests |
| Audience defaults (fresh install) | `NotificationTypeSettingSeeder.php` |
| Message copy | `lang/en|id|ja/api/notifications.php` |
| Engine / visibility | `NotificationService.php`, `NotificationRepository.php` — **rarely** touch for new types |
| Tests | `NotificationTest.php`, `NotificationTypeSettingRepositoryTest.php` |

---

## Pitfalls — do not

| Mistake | Why |
|---------|-----|
| Add admin UI or API to manage notification types or audience | Out of scope; audience via seeder; types via enum |
| Add “create notification type” admin CRUD | Out of scope; types are code catalogue |
| Dispatch from web controller **and** API controller | Duplicate notifications; use one service |
| Hardcode role names in dispatch | Use DB settings; pass `roles` only as rare override |
| Register `SendActivityNotification` in `Event::listen` | Auto-discovered — double fire |
| Use Laravel `Notifiable` / `notifications` table | Different feature; we use `activity_notifications` |
| Skip `id` / `ja` translations | Project rule — all locales in same PR |
| Store request state on static properties | [Octane readiness](./octane-readiness.md) |

---

## Suggested prompt for AI agents

Copy, fill in the bracketed parts, and attach this file + `docs/notifications.md`:

```text
@README.md @docs/notifications.md @docs/notifications-implementation-guide.md

Task: [describe the business event, e.g. "notify Root+Admin when an order is created"]

Follow the implementation guide task router and complete the matching workflow end-to-end:
- enum + translations (en, id, ja) + seeder + service dispatch + tests
- Do NOT add admin UI or API for notification types or audience
- Audience should use notification_type_settings (not hardcoded roles in dispatch)
- Run php artisan test --filter=Notification before finishing
- Update the catalogue table in notifications-implementation-guide.md if adding a new type
```

For **audience-only** changes:

```text
@docs/notifications-implementation-guide.md

Task: Change who receives [type] notifications to [roles/users description].
This is Workflow B — no new enum case. Update NotificationTypeSettingSeeder defaults if needed for fresh installs.
```

---

## Related documentation

- [`notifications.md`](./notifications.md) — architecture, API, bell, websocket, DataTable hybrid reload
- [`user-management-hrbac.md`](./user-management-hrbac.md) — reference service that emits notifications
- [`localization.md`](./localization.md) — translation rules
- [`web-frontend.md`](./web-frontend.md) — Blade admin as API client
