# Environment configuration

Laravel loads **one** `.env` at boot. Monolith adds profile snapshots for local switching.

## Files

| File | Purpose |
|------|---------|
| `.env.example` | Master catalog (all keys + comments). Sync profile templates when keys change. |
| `.env.{local,staging,production}.example` | Committed profile templates |
| `.env` | Active (gitignored) — Laravel reads this |
| `.env.profile` | Active profile name |
| `.env.{local,staging,production}` | Machine snapshots (gitignored) |

## Quick start

```bash
composer install
php artisan env:install local
# set APP_URL, DB_*, secrets…
php artisan migrate
php artisan db:seed
```

`env:install` copies the template, seeds the snapshot, sets profile, generates empty `APP_KEY`, `JWT_SECRET`, `ENCRYPTION_BLIND_INDEX_PEPPER`.

## Value types

Defined in `EnvironmentVariableCatalog`:

| Type | Format |
|------|--------|
| string | Always double-quoted (`APP_ENV="local"`) |
| bool | `true` / `false` unquoted |
| int | Digits unquoted |
| null | `null` unquoted |

Empty secrets: `KEY=""`. When adding keys: update `.env.example`, all three profile templates, and `EnvironmentVariableCatalog::types()`.

## Application keys

| Variable | Role |
|----------|------|
| `APP_KEY` | Active Laravel encryption key |
| `APP_PREVIOUS_KEYS` | Retired keys after rotation (decrypt still works) |

`key:generate --force` appends current key to previous, writes new key. Implementation: `EnvironmentKeyWriter` (file-based, not broken by quoted values).

## Switching profiles (local)

| Command | Purpose |
|---------|---------|
| `env:install {profile}` | Fresh from template |
| `env:switch {profile}` | Save current → load target snapshot; clears config cache |
| `env:switch` / `--list` | Show active / list snapshots |

**Always edit active `.env`.** Bootstrap uses `LoadMonolithEnvironmentVariables` so Laravel does **not** silently load snapshot `.env.local` when `APP_ENV` is preset (Laragon trap).

## Config caching

When `bootstrap/cache/config.php` exists, `.env` is ignored for config.

| Command | Use |
|---------|-----|
| `config:clear` | Local — live `.env` |
| `config:verify-secrets` | Check secrets (`--fresh` against `.env`) |
| `config:rebuild` | **Preferred** — clear → verify → cache → re-verify |

Never bare `config:cache` without verify. After secret changes: `config:rebuild` then `php artisan oci:kms-verify` if using KMS.

## Profile differences (high level)

| Variable | local | staging/production |
|----------|-------|--------------------|
| `APP_DEBUG` | true | false |
| `SESSION_SECURE_COOKIE` / encrypt | false | true |
| `HCAPTCHA_ENABLED` | false | true |
| Cache/queue | database | redis |
| Notifications | polling | realtime |

## Production checklist

- `APP_ENV=production`, `APP_DEBUG=false`
- Secure session cookies; `JWT_SECRET` set
- `config:rebuild`; `oci:kms-verify` if OCI
- Liveness: `route:list --path=api/ping` shows `GET /api/ping`
- Network exposure: firewall / reverse proxy as needed (no in-app shared-secret API perimeter)

## Testing environment

PHPUnit: `APP_ENV=testing`, sqlite, CSRF bypass for web proxy tests. Prefer `composer test`. See [database-safety.md](database-safety.md).

Payment: `checkout_mode` derived from active channels at runtime — see [payment-gateway](../domains/payment-gateway/README.md). Encryption env: [encryption](../domains/payment-gateway/encryption.md).

## Persistent error log

| Variable | Role |
|----------|------|
| `ERRORLOG_ENABLED` | Store reportable exceptions + `Log::error+` in DB (`true` / `false`) |
| `ERRORLOG_CLEAN_AFTER_DAYS` | Retention for `php artisan errorlog:clean` |

Independent of `LOG_CHANNEL` file logs. Domain guide: [error-log](../domains/error-log/README.md).

## Related

- [octane.md](octane.md)
- [domains/error-log/README.md](../domains/error-log/README.md)
