# Configuration & secrets policy

Canonical rules for where values live and how runtime settings are read. Agent protocol: [AGENTS.md](../../AGENTS.md); [core-rules.md](../agents/core-rules.md) rule 6.

## Three tiers

| Tier | Storage | Examples | Runtime read |
|------|---------|----------|--------------|
| **A — env only** | `.env` + `config/*.php` `env()` bindings | `APP_KEY`, `JWT_SECRET`, `DB_*`, `REDIS_*`, `OCI_*`, mail/broadcast secrets, S3/upload-proxy credentials, payment gateway keys | `config()` at bootstrap only |
| **B — DB plaintext** | `system_settings` via Settings UI | JWT TTLs, upload limits, notification delivery, claim TTLs, timezone knobs, AI consumer knobs (model / timeouts / max_tokens / connection slug) | `setting()` / `setting_config()` → **cached snapshot** |
| **C — DB envelope** | Dedicated credential tables | AI connections (`ai_connections`), YouTube/Twitch keys (`vtual_platform_credentials`), marketplace FTP (`microstock_marketplace_credentials`) | Model cast → `EnvelopeEncryptionService` |

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

Envelope encryption for product-domain secrets follows `EnvelopeEncryptionService` + `EnvelopeEncryptedArray` cast. Reference: [payment-gateway encryption](../domains/payment-gateway/encryption.md), vTual `PlatformCredential`, Microstock `MarketplaceCredential`, core Ai [`ai_connections`](../domains/ai/README.md).

**AI transport is Tier C, not Tier A.** Endpoints + API keys live in `ai_connections` (managed at Administration → AI connections, per-consumer binding by slug). A consumer's non-secret knobs (model, timeout, max_tokens, min confidence) stay Tier B. `MICROSTOCK_AI_*` / `VTUAL_AI_CLASSIFY_*` env keys are gone; `php artisan ai:import-legacy-env` performs the one-time import.

Payment gateway **signing secrets** remain Tier A env today (CRUD manages metadata only).

## Local profile overrides

When `APP_ENV=local`, [`LocalSettingsSeeder`](../../database/seeders/LocalSettingsSeeder.php) runs immediately after [`SettingsSeeder`](../../database/seeders/SettingsSeeder.php) and applies [`config/settings-local.php`](../../config/settings-local.php) via `upsertMany` (overwrites local rows on each seed).

```
SettingsSeeder (all envs)     → firstOrCreate from config/*.php literals (prod-safe)
LocalSettingsSeeder (local)   → upsertMany from settings-local.overrides (dev ergonomics)
AiConnectionSeeder (local)    → Tier C rows + slug bindings from the gitignored
                                database/seeders/data/ai-connections.local.php
```

Staging and production **never** run `LocalSettingsSeeder` or `AiConnectionSeeder`. Do not put local-only values in config literals, `.env`, or orphan keys.

### Local Tier C seeding

Tier C secrets (AI endpoints + API keys) have no `.env` home by design — they live envelope-encrypted in their credential tables. To keep a fresh local checkout usable, [`AiConnectionSeeder`](../../database/seeders/AiConnectionSeeder.php) reads a **gitignored** PHP data file and writes the rows through the domain service (`create` / `update` / `test` / `setDefault`).

| | |
|--|--|
| Trigger | `APP_ENV=local` only |
| Data file | `database/seeders/data/ai-connections.local.php` (gitignored; `.example.php` is tracked) |
| Template/config | [`config/ai.php`](../../config/ai.php) → `ai.seed.path` + `ai.seed.binding_models` |
| Command | `php artisan db:seed --class=AiConnectionSeeder` (also runs last in local `DatabaseSeeder`) |
| Never in git | the data file. Secrets reach the repo only via the encrypted `secret_payload` column |

This is the one sanctioned exception to "secrets never on disk": the file is local-only and gitignored precisely so a real key cannot be committed. Never mirror these values into `.env`, `config/*.php`, or docs — that is exactly how `.env.pre-archivd-backup` leaked. Detail: [domains/ai/README.md — Local seeding](../domains/ai/README.md#local-seeding--wiring).

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
| `vtual.content_classification.ai.enabled` | `true` | Local AI sub-category audit on |
| `vtual.content_classification.ai.model` | `internal.sspn_misc` | Local audit alias (also the probe model for the `vtual-classify` connection) |
| `vtual.content_classification.ai.max_tokens` | `2_000` | Reasoning models need headroom before they emit JSON |
| `vtual.content_classification.ai.timeout` | `120` | Local alias answers in 6-20 s; prod literal is 15 s |
| `microstock.ai.vision.model` | `internal.sspn_vision` | Local Open WebUI vision alias (catalogue: `php scripts/ai/list_models.php` / `probe_models.php`) |
| `microstock.ai.vision.free_model` | `''` | Inherits paid — no separate local free alias |
| `microstock.ai.vision.timeout` | `300` | Shorter local vision HTTP timeout |
| `microstock.ai.thinking.model` | `internal.sspn_thinking` | Local Open WebUI thinking alias |
| `microstock.ai.thinking.free_model` | `''` | Inherits paid — no separate local free alias |
| `microstock.ai.thinking.timeout` | `300` | Shorter local thinking HTTP timeout |

## Agent checklist — new operational knob

1. Classify tier (A / B / C).
2. **Tier B:** add `SettingRegistry` entry → `SettingsSeeder` picks it up → translations in `lang/{en,id,ja,ko,zh_CN,ar,ru}/web/settings.php` (`fields` + optional `hints`) → `SettingRegistryTranslationTest` green → **no** new env key. For enum-like strings, declare registry `options`, ship `web/settings.options.{key}.{value}` labels in all seven locales, and rely on `presentGroup` + SPA `SettingsFormView` `FormSelect` — see [settings.md](settings.md). For **local-only** dev defaults, add to `config/settings-local.php` (not config literals).
3. **Tier C:** dedicated migration + model with envelope cast; admin UI; never registry. For local wiring, add a slug entry to the gitignored `database/seeders/data/ai-connections.local.php` — never a new `.env` key and never a config literal.
4. Domain code reads via `setting_config()` only — never `config()` for registry paths.
5. Update [settings.md](settings.md) and this doc if tier boundaries change.

## Related

- [settings.md](settings.md) — registry inventory and Web UI
- [environment.md](environment.md) — `.env` profiles and curation
- [octane.md](octane.md) — no runtime `config()->set()` or `env()`
