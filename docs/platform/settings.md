# Runtime settings (Web Surface)

Operational configuration that changes application behavior without redeploying secrets or infra profiles. Secrets and infrastructure bindings stay in `.env` — see [environment.md](environment.md) and [configuration-policy.md](configuration-policy.md).

## Fallback chain

```
Cached system_settings snapshot (forever TTL, rebuild on write/miss)
    ↓ key missing from DB
config/*.php PHP literal default (bootstrap / pre-seed only)
    ↓ miss
caller default
```

Runtime reads use `App\Support\Settings\SettingsReader` — never `config()->set()` or `env()` outside config files ([octane.md](octane.md)). Recovery: `php artisan settings:rebuild-cache`.

After changing `.env` directly (Tier A only), run `php artisan config:rebuild`. Tier B DB overrides take effect immediately (cache rebuild on write).

## Permissions

| Permission | Use |
|------------|-----|
| `view settings` | Read settings groups |
| `manage settings` | Update operational settings |

Root and Admin receive both via `RoleSeeder`.

## Web surface (hybrid)

| Surface | Route | Group key |
|---------|-------|-----------|
| Platform hub | `/apps/settings/platform` | `platform` |
| File Hosting | `/apps/file-hosting/settings` | `file-hosting` |
| Microstock | `/apps/microstock/settings` | `microstock` |
| Vtual | `/apps/vtual/settings` | `vtual` |
| Notification audiences | `/apps/notification-audiences` | (separate table) |

Menu: **Administration → Settings** (platform, microstock, notification audiences, currencies); **File Hosting** and **vTual** product branches each include a **Settings** child (`settings.file-hosting`, `settings.vtual`) gated by the product parent plus `view settings` (Root, Admin).

### Localization

Field labels resolve dynamically from `SettingRegistry` keys. The settings form loads the full `fields` map via `__('web/settings.fields')` and looks up each registry key by array index (dot-containing keys such as `platform.auth.default_role` are stored as literal PHP keys, not nested paths). Each registry key must exist in `lang/{en,id,ja}/web/settings.php` under `fields`. Optional helper copy lives under `hints` with the same key.

When adding a new `SettingRegistry` entry, update all three locale files in the same task and keep `SettingRegistryTranslationTest` green. API flash messages for settings live in `lang/{locale}/settings.php`.

Enum-like string keys may declare an optional `options` list on the registry definition (values only). `SettingsService::presentGroup` exposes those values; the Blade form renders a native `<select>` (no Select2) and labels come from `web/settings.options.{key}.{value}` (nested lang keys). First use: `platform.notifications.delivery` → `polling` \| `realtime`.
## Tier A — env / artisan only (not in Web UI)

| Group | Examples | Why |
|-------|----------|-----|
| App crypto | `APP_KEY`, `ENCRYPTION_BLIND_INDEX_PEPPER` | Rotation breaks decrypt |
| JWT signing | `JWT_SECRET`, `JWT_*_KEY` | Invalidates tokens |
| Database / Redis | `DB_*`, `REDIS_*` | Infra binding |
| OCI KMS | `OCI_*` | Cloud credentials |
| Payment secrets | `MIDTRANS_*`, `IPAYMU_*`, … | PCI-adjacent |
| S3 / proxy | `FILE_HOSTING_S3_*`, `FILE_HOSTING_UPLOAD_PROXY_TOKEN` | Cloud credentials |
| AI API keys | `MICROSTOCK_AI_*_API_KEY` | Provider secrets |
| Mail / broadcast secrets | `MAIL_PASSWORD`, `PUSHER_APP_SECRET` | Standard secret hygiene |

Platform settings page shows **read-only secret status** via `php artisan config:verify-secrets` metadata — no edit fields.

## Tier B — Platform group (`platform.*`)

| Key | Config fallback | Notes |
|-----|-----------------|-------|
| `platform.jwt.ttl` | `jwt.ttl` | New cookies only |
| `platform.jwt.refresh_ttl` | `jwt.refresh_ttl` | |
| `platform.jwt.remember_ttl` | `jwt.remember_ttl` | |
| `platform.jwt.refresh_skew` | `jwt.refresh_skew` | |
| `platform.jwt.blacklist_grace_period` | `jwt.blacklist_grace_period` | |
| `platform.auth.default_role` | `auth.default_role` | New registrations |
| `platform.auth.verification_expire` | `auth.verification.expire` | |
| `platform.two_factor.window` | `auth.two_factor.window` | |
| `platform.two_factor.issuer` | `auth.two_factor.issuer` | |
| `platform.hcaptcha.enabled` | `hcaptcha.enabled` | Sitekey stays env |
| `platform.notifications.delivery` | `notifications.delivery` | `polling` \| `realtime` — select in UI via registry `options` |
| `platform.notifications.polling_interval` | `notifications.polling_interval` | |
| `platform.errorlog.enabled` | `errorlog.enabled` | |
| `platform.errorlog.clean_after_days` | `errorlog.clean_after_days` | |
| `platform.errorlog.max_message_length` | `errorlog.max_message_length` | |
| `platform.errorlog.max_trace_length` | `errorlog.max_trace_length` | |
| `platform.errorlog.max_context_json_length` | `errorlog.max_context_json_length` | |
| `platform.activitylog.enabled` | `activitylog.enabled` | |
| `platform.activitylog.buffer_enabled` | `activitylog.buffer.enabled` | |
| `platform.localization.locale_cookie` | `localization.locale_cookie` | |
| `platform.localization.locale_cookie_minutes` | `localization.locale_cookie_minutes` | |
| `platform.payment.channel_fee_bearer` | `payment.channel_fee_bearer` | `buyer` \| `merchant` |
| `platform.payment.reporting_timezone` | `payment.reporting_timezone` | |
| `platform.payment.prefer_channel_checkout` | `payment.checkout.prefer_channel_checkout` | |
| `platform.service.grace_days` | `service.grace_days` | Purge scheduler |
| `platform.service.settlement_currency` | `service.settlement_currency` | |
| `platform.service.purchase_max_quantity` | `service.purchase.max_quantity` | |
| `platform.inference.default_provider_slug` | `inference.default_provider_slug` | |
| `platform.inference.default_input_per_million_usd` | `inference.default_rates.input_per_million_usd` | |
| `platform.inference.default_output_per_million_usd` | `inference.default_rates.output_per_million_usd` | |
| `platform.inference.unknown_input_per_million_usd` | `inference.unknown_model_rates.input_per_million_usd` | |
| `platform.inference.unknown_output_per_million_usd` | `inference.unknown_model_rates.output_per_million_usd` | |
| `platform.frankfurter.cache_ttl` | `services.frankfurter.cache_ttl` | |

**Not in hub:** `APP_DEBUG`, `APP_ENV`, `LOG_LEVEL`, cache/queue/session drivers — profile switch via `env:switch`.

## Tier C — Domain groups

### File Hosting (`file-hosting.*`)

Upload limits, multipart tuning, TTL/cleanup, quota defaults, region policy, download TTLs, MIME allowlist, vault download flag. See `config/file-hosting.php` and [file-hosting README](../domains/file-hosting/README.md).

SKU codes and free-tier bytes stay in `FileHostingCatalog` (deploy + seeder).

### Microstock (`microstock.*`)

Daily free tier, AI billing markup, pipeline batch/watchdog, image prep, retries, vision delivery, upstream concurrency. API keys and queue names stay env.

### Vtual (`vtual.*`)

Claim/channel-claim TTLs, credential failover, HTTP timeouts, channel pool thresholds. Platform credentials stay in `vtual_platform_credentials` table.

## Already UI-manageable (DB, not system_settings)

| Area | Admin route |
|------|-------------|
| Service catalog | `services.*` |
| Inference catalog | `inference.*` |
| Navigation menus | `menus.*` |
| Domain access | `domain-access.*` |
| Product activations | `product-activations.*` |
| Payment channels | `payment-channels.*` |
| Payment gateways | `payment-gateways.*` (CRUD) |
| Currencies | `currencies.*` |
| Notification audiences | `notification-audiences.*` |
| Vtual credentials | `vtual.admin.credentials.*` |
| FH blocklist | `file-hosting.blocklist.*` |

Payment routing fees: YAML → `payments:sync-routing-policy` (partial UI toggle on channels).

## Seeding

`SettingsSeeder` seeds all registry keys from current `config()` **PHP literal** defaults (idempotent `firstOrCreate`). Run after migrate:

```bash
php artisan db:seed --class=SettingsSeeder
```

**Local profile only** (`APP_ENV=local`): `DatabaseSeeder` also runs `LocalSettingsSeeder`, which `upsertMany`s dev overrides from [`config/settings-local.php`](../../config/settings-local.php) (e.g. generous daily free, realtime notifications). Re-seeding local refreshes those values; staging/production never run this seeder. Full override inventory: [configuration-policy.md — Local profile overrides](configuration-policy.md#local-profile-overrides).

```bash
php artisan db:seed --class=LocalSettingsSeeder   # local only; upserts dev pack
```

Cache is rebuilt automatically after seeding. See [configuration-policy.md](configuration-policy.md) for tier rules and cache contract.

## Related

- [configuration-policy.md](configuration-policy.md) — env vs DB tiers, cache contract, agent checklist
- [environment.md](environment.md) — `.env` profiles and secret rotation
- [octane.md](octane.md) — no runtime config mutation
- [domains/notification/README.md](../domains/notification/README.md) — audience settings
