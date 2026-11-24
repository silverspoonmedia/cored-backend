# Runtime settings — reference

Detail behind [settings.md](settings.md): the fallback chain, the permission contract, and the full registry inventory per tier and group.

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

Root and Admin receive both via `RoleSeeder`. After adding a core permission, re-run `RoleSeeder` **and** any host/product role seeder that `givePermissionTo`s its own perms back onto Admin after the core `syncPermissions`, then `php artisan permission:cache-reset`. Do **not** run full `DatabaseSeeder` on an existing database — it tries to recreate `admin@example.com`. Log out/in so `auth/me` picks up the new list. The SPA manage form is gated on `manage settings` in that payload.

## Web surface (hybrid)

| Surface | Route | Group key |
|---------|-------|-----------|
| Platform hub | `/apps/settings/platform` | `platform` |
| Notification audiences | `/apps/notification-audiences` | (separate table) |

Menu: **Administration → Settings** — single hub for all groups (`settings.platform`, plus notification audiences and currencies). Root/Admin via `view settings` / `manage settings`. A host that installs a product domain gets one more entry per contributing group; the boilerplate ships `platform` only.

### Localization

Field labels resolve dynamically from `SettingRegistry` keys. Backend copy lives in `lang/{en,id,ja,ko,zh_CN,ar,ru}/web/settings.php` under `fields` (dot-containing keys such as `platform.auth.default_role` are literal PHP/JSON keys, not nested paths). Optional helper copy lives under `hints` with the same key. The SPA `SettingsFormView` loads `web.settings.fields` / `hints` via vue-i18n `tm()`, looks up each registry key as that literal dotted key (nested walk if needed), then `rt()` so runtime-compiled message ASTs become text — never `String(object)`. `t('web.settings.fields.' + key)` is the wrong lookup: vue-i18n splits on `.` and misses the literal JSON key. Each registry key must exist in all seven locale files.

The manage form renders each field with the shared `FormInput` / `FormSelect` / `FormTextarea` / `FormCheckbox` (`switch` for bools) stack. The registry key and `web.settings.source` stay a muted identifier under the control (`FormField` / `FormCheckbox` hint) — not inside Bootstrap `form-check` (that class is a flex row for the toggle + label only).

`PUT /api/v1/admin/settings/{group}` field names are **underscore aliases** of the registry key (`platform.jwt.ttl` → `platform_jwt_ttl`). Laravel `Request::input('a.b.c')` is nested-array lookup, so a JSON body that uses the dotted GET keys would validate as empty (`sometimes` rules) and 422 `settings.empty_payload`. `UpdateSettingsGroupRequest` also copies a literal dotted key onto the underscore alias when the alias is absent, so either shape is accepted. GET still returns dotted registry keys. The SPA `SettingsFormView` submits `fieldId(key)` (underscores), matching the HTML `name` attributes.

When adding a new `SettingRegistry` entry, update all seven locale files in the same task and keep `SettingRegistryTranslationTest` green (field labels **and** any registry `options` value labels). API flash messages for settings live in `lang/{locale}/settings.php`.

Enum-like string keys may declare an optional `options` list on the registry definition (values only). `SettingsService::presentGroup` exposes those values; `SettingsFormView` renders `FormSelect` and labels come from `web.settings.options.{key}.{value}` (nested lang keys, same dotted-key walk plus `rt()`). First use: `platform.notifications.delivery` → `polling` | `realtime`.

## Tier A — env / artisan only (not in Web UI)

| Group | Examples | Why |
|-------|----------|-----|
| App crypto | `APP_KEY`, `ENCRYPTION_BLIND_INDEX_PEPPER` | Rotation breaks decrypt |
| JWT signing | `JWT_SECRET`, `JWT_*_KEY` | Invalidates tokens |
| Database / Redis | `DB_*`, `REDIS_*` | Infra binding |
| OCI KMS | `OCI_*` | Cloud credentials |
| Payment secrets | `MIDTRANS_*`, `IPAYMU_*`, … | PCI-adjacent |
| S3 / proxy | `S3_PRIVATE_*`, upload-proxy token | Cloud credentials |
| AI connection keys | _(none — Tier C `ai_connections`, Administration → AI connections)_ | Provider secrets live in the encrypted table; per-consumer queue names stay Tier A |
| Mail / broadcast secrets | `MAIL_PASSWORD`, `PUSHER_APP_SECRET` | Standard secret hygiene |
| Public origins | `APP_URL`, `SPA_URL`, `CORS_EXTRA_ORIGINS` | Laravel API origin + Vue-Workers origin + extra comma-separated origins (preview URLs, localhost dev). `SPA_URL` is OAuth `return_to` allowlist, `SpaPublicUrl` mint origin, and CORS allowlist with `APP_URL` (+ `CORS_EXTRA_ORIGINS` for extras). Empty `SPA_URL` falls back to `APP_URL` + server endpoints and disables SPA OAuth return. |

Platform settings page shows **read-only secret status** via `php artisan config:verify-secrets` metadata — no edit fields.

## Tier B — Platform group (`platform.*`)

| Key | Config fallback | Notes |
|-----|-----------------|-------|
| `platform.jwt.ttl` | `jwt.ttl` | Default **240** (`config/jwt.php`). New cookies only. |
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
| `platform.localization.locale_cookie` | `localization.cookie_name` | |
| `platform.localization.locale_cookie_minutes` | `localization.cookie_minutes` | |
| `platform.mail.from_address` | `mail.from.address` | Applied on `MessageSending` (Octane-safe) |
| `platform.mail.from_name` | `mail.from.name` | |
| `platform.payment.channel_fee_bearer` | `payment.channel_fee_bearer` | `buyer` \| `merchant` |
| `platform.payment.reporting_timezone` | `payment.reporting_timezone` | |
| `platform.payment.prefer_channel_checkout` | `payment.checkout.prefer_channel_checkout` | |
| `platform.payment.default_buyer_phone` | `payment.default_buyer_phone` | iPaymu fallback |
| `platform.service.grace_days` | `service.grace_days` | Purge scheduler |
| `platform.service.settlement_currency` | `service.settlement_currency` | |
| `platform.service.purchase_max_quantity` | `service.purchase.max_quantity` | |
| `platform.inference.default_provider_slug` | `inference.default_provider_slug` | |
| `platform.inference.default_input_per_million_usd` | `inference.default_rates.input_per_million_usd` | |
| `platform.inference.default_output_per_million_usd` | `inference.default_rates.output_per_million_usd` | |
| `platform.inference.unknown_input_per_million_usd` | `inference.unknown_model_rates.input_per_million_usd` | |
| `platform.inference.unknown_output_per_million_usd` | `inference.unknown_model_rates.output_per_million_usd` | |
| `platform.frankfurter.cache_ttl` | `services.frankfurter.cache_ttl` | Freshness window (seconds) for FX L1/DB — not Laravel Cache TTL; see [Currency](../domains/currency/README.md) + [cache-policy](cache-policy.md) |
| `platform.frankfurter.timeout` | `services.frankfurter.timeout` | |

**Not in hub:** `APP_DEBUG`, `APP_ENV`, `LOG_LEVEL`, cache/queue/session drivers — profile switch via `env:switch`.

## Tier C — Domain groups

A domain that owns settings registers its own group through a `SettingContributor`
(`SettingRegistry` entries plus a `config/{domain}.php` fallback). The boilerplate
ships **no** product group: `platform` is the only group in the hub. When you add a
domain, its group appears in the same hub (`settings.{domain}`), gated by the same
`view settings` / `manage settings` permissions, and its field labels need entries in
all seven locale files under `web/settings.fields` / `.hints`.

See [reference-package-seams.md](../integration/reference-package-seams.md#seams-a-bundled-package-needs)
for the contributor contract.

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
| Archivd blocklist | `archivd.blocklist.*` |
| AI connections | `ai.connections.*` (Tier C — see [domains/ai](../domains/ai/README.md)) |

Payment routing fees: YAML → `payments:sync-routing-policy` (partial UI toggle on channels).

## Seeding

`SettingsSeeder` seeds all registry keys from current `config()` **PHP literal** defaults (idempotent `firstOrCreate`). Run after migrate:

```bash
php artisan db:seed --class=SettingsSeeder
```

**Local profile only** (`APP_ENV=local`): `DatabaseSeeder` also runs `LocalSettingsSeeder`, which `upsertMany`s dev overrides from [`config/settings-local.php`](../../config/settings-local.php) (e.g. generous daily free, realtime notifications, local Open WebUI model aliases for the Microstock stages). Re-seeding local refreshes those values; staging/production never run this seeder. Full override inventory: [reference-configuration-policy.md — Local profile overrides](reference-configuration-policy.md#local-profile-overrides).

```bash
php artisan db:seed --class=LocalSettingsSeeder   # local only; upserts dev pack
```

**Local profile only** (`APP_ENV=local`): `DatabaseSeeder` also ends with `AiConnectionSeeder`, which upserts `ai_connections` rows (Tier C) from the gitignored `database/seeders/data/ai-connections.local.php`, probes each new row, binds the consumer slug settings (`microstock.ai.vision.connection`, `microstock.ai.thinking.connection`) and promotes the flagged entry to default. It runs last because it needs the seeded users for `created_by` and `LocalSettingsSeeder`'s model aliases for `test_model`. `php artisan db:seed` on a fresh local checkout is therefore enough once the data file is filled in — see [domains/ai/README.md — Local seeding](../domains/ai/reference.md#local-seeding--wiring).

Cache is rebuilt automatically after seeding. See [configuration-policy.md](configuration-policy.md) for tier rules and cache contract.

## Related

- [settings.md](settings.md) — brief
- [configuration-policy.md](configuration-policy.md) — env vs DB tiers, cache contract, agent checklist
- [reference-configuration-policy.md](reference-configuration-policy.md) — Tier C pattern, local overrides
- [environment.md](environment.md) — `.env` profiles and secret rotation
- [octane.md](octane.md) — no runtime config mutation
- [domains/notification/README.md](../domains/notification/README.md) — audience settings
