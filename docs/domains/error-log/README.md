# ErrorLog — persistent grouped application errors

Browsable error surface for administrators: reportable exceptions and `Log::error`+ records, stored in `error_logs` and grouped by fingerprint. It exists so a failure is diagnosed from the admin UI (and by an agent) without reading one giant `laravel.log`; the audit trail lives in [activity-log](../activity-log/README.md) instead. Code: `vendor/silverspoonmedia/audit/src/ErrorLog/` (`silverspoonmedia/audit`, bundled with ActivityLog). Invariants: [.cursor/rules/error-log.mdc](../../../.cursor/rules/error-log.mdc).

| System | Table / store | Purpose |
|--------|---------------|---------|
| **ErrorLog (this domain)** | `error_logs` | Grouped runtime failures |
| Spatie activitylog | `activity_log` | Immutable audit of domain mutations |
| Notifications | `activity_notifications` | In-app bell |
| Laravel Monolog | `storage/logs/*` | File log (unchanged dual-write) |

| Need | Path |
|------|------|
| Code | `vendor/silverspoonmedia/audit/src/ErrorLog/` |
| Provider | [`vendor/silverspoonmedia/audit/src/ActivityLog/AuditServiceProvider.php`](../../../vendor/silverspoonmedia/audit/src/ActivityLog/AuditServiceProvider.php) — one provider for both audit domains |
| Config | [`vendor/silverspoonmedia/audit/config/errorlog.php`](../../../vendor/silverspoonmedia/audit/config/errorlog.php) · Settings → Platform (`platform.errorlog.*`) |
| Registry | `kind => core`, `package => silverspoonmedia/audit` in [`config/domains.php`](../../../config/domains.php); `DomainManager::PLATFORM_CORE` |
| API | [`vendor/silverspoonmedia/audit/routes/error-log.php`](../../../vendor/silverspoonmedia/audit/routes/error-log.php) |
| SPA | Vue Router `error-logs.index` / `error-logs.show` under `/apps/error-logs` |
| Realtime | `logs.errors` private channel — `.log.error.recorded` |
| Translations | `vendor/silverspoonmedia/audit/resources/lang/*/api/error-log.php` |
| Tests | `KernelBootTest` (host: the trail provider boots) · the ErrorLog suite runs from a clone of `silverspoonmedia/audit`, not installed in `vendor/` |
| Pakem | [.cursor/rules/error-log.mdc](../../../.cursor/rules/error-log.mdc) |
| Deep dive | [reference.md](reference.md) — capture pipeline, fingerprint, schema, settings, day-2 |
| Related | [activity-log](../activity-log/README.md) (audit, not errors) · [platform/security.md](../../platform/security.md) (redaction) |
