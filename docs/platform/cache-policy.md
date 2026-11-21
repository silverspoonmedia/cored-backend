# Cache policy — Monolith

Canonical rules for Laravel `Cache` usage. Agent protocol: [AGENTS.md](../../AGENTS.md); core rule: [cache-policy.mdc](../../.cursor/rules/cache-policy.mdc). Tier B settings snapshot is a special case of this contract — see [configuration-policy.md](reference-configuration-policy.md#cache-contract-tier-b).

## Rules (mandatory)

1. **Lifetime TTL** — domain/derived data uses `Cache::rememberForever` / `Cache::forever` (or equivalent). Do not expire snapshot data with a short TTL “just in case.”
2. **Rebuild on miss** — absent key → load from DB / recompute → store forever.
3. **Invalidate on data change** — writes that affect cached content must `forget` keys or bump an epoch so the next read rebuilds. Never rely on wall-clock expiry for correctness.
4. **Driver-agnostic** — must work on the default `database` cache store (and Redis, etc.). **No `Cache::tags`.** Prefer serialization-safe primitives (arrays/scalars), not Eloquent models. Prefer explicit key forget or generation/epoch bump over tag flush.

```
Read → hit → return
Read → miss or logically stale → build → forever store → return
Write → forget / bump epoch → next read rebuilds
```

## In scope (domain / derived data)

| Cache | Keys | Invalidation |
|-------|------|--------------|
| Settings snapshot | `system_settings.snapshot` | `rebuildCache()` on upsert/seed; `settings:rebuild-cache` |
| Navigation snapshot + presented trees | `menus.navigation.snapshot`, `tree_epoch`, `presented.{epoch}.{locale}.{layout}.{viewer}` | Menu CRUD/seeder flush + epoch; role update/delete; Domain Access upsert → epoch; activation / user role sync → per-user forget; `menu:flush-cache` |
| Notification audiences | `notification_type_settings.snapshot` | `flushCache()` on sync / seeder |
| Product insight aggregates | `product_insights.{productKey}.metrics` | `ProductAnalyticsService` forget on every analytics write |
| Spatie RBAC catalog | `spatie.permission.cache` (config) | Package `forgetCachedPermissions()` on role/permission mutation; expiration set to century-scale (Spatie API has no forever) |
| Decrypt plaintext | `encryption.decrypted.*` | Forget on encrypt / cast set / explicit invalidate; default max TTL `0` (forever until forget) |
| FX L1 | `fx.frankfurter.{from}.{to}` | Forever store; **logical freshness** via payload `fetched_at` vs `platform.frankfurter.cache_ttl` — aged → forget → DB window then Frankfurter |
| Unregistered-model notify dedup | `inference:unregistered_model_notify:{slug}` | Forever flag; forget when model is classified/registered |
| AI default connection slug | `ai.connection.default_slug` | Forever primitive (no Eloquent in the key). Forgotten by `AiConnectionResolver::forget()` on every connection mutation. Decrypt plaintext for the row stays on `encryption.decrypted.*` |

## FX freshness (not Cache TTL)

`platform.frankfurter.cache_ttl` is a **freshness window** (Settings → Platform), not a Laravel Cache expiration. L1 entries are forever. On read, if `fetched_at` is older than the window, forget the key and rebuild from a fresh DB snapshot or Frankfurter. See [Currency](../domains/currency/README.md).

## Explicit exceptions (short TTL allowed)

These are leases, security expiries, or rate limits — **not** source-of-truth data caches. Do not convert them to forever.

| Use | Why TTL stays |
|-----|----------------|
| Cache locks (`Cache::lock`) | Lease must expire if the holder dies |
| Broadcast / realtime throttles (`Cache::add` 1–5s) | Rate limiting |
| 2FA challenge tokens (`2fa_token:…`) | Security expiry |
| JWT blacklist (tymon/jwt-auth Illuminate store) | Token lifetime |

## Anti-patterns

- TTL on domain snapshots “to avoid stale data” instead of write-path invalidation
- `Cache::tags` (breaks on `database` driver)
- Caching hydrated Eloquent graphs in forever keys
- Reading registry settings via `env()` / direct `config()` instead of the settings snapshot ([configuration-policy](configuration-policy.md))

## Recovery commands

| Command | Scope |
|---------|-------|
| `php artisan settings:rebuild-cache` | Settings snapshot |
| `php artisan menu:flush-cache` | Navigation snapshot + presented epoch |
| `php artisan encryption:flush-decrypted-cache {context}` | Decrypt plaintext for one context only (no bulk flush; the argument is required) |
| `php artisan permission:cache-reset` | Spatie permission cache (if available) / package forget |

## Related

- [configuration-policy.md](configuration-policy.md) — Tier B forever snapshot
- [settings.md](settings.md) — settings inventory (incl. FX freshness window)
- [navigation README](../domains/navigation/reference.md#caching) — two-tier menu cache; Domain Access upsert → presented epoch bump
- [domain-access README](../domains/navigation/reference.md#visibility-navigation-tree) — Public/Private gate + epoch invalidation
- [product-activations README](../domains/product-activations/README.md) — insights forever aggregate + activation → per-user presented forget
- [currency README](../domains/currency/README.md) — forever L1 + freshness
- [identity README](../domains/identity/README.md) — Spatie century-scale permission cache
- [notification README](../domains/notification/README.md) — audience snapshot; inference unregistered-model forever dedup
- [inference README](../domains/inference/README.md) — unregistered-model notify dedup key
- [ai README — Resolution](../domains/ai/reference.md#resolution) — default-slug forever cache + `forget()` on mutation
- [envelope-encryption.md](envelope-encryption.md) — decrypt cache
- [octane.md](octane.md) — no request state on statics; shared cache store in production
