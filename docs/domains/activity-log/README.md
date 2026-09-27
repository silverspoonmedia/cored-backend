# ActivityLog — Spatie audit trail

Immutable forensic log of domain mutations, so a change to a user, menu, service or vTual management can be traced back to a causer. It is **not** the notification bell and **not** [error-log](../error-log/README.md): this records what someone did, error-log records what broke. Code: `app/Domain/ActivityLog/`. Invariants: [.cursor/rules/activity-log.mdc](../../../.cursor/rules/activity-log.mdc).

| System | Table | Purpose |
|--------|-------|---------|
| Spatie activitylog | `activity_log` | Audit trail |
| Notifications | `activity_notifications` | In-app bell |

| Need | Path |
|------|------|
| Code | `app/Domain/ActivityLog/` |
| Provider | `app/Providers/Domain/ActivityLogServiceProvider.php` |
| Config | [`config/activitylog.php`](../../../config/activitylog.php) · [`config/encryption.php`](../../../config/encryption.php) |
| API | [`routes/api/v1/activity-log.php`](../../../routes/api/v1/activity-log.php) |
| SPA | Vue Router `activity-logs.index` / `activity-logs.show` under `/apps/activity-logs` |
| Realtime | `logs.activity` private channel — `.log.activity.appended` |
| Tests | `tests/Domain/ActivityLog/` · `tests/Feature/Http/CorsBroadcastAuthTest.php` |
| Pakem | [.cursor/rules/activity-log.mdc](../../../.cursor/rules/activity-log.mdc) |
| Deep dive | [reference.md](reference.md) — log names, PII envelope encryption, API, maintenance |
| Related | [error-log](../error-log/README.md) (shared redaction) · [identity](../identity/README.md) (`auth` log name) · [vtual](../vtual/README.md) (`vtual` log name) |
