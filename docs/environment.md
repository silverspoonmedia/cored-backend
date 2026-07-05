# Environment configuration

How Monolith manages `.env` files across local, staging, and production.

## How it works

Laravel loads **one** `.env` file at boot. It does not merge `.env.local` + `.env.production` automatically (unlike some Node.js frameworks).

```
Committed (in git)                    Gitignored (per machine)
─────────────────────                 ──────────────────────────
.env.example          master catalog  .env
.env.local.example    local template
.env.staging.example  staging template
.env.production.example production template
```

| File | Purpose |
|------|---------|
| `.env.example` | **Master catalog** — every variable with block comments. Do not copy directly; use it when adding or removing keys, then sync the profile templates. |
| `.env.local.example` | Local development defaults (debug on, polling notifications, `MAIL_MAILER=log`, database cache/queue). |
| `.env.staging.example` | Pre-production mirror: production-like security, playground **still enabled** (`APP_ENV !== production`). |
| `.env.production.example` | Hardened production defaults; playground routes disabled. |
| `.env` | Active secrets on your machine or server. Never commit. |

## Quick start (local)

```bash
composer install
php artisan env:install local
```

This copies `.env.local.example` → `.env`, generates `APP_KEY`, and generates `JWT_SECRET` when empty.

Overwrite an existing `.env`:

```bash
php artisan env:install local --force
```

Then set at least:

| Variable | Notes |
|----------|--------|
| `APP_URL` | Base URL (e.g. `http://monolith.test` on Laragon) |
| `DB_*` | MySQL connection (`DB_CONNECTION=mysql` by default) |
| `MAIL_*` | Optional locally (`MAIL_MAILER=log` writes to log) |
| `PUSHER_*` / `APP_NOTIFICATION_TYPE` | Optional locally (bell uses polling by default) |
| `HCAPTCHA_*` | Disabled locally (`HCAPTCHA_ENABLED=false`) |

```bash
php artisan migrate
php artisan db:seed
npm install && npm run build
```

Or use the Composer shortcut (skips if `.env` already exists):

```bash
composer run setup
php artisan db:seed
```

## Staging and production

On the target server:

```bash
php artisan env:install staging     # or production
```

Fill in real values for:

- `APP_URL`
- `DB_*`
- `MAIL_*` (SMTP)
- `REDIS_*` (staging/production templates use `redis` for cache and queue)
- `PUSHER_*` and `CUSTOM_S3_*` when using realtime notifications and file uploads
- `HCAPTCHA_*` (enabled in staging/production templates)

After deploy:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force
npm run build
```

Production platforms (Laravel Forge, Vapor, Kubernetes, CI/CD) can inject env vars instead of a `.env` file. The variable names stay the same.

## Profile differences

| Variable | local | staging | production |
|----------|-------|---------|------------|
| `APP_ENV` | `local` | `staging` | `production` |
| `APP_DEBUG` | `true` | `false` | `false` |
| `LOG_LEVEL` | `debug` | `info` | `warning` |
| `SESSION_SECURE_COOKIE` | `false` | `true` | `true` |
| `SESSION_ENCRYPT` | `false` | `true` | `true` |
| `HCAPTCHA_ENABLED` | `false` | `true` | `true` |
| `MAIL_MAILER` | `log` | `smtp` | `smtp` |
| `CACHE_STORE` / `QUEUE_CONNECTION` | `database` | `redis` | `redis` |
| `APP_NOTIFICATION_TYPE` | `polling` | `realtime` | `realtime` |
| Playground routes | enabled | enabled | **disabled** |

## Production checklist

- `APP_ENV=production` and `APP_DEBUG=false`
- `SESSION_SECURE_COOKIE=true` and `SESSION_ENCRYPT=true`
- `JWT_SECRET` generated (`env:install` does this on first run)
- `HCAPTCHA_ENABLED=true` with valid keys
- Verify playground is off: `APP_ENV=production php artisan route:list --path=api/playground` (no routes)
- See also [README Security checklist](../README.md#security)

## Keeping templates in sync

When you add or remove an environment variable:

1. Update `.env.example` (master catalog + block comment).
2. Add the same key to `.env.local.example`, `.env.staging.example`, and `.env.production.example` with profile-appropriate defaults.
3. Run tests — `EnvProfileSyncTest` fails if keys drift between templates.

## Testing environment

PHPUnit sets `APP_ENV=testing` and overrides drivers in `phpunit.xml`. No `.env.testing.example` is needed.

## Related docs

- [JWT authentication](jwt-authentication.md#configuration)
- [Activity notifications (polling vs realtime)](notifications.md#realtime-vs-polling)
- [Playground (non-production only)](playground.md)
- [Octane readiness (Redis for cache/queue)](octane-readiness.md)
