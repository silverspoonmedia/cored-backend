# Notification — activity bell (role + user)

Hybrid engine: **types/triggers in code**, **audience in DB** (`notification_type_settings` + seeder). Not Laravel’s `notifications` table.

Code: `app/Domain/Notification/`.

## Mental model

| Mode | Audience | Use |
|------|----------|-----|
| **Broadcast (role)** | Role pivots — any current/future role holder | “User created” → Root+Admin |
| **Personal (user)** | User pivots | “Your account was updated” |

One row + pivots; per-user read state in `activity_notification_reads`. Viewer sees if role match **OR** user target.

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

Realtime: after persist, `UserActivityNotificationReceived` on private `App.Models.User.{id}`; bell refetches unread via HTTP for localized copy.

## Schema (essentials)

- `activity_notifications` — type, data JSON, action route/params, timestamps  
- Pivots: roles, target users, reads  
- `notification_type_settings` + role/user pivots, `notify_subject_user`

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

Map `tableId()` to table id; ensure list page listens for bell ping.

## Role vs user (decision)

| Need | Mode |
|------|------|
| Anyone with role should see (including future assigns) | Role broadcast |
| Only specific people | Personal users |
| Edited user should know | `notify_subject_user` / personal to subject |

**Pitfalls:** dispatch from controller; hardcode audience in code instead of settings; forget seeder; double listener registration; mix up Spatie activitylog vs this bell.

## Related

- [platform/localization.md](../../platform/localization.md)
- [platform/crud.md](../../platform/crud.md)
- [activity-log](../activity-log/README.md) — separate audit trail
