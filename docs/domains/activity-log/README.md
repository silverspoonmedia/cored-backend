# ActivityLog — Spatie audit trail

Immutable forensic log of domain mutations, so a change to a user, menu or service can be traced back to a causer. It is **not** the notification bell and **not** [error-log](../error-log/README.md): this records what someone did, error-log records what broke. Code: `vendor/silverspoonmedia/audit/src/ActivityLog/` (`silverspoonmedia/audit`, bundled with ErrorLog). Invariants: [.cursor/rules/activity-log.mdc](../../../.cursor/rules/activity-log.mdc).

| System | Table | Purpose |
|--------|-------|---------|
| Spatie activitylog | `activity_log` | Audit trail |
| Notifications | `activity_notifications` | In-app bell |

| Need | Path |
|------|------|
| Code | `vendor/silverspoonmedia/audit/src/ActivityLog/` |
| Provider | [`vendor/silverspoonmedia/audit/src/ActivityLog/AuditServiceProvider.php`](../../../vendor/silverspoonmedia/audit/src/ActivityLog/AuditServiceProvider.php) — one provider for both audit domains |
| Config | [`config/activitylog.php`](../../../config/activitylog.php) (host-owned: Spatie's published config) · [`config/encryption.php`](../../../config/encryption.php) · `vendor/silverspoonmedia/audit/config/errorlog.php` (redaction list) |
| API | [`vendor/silverspoonmedia/audit/routes/activity-log.php`](../../../vendor/silverspoonmedia/audit/routes/activity-log.php) |
| SPA | Vue Router `activity-logs.index` / `activity-logs.show` under `/apps/activity-logs` |
| Realtime | `logs.activity` private channel — `.log.activity.appended` |
| Translations | `vendor/silverspoonmedia/audit/resources/lang/*/api/activity-log.php` |
| Tests | `tests/Feature/Http/CorsBroadcastAuthTest.php` (host) · the trail suite runs from a clone of `silverspoonmedia/audit`, not installed in `vendor/` |
| Pakem | [.cursor/rules/activity-log.mdc](../../../.cursor/rules/activity-log.mdc) |
| Deep dive | [reference.md](reference.md) — log names, PII envelope encryption, API, maintenance |
| Related | [error-log](../error-log/README.md) (shared redaction) · [identity](../identity/README.md) (`auth` log name) |
