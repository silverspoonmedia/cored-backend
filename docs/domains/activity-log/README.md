# ActivityLog — Spatie audit trail

Immutable forensic log of domain mutations. **Not** the notification bell.

| System | Table | Purpose |
|--------|-------|---------|
| Spatie activitylog | `activity_log` | Audit trail |
| Notifications | `activity_notifications` | In-app bell |

Code: `app/Domain/ActivityLog/`. Config: `config/activitylog.php`.

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
| Order | `order` | fillable |
| Menu | `menu` | structural + translatable |

## Manual / semantic

Pivots (e.g. role assign) → explicit `activity()->useLog('user')->event('role_assigned')…`.

### Auth (`log_name: auth`)

Login, logout, 2FA, password — via domain events + `AuthActivityRecorder`. Coverage matrix: [platform/security.md](../../platform/security.md). Filter `?log_name=auth`.

### Crypto (`log_name: crypto`)

Decrypts only through `EnvelopeEncryptionService` → `CryptoActivityRecorder`. Modes: `ENCRYPTION_AUDIT_DECRYPTS=cache_miss|always`. Never log plaintext/DEKs. [payment-gateway/encryption](../payment-gateway/encryption.md).

## API (`/api/v1`)

Requires `auth:api` + `permission:view activity log` (Root/Admin).

| Method | URI |
|--------|-----|
| GET | `/activity-logs` |
| GET | `/activity-logs/{activity}` |
| GET | `/users/{user}/activity-logs` |

Resource: event, labels, causer, attribute_changes, properties. Web list/show proxy API; user edit shows recent activity card.

## Web (Blade)

Authenticated UI under `/apps/*` (route names unprefixed): `/apps/activity-logs` (`activity-logs.index`), `/apps/activity-logs/{activity}` (`activity-logs.show`). See [blade-admin — Routes](../../web/blade-admin.md#routes).

**Sidebar:** **Administration → Monitoring → Activity log** (`activity-logs.index`), visible to Root and Admin.

## Maintenance

```bash
php artisan activitylog:clean   # default retention 365 days
```

## Related

- [identity](../identity/README.md)
- [notification](../notification/README.md)
- [error-log](../error-log/README.md) — grouped runtime failures (not audit)
- [platform/octane.md](../../platform/octane.md)
