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

All four committed templates share the **same section layout** (Application → locale → auth → encryption → logging → database → infrastructure → mail → File Hosting → hCaptcha → notifications & realtime → payments → Microstock). Subsections use `# · …` markers. See [`.env.example`](../../.env.example) for the canonical order and comments.

**Assignment key parity (mandatory):** master, all three profile templates, and `EnvironmentVariableCatalog::types()` must expose the **same set of live `KEY=` assignments**. A commented `# KEY=…` line does **not** count — `env:curate` only sees real assignments in the active profile template, so a key that exists only as a comment (or only in master) becomes an orphan in `.env`. Lock: `EnvProfileSyncTest` + `EnvironmentTemplateQuotingTest`. Values may still differ per profile (`APP_DEBUG`, drivers, proxy URLs, etc.).

**Storage env:** cloud uploads use `FILE_HOSTING_S3_*` (disk `s3FileHostingCe`). The Laravel default `s3` disk remains in `config/filesystems.php` but `AWS_*` keys are **not** listed in env templates — set them manually only if `FILESYSTEM_DISK=s3`.

## Quick start

```bash
composer install
php artisan env:install local
# set APP_URL, DB_*, secrets…
php artisan migrate
php artisan db:seed
```

`env:install` copies the template, seeds the snapshot, sets profile, and generates `APP_KEY`, `JWT_SECRET`, and `ENCRYPTION_BLIND_INDEX_PEPPER` (filled, double-quoted via Monolith writers — not bare `jwt:secret`).

## Value types

Defined in `EnvironmentVariableCatalog`:

| Type | Format |
|------|--------|
| string | Always double-quoted (`APP_ENV="local"`) |
| bool | `true` / `false` unquoted |
| int | Digits unquoted |
| null | `null` unquoted |

Empty secrets or optional string overrides use `KEY=""`. When adding keys: update `.env.example`, all three profile templates, and `EnvironmentVariableCatalog::types()` with a **live assignment** in every file (not a commented example). Microstock's optional free-tier model overrides inherit their paid/default model when empty.

`EnvironmentVariableCatalog::formatRawValue()` is the single formatter: templates must already match these rules (`EnvironmentTemplateQuotingTest`), and `env:curate` re-applies them to live `.env` values (so unquoted strings from tools like bare `jwt:secret` get healed).

## Application keys

| Variable | Role |
|----------|------|
| `APP_KEY` | Active Laravel encryption key |
| `APP_PREVIOUS_KEYS` | Retired keys after rotation (decrypt still works) |
| `SPA_URL` | Public origin of Monolith-Vue-Workers (no trailing slash). OAuth `return_to` allowlist (with `APP_URL`). Non-empty mints password-reset, email-verify, File Hosting `share_url`, and iPaymu hosted return/cancel onto that origin (`App\Support\SpaPublicUrl`) and is a CORS origin (`config/cors.php`). Empty falls back to `APP_URL` + the surviving server endpoints and disables SPA OAuth return. |

`key:generate --force` appends current key to previous, writes new key. Implementation: `EnvironmentKeyWriter` (file-based, not broken by quoted values).

`env:install` / profile secret seeding writes `JWT_SECRET` via Monolith’s quoted writer (`EnvironmentProfileManager::writeJwtSecret`). Do **not** rely on standalone `jwt:secret` for on-disk writes — the package writes unquoted `JWT_SECRET` / `JWT_ALGO` and is not mutation-guarded.

## Switching profiles (local)

| Command | Purpose |
|---------|---------|
| `env:install {profile}` | Fresh from template (backs up existing `.env` / snapshot first) |
| `env:switch {profile}` | Save current → load target snapshot; clears config cache |
| `env:switch` / `--list` | Show active / list snapshots |
| `env:curate` | Reformat `.env` using active profile template layout; preserves values; **normalizes quoting** to catalog types |
| `env:restore` | Restore from a pending transactional backup |
| `env:backup:create` | Manual pending backup before hand-editing `.env` |

**Always edit active `.env`.** Bootstrap uses `LoadMonolithEnvironmentVariables` so Laravel does **not** silently load snapshot `.env.local` when `APP_ENV` is preset (Laragon trap).

### Transactional backup

`env:install`, `env:switch`, `env:curate`, and `key:generate` back up `.env` (and snapshots when they would be overwritten) under `storage/framework/env-backups/pending/` before mutating files. On success the backup is deleted; on failure files are rolled back and the backup is kept for `php artisan env:restore`.

If a pending backup exists, further env mutations are blocked until you run `env:restore` (restore files) or `env:restore --discard` (drop backup without restoring).

`env:install --force` prompts for confirmation (unless `--no-interaction`) and always creates a backup when `.env` already exists.

### Curate layout

```bash
php artisan env:curate --dry-run      # preview keys added / orphans
php artisan env:curate                # apply layout from .env.{profile}.example
php artisan env:curate --sync-snapshot
```

Layout source: **active profile template** (`.env.profile` → `.env.{profile}.example`). Existing **semantic** values are kept; assignment formatting is **normalized** to catalog types (strings always double-quoted; bool/int/null unquoted). Missing template keys get template defaults (also normalized); keys only in `.env` are appended under `Extra keys (not in template)`.

AI agents: the exact phrase **`curate the env`** must use `env:curate` (dry-run first) — never hand-edit `.env` for layout. See [AGENTS.md](../../AGENTS.md).

### Known behaviors (QA-verified)

| Behavior | Detail |
|----------|--------|
| Pending backup after failure | `env:restore` rolls back files but **keeps** the pending folder until `env:restore --discard` |
| Blocked operations | Any pending backup blocks `env:install`, `env:switch`, `env:curate`, `key:generate` until discard |
| `force-past-backup` | Bypasses the block but can leave **stale** pending dirs; only the latest is restored by `env:restore` without an id |
| Snapshot staleness | Editing `.env` does not update `.env.{profile}` until you switch away (save-on-switch) or `env:curate --sync-snapshot` |
| Duplicate keys in `.env` | Curator uses **last assignment wins** when parsing; duplicate keys in the template emit **duplicate lines** |
| Orphan keys | Keys in `.env` but not in the **active profile template** are appended under `Extra keys (not in template)`. Catalog Tier A keys landing here usually mean **profile template drift** (violates assignment key parity above). Genuine orphans are true extras or retired Tier B leftovers after an env→settings migration (wave 1 JWT/FH/notifications; wave 2 mail-from / Frankfurter timeout / Microstock models+timeouts / Vtual token mint). Drop Extra keys after confirming Settings rows exist (`php artisan db:seed --class=SettingsSeeder` then LocalSettingsSeeder on local) |
| Typed quoting | Curate normalizes catalog keys to typed quoting; unquoted strings become `"…"` |
| First switch to a profile | If no snapshot exists, bootstraps from `.env.{profile}.example` (fresh secrets, not a copy of current `.env`) |
| `jwt:secret` standalone | Not wrapped by the mutation guard; writes unquoted values — prefer guarded install/seed or `env:backup:create` then `env:curate` |
| `env:backup:create` | Creates a pending backup **without** mutating files; blocks other env commands until `--discard` |

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
| `HCAPTCHA_SITEKEY` / `HCAPTCHA_SECRET` | optional / false | required when enabled in Settings |
| Cache/queue | database | redis |
| Tier B settings seed | `LocalSettingsSeeder` + [settings-local.php](../../config/settings-local.php) (after `SettingsSeeder`) | `SettingsSeeder` + config literals only |

On **local**, `php artisan db:seed` applies dev overrides (e.g. daily free enabled, realtime notifications) via `LocalSettingsSeeder` — no Tier B keys in `.env`. See [settings.md — Seeding](settings.md#seeding).

## JWT lifecycle (SPA identity)

SPA identity is the HttpOnly `__Host-spa_jwt` cookie (JWT) minted on the API origin, **not** the Laravel session cookie — the removed Blade identity bridge used `auth_token`. Session still needs secure/encrypt settings for the surviving server endpoints (CSRF/flash).

| Variable | Role |
|----------|------|
| `JWT_SECRET` | Signing secret (Tier A — env only) |

Operational JWT knobs (TTL, refresh skew, blacklist grace) live in **Settings → Platform** (`system_settings`). See [settings.md](settings.md) and [configuration-policy.md](configuration-policy.md).

See [identity](../domains/identity/README.md#configuration). The Blade-era silent-refresh cookie bridge is gone; the SPA refreshes the `__Host-spa_jwt` cookie through `/api/v1/auth/refresh` — [vue-workers-spa.md](../integration/vue-workers-spa.md).

## Production checklist

- `APP_ENV=production`, `APP_DEBUG=false`
- Secure session cookies; `JWT_SECRET` set
- Prefer non-zero **Settings → Platform** `platform.jwt.blacklist_grace_period` so concurrent SPA requests survive a token refresh
- `config:rebuild`; `oci:kms-verify` if OCI
- Liveness: `route:list --path=api/ping` shows `GET /api/ping`
- Network exposure: firewall / reverse proxy as needed (no in-app shared-secret API perimeter)

## Testing environment

PHPUnit: `APP_ENV=testing` (`phpunit.xml` `force="true"`), sqlite, CSRF bypass for web proxy tests. Tests that `putenv` another env (e.g. bootstrapping a second app) must restore process env in `tearDown`. Prefer `composer test`. See [database-safety.md](database-safety.md).

Payment: `checkout_mode` derived from active channels at runtime — see [payment-gateway](../domains/payment-gateway/README.md). After editing `Routing_Price.yaml`, run `php artisan payments:sync-routing-policy` (or use the admin **Sync routing policy** button). Channel fee bearer and reporting timezone are in **Settings → Platform**. Encryption env: [encryption](../domains/payment-gateway/encryption.md).

## Persistent error log

Retention and enable/disable are in **Settings → Platform** (`errorlog.*` registry keys). Independent of `LOG_CHANNEL` file logs. Domain guide: [error-log](../domains/error-log/README.md).

## Runtime settings (Web UI)

Operational knobs (JWT TTLs, upload limits, notification delivery, etc.) live in **`system_settings`** and are edited via **Administration → Settings**. They are **not** `.env` variables — see [configuration-policy.md](configuration-policy.md). Runtime reads use a forever-cached snapshot (`setting_config()`). Full inventory: [settings.md](settings.md).

Monolith no longer carries any `VITE_*` keys. Vite belongs to the SPA build, so `VITE_PUSHER_*` / `VITE_API_BASE_URL` / `VITE_HCAPTCHA_SITE_KEY` live in `monolith-vue-workers/.env` — see [vue-workers-spa.md](../integration/vue-workers-spa.md#env-vars). They must still be **literals** mirroring Monolith's `PUSHER_*` values, because Vite does not expand `${PUSHER_*}` at build time. If an old `.env` still has them, `env:curate` will surface them under `Extra keys (not in template)`; delete them.

After the env→settings migration, `php artisan env:curate` may leave **orphan** operational keys in a local `.env` (moved to the orphans section) when `.env` still contains Tier B vars removed from templates. Safe to delete them — live values are in `system_settings`. After template sync (`env:curate --sync-snapshot`), a clean local `.env` should show **0 orphans**; do not re-add Tier B keys. Upload proxy vars (`FILE_HOSTING_UPLOAD_PROXY_*`) are **Tier A** and belong as live assignments in the File Hosting env section of **all** templates (empty URL = browser-direct; local profile may ship gateway defaults).

## Related

- [configuration-policy.md](configuration-policy.md) — env vs DB tiers, cache contract
- [settings.md](settings.md)
- [octane.md](octane.md)
- [domains/error-log/README.md](../domains/error-log/README.md)
