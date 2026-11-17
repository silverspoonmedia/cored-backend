# ActivityLog — Spatie audit trail

Immutable forensic log of domain mutations, so a change to a user, menu or service can be traced back to a causer. It is **not** the notification bell and **not** [error-log](../error-log/README.md): this records what someone did, error-log records what broke. Code: `packages/audit/src/ActivityLog/` (`silverspoon/audit`, bundled with ErrorLog). Invariants: [.cursor/rules/activity-log.mdc](../../../.cursor/rules/activity-log.mdc).

| System | Table | Purpose |
|--------|-------|---------|
| Spatie activitylog | `activity_log` | Audit trail |
| Notifications | `activity_notifications` | In-app bell |

| Need | Path |
|------|------|
| Code | `packages/audit/src/ActivityLog/` |
| Provider | [`packages/audit/src/ActivityLog/AuditServiceProvider.php`](../../../packages/audit/src/ActivityLog/AuditServiceProvider.php) — one provider for both audit domains |
| Config | [`config/activitylog.php`](../../../config/activitylog.php) (host-owned: Spatie's published config) · [`config/encryption.php`](../../../config/encryption.php) · `packages/audit/config/errorlog.php` (redaction list) |
| API | [`packages/audit/routes/activity-log.php`](../../../packages/audit/routes/activity-log.php) |
| SPA | Vue Router `activity-logs.index` / `activity-logs.show` under `/apps/activity-logs` |
| Realtime | `logs.activity` private channel — `.log.activity.appended` |
| Translations | `packages/audit/resources/lang/*/api/activity-log.php` |
| Tests | `packages/audit/tests/ActivityLog/` · `tests/Feature/Http/CorsBroadcastAuthTest.php` |
| Pakem | [.cursor/rules/activity-log.mdc](../../../.cursor/rules/activity-log.mdc) |
| Deep dive | [reference.md](reference.md) — log names, PII envelope encryption, API, maintenance |
| Related | [error-log](../error-log/README.md) (shared redaction) · [identity](../identity/README.md) (`auth` log name) |
