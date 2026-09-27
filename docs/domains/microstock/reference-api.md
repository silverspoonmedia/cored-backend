# Microstock - API, SPA surfaces & operations

> Part of the Microstock deep dive. Siblings: [reference.md](reference.md) - activation, workspaces, billing · [reference-pipeline.md](reference-pipeline.md) - collections, AI metadata pipeline, prompts, variants.

## CSV export

Two scopes, both finalized-only. Columns are config-driven (`microstock.csv.columns`, default `filename,title,description,category,keywords`; `microstock.csv.keywords_separator`). Per-marketplace field mapping is a later iteration.

- **Per collection** (primary): `CsvExportService::buildForCollection($folder)` — finalized metadata of files directly in the folder. Persisted to `microstock.csv_disk` (default `local`) at `microstock/collections/{folder}.csv` on export.
- **Whole workspace**: `CsvExportService::build($owner)` — all finalized metadata in the Microstock workspace (legacy/ad-hoc).

## Marketplace credentials

`microstock_marketplace_credentials` stores per-user marketplace connection secrets (host/username/password/port/…) for the future FTP/API auto-upload. `secret_payload` uses the shared **envelope encryption** cast (`EnvelopeEncryptedArray`, resource type `microstock_marketplace_credentials`) — the same KEK/DEK infra as payment/vTual credentials; plaintext never reaches the DB. MVP only stores/lists/deletes credentials; no auto-upload yet. List rows emit query-mode `actions[]` (`delete`); SPA maps into `RowActions`.

## API (`/api/v1/microstock/*`, `auth:api`)

Middleware: `auth:api` → `domain.access:Microstock` → `product.active:Microstock`.

| Method | Path | Purpose |
|------|---------|---------|
| GET | `workspace` | Resolve (lazy-create, gated) the owner's system workspace |
| GET | `slots` | Slot status snapshot (`activated`, `used`, `limit`, `can_create`) |
| GET | `billing` | Prepaid AI credit balance + per-analysis estimate |
| GET | `billing/ledger` | Paginated prepaid ledger (`BillingLedgerService`) |
| GET | `billing/usage-events` | Paginated per-HTTP-call usage detail. Customer `BillingUsageEventResource` omits `provider_cost_usd` and `rate_snapshot` (upstream cost). Admin variant keeps them. |
| POST | `workspaces` | Create an additional Microstock workspace (slot-gated) |
| POST | `assets/{file}/analyze` | Queue AI metadata analysis (202) |
| GET | `assets/{file}/metadata` | Read current metadata row |
| PATCH | `assets/{file}/metadata` | Finalize manual curation (`title`, `keywords[]`) |
| GET | `credentials` | List marketplace credentials |
| POST | `credentials` | Store credential (`marketplace`, `label`, `secret{}`) |
| DELETE | `credentials/{id}` | Delete credential |
| POST | `collections/{folder}/lock` | Lock collection + store hint (`hint`) |
| POST | `collections/{folder}/analyze` | Start the per-file AI pipeline (202) |
| GET | `collections/{folder}/status` | Status + progress snapshot (incl. derived `phase`) |
| POST | `collections/{folder}/export` | Render + persist collection CSV |
| GET | `collections/{folder}/download` | Stream the exported collection CSV |

**Admin API** (`routes/api/v1/admin-microstock.php`): `auth:api` + in-controller `authorizePlatformBilling()` (Root / Admin / Moderator; Management excluded). Endpoints: `GET admin/microstock/billing/overview`, `GET admin/microstock/billing/usage-events`. See [Usage & balance UI](reference.md#usage--balance-ui).

## Vue-Workers SPA consumer

The sibling Vue-Workers SPA uses `useMicrostockHydrate` for user billing/ledger, usage events, and admin platform overview/usage events. These batch reads reduce Worker invocations without changing API billing or authorization; failed entries retain each view’s individual-loader fallback. See [vue-workers-spa.md — Invocation budget controls](../../integration/reference-vue-workers-spa.md#invocation-budget-controls).

## SPA routes

Symbolic Vue Router names (registered in [`config/spa-routes.php`](../../../config/spa-routes.php)). All data calls go **direct to `/api/v1/*`** — there are no Monolith `web` proxies for Microstock.

| Route name | Purpose |
|----------|---------|
| `archivd.microstock-credentials` | Marketplace credential CRUD (`MicrostockCredentialsView.vue`) |
| `archivd.microstock-usage` | Usage & balance page (`ArchivdMicrostockUsageView.vue`) |
| `settings.microstock` | Operational knobs (Settings → Microstock tab) |

Collection toolbar, workspace switcher, Lock & Describe, and Review panel live inside **`archivd.index`** / folder routes (`ArchivdView.vue`) — not separate named routes. API surface:

| API prefix | Purpose |
|------------|---------|
| `GET/POST /api/v1/microstock/slots` | Slot entitlement for switcher |
| `GET /api/v1/microstock/billing*` | Balance, ledger, usage events (+ admin overview paths) |
| `POST /api/v1/microstock/workspaces` | Create Microstock workspace from switcher |
| `POST/PATCH/GET …/collections/*` | Lock, analyze, status, export, download |
| `GET/PATCH/POST …/assets/{file}/metadata` + `POST …/analyze` | Review panel CRUD + re-analyze |
| Signed preview | Inline preview URL from `SignedDownloadService` after `MicrostockService::resolveAsset` |

Removed Blade island history: [archivd reference-realtime.md — UI architecture / SPA shell](../archivd/reference-realtime.md#ui-architecture--spa-shell-historical).

### Collection UI (SPA)

Auth Archivd browse is **`ArchivdView.vue`** — three Vue Router routes (index, folder, show), not the removed single-island soft-nav shell.

Inside a Microstock collection folder the toolbar exposes **Lock & Describe** and **Review** when the workspace `kind` is `microstock` and a folder is selected. Collection progress text under the quota bar comes from browse folder fields via `formatCollectionProgress` in `useMicrostock.ts` — processed/total/failed counts, not a stepper widget. Lifecycle lives on the folder (`collection_status` / lock / analyze / export APIs).

**Lock & Describe** — `ArCollectionDescribeModal.vue` + `utils/archivd/arCollectionDescribe.ts`. Compact 2-column grid for visible-subjects and buyer-intent sections; char count and billing line in the modal footer. All describe sections optional; only a too-short primary concept or hint over 2000 chars is a hard validation error. Mandatory **Free / Paid** billing-mode radio when daily free is enabled; modal resets selection on open. Paid mode bypasses daily-free quota; Free mode uses daily quota first then prepaid. Re-lock from `failed` pre-fills from `folder.collection_hint`. Upload into a locked collection / workspace root blocked client-side and server-side.

**Workspace switcher** — reactive workspace list from `GET /api/v1/archivd/workspaces`; Microstock rows show `kind === 'microstock'`. Team create gated by plan state; Microstock create POSTs `/api/v1/microstock/workspaces`.

### Review UI (curate metadata)

- **Per-file metadata status** — `FolderService::browse` eager-loads `HostedFile::microstockMetadata` when workspace `kind` is `microstock`; `HostedFileResource` exposes a `microstock` key (`{title, keywords, status, phase, stages}`). SPA listing paints stage-aware badges via `resolveMicrostockBadge` (`utils/archivd/arMicrostockBadge.ts`) and a Review control opening `ArMicrostockReviewModal.vue`.
- **Review modal** — editable title, keyword chips, description/category under an **AI details** toggle; signed preview image. Echo-first metadata updates via Archivd websocket; fallback HTTP poll every 30 s when broadcast is off. Saves via `PATCH /api/v1/microstock/assets/{file}/metadata`; **Re-analyze** via `POST …/analyze`.
- **Progress realtime** — while `collection_status === 'processing'`, `useArchivdRealtime` listens for `collection.analysis.progress` and `asset.metadata.progress`. Fallback poll every 60 s (collection banner) / 30 s (review modal) when broadcast unconfigured.
- **Collection billing** — `GET /api/v1/microstock/billing` pre-flight in the Lock & Describe footer; submit blocks when balance &lt; `fileCount × estimate`. **View balance & history** navigates to `archivd.microstock-usage`. Locked collections expose **Start analysis** (`POST …/analyze`).
- **Review shortcut** — in `review` state, toolbar **Review files** opens the modal for the first listed file.

### Realtime

Progress/billing events ride the Archivd channels (see [archivd reference-realtime.md — Realtime / live listing](../archivd/reference-realtime.md#realtime--live-listing)): `.collection.analysis.progress`, `.asset.metadata.progress`, `.microstock.billing.changed` via `MicrostockRealtime`. Credential CRUD additionally pings the owner's `archivd.user.{id}` channel as `.microstock.credentials.changed` (ids + `action`, no secret data) — `MicrostockCredentialsView.vue` refetches on ping.

## Tests

`php artisan test --filter="MicrostockTest|CollectionLifecycleTest|CollectionApiTest|AnalysisWatchdogTest|AiBillingServiceTest|AiCreditServiceTest|BillingLedgerServiceTest|BillingLedgerApiTest|BillingAdminApiTest|KeywordNormalizerTest|HttpAiMetadataServiceThinkingFallbackTest|HttpAiMetadataServiceResponseParsingTest|HttpAiMetadataServiceCurateDraftTest|HttpAiMetadataServiceVisualThinkingTest|HttpAiMetadataServiceTierModelTest|MicrostockAiConfigTest|PromptsTest"` —
- `MicrostockTest`: provisioning gated by entitlement (403 without), slot-limit multi-workspace create, public-share block, analyze → generate (stub AI, asserts `title`/`description`/`category`/`keywords`), finalize curation, CSV export finalized-only, credential encrypt/decrypt roundtrip, invite paid-gate. Checkout coverage in `ServiceCheckoutZeroChargeTest`.
- `CollectionLifecycleTest`: root-upload blocked (422), locked-collection upload blocked, lock requires hint + transitions + rejects re-lock + rejects non-microstock folder, analyze requires lock/files + dispatches `ProcessCollection`, non-recursive batching (sub-folder untouched), empty→failed, **multi-file dual-lane pipeline** (all files → `review`), prompt hint threading (`Prompts::visionUser`/`thinkingUser` + the `COLLECTION CONTEXT` block), status progress counts, per-collection CSV filter (incl. `description`/`category` columns), export persists CSV + marks exported.
- `CollectionPipelineTest`: per-file vision dispatch count, solo vs collection queue routing, thinking idempotency, watchdog thinking backlog recovery.
- `CollectionApiTest`: HTTP lock/analyze/status/export/download endpoints (auth, validation, transitions, foreign-collection 403), browse exposes `microstock` key per file in a Microstock workspace (omitted in personal), per-file metadata show/finalize/re-analyze + signed preview API responses.
- `AnalysisWatchdogTest`: pending metadata past grace → `failed`; recent pending untouched; `processing` folder with all-failed metadata → `failed`; folder with some `generated` → `review`; zombie pending reaped; watchdog self-dispatches only while a collection is `processing`; `status()` returns derived `phase` (`analyzing`/`degraded`/echo).
- `HttpAiMetadataServiceThinkingFallbackTest`: thinking happy path uses curated result; system-prompt hang retries with fused user message; both attempts failing falls back to the vision draft (`raw.thinking = null`) — all asserting `description`/`category` are carried through.
- `HttpAiMetadataServiceResponseParsingTest`: SSE `data: [DONE]` trailer stripping, full SSE delta stream aggregation, `stream: false` on requests, markdown-fenced JSON, Anthropic content blocks, batch `assets` wrapper, unparseable body → `ai_invalid_response`.
- `HttpAiMetadataServiceCurateDraftTest`: `curateDraft()` delegates to thinking stage; curated keywords returned; multimodal request when image provided.
- `HttpAiMetadataServiceVisualThinkingTest`: `generate()` sends image on thinking call; multimodal failure falls back to text-only.
- `HttpAiMetadataServiceTierModelTest`: prepaid/no-context uses paid aliases, daily-free uses free aliases for both stages, empty free overrides inherit paid, and usage events keep the selected alias/source.
- `MicrostockAiConfigTest`: locks paid/default model aliases and nullable free-tier overrides in config.
- `PromptsTest`: `visionSystem()` and `thinkingSystem()` char count under gateway limit (~1200); keyword target 50–60 in thinking prompt.
- `BillingLedgerServiceTest`: ledger pagination, running `balance_after`, signed amounts per transaction type.
- `UnknownModelBillingTest`: provisional notify dedup, consolidation refund/debit, negative balance, `planAnalyze` gate when balance &lt; 0.
- `InferenceRateServiceTest`: provisional config rates, `isRegisteredOnProvider`, classification flag on `ensureModelCard`.
- `BillingLedgerApiTest`: user `GET billing/ledger` + `GET billing/usage-events` (auth, pagination, daily-free vs prepaid rows).
- `BillingAdminApiTest`: platform overview aggregates, cross-user usage events, role gate (Root/Admin/Moderator vs Management).
- `KeywordNormalizerTest`: dedupe + cap at 60 keywords.
- Client-side upload helpers are covered by SPA Vitest (`monolith-vue-workers/tests/` and composable unit tests) — see [Archivd — UI architecture / SPA shell](../archivd/reference-realtime.md#ui-architecture--spa-shell-historical).

## Operations: queue worker

Microstock uses **dual-lane pipeline queues** plus a solo lane and daily-free lanes (see `config/microstock.php` → `queues`). Orchestration jobs (`ProcessCollection`, `AnalysisWatchdog`) run on `default`.

| Queue (default name) | Jobs | Workers needed |
|----------------------|------|----------------|
| `microstock-collection-vision` | `AnalyzeAssetVision` (collection) | ≥1 (overlap with thinking lane) |
| `microstock-collection-thinking` | `AnalyzeAssetThinking` (collection) | ≥1 |
| `microstock-solo` | Solo re-analyze vision + thinking | ≥1 |
| `microstock-collection-vision-free` | Daily-free collection vision | ≥1 (same worker process is fine) |
| `microstock-collection-thinking-free` | Daily-free collection thinking | ≥1 |
| `microstock-solo-free` | Daily-free solo re-analyze | ≥1 |
| `default` | `ProcessCollection`, `AnalysisWatchdog`, checksum, etc. | ≥1 |

**Single command (recommended):** runs all Microstock queues in priority order (paid → free → `default`):

```bash
php artisan microstock:queue-work
```

Windows / Laragon (respawn per job):

```bash
php artisan microstock:queue-work --listen
```

Debug one job:

```bash
php artisan microstock:queue-work --once
```

**Split workers (production scale):** run at least two processes (vision + thinking) so vision file N+1 can overlap thinking file N within a collection. `LimitAnalysisConcurrency` enforces per-folder and global caps from `ai.upstream`. Use a **shared cache** (Redis) for counters in multi-server deploys.

```bash
php artisan queue:work --queue=microstock-collection-vision,microstock-solo --tries=3
php artisan queue:work --queue=microstock-collection-thinking,microstock-solo --tries=3
php artisan queue:work --queue=default --tries=3
```

Single-asset runs can be dispatched synchronously in Tinker with `QUEUE_CONNECTION=sync` for a quick check. `AnalysisWatchdog` is dispatched by `ProcessCollection` and re-arms while any collection is `processing`.

### Finding: `queue:work` exits on Windows/Laragon (environment, not a code bug)

On Laragon/Windows, `php artisan queue:work` **exits back to the shell prompt right after a job finishes (`DONE`)** instead of staying alive as a daemon. Consequence: the next queued job is never picked up until you re-run the command manually.

- Cause: the Windows PHP CLI build (ZTS) ships **without `pcntl`/`posix`** (verified `pcntl: False`, `posix: False`), so the daemon's signal-based loop is not stable as a long-running process on Windows. `max_execution_time=0` (no PHP timeout), and no `--once`/`--stop-when-empty` was used.
- Not the app: there is no `shouldQuit` / `stopWhenEmpty` / `queue:restart` / `exit` in `app/`, `bootstrap/`, or `routes/console.php` that would stop the worker. The same `queue:work` stays alive on Linux (which has `pcntl`).
- Production: run the worker on Linux, or keep it alive under Windows with a watchdog — e.g. a restart-loop (`.bat` that re-launches `queue:work` when it exits), Windows Task Scheduler / a service with auto-restart, or for local dev `php artisan microstock:queue-work --listen` (respawns per job) / `composer run dev`.

## Explicitly out of scope (MVP)

- FTP/API auto-upload to marketplaces (CSV-only for now; phpseclib next iteration).
- Concrete AI provider lock-in — interface + HTTP client is enough; tests stub it.
- Per-marketplace metadata field mapping (generic CSV).

## Related

- [archivd/README.md](../archivd/README.md) — base storage layer, system workspaces, storage classification
- [envelope-encryption.md](../../platform/envelope-encryption.md) — envelope encryption infra
- [service/README.md](../service/README.md) — entitlements / quota fulfillment
