# ActivityLog — deep dive

Config, automatic vs manual logging, PII envelope encryption, API and maintenance. Invariants: [.cursor/rules/activity-log.mdc](../../../.cursor/rules/activity-log.mdc). Brief: [README.md](README.md).

## Config highlights

| Key | Value |
|-----|-------|
| `default_auth_driver` | `api` |
| `include_soft_deleted_subjects` | `true` |
| `default_except_attributes` | password, tokens, 2FA secrets |

Morph columns `subject_id` / `causer_id` are **UUID**. Activity row `id` is bigint. Disable: `ACTIVITYLOG_ENABLED=false`.

## Automatic logging

Models with `LogsActivity` / `HasActivity` + `logOnlyDirty()`:

| Model | Log name | Notes |
|-------|----------|-------|
| User | `user` | name, email, locale, verified_at |
| Service | `service` | fillable |
| Menu | `menu` | structural + translatable |

## Manual / semantic

Pivots (e.g. role assign) → explicit `activity()->useLog('user')->event('role_assigned')…`.

### Auth (`log_name: auth`)

Login, logout, 2FA, password — via domain events + `AuthActivityRecorder`. Coverage matrix: [platform/security.md](../../platform/security.md). Filter `?log_name=auth`.

### Crypto (`log_name: crypto`)

Decrypts only through `EnvelopeEncryptionService` → `CryptoActivityRecorder`. Modes: `ENCRYPTION_AUDIT_DECRYPTS=cache_miss|always`. Never log plaintext/DEKs. [envelope-encryption](../../platform/envelope-encryption.md).

## API (`/api/v1`)

Requires `auth:api` + `permission:view activity log` (Root/Admin).

| Method | URI |
|--------|-----|
| GET | `/activity-logs` |
| GET | `/activity-logs/{activity}` |
| GET | `/users/{user}/activity-logs` |

Resource: event, labels, causer, attribute_changes, properties. **Properties and attribute_changes are redacted** with the same key list as error-log context (`ErrorContextRedactor` / `config/errorlog.php` `redact_keys`) for every log name; crypto rows still mask `kms_key_id` after that.

## PII at rest (envelope encryption)

`properties` keys `ip_address`, `user_agent`, `email` are **envelope-encrypted** (OCI KMS) at rest — see [envelope-encryption.md](../../platform/envelope-encryption.md). Every other key stays plaintext so `properties->some_key` JSON queries and list filters keep working (e.g. `properties->workspace_id` in the Archivd workspace feed).

| Piece | Path | Role |
|-------|------|------|
| Write | `SensitiveActivityProperties::encryptInPlace()` | Called from `LogActivityAction::beforeLogging()` (registered in `AuditServiceProvider::boot()`) — single choke point for every recorder plus the `activity()` helper |
| Read | `SensitiveActivityPropertyPresenter::forApi()` | Decrypts for `ActivityLogResource` + `WorkspaceController::activity()`; `[unavailable]` sentinel when an envelope cannot be decrypted (never leaks ciphertext) |
| Config | `config/activitylog.php` `sensitive_properties` | `enabled` + `keys`; no env var — switch off in config only |
| Cache | `config/encryption.php` `decrypted_cache_ttl_by_resource_type.activity_log` | 900s plaintext cache cap (rows are immutable, so bounded retention is enough) |
| Audit | `config/encryption.php` `audit_excluded_resource_types` | `activity_log` excluded so viewing the trail does not append `crypto` rows to itself |

Notes:

- **PII keys apply to every log name**, including `crypto` rows. Because `CryptoActivityRecorder` runs inside `EnvelopeEncryptionService::decrypt()`, each audited decrypt performs one extra DEK wrap (KMS). Keep `ENCRYPTION_AUDIT_DECRYPTS=cache_miss` (default); `always` would make the decrypt cache stop avoiding KMS.
- A KMS failure during an audit write **drops** the PII key instead of failing the request (login must never break because the audit row could not be encrypted) and reports the exception.
- Backfill existing rows with `php artisan encryption:migrate-activity-log` (`--dry-run` first). Idempotent: rows already holding envelopes are skipped. Legacy plaintext rows are still returned as-is by the API until then.
- Out of scope today: `attribute_changes` (e.g. `user` log name/email), `error_logs.context`, and the file log under `storage/logs/`.

## SPA UI

Authenticated screens live in Cored Frontend: `/apps/activity-logs` (`activity-logs.index`), `/apps/activity-logs/{activity}` (`activity-logs.show`). Query-mode list rows emit `actions[]` (`view` → `activity-logs.show`); SPA maps into `RowActions`.

**Sidebar:** **Administration → Monitoring → Activity log** (`activity-logs.index`), visible to Root and Admin.

## Realtime

Every recorded entry pings the `logs.activity` private channel (permission `view activity log`) as `.log.activity.appended` — payload `{ log_name, event }`, no entry content. Emitted by `ActivityLogRealtime` from the auth/crypto recorders, gated on `BroadcastConnection::isConfigured()`. SPA: `useActivityLogRealtime.ts` (a `createRealtimeChannel({ channel: 'logs.activity', … })` binding — channel lifecycle lives in `src/composables/echo/channelRegistry.ts`); `ActivityLogsView` and `ActivityLogShowView` refetch on ping.

**Debugging a "CORS error" on the detail page.** `POST /api/v1/broadcasting/auth` for `private-logs.activity` failing in DevTools is a transport problem, not a channel-permission problem. Two causes: the request URL is missing `/api/v1` (the framework's web twin at `/broadcasting/auth` is CSRF-guarded and answered 403 without CORS headers before `config/cors.php` listed it), or the authorize overlapped the `__Host-spa_csrf` write from the shell batch (the SPA now awaits `whenCsrfCookieQuiet()` before `initEcho()` and before each authorize). Check the URL first, then `curl -i -X POST …/api/v1/broadcasting/auth -H "Origin: <spa-origin>"`: a 401 **with** `Access-Control-Allow-Origin` means the backend perimeter is healthy and the problem is client-side. Invariant test: `tests/Feature/Http/CorsBroadcastAuthTest.php`.

## Maintenance

```bash
php artisan activitylog:clean   # default retention 365 days
php artisan encryption:migrate-activity-log --dry-run   # PII envelope backfill (see above)
php artisan encryption:migrate-activity-log
```
