# Activity log (Spatie laravel-activitylog)

Immutable **audit trail** for domain mutations: who changed what, when, and which attributes moved. This is **not** the in-app notification bell (`ActivityNotification` — see [Activity notifications](./notifications.md)) and **not** the approval workflow journal (`approval_logs` — see [Approval matrix engine](./approval-matrix-engine.md)).

| System | Table | Purpose |
|--------|-------|---------|
| **Spatie activitylog** | `activity_log` | General forensic audit trail |
| Activity notifications | `activity_notifications` | Role/personal in-app bell |
| Approval logs | `approval_logs` | Workflow state machine history |

---

## Package and configuration

| Item | Location |
|------|----------|
| Composer | `spatie/laravel-activitylog` ^5.0 |
| Config | `config/activitylog.php` |
| Migration | `database/migrations/2026_06_21_043446_create_activity_log_table.php` |

Project-specific config:

| Key | Value | Why |
|-----|-------|-----|
| `default_auth_driver` | `api` | JWT guard resolves the causer on API requests |
| `include_soft_deleted_subjects` | `true` | `User` and `Menu` use soft deletes |
| `default_except_attributes` | `password`, `remember_token`, 2FA secrets | Never log credentials |

**UUID morph columns:** `subject_id` and `causer_id` are **UUID** (`nullableUuidMorphs`) so they match domain models that use `HasUuids`. The activity row's own `id` stays a bigint (Spatie default).

Disable logging globally with `ACTIVITYLOG_ENABLED=false` in `.env`.

---

## What gets logged automatically

Models use Spatie v5 traits (`LogsActivity` / `HasActivity`) with `logOnlyDirty()` so empty updates are skipped.

| Model | Trait | Log name | Tracked attributes |
|-------|-------|----------|-------------------|
| `User` | `HasActivity` | `user` | `name`, `email`, `locale`, `email_verified_at` (+ create/delete/restore events) |
| `Order` | `LogsActivity` | `order` | All fillable columns |
| `Menu` | `LogsActivity` | `menu` | Structural + translatable fields |

Eloquent `created`, `updated`, `deleted`, and `restored` (when `SoftDeletes` is present) are recorded automatically when mutations go through the model.

---

## Manual logging (pivot / semantic events)

Spatie does **not** log `syncRoles()` on its own. `UserManagementService` writes an explicit entry after role assignment:

```php
activity()
    ->useLog('user')
    ->causedBy($actor)
    ->performedOn($user)
    ->event('role_assigned')
    ->withProperties(['role' => $roleName])
    ->log('role_assigned');
```

Use the same pattern for other pivot-only or cross-model events that should appear in the audit trail.

### Authentication lifecycle (`log_name: auth`)

Login, logout, 2FA, and password changes do **not** mutate tracked `User` columns, so they are recorded via domain events and `AuthActivityRecorder` (see [Authentication activity assessment](./auth-activity-assessment.md)).

| Event | Activity `event` | Dispatched from |
|-------|------------------|-----------------|
| `LoginSuccessful` | `login` | `AuthService` (password or post-2FA) |
| `LoginFailed` | `login_failed` | `AuthService` |
| `UserLoggedOut` | `logout` | `AuthService` |
| `TwoFactorChallengeIssued` | `two_factor_challenge` | `AuthService` |
| `TwoFactorLoginFailed` | `two_factor_login_failed` | `AuthService` |
| `PasswordChanged` | `password_changed` | `PasswordResetService` |
| `TwoFactorEnabled` | `two_factor_enabled` | `TwoFactorService` |
| `TwoFactorDisabled` | `two_factor_disabled` | `TwoFactorService` |

Listeners: `RecordAuthenticationActivity` (activity log) and `LogAuthenticationEvents` (Laravel file log). They are **auto-discovered** from `subscribe()` — never register them again with `Event::subscribe()` in a service provider or every auth row is duplicated. Filter the viewer with `?log_name=auth`.

### Cryptographic decrypt accountability (`log_name: crypto`)

Envelope decryption is recorded via `CryptoActivityRecorder` — called **only** from `EnvelopeEncryptionService::decrypt()` (the sole decrypt choke point). Never log plaintext, ciphertext, DEKs, or wrapped keys.

| Event | Activity `event` | Trigger |
|-------|------------------|---------|
| Field decrypted | `field_decrypted` | Successful decrypt when audit rules apply (see below) |

Properties (safe metadata only): `resource_type`, `resource_id`, `field`, `purpose`, `kms_key_id`, `ip_address`, `cache_hit`, `envelope_version`, `legacy_ciphertext`.

**Audit frequency** (`ENCRYPTION_AUDIT_DECRYPTS`):

| Mode | Behavior |
|------|----------|
| `cache_miss` (default) | Log the first decrypt per context within the decrypt-cache TTL only. |
| `always` | Log every decrypt; `cache_hit: true` when served from decrypt cache. |

**API masking:** `ActivityLogResource` masks `kms_key_id` OCIDs in JSON responses. Database rows keep the full OCID for forensics.

**Not audited:** Session reads (`sessions` / `session_read`) — sessions use Laravel `SESSION_ENCRYPT`, not envelope KMS.

Filter the viewer with `?log_name=crypto`. See [Envelope encryption](./envelope-encryption.md) and [Crypto activity assessment](./crypto-activity-assessment.md).

---

## API surface (`/api/v1`)

All routes require `auth:api` and `permission:view activity log` (Root + Admin by default).

| Method | URI | Route name | Description |
|--------|-----|------------|-------------|
| `GET` | `/activity-logs` | `api.v1.activity-logs.index` | Paginated-style list (`limit`, optional `log_name`, `subject` + `subject_id`, `event`) |
| `GET` | `/activity-logs/{activity}` | `api.v1.activity-logs.show` | Single entry (`{activity}` is numeric Spatie id) |
| `GET` | `/users/{user}/activity-logs` | `api.v1.users.activity-logs` | Timeline for one user (includes soft-deleted subjects) |

Controllers: `App\Domain\ActivityLog\Http\Controllers\API\V1\ActivityLogController`  
Service: `App\Domain\ActivityLog\Services\ActivityLogService`  
Repository: `App\Domain\ActivityLog\Repositories\Eloquent\ActivityLogRepository`

Response shape: `ActivityLogResource` — `id`, `log_name`, `event`, localized `event_label`, `subject_label`, `causer`, `attribute_changes`, `properties`, `created_at`.

---

## Web frontend

The Blade admin **proxies** the API (no duplicate RBAC):

| Web route | API target | Screen |
|-----------|------------|--------|
| `activity-logs.index` | `GET /api/v1/activity-logs` | Full audit list |
| `activity-logs.show` | `GET /api/v1/activity-logs/{id}` | Entry detail (attribute diff + properties) |

`users.edit` also loads `GET /api/v1/users/{user}/activity-logs` and renders a **Recent activity** card (`resources/views/adminlte4/users/_activity_history.blade.php`).

Sidebar entry: **Administration → Activity log** (Root + Admin), seeded in `MenuSeeder`.

---

## Permissions

| Permission | Roles (default) |
|------------|-----------------|
| `view activity log` | Root, Admin |

Defined in `database/seeders/RoleSeeder.php`.

---

## Localization

| Area | Files |
|------|-------|
| API messages + event labels | `lang/{en,id,ja}/api/activity-log.php` |
| Blade copy | `lang/{en,id,ja}/web/activity-log.php` |

Event keys live under `api/activity-log.events.*` (e.g. `created`, `updated`, `role_assigned`).

---

## Octane / Swoole

Activity logging uses Spatie's request-scoped `ActivityLogger` and Eloquent events — **no static request state** in app code. Follow [Octane readiness](./octane-readiness.md): do not cache the authenticated user on singletons; the `api` auth driver in config resolves the causer per request.

---

## Maintenance

```bash
php artisan activitylog:clean   # Deletes entries older than config('activitylog.clean_after_days')
```

Default retention: **365 days** (`config/activitylog.php`).

---

## Related docs

- [CRUD architecture](./crud-architecture.md) — where mutations flow (Service → Repository → Model)
- [User management & H-RBAC](./user-management-hrbac.md) — user CRUD + role assignment
- [Authentication activity assessment](./auth-activity-assessment.md) — auth event coverage and activity log mapping
- [Envelope encryption](./envelope-encryption.md) — decrypt audit (`log_name: crypto`)
- [Activity notifications](./notifications.md) — in-app bell (separate system)
- [User primary keys (UUID v7)](./user-uuid-v7.md) — UUID morph FK rules
