# Notification — activity bell

In-app notification engine behind the navbar bell and the `/apps/notifications` history page: one persisted row per event, delivered to a **role broadcast** or to **specific users**, with per-user read state. It is a hybrid by design — the type catalogue and the trigger live in code, the audience lives in the database and is editable from the admin UI, so changing who hears about an event never needs a deploy. It is not Laravel's `notifications` table and not the audit trail in [activity-log](../activity-log/README.md). Code: `packages/notification/src/`. Invariants: [.cursor/rules/notification.mdc](../../../.cursor/rules/notification.mdc).

| Need | Path |
|------|------|
| Code | `packages/notification/src/` — `NotificationType` enum, `NotificationService`, `NotificationTypeSettingRepository`, `UserActivityNotificationReceived` |
| Config | [`packages/notification/config/notifications.php`](../../../packages/notification/config/notifications.php) · Settings → Platform `platform.notifications.delivery` |
| Tables | `activity_notifications`, `activity_notification_reads`, `notification_type_settings` + role/user pivots |
| Seeder | [`packages/notification/database/seeders/NotificationTypeSettingSeeder.php`](../../../packages/notification/database/seeders/NotificationTypeSettingSeeder.php) (audience baseline) |
| API | [`packages/notification/routes/api.php`](../../../packages/notification/routes/api.php) — `/api/v1/notifications*` |
| SPA | Vue Router `notifications.index`; bell in `AppNavbar`; audience admin `notification-audiences.*` |
| Realtime | private `App.Models.User.{userId}` — `.activity-notification.received` |
| Translations | `lang/*/api/notifications.php` (`types.*`) |
| Tests | `packages/notification/tests/` · `tests/Unit/Support/Broadcasting/BroadcastConnectionTest.php` · SPA `npm test` |
| Pakem | [.cursor/rules/notification.mdc](../../../.cursor/rules/notification.mdc) |
| Deep dive | [reference.md](reference.md) — mental model, delivery contract, workflows A–E, type inventory, verification, debug checklist |
| Related | [identity](../identity/README.md) (user CRUD types) · [service](../service/README.md) (subscription types) · [inference](../inference/README.md) (unregistered-model alert) · [platform/cache-policy.md](../../platform/cache-policy.md) (audience snapshot) |
