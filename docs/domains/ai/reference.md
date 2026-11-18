# Ai — deep dive

Schema, test gate, resolution, consumers, import command, local seeding. Invariants: [.cursor/rules/ai.mdc](../../../.cursor/rules/ai.mdc). Brief: [README.md](README.md).

## Why a central connection

Microstock (vision + thinking) and other consumers all speak the same `openai_compatible` chat-completions shape. Their endpoints were env keys, so rotating one key meant editing five `.env` files and redeploying.

| Before | After |
|--------|-------|
| `MICROSTOCK_AI_VISION_ENDPOINT` / `_API_KEY`, `MICROSTOCK_AI_THINKING_*` in `.env` (Tier A) | One `ai_connections` row (Tier C) per endpoint + key |
| Consumer picked its own model/knobs from `config()` | Consumer keeps its Tier B knobs and binds a connection **slug** |
| Key rotation = redeploy every app | Key rotation = edit the row, run Test |

Uniform by default (all consumers share the default connection), independent when needed (a consumer binds its own slug — Microstock can point vision at a fast endpoint and thinking at a cheap one).

## Layers

| Layer | Tier | Owns |
|-------|------|------|
| Connection | C (`ai_connections`) | `endpoint`, `secret_payload.api_key`, `driver`, `timeout_seconds`, `connect_timeout_seconds`, `max_retries`, `extra_headers`, `test_model`, `is_default`, `status` |
| Consumer binding + knobs | B (`system_settings`) | `{consumer}.ai.*.connection` slug + model, max_tokens, min_confidence, cache TTL, concurrency |
| Pricing | Inference tables | `ai_model_cards` / `ai_provider_model_rates` (unchanged) |

## Schema

`ai_connections` — UUIDv7 PK, `secret_payload` cast to `EnvelopeEncryptedArray` (`resource_type = ai_connections`, purpose `ai`).

| Column | Notes |
|--------|-------|
| `slug` | Unique, `[a-z0-9._-]`, lowered — what consumers bind |
| `label`, `driver`, `endpoint`, `test_model` | Human + wire identity |
| `secret_payload` | Encrypted `{api_key: string}`; **never** serialized |
| `secret_hint` | `…w9Qx` — display only, safe to expose |
| `secret_fingerprint` | sha-256 of the normalized payload; change detection only |
| `timeout_seconds`, `connect_timeout_seconds`, `max_retries`, `extra_headers` | Connection-level overrides; `null` = consumer default |
| `is_default` | At most one row (single-default invariant, DB-enforced by service) |
| `status` | `pending` \| `active` \| `disabled` |
| `verified_at`, `last_used_at`, `last_error_at`, `last_error_message` | Health stamps for the admin list |
| `created_by` | Actor FK |

## Test gate

Mirrors other credential stores: **nothing resolves until a manual Test passes**.

| Event | Effect |
|-------|--------|
| Create | `status = pending`, `verified_at = null` |
| Test succeeds | `status = active`, `verified_at = now()` |
| Test fails | status unchanged, `last_error_at` / `last_error_message` stamped |
| Edit secret / endpoint / driver | `status = pending`, `verified_at = null` (identity changed) |
| Edit label / model / timeouts only | status untouched |
| `disabled` → `active` | `verified_at` retained — no re-Test needed |
| Default | only an `active` row can be promoted; the default row cannot be deleted (hand over first) |

Endpoints that cannot be probed write to `last_error_message`; the SPA list surfaces it. A non-2xx answer appends a trimmed copy of the upstream body (`api/ai.connection.test_http_failed_with_body`), so `HTTP 400: {"detail":"Model not found"}` names the bad `test_model` instead of leaving a bare status.

## Model aliases

`test_model` (probe only) and the consumer's Tier B `*.model` (what the consumer actually sends) are Open WebUI-style alias strings the endpoint owns — they are **not** validated at write time, so a rename upstream only surfaces as a failed Test.

| Helper | Use |
|--------|-----|
| `php scripts/ai/list_models.php [slug]` | Read-only: `GET {endpoint-base}/api/models` with the stored key, prints the live aliases. Endpoint defaults to the first row (`microstock-vision`) |
| `php scripts/ai/probe_models.php [slug] [alias …]` | Read-only: 1-token chat request per candidate alias, prints HTTP status + body (`200` = exists, `400 Model not found` = renamed). Prints the configured aliases too |

Both read a row directly (including `pending` ones — the resolver refuses those on purpose) and never print the key.

Local consumer aliases live in [`config/settings-local.php`](../../../config/settings-local.php) — `microstock.ai.vision.model` / `.thinking.model` (plus `.enabled`, `.max_tokens` = 4000, `.timeout`); re-seed `LocalSettingsSeeder` after changing them, then `AiConnectionSeeder` picks the alias up as the probe model.

## Resolution

`AiConnectionResolver` is the single read path:

```
consumer → resolver->resolve($slug)
  → null/blank slug → cached default slug (ai.connection.default_slug) → that row
  → explicit slug → that row
  → miss / pending / disabled / empty key / empty endpoint → null  (never throws)
```

Returns `AiConnectionData` (readonly DTO: id, slug, driver, endpoint, `apiKey`, timeouts, retries, headers). The DTO is the only carrier of the decrypted key; it is never part of an API response.

`forget($slug)` / `forget()` clear the cached default slug and the row's decrypted-plaintext cache — called by every mutation, so a rotated key takes effect on the next request without a redeploy.

## Consumers

| Consumer | Binding setting | Client |
|----------|-----------------|--------|
| Microstock vision | `microstock.ai.vision.connection` (empty = default) | `HttpAiMetadataService::stageConfig()` merges Tier B knobs with `AiConnectionData` |
| Microstock thinking | `microstock.ai.thinking.connection` | same |

A consumer with no bound connection and no default resolves to `null` and reports "AI not configured" instead of issuing an HTTP call. Connection timeouts override the consumer's Tier B timeout when set.

## API

`/api/v1/ai/connections` (`auth:api`).

| Verb | Path | Permission |
|------|------|-----------|
| GET | `connections` (query mode, `actions[]`) | `view ai connections` |
| GET | `connections/{id}` | `view ai connections` |
| POST | `connections` | `manage ai connections` |
| PATCH | `connections/{id}` | `manage ai connections` |
| DELETE | `connections/{id}` | `manage ai connections` |
| POST | `connections/{id}/test` | `manage ai connections` |
| POST | `connections/{id}/default` | `manage ai connections` |

`AiConnectionResource` masks: `secret_hint` + `has_secret` only — `secret_payload` and `secret_fingerprint` are `$hidden` and asserted absent in tests.

PATCH semantics: blank text field = keep stored value; blank `api_key` = keep stored key; explicit `null` on `timeout_seconds` / `connect_timeout_seconds` / `max_retries` / `extra_headers` = clear the override (those keys are exempt from the null filter in `UpdateAiConnectionRequest::connectionPayload()`).

Row actions (`AiConnectionQueryRowActions`): `test`, `default` (active + non-default only), `edit`, `delete` (non-default only).

## Env transition

`php artisan ai:import-legacy-env [--dry-run] [--skip-test] [--actor=<uuid>]` reads the former Tier A keys and creates/binds connections:

| Legacy key | Becomes |
|------------|---------|
| `MICROSTOCK_AI_VISION_ENDPOINT` + `_API_KEY` | connection `microstock-vision`, bound to `microstock.ai.vision.connection` |
| `MICROSTOCK_AI_THINKING_ENDPOINT` + `_API_KEY` | connection `microstock-thinking` |

Behavior:

1. Reads the legacy pairs via `Env::get()` — a pair with an empty endpoint **or** key is skipped.
2. Probes each imported row (`AiConnectionService::test()`) unless `--skip-test`; a failed probe leaves it `pending` with the error stamped.
3. `test_model` comes from the consumer's existing Tier B model setting — a source with no known model is skipped with a warning (set the model, then re-run).
4. Binds `{consumer}.ai.*.connection` through `SystemSettingRepository::upsertMany()`.
5. Promotes the first active imported row to default, so unbound consumers keep working.
6. Idempotent: an existing row with the same slug is updated in place. `--actor` credits `created_by` (defaults to the first `Root` user).

The env keys are **removed** from `EnvironmentVariableCatalog` and all `.env.*.example` files; `SettingRegistryEnvPolicyTest` guards against reintroduction. Run `php artisan env:curate --sync-snapshot` after the import.

**History caveat (2026-09-17):** the retired pairs had also leaked into the tracked `.env.pre-archivd-backup` (committed in `d7c1d15`). Those values are scrubbed in the working tree, but the blob still sits in history — rotate any key that was ever committed and plan a history rewrite before making the repo public.

## Local seeding / wiring

Setting up local from scratch is the fiddly part: an empty `ai_connections` table plus an unbound consumer setting means Microstock silently reports "AI not configured".

`AiConnectionSeeder` wires it in one command. It reads a **gitignored** data file — the only place a plaintext AI key may live on disk besides the encrypted column:

```bash
cp database/seeders/data/ai-connections.local.example.php \
   database/seeders/data/ai-connections.local.php
# fill in the api_key, then:
php artisan db:seed --class=AiConnectionSeeder
```

Data file shape — a top-level `defaults` block (shared transport, so one endpoint + key is written once) plus a `slug => entry` map:

```php
return [
    'probe' => true,
    'defaults' => [
        'endpoint' => 'https://ai.example.test/v1/chat/completions',
        'api_key' => null,          // null/blank = not filled in yet → entries skip
    ],
    'connections' => [
        'microstock-vision' => [
            'label' => 'Microstock vision (local)',
            'binds' => ['microstock.ai.vision.connection'],
            'is_default' => true,
        ],
    ],
];
```

| Key | Level | Required | Notes |
|-----|-------|----------|-------|
| `probe` | top | no | Probe each new row with a 1-token request (default `true`) |
| `defaults` | top | no | Inherited transport: `endpoint`, `api_key`, `test_model`, `timeout_seconds`, `connect_timeout_seconds`, `max_retries`, `extra_headers`. `label` / `binds` / `is_default` are never inherited — a shared label would make every row read the same |
| `endpoint`, `api_key` | entry | yes | Resolved from the entry first, then `defaults`. Missing/blank after both = skipped with a warning |
| `label` | entry | no | Defaults to the slug |
| `binds` | entry | no | Consumer setting keys bound to this slug, e.g. `microstock.ai.vision.connection`. Unknown keys are ignored with a warning |
| `is_default` | entry | no | First entry flagged `true` is promoted — only if the probe passed |
| `test_model` | entry | no | Falls back to the bound consumer's Tier B model setting (`config('ai.seed.binding_models')`). No model anywhere = skipped. Must be a live alias at the endpoint — see [Model aliases](#model-aliases) |

An entry value always wins over `defaults`, and because the merge only fills **missing** keys, an explicitly blank entry value (`'endpoint' => ''`) parks that one entry instead of inheriting.

The file is plain PHP loaded in an isolated scope, so hoisted variables also work (`$endpoint = '…';`, then `'endpoint' => $endpoint`) and cannot clobber the seeder's own variables.

Behaviour:

1. **Local only** — `APP_ENV=local`, same contract as `LocalSettingsSeeder`. Staging/production wire connections by hand at Administration → AI connections.
2. Idempotent by slug: `AiConnectionService::create()` on first run, `::update()` after. An unchanged endpoint + key does **not** demote, so re-seeding keeps `active` + `verified_at`; rotating the key does demote and re-probe.
3. `created_by` = the first `Root` user (falls back to the oldest user). No users yet = skipped with a warning.
4. Binds through `SystemSettingRepository::upsertMany()`, then `AiConnectionResolver::forget($slug)` so the next request sees the new value.
5. Renaming a slug in the data file leaves the old row behind — delete it at Administration → AI connections.
6. Each local consumer resolves its own alias, so both entries seed without an explicit `test_model`: Microstock from `microstock.ai.vision.model` / `.thinking.model` (`config/settings-local.php`). Name `test_model` here only when a connection needs a probe model different from what the consumer sends.
7. A probe can fail on a renamed alias (`HTTP 400: {"detail":"Model not found"}`) — the row is still created + bound, but stays `pending`, so consumers keep resolving the default. Fix the alias setting (Tier B) or the entry's `test_model`, then re-seed or re-Test from the SPA.

`DatabaseSeeder` calls it last (local only), after the users exist and after `LocalSettingsSeeder`'s model aliases — so `php artisan db:seed` on a fresh local checkout is enough, provided the data file is filled in.

The data file must stay out of git: `/database/seeders/data/*.local.php` is in `.gitignore`, only `.example.php` is tracked. Never paste a real key into `.env`, `config/*.php`, or a doc — that is exactly how `.env.pre-archivd-backup` leaked.

## Seams

- `AiConnectionProbe` (`OpenAiCompatibleProbe` = 1-token request) — swap for a new driver.
- `AiConnectionDriver` enum — currently `openai_compatible` only.
- `AiConnectionSecrets` — normalize / fingerprint / hint; one place that knows the payload shape.

## Tests

| Suite | Covers |
|-------|--------|
| `packages/ai/tests/Ai/Feature/Http/Controllers/API/V1/AiConnectionApiTest.php` | CRUD, mask-only payload, test gate, demotion, single default, RBAC |
| `packages/ai/tests/Ai/Unit/Services/AiConnectionServiceTest.php` | lifecycle, slug uniqueness, encryption at rest |
| `packages/ai/tests/Ai/Unit/Services/AiConnectionResolverTest.php` | default fallback, inactive rows, cache invalidation |
| `packages/ai/tests/Ai/Unit/Support/AiConnectionSecretsTest.php` | normalization, fingerprint, hint |
| `packages/ai/tests/Ai/Feature/Console/Commands/ImportLegacyEnvCommandTest.php` | import + dry-run + skip invalid |
| `packages/ai/tests/Ai/Feature/Database/Seeders/AiConnectionSeederTest.php` | local-only guard, skip rules, probe gate, `defaults` inheritance, hoisted variables, scope isolation, no-demotion re-seed, key rotation, binding typos |
| `tests/Feature/Broadcasting/AiConnectionsChannelTest.php` | channel authorization |
| `tests/Concerns/BindsAiConnection.php` | consumer-test stub (no DB, no encryption) |

Consumer suites (`HttpAiMetadataService*`) bind the stub through `BindsAiConnection` instead of seeding a row.
