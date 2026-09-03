# Artisan commands (project-owned index)

Index of the **project-specific** command surface (beyond Laravel/package defaults). Domain detail stays in the owning domain doc — this file is the map. Code: `app/Console/Commands/` + `app/Domain/*/Console/Commands/`.

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
| `domain:list` | List registered domain modules (kind core\|product + removal status) | [architecture.md](architecture.md) |
| `domain:make` | Scaffold a new product domain module under `app/Domain/*` (locked by default) | [architecture.md](architecture.md) |
| `domain:remove` | Remove an unlocked product domain (refuses core and locked product domains) | [architecture.md](architecture.md) |

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
| `encryption:flush-decrypted-cache` | Flush decrypted-value cache entries used by envelope encryption | [domains/payment-gateway/encryption.md](../domains/payment-gateway/encryption.md) |
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
| `encryption:migrate-p0` | Migrate P0 sensitive columns from plaintext/legacy Crypt values to OCI envelopes | [domains/payment-gateway/encryption.md](../domains/payment-gateway/encryption.md) |
| `oci:kms-verify` | Verify Oracle Cloud KMS connectivity via `generateDataEncryptionKey` | [domains/payment-gateway/encryption.md](../domains/payment-gateway/encryption.md) |

## Payments (`payments:*`)

| Command | Purpose | Details |
|---------|---------|---------|
| `payments:sync-routing-policy` | Materialize `Routing_Price.yaml` into `payment_channels` + `payment_channel_routes` | [domains/payment-gateway/Routing_Rules.md](../domains/payment-gateway/Routing_Rules.md) |
| `payments:webhook-urls` | Print public webhook URLs to register with Midtrans/iPaymu | [domains/payment-gateway/README.md](../domains/payment-gateway/README.md) |
| `payments:midtrans-reconcile-pending` | Poll Midtrans Get Status for pending/challenge invoices (cron backup when webhooks fail) | [domains/payment-gateway/README.md](../domains/payment-gateway/README.md) |
| `payments:reconcile-withdrawable-funds` | Poll Midtrans/iPaymu for paid invoices not yet withdrawable | [domains/payment-gateway/README.md](../domains/payment-gateway/README.md) |
| `payments:audit-invoice-funds` | Summarize invoices by withdrawable/disbursement buckets | [domains/payment-gateway/README.md](../domains/payment-gateway/README.md) |
| `payments:disbursement-begin` / `-complete` / `-fail` / `-reopen` | Disbursement lifecycle: eligible → processing → completed/failed; reopen after a failed payout | [domains/payment-gateway/README.md](../domains/payment-gateway/README.md) |

## Services (entitlements)

| Command | Purpose | Details |
|---------|---------|---------|
| `service:process-entitlements` | Enter grace for ended subscriptions; expire grace and purge free-tier excess storage | [domains/service/README.md](../domains/service/README.md) |
| `service:process-addon-renewals` | Create pending full-stack addon renewal invoices for due anniversaries | [domains/service/README.md](../domains/service/README.md) |

## File Hosting

| Command | Purpose | Details |
|---------|---------|---------|
| `file-hosting:prune-stale-uploads` | Abort expired/S3-inactive pending multipart sessions; reclaim orphaned storage | [domains/file-hosting/README.md](../domains/file-hosting/README.md) |
| `file-hosting:blocklist-hash` | Add/remove a sha256 from the DMCA hash blocklist | [domains/file-hosting/README.md](../domains/file-hosting/README.md) |

## vTual (`vtual:*`)

| Command | Purpose | Details |
|---------|---------|---------|
| `vtual:poll-channel-feeds` | Poll YouTube Atom feeds; enqueue new video ids for API hydrate | [domains/vtual/README.md](../domains/vtual/README.md) |
| `vtual:poll-live-contents` | Poll API for live contents (CCV snapshot + ended detection) | [domains/vtual/README.md](../domains/vtual/README.md) |
| `vtual:flush-channel-pool` | Flush channel fetch pool (batch platform channel snapshots; registration phase first) | [domains/vtual/README.md](../domains/vtual/README.md) |
| `vtual:flush-content-pool` | Flush content fetch pool (batch `videos.list` hydrate) | [domains/vtual/README.md](../domains/vtual/README.md) |
| `vtual:flush-claim-verify-pool` | Flush creator claim verify pool (queued bio verification) | [domains/vtual/README.md](../domains/vtual/README.md) |
| `vtual:renew-websub-subscriptions` | Renew YouTube WebSub subscriptions nearing lease expiry | [domains/vtual/README.md](../domains/vtual/README.md) |
| `vtual:repair-archived-websub` | Re-subscribe archived channels missing active WebSub (legacy repair) | [domains/vtual/README.md](../domains/vtual/README.md) |
| `vtual:backfill-initial-content` | One-time YouTube content backfill for an existing active channel | [domains/vtual/README.md](../domains/vtual/README.md) |
| `vtual:fetch-channel-content` | Fetch channel content and upsert into `vtual_contents` (POC) | [domains/vtual/README.md](../domains/vtual/README.md) |

## Microstock

| Command | Purpose | Details |
|---------|---------|---------|
| `microstock:queue-work` | Run a queue worker for all Microstock queues, paid → free → `default` (`--listen` respawn-per-job on Windows, `--once` debug) | [domains/microstock/README.md](../domains/microstock/README.md) |

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

- [environment.md](environment.md) — env profiles, guarded env commands
- [database-safety.md](database-safety.md) — destructive command guards
- [cache-policy.md](cache-policy.md) — cache lifecycle commands
