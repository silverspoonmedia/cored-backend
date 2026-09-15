# Configuration & secrets policy

Canonical rules for where values live and how runtime settings are read. Agent protocol: [AGENTS.md](../../AGENTS.md); [core-rules.md](../agents/core-rules.md) rule 6.

## Three tiers

| Tier | Storage | Examples | Runtime read |
|------|---------|----------|--------------|
| **A — env only** | `.env` + `config/*.php` `env()` bindings | `APP_KEY`, `JWT_SECRET`, `DB_*`, `REDIS_*`, `OCI_*`, mail/broadcast secrets, S3/upload-proxy credentials, payment gateway keys, AI provider API keys | `config()` at bootstrap only |
| **B — DB plaintext** | `system_settings` via Settings UI | JWT TTLs, upload limits, notification delivery, claim TTLs, timezone knobs | `setting()` / `setting_config()` → **cached snapshot** |
| **C — DB envelope** | Dedicated credential tables | YouTube/Twitch keys (`vtual_platform_credentials`), marketplace FTP (`microstock_marketplace_credentials`) | Model cast → `EnvelopeEncryptionService` |

**Never** put Tier B values in `.env`. **Never** put Tier C secrets in `.env` or `system_settings`.

## Cache contract (Tier B)

This is the Tier B specialization of the project-wide [cache-policy.md](cache-policy.md) (forever + miss rebuild + invalidate; any driver; no tags).

All runtime reads of `SettingRegistry` keys go through `App\Support\Settings\SettingsReader`:

```
setting_config('jwt.ttl')
    → SettingsReader
    → SystemSettingRepository::snapshot()
    → Cache::get('system_settings.snapshot') or rebuildCache() on miss
    → return snapshot
```

Rules:

1. **Forever TTL** on the snapshot cache key unless invalidated.
2. **Rebuild on write** — `upsertMany()` and `SettingsSeeder` call `rebuildCache()` (eager warm), not flush-only.
3. **Rebuild on miss** — `snapshot()` calls `rebuildCache()` when the cache key is absent.
4. **No per-key DB queries** at runtime in domain code.
5. **Boot warm** — `SettingsServiceProvider` loads snapshot on worker boot (when `system_settings` exists).
6. **Recovery** — `php artisan settings:rebuild-cache`.

Fallback when a registry key has no DB row yet (pre-seed): `config()` **PHP literal** default from the owning config file — not `env()`.

Parent array reads via `setting_config('microstock.ai.vision')` (and similar) overlay any registered descendant leaf overrides onto the config array so mixed Tier A secrets + Tier B knobs stay coherent without `config()->set()`.

## Tier A inventory

See [settings.md — Tier A](settings.md#tier-a--env--artisan-only-not-in-web-ui). Infra profile keys (`APP_ENV`, `LOG_LEVEL`, cache/queue drivers) stay env via `env:switch` — not in `SettingRegistry`.

## Tier C pattern

Envelope encryption for product-domain secrets follows `EnvelopeEncryptionService` + `EnvelopeEncryptedArray` cast. Reference: [payment-gateway encryption](../domains/payment-gateway/encryption.md), vTual `PlatformCredential`, Microstock `MarketplaceCredential`.

Payment gateway **signing secrets** remain Tier A env today (CRUD manages metadata only).

## Local profile overrides

When `APP_ENV=local`, [`LocalSettingsSeeder`](../../database/seeders/LocalSettingsSeeder.php) runs immediately after [`SettingsSeeder`](../../database/seeders/SettingsSeeder.php) and applies [`config/settings-local.php`](../../config/settings-local.php) via `upsertMany` (overwrites local rows on each seed).

```
SettingsSeeder (all envs)     → firstOrCreate from config/*.php literals (prod-safe)
LocalSettingsSeeder (local)   → upsertMany from settings-local.overrides (dev ergonomics)
```

Staging and production **never** run `LocalSettingsSeeder`. Do not put local-only values in config literals, `.env`, or orphan keys.

### Current local override inventory

Mirrors [`config/settings-local.php`](../../config/settings-local.php) — update both when adding dev ergonomics:

| Registry key | Local override | Purpose |
|--------------|----------------|---------|
| `microstock.daily_free.enabled` | `true` | Dev daily free on |
| `microstock.daily_free.limit_per_user` | `1_000_000` | Generous local limit |
| `platform.notifications.delivery` | `realtime` | Bell without polling |
| `archivd.download_vault.enabled` | `true` | Vault download path in hybrid proxy |
| `microstock.ai.http_retries` | `1` | Faster AI failure in dev |
| `vtual.claim.accept_anyway_enabled` | `true` | Claim dev ergonomics |
| `vtual.channel_claim.accept_anyway_enabled` | `true` | Channel claim dev ergonomics |
| `microstock.ai.vision.model` | `internal.sspn_pro_vision` | Local Open WebUI vision alias |
| `microstock.ai.vision.free_model` | `internal.sspn_free_vision` | Local free-tier vision alias |
| `microstock.ai.vision.timeout` | `300` | Shorter local vision HTTP timeout |
| `microstock.ai.thinking.model` | `internal.sspn_pro_thinking` | Local Open WebUI thinking alias |
| `microstock.ai.thinking.free_model` | `internal.sspn_free_thinking` | Local free-tier thinking alias |
| `microstock.ai.thinking.timeout` | `300` | Shorter local thinking HTTP timeout |

## Agent checklist — new operational knob

1. Classify tier (A / B / C).
2. **Tier B:** add `SettingRegistry` entry → `SettingsSeeder` picks it up → translations in `lang/{en,id,ja,ko,zh_CN,ar,ru}/web/settings.php` (`fields` + optional `hints`) → `SettingRegistryTranslationTest` green → **no** new env key. For enum-like strings, declare registry `options`, ship `web/settings.options.{key}.{value}` labels in all seven locales, and rely on `presentGroup` + SPA `SettingsFormView` `FormSelect` — see [settings.md](settings.md). For **local-only** dev defaults, add to `config/settings-local.php` (not config literals).
3. **Tier C:** dedicated migration + model with envelope cast; admin UI; never registry.
4. Domain code reads via `setting_config()` only — never `config()` for registry paths.
5. Update [settings.md](settings.md) and this doc if tier boundaries change.

## Related

- [settings.md](settings.md) — registry inventory and Web UI
- [environment.md](environment.md) — `.env` profiles and curation
- [octane.md](octane.md) — no runtime `config()->set()` or `env()`
