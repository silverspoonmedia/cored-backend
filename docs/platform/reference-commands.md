# Artisan commands — reference

Full index of the **project-specific** command surface (beyond Laravel/package defaults). Domain detail stays in the owning domain doc — this file is the map. Code: `app/Console/Commands/` + `app/Domain/*/Console/Commands/`.

## Environment & config

| Command | Purpose | Details |
|---------|---------|---------|
| `env:install` | Create `.env` from a profile template (local/staging/production) and generate secrets | [environment.md](environment.md) |
| `env:switch` | Switch active `.env` between profiles without losing per-profile config | [environment.md](environment.md) |
| `env:curate` | Reformat `.env` to the active profile template layout, preserving values + typed quoting (`--dry-run`, `--sync-snapshot`) | [environment.md](environment.md); trigger: `curate the env` |
| `env:backup:create` | Create a pending transactional backup of `.env` without mutating files | [environment.md](environment.md) |
| `env:restore` | Restore or manage pending transactional env backups | [environment.md](environment.md) |
| `env:encrypt` / `env:decrypt` | Laravel-native env encryption | — |
| `key:generate` | Set `APP_KEY` (overridden wrapper — transactional backup first) | [environment.md](environment.md) |
| `config:verify-secrets` | Verify critical secrets are present and not template placeholders | [environment.md](environment.md) |
| `config:rebuild` | Clear + verify secrets from `.env` + cache config + verify the baked cache | [environment.md](environment.md) |
| `config:cache` / `config:clear` | Standard config cache lifecycle | — |

## Domains (scaffolding)

| Command | Purpose | Details |
|---------|---------|---------|
| `domain:list` | List registered domain modules (kind core\|product, parent subproduct + removal status) | [architecture.md](architecture.md) |
| `domain:make` | Scaffold a new product domain module under `app/Domain/*` (locked by default; `--parent={Product}` for a subproduct) + a starter `uninstall.json` | [architecture.md](architecture.md) |
| `domain:remove` | Remove an unlocked product domain by executing its `uninstall.json` — `--dry-run`, `--draft-manifest`, `--force`, `--purge-data` (guarded); refused while a registered subproduct points at it | [architecture.md](architecture.md), [reference-architecture.md](reference-architecture.md#domain-uninstall-manifest) |

## Database

| Command | Purpose | Details |
|---------|---------|---------|
| `db:migration-diagnose` | Inspect DB context, migration history, indexes, FKs for a migration failure | [database-safety.md](database-safety.md) |
| `db:wipe`, `migrate:fresh`, `migrate:refresh` | Destructive — guarded on non-local envs | [database-safety.md](database-safety.md) |

## Cache

| Command | Purpose | Details |
|---------|---------|---------|
| `settings:rebuild-cache` | Rebuild the forever cache snapshot for `system_settings` from the DB | [settings.md](settings.md) |
| `menu:flush-cache` | Flush the two-tier navigation cache (snapshot + presented trees) | [domains/navigation/README.md](../domains/navigation/README.md) |
| `encryption:flush-decrypted-cache` | Forget decrypted plaintext for one context (kernel-shipped; no bulk flush) | [envelope-encryption.md](envelope-encryption.md) |
| `permission:cache-reset` | Spatie permission catalog cache | [domains/identity/README.md](../domains/identity/README.md) |

Domain data caches are forever + rebuild-on-miss + invalidate-on-change — [cache-policy.md](cache-policy.md).

## Localization

| Command | Purpose | Details |
|---------|---------|---------|
| `locale:add` | Add a new application locale: laravel-lang files, `supported_locales` config, translation scaffolding | [localization.md](localization.md) |
| `lang:add` / `lang:update` / `lang:publish` / `lang:rm` / `lang:reset` | Managed translation packs (`laravel-lang/common`) | [localization.md](localization.md) |
| `lang:models-helper` | Model-translation helper generation | [localization.md](localization.md) |

## Encryption / KMS

| Command | Purpose | Details |
|---------|---------|---------|
| `encryption:migrate-p0` | Migrate P0 sensitive columns from plaintext/legacy Crypt values to OCI envelopes | [envelope-encryption.md](envelope-encryption.md) |
| `encryption:migrate-activity-log` | Encrypt PII in `activity_log.properties` (`ip_address`, `user_agent`, `email`) to envelopes; idempotent, `--dry-run` | [domains/activity-log/README.md](../domains/activity-log/README.md) |
| `oci:kms-verify` | Verify Oracle Cloud KMS connectivity via `generateDataEncryptionKey` | [envelope-encryption.md](envelope-encryption.md) |

## Payments (`payments:*` — `packages/billing`)

| Command | Purpose | Details |
|---------|---------|---------|
| `payments:sync-routing-policy` | Materialize the routing policy YAML (`packages/billing/resources/routing/price.yaml`) into `payment_channels` + `payment_channel_routes` | [domains/payment-gateway/reference.md](../domains/payment-gateway/reference.md) |
| `payments:webhook-urls` | Print public webhook URLs to register with Midtrans/iPaymu | [domains/payment-gateway/README.md](../domains/payment-gateway/README.md) |
| `payments:midtrans-reconcile-pending` | Poll Midtrans Get Status for pending/challenge invoices (cron backup when webhooks fail) | [domains/payment-gateway/README.md](../domains/payment-gateway/README.md) |
| `payments:reconcile-withdrawable-funds` | Poll Midtrans/iPaymu for paid invoices not yet withdrawable | [domains/payment-gateway/README.md](../domains/payment-gateway/README.md) |
| `payments:audit-invoice-funds` | Summarize invoices by withdrawable/disbursement buckets | [domains/payment-gateway/README.md](../domains/payment-gateway/README.md) |
| `payments:disbursement-begin` / `-complete` / `-fail` / `-reopen` | Disbursement lifecycle: eligible → processing → completed/failed; reopen after a failed payout | [domains/payment-gateway/README.md](../domains/payment-gateway/README.md) |

## Services (entitlements — `packages/billing`)

| Command | Purpose | Details |
|---------|---------|---------|
| `service:process-entitlements` | Enter grace for ended subscriptions; expire grace and purge free-tier excess storage | [domains/service/README.md](../domains/service/README.md) |
| `service:process-addon-renewals` | Create pending full-stack addon renewal invoices for due anniversaries | [domains/service/README.md](../domains/service/README.md) |

## AI

| Command | Purpose | Details |
|---------|---------|---------|
| `ai:import-legacy-env` | One-time import of a product's legacy AI env pairs into `ai_connections` rows + consumer slug bindings (`--dry-run`, `--skip-test`, `--actor=`). Walks `ai.consumers`, so it names no product. Read-only alias helpers (not Artisan): `php scripts/ai/list_models.php`, `php scripts/ai/probe_models.php` | [domains/ai/README.md](../domains/ai/README.md) |

## Maintenance & cleanup

| Command | Purpose | Details |
|---------|---------|---------|
| `errorlog:clean` | Delete persistent error log groups older than the retention window | [domains/error-log/README.md](../domains/error-log/README.md) |
| `activitylog:clean` | Prune old Spatie activity log rows | [domains/activity-log/README.md](../domains/activity-log/README.md) |
| `jwt:secret` | Generate the JWT HS256 signing secret | [domains/identity/README.md](../domains/identity/README.md) |
| `auth:clear-resets` | Expire password-reset tokens | — |

## Scheduling

Recurring runs live in `routes/console.php`; check `php artisan schedule:list` for the live schedule. Production needs `schedule:work` (or cron) plus `queue:work` per queue — see each domain doc's Operations section.

## Related

- [commands.md](commands.md) — brief
- [environment.md](environment.md) — env profiles, guarded env commands
- [database-safety.md](database-safety.md) — destructive command guards
- [cache-policy.md](cache-policy.md) — cache lifecycle commands