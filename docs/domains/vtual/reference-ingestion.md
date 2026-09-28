# vTual - platform clients & ingestion

> Part of the vTual deep dive. Siblings: [reference.md](reference.md) - identity, claims, credentials · [reference-api.md](reference-api.md) - API + SPA surfaces · [reference-classification.md](reference-classification.md) - music layer, cover detection, merge.

## Kick strategy (foundation)

- Platform row exists with `enabled = false`.
- `ChannelClaimService::start` / platform resolve rejects disabled platforms (no scrape path); Kick has no profile resolve.
- Re-enable only after official API decision (see archive audit).

## Platform HTTP clients (manual fetch)

Worker-facing foundation for **pull** reads against YouTube Data API v3 and Twitch Helix, plus `vtual_contents` upsert persistence (ingestion services). Push ingestion (WebSub/EventSub webhooks) sits on top of this layer — see the ingestion sections below.

### Layout

```
app/Domain/Vtual/Integrations/
  PlatformApiGateway.php          # public facade
  PlatformClientRegistry.php
  Youtube/YoutubeDataApiClient.php
  Twitch/TwitchHelixClient.php
  Contracts/{PlatformClient,FetchesVideos,FetchesChannels,ResolvesChannel,FetchesStreams}.php
  Dto/{PlatformVideoDto,PlatformChannelDto,PlatformStreamDto,YoutubeParts}.php
  Support/{BatchIdChunker,PlatformHttp,ChannelProfileInput,ChannelProfileInputParser,ChannelProfileResolver}.php
  Enums/PlatformApiOperation.php
```

### Upstream retry and backoff (429 / 5xx)

`PlatformHttp::get()` is the single choke point for every platform read, so the retry policy lives there and nowhere else. **Never wrap a pool loop in a retry** — the pool services already own their own attempt semantics, and a retrying loop would multiply its own work.

| Condition | Behavior |
|-----------|----------|
| 429, 5xx | Retried up to `vtual.http.retry.times` (default 3) with exponential backoff from `base_ms` (250), capped at `max_ms` (8000), **full jitter** (`random_int(0, backoff)` spreads a fleet-wide quota hit instead of resynchronising it) |
| `Retry-After` present and sane (1–60 s, delta-seconds or HTTP date) | Honoured, capped at `max_ms`. Absurd values are ignored — the upstream is confused, not asking us to sleep for an hour inside a request |
| 401 / 403 | **Never retried** — the credential-failover path (`PlatformApiGateway::runWithCredentialFailover`) owns those |
| Other 4xx | **Never retried** — a hard client error a retry cannot fix |
| 429 after the budget | `PlatformApiException` with `upstreamStatus: 429` and a distinct message (`api/vtual.integrations.upstream_quota_exhausted`) so ops can tell quota exhaustion from a hard failure |
| Connection error | Immediate `PlatformApiException` (502) — no retry |

Every attempt logs to ErrorLog through `PlatformErrorLogger` with `event` (`platform_http_retry_scheduled` / `platform_http_quota_exhausted` / `platform_http_failed`), `attempt` and `max_attempts`, so a quota incident is greppable. Knobs are Tier B settings under `vtual.http.retry.*` (`config/vtual.php`). The pool commands and their gates are unchanged — see [platform/commands.md](../../platform/commands.md).

### Channel profile identity (canonical vs handle)

| Platform | `external_id` (stable, unique) | `handle` (mutable, display) |
|----------|--------------------------------|-----------------------------|
| YouTube | Channel id `UC…` | bare handle (no `@`) from `customUrl` |
| Twitch | Numeric user id | lowercase login |

**Register** (`POST /api/v1/vtual/creators/{id}/channels`): body `{ platform_key, profile }`. `profile` accepts:

- YouTube: `UC…`, `@handle`, `youtube.com/@handle`, `youtube.com/channel/UC…`
- Twitch: numeric id, login, `twitch.tv/login`

Requires **active** platform credential (same as gateway). Parser → `ChannelProfileResolver` → `fetchChannels` or `resolveChannel` → persist DTO.

**Create** (`POST /api/v1/vtual/creators/{id}/channels`): body `{ platform_key, profile }`. Successful platform resolve stores canonical identity and status **`active`** (no second verify click required).

**Profile snapshot columns** (intersection YouTube + Twitch from existing channel/user endpoints; auto-filled on create/refresh):

| Column | YouTube | Twitch | Notes |
|--------|---------|--------|-------|
| `description` | `snippet.description` | `description` | channel bio |
| `avatar_url` | best `snippet.thumbnails` (high→medium→default) | `profile_image_url` | DTO `thumbnailUrl` |
| `banner_url` | `brandingSettings.image.bannerExternalUrl` | `offline_image_url` | Twitch offline banner ≠ YT banner pixel-perfect |
| `platform_created_at` | `snippet.publishedAt` | `created_at` | platform account/channel birth |
| `subscriber_count` | `statistics.subscriberCount` (null when hidden) | — | YouTube only; refreshed via channel pool |

Not shared this phase (no dual-platform signal without extra APIs): Twitch follower counts, video_count, view_count (Twitch deprecated), country, broadcaster_type. Existing rows: channel pool resync backfills snapshot including `subscriber_count`.


**Refresh** (`POST /api/v1/vtual/channels/{id}/refresh`): ops/API enqueue into `vtual_channel_fetch_pool` by stored `external_id` (no immediate platform HTTP). Snapshot updates on pool flush. Rejects archived channels. **SPA owned-profile manage does not expose a refresh button** — display name, avatar, banner, description, and YouTube `subscriber_count` are kept current by the hourly scheduled resync below.

**Channel fetch pool:** non-interactive only (API refresh + scheduled resync). Flush when pending unique ids per platform ≥ `config(vtual.channel_pool.threshold)` (50) **or** oldest pending age ≥ `max_wait_minutes` (15). Single `Cache::lock` + claim-to-`processing` prevents double HTTP for the same set. **Creator claim bio verify** uses `claim_verify_pool` (same threshold semantics; batch `fetchChannels` up to 50 ids per platform). Accept Anyway resolve stays sync. Commands: `php artisan vtual:flush-channel-pool` (`--force`, `--platform=`, `--resync`) runs **registration flush first**, then fetch pool; `php artisan vtual:flush-claim-verify-pool` (`--force`) for claim verify queue. `--resync` enqueues active channels with stale/null `last_synced_at` **or** null `subscriber_count` (snapshot backfill). Schedule: every minute flush channel pool + claim verify pool; hourly channel `--resync`.

**YouTube content fetch (POC):** `php artisan vtual:fetch-channel-content {vtual_channels_uuid}` — **manual last frontier** (recovery/debug). Not scheduled. Use `--video` / `--discover-live` for known ids or live search. Default path ingests via onboarding backfill + feed poll + content pool below.

**YouTube channel onboarding (one-time):** All YouTube activations go through [`ChannelActivationService`](../../../app/Domain/Vtual/Services/ChannelActivationService.php) (`activateFromDto`, `activatePendingFromDto` via registration flush): **WebSub subscribe** + **`RunChannelInitialContentBackfillJob`** (async) run together. Backfill: `playlistItems.list` (≤50) + `videos.list` (≤50) → upsert contents; sets `content_initial_backfill_at` and seeds `feed_last_entry_updated_at` from the persist watermark (`publishedAt` / `startedAt` / `endedAt` max) — no second Atom `listVideos` call. Legacy channels without backfill: `php artisan vtual:backfill-initial-content {vtual_channels_uuid}`. Legacy channels without WebSub row: `php artisan vtual:renew-websub-subscriptions --repair-missing`. Feed poll skips channels until `content_initial_backfill_at` is set.

**Content ingestion (scheduled):**

| Layer | Command | Schedule | Quota |
|-------|---------|----------|-------|
| Feed discovery | `vtual:poll-channel-feeds` | every 15m | 0 (public Atom `feeds/videos.xml`) |
| API hydrate pool | `vtual:flush-content-pool` | every 1m | `videos.list` batch ≤50 |
| Live CCV + ended | `vtual:poll-live-contents` | every 2m | `videos.list` for `status=live` rows only |
| Classification AI audit | `vtual:audit-content-ai` | every 5m (`--limit=5`, `withoutOverlapping`) | AI endpoint only (no platform quota); see [Content classification](reference-classification.md#content-classification-music-layer--cover-song-detection) |
| WebSub renew / repair | `vtual:renew-websub-subscriptions` (`--repair-missing` for legacy active channels); `vtual:repair-archived-websub` for archived channels missing active/pending subscription (pre-policy repair); `vtual:websub-status` per-channel report + `active_never_notified` summary | daily / weekly / on-demand | hub subscribe |

Flow: **onboarding** (WebSub + backfill job on activate via `ChannelActivationService`) → feed/WebSub deltas → enqueue video id → `ContentFetchPoolService` → `ChannelContentFetchService::fetchVideosAndPersist` → `ContentService::upsertFromPrimary` (each hydrate also recomputes `content_category` and sets `content_type` — see [Content classification](reference-classification.md#content-classification-music-layer--cover-song-detection)). Feed/WebSub discovery only (0 quota); hydrate via API batch ≤50. WebSub and feed share the same content pool (coalesce on pending id; upsert idempotent — no conflict). WebSub callback: `GET /api/v1/vtual/webhooks/youtube-websub` (hub verify) and `POST` same path (notifications; `throttle:120,1` per IP). Subscribe on YouTube channel activate (all paths); **archive keeps WebSub + feed poll alive** (stub ingest only — see Archive below). Hub verify needs public `APP_URL` — local without tunnel may leave row `pending`/`failed`; feed poll remains safety net. Scheduled feed poll skips channels until `content_initial_backfill_at` is set. **Private discovered channels** (`visibility=private`) are excluded from feed poll and WebSub until owner promote.

**Stub contents (`hydrated_at` null):** While a channel is archived, feed poll and WebSub/content-pool flush call `ContentService::upsertStubFromPrimary` (minimal metadata, no API quota). Rows are **hidden** from public vTuber profile (`listForPublicProfile` excludes primary channel `status=archived`). API hydrate (`fetchVideosAndPersist`, live CCV poll, channel refresh pool) stays blocked until restore.

### YouTube operational stability (ops)

Live probe rubric and results: [archive/vtual-youtube-stability-assessment-2026-08-07.md](../../archive/vtual-youtube-stability-assessment-2026-08-07.md).

| Requirement | Why |
|-------------|-----|
| `schedule:work` or cron | `vtual:flush-*-pool`, feed poll, live CCV, hourly resync |
| `queue:work` when `QUEUE_CONNECTION=database` | `RunChannelInitialContentBackfillJob` on activate; `RunChannelStubContentBackfillJob` on restore — sync driver runs inline |
| Public `APP_URL` | WebSub hub verify + subscription lease |

**Assessment rubric:** L1–L8 (credentials, gateway, cohort, pools, WebSub, scheduler, **DB performance**, **API quota**). **Product gate:** L7 or L8 **Red** → not worth it at scale even if functional probes pass. Re-run assessment after major ingestion or credential changes.

**YouTube WebSub hardening:** Shared Atom parsing via [`YoutubeAtomFeedParser`](../../../app/Domain/Vtual/Integrations/Youtube/YoutubeAtomFeedParser.php) (feed client + notification, including `at:deleted-entry` tombstones). **GET verify:** only `hub.mode` ∈ `subscribe` | `unsubscribe`; `markVerified` updates row only when state allows (`pending`/`failed` → `active` on subscribe; `active`/`pending` → `unsubscribed` on unsubscribe); unknown mode → **403** (no challenge echo). **POST:** dual HMAC (`X-Hub-Signature` `sha1=` or `sha256=`); digest compared as **hex or base64** (YouTube sends hex); body cap `vtual.websub.max_body_bytes` (default 1 MiB, Settings registry) → **413** when exceeded; invalid signature → **204** (no leak) + structured `Log::warning` + `last_error` on subscription row. **Topic lookup:** exact `topic` column, then fallback parse `channel_id` from topic URL → match active subscription by channel `external_id`. **Deleted/tombstone videos:** WebSub `at:deleted-entry` and `videos.list` omissions during pool flush mark content `status=unavailable` + `first_unavailable_at` (first mark only); successful hydrate clears the marker; public profile/directory listings exclude unavailable rows. Ops: `vtual:websub-status` per-channel report with `active_never_notified` summary for staging push validation.

**Twitch EventSub (webhook push):** Mirror of the WebSub pattern for Twitch channels — schema in `vtual_eventsub_subscriptions` (**one row per channel × event type**: `channel.update` v2, `stream.online` v1, `stream.offline` v1; unique `(channel_id, event_type)`; per-row `secret`; `TwitchEventSubStatus` `pending|active|failed|unsubscribed`; `twitch_subscription_id` unique from Twitch). **Subscribe** (`TwitchEventSubSubscriptionService`): on Twitch channel activation via `ChannelActivationService` (plus `vtual:renew-eventsub-subscriptions --repair-missing` repair) — creates 3 rows and POSTs Helix `POST /eventsub/subscriptions` (webhook transport, callback `route('api.v1.vtual.webhooks.twitch-eventsub')`) with the active Twitch `OauthApp` **app access token** from `PlatformCredentialService::getAppAccessToken`; no active credential → graceful skip (log only). Twitch's signed `webhook_callback_verification` message flips rows `pending/failed → active`. **Notifications** (`TwitchEventSubNotificationService`): signature = `Twitch-Eventsub-Message-Signature: sha256=<hex>` HMAC-SHA256 over `message_id + message_timestamp + raw body` with the row's secret (`hash_equals`); `Twitch-Eventsub-Message-Id` deduped via cache 6h (Twitch redelivers on timeout); all three event types coalesce into `ChannelFetchPoolService::enqueue(Twitch, broadcaster_user_id)` — batched snapshot refresh through the existing flush + `ChannelSnapshotUpdated` realtime. **Live marking** (`TwitchLiveStateService`): `stream.online` → one sync Helix `GET /streams` fetch → `ContentService::upsertFromPrimary` writes/refreshes a live `vtual_contents` row (`PlatformVideoDtoMapper::toUpsertPayloadFromStream`; Twitch stream session id = content `external_id`, so repeated notifications refresh CCV/title in place); `stream.offline` → `ContentService::endLiveStreamsForChannel` flips the channel's `Live/Live` rows to `Archive/Ended` + `ended_at` (idempotent). `channel.update` does not touch live rows. Live-state changes fire `channel.snapshot.updated` realtime; no active credential or fetch error → graceful skip (live row reconciled by the poll backstop). **Revocation** (`message_type=revocation`) → row `unsubscribed` with reason in `last_error`; daily renewal (`vtual:renew-eventsub-subscriptions`) re-subscribes rows within `renew_before_hours` (24) of lease expiry. Callback: `POST /api/v1/vtual/webhooks/twitch-eventsub` (`throttle:120,1`, exempt from `RequireSpaRequestHeader`), body cap `vtual.eventsub.max_body_bytes` (1 MiB → 413), always 2xx (non-2xx triggers Twitch redelivery). Ops: `vtual:eventsub-status {--channel=}` per-channel report. Config: `vtual.eventsub`. Tests: `TwitchEventSubWebhookTest`, `TwitchEventSubSubscriptionServiceTest`.

**Channel registration pool (staff):** `registerPendingForStaff` stores `profile_input` on `vtual_channels` (`status=pending`, `external_id=pending:{uuid}`). `ChannelRegistrationFlushService` uses the same 50 / 15m gate **per platform** (YouTube count ≠ Twitch count). On flush: resolve profiles → activate channel row. Failed resolve sets `resolve_error`, keeps pending. Discard a speculative pending row with `DELETE /api/v1/vtual/channels/{id}` (`ChannelService::deletePending`, `forceDelete`).

**Archive** (`POST /api/v1/vtual/channels/{id}/archive`): soft-remove (`status=archived`); **422** if the row is still pending. Hidden from default channel list and **all content hidden** on public vTuber profile. WebSub subscription and feed poll **continue** — new deltas persist as stub rows (`hydrated_at` null) only; no API hydrate, live poll, or channel refresh while archived. Rate-limited (same lifecycle keys as restore).

**Restore** (`POST /api/v1/vtual/channels/{id}/restore`): archived → `active`; **always resubscribes WebSub** via `YoutubeWebSubSubscriptionService::resubscribe()` (primary repair path for legacy unsubscribed rows); dispatches `RunChannelStubContentBackfillJob` to batch-hydrate stub contents via API. Rate-limited: per-channel cooldown (`vtual.channel_lifecycle.cooldown_seconds`, default 30s) and per-user burst (`vtual.channel_lifecycle.max_per_minute`, default 10/min). Non-archived → 422. List archived via `GET /creators/{id}/channels/archived`. Profile snapshots (display name, avatar, banner, subscriber_count) catch up on the next hourly `vtual:flush-channel-pool --resync` (or ops `POST /channels/{id}/refresh`). | **Optional ops backfill** for channels that stay archived: `php artisan vtual:repair-archived-websub` (also scheduled weekly).

**External links** (`ExternalLinkService`): creator-scoped CRUD gated by `CreatorAccessService` (membership mutate or `manage any`). Kinds: `social` | `donation` | `website` | `other`.

**Workers:** always call streams/videos APIs with stored `external_id` (Twitch `user_id`, YT channel id) — never login/handle as batch id.

### Credential flow

1. `PlatformApiGateway` resolves `Platform` by key; rejects disabled (Kick).
2. `PlatformCredentialService::pickActive` — only `status=active` (post-Test).
3. YouTube: `resolvePayload` → `key=` query param.
4. Twitch: `getAppAccessToken` + `Client-Id` + `Authorization: Bearer`.
5. Success → `markUsed` (clears failover counters). HTTP 401/403 → `recordAuthFailure` (last_error + consecutive counter; soft demote when failover enabled).

### ErrorLog for platform HTTP failures

`PlatformApiException` extends `BusinessRuleException`, so uncaught `report()` / `recordException` **skip** it (intentional API noise filter). Platform upstream failures are **ops signals** (bad key, quota, outage) and must still land in `error_logs` for manual credential decisions.

| Path | Writes `error_logs`? | Notes |
|------|----------------------|-------|
| `PlatformHttp::get` non-2xx / connection fail | **Yes** via `PlatformErrorLogger` → `ErrorLogRecorder::recordLog` | Context: `domain=vtual`, `component=platform_http`, `platform`, `upstream_status`, `url_host` + `url_path` only (never query/`key=`/tokens), optional `credential_id` + `operation` |
| HTTP 401/403 → `markFailed` | Credential `last_error_*` + ErrorLog from **PlatformHttp only** | `markFailed(..., logToErrorLog: false)` avoids double row |
| Credential **Test** / Twitch token mint fail → `markFailed` | **Yes** (`component=platform_credential`, `event=credential_mark_failed`) | Incomplete secret / probe reject with no HTTP client path |
| Auto-rotate on log | **No** hard rotate | Soft failover is separate (config flag); ErrorLog is audit only |

Do **not** reclassify all BRE as reportable. Do **not** put secrets in message/context.

### Three failure stores, three jobs

The same failure can appear in more than one place, and that is deliberate — each store answers a different question, so collapsing them would lose one of the three answers:

| Store | Answers | Lifetime |
|-------|---------|----------|
| Per-row `last_error*` columns (credentials, subscriptions, pool rows) | "is **this row** healthy?" | Overwritten on the next attempt |
| `error_logs` (ErrorLog domain) | "what threw, with context?" | Log retention, one row per event |
| `vtual_incidents` (`VtualIncidentRecorder`) | "what is **broken right now**, how often, and who owns it?" | One row per distinct failure (`VtualIncidentFingerprint` = sha1 of kind + subject + normalized title/detail), `occurrences` + `first_seen_at`/`last_seen_at`, reopened by a recurrence |

The recorder is called from the failure points that already existed (WebSub + EventSub subscribe/notify, `PlatformCredentialService::recordAuthFailure` / `markFailed`, `PlatformHttp` when the retry budget is exhausted, `ContentFetchPoolService`) and never replaces the other two. Triage is staff-facing: `GET/PATCH /vtual/admin/incidents*` with the core perms `view`/`manage vtual incident`, menu node Administration → vTual → Incidents, SPA queue `/apps/vtual/incidents`, realtime `private-vtual.incidents` carrying **ids + enums only**.

### Soft credential failover

Default **OFF** (`vtual.credential.failover.enabled` in **Settings → vTual**; thresholds via `vtual.credential.failover.*` registry keys). See [configuration-policy.md](../../platform/configuration-policy.md).

| Rule | Behavior |
|------|----------|
| Trigger | Upstream **401/403** only (`recordAuthFailure`) |
| Soft demote | Status stays `active`; `consecutive_auth_failures++`; at threshold set `failover_skip_until` |
| `pickActive` | Skips rows with future `failover_skip_until`; optional `excludeIds`; priority ASC |
| Gateway retry | `PlatformApiGateway` fetch* methods: max `max_retries_per_call` (default 1) alternate credential |
| Clear | `markUsed` / successful **Test** zero counter and clear skip |
| Not trigger | 429, 5xx, connection errors, business 4xx |
| Never | Auto-promote `pending`; hard `disabled` on auth fail; pool burn |

Workers should call gateway `fetch*` / `resolveChannel` (failover). Direct `client()` is single-pick only.


### Shared operations (interchangeable)

| Method | YouTube | Twitch | Batch max |
|--------|---------|--------|-----------|
| `fetchVideos(ids, parts?)` | `GET /videos?id=&part=` | `GET /helix/videos?id=` (repeated `id`) | **50** |
| `fetchChannels(ids, parts?)` | `GET /channels?id=&part=` | `GET /helix/users?id=` | **50** |
| `resolveChannel(handle)` | `channels?forHandle=` | `users?login=` | 1 |

`parts` is YouTube-only (whitelist in `config/vtual.php` → `youtube.allowed_*_parts`). Twitch ignores parts.

Batch rules (`config/vtual.http.batch`):

- Hard-fail if caller passes more than max on single `fetch*` (no silent multi-request).
- `fetchVideosChunked` auto-splits for large lists.

Product flags `supports_live` / `supports_vod` gate **ingestion policy**, not raw HTTP capability. Twitch still implements `fetchVideos` (Helix past broadcasts) even when `supports_vod=false`.

### Platform-specific (no god-method)

| Method | Platform | Notes |
|--------|----------|-------|
| `fetchStreamsByUserIds` | Twitch only | `GET /helix/streams?user_id=` (max 100). Throws `UnsupportedPlatformOperation` on YouTube. |
| Channel catalog / uploads playlist | YouTube `playlistItems.list` on uploads playlist (`UC`→`UU`) via `PlatformApiGateway::listChannelVideos` | Twitch catalog fetch **closed — not planned** (platform scope: Twitch has no uploads/archive catalog on the public Helix API; VOD listing is broadcaster-scoped and expires). |
| Live discovery via `search.list` | YouTube `search.list` (`channelId`, `type=video`, `eventType=live|upcoming|completed`) via `PlatformApiGateway::searchChannelVideos` | Manual only (`--discover-live` on `vtual:fetch-channel-content`); separate quota bucket (100 units/call) — not primary path. |

### Normalized DTOs

Workers consume `PlatformVideoDto` / `PlatformChannelDto` / `PlatformStreamDto` (stable fields toward `vtual_contents` / `vtual_channels`). Mappers are per-client; no shared raw JSON shape.

### Config

See `config/vtual.php`: `http.timeout`, `http.batch.*`, `youtube.default_*_parts`, `youtube.allowed_*_parts`, `twitch.helix_base_url`.

### Usage

```php
$gateway = app(\App\Domain\Vtual\Integrations\PlatformApiGateway::class);

$videos = $gateway->fetchVideos(\App\Domain\Vtual\Enums\PlatformKey::Youtube, ['dQw4w9WgXcQ']);
$videos = $gateway->fetchVideos(\App\Domain\Vtual\Enums\PlatformKey::Twitch, ['9001']);

$streams = $gateway->fetchStreamsByUserIds(\App\Domain\Vtual\Enums\PlatformKey::Twitch, ['12345']);
```

### Admin probe (permanent Root/Admin debug)

Permanent debug surface for **production** `PlatformApiGateway` — **not** temporary. Root/Admin only (`manage vtual platform credentials`). Uses the **active** credential; hits live YouTube/Twitch (or `Http::fake` in tests). No secrets in response.

| Surface | Method | Path |
|---------|--------|------|
| Web form | GET/POST | `/apps/vtual/platform-credentials/probe` |
| API | POST | `/api/v1/vtual/admin/platform-api/probe` |

**Methods:** `fetch_videos`, `fetch_videos_chunked`, `fetch_channels`, `resolve_channel`, `fetch_streams` (Twitch only).

**Classification preview (documented exception):** `fetch_videos` / `fetch_videos_chunked` items append `content_type` + `content_type_signal` + `content_category` + `content_category_detail` + `content_category_signal` + `content_category_catalog` — debug-only, computed in `PlatformApiProbeService::appendClassificationPreview`, **never persisted** (persistence stays on the hydrate path). `content_type_signal` (`topic` | `category_id` | `keyword` | `original_credit` | `long_form`, null = unknown) names the signal that decided the music layer; `content_category_signal` (`original_keyword` | `original_provide` | `singing_stream` | `strong_cover` | `tags` | `cover_provenance` | `weak_cover` | `catalog_artist_match` | `catalog_artist_mismatch` | `none`) does the same for the sub-category, and `content_category_catalog` carries the iTunes label the catalog branch compared against. Wrong verdicts can be traced without re-running the classifier by hand. The probe stays program-only — it never calls the AI. See [Content classification](reference-classification.md#content-classification-music-layer--cover-song-detection).

#### Agent rule — production mirror (mandatory)

Probe **must** exercise the same production paths workers use. Results must represent real gateway method output (same clients, credentials, DTOs, errors).

| Allowed | Forbidden |
|---------|-----------|
| Call `PlatformApiGateway` methods only | Duplicate Helix/YouTube HTTP clients or alternate query builders |
| Input validation + optional profile normalize via **existing** production helpers (`ChannelProfileInputParser` / `ChannelProfileResolver`) | Probe-only business rules or fake success shapes |
| Serialize gateway DTOs (`PlatformDtoSerializer`) for display | Alternate DTO mapping that diverges from production clients |

Flow:

```text
Probe form/API
  → PlatformApiProbeService (input parse / optional profile normalize)
  → PlatformApiGateway (credential + client + capability)
  → YoutubeDataApiClient / TwitchHelixClient
  → DTO serialize → JSON
```

When **adding, removing, or renaming** a worker-facing gateway method that should be manually testable, update in the **same PR**:

1. `PlatformApiProbeService::METHODS` + `run()` match arm
2. `PlatformApiProbeRequest` validation (`Rule::in`)
3. Probe labels/help in the SPA (`monolith-vue-workers/src/i18n/locales/*.json` under `web.vtual.probe.*`) — the old Blade-era `probe.method_*` / `ids_help` PHP keys were deleted with the frontend
4. Feature tests (`PlatformApiProbeApiTest`; the Blade probe web test was deleted, not archived)
5. This README section (methods table + notes)

Video/VOD ids stay raw (no profile resolve). Channel/stream profile normalize is the shared production resolver used by channel register — not probe-only logic.

#### Profile normalization

Same `ChannelProfileInputParser` + `ChannelProfileResolver` as channel register:

| Method | IDs field | Behavior |
|--------|-----------|----------|
| `fetch_streams` | user id, login, `@handle`, or profile URL | Normalize → canonical `user_id` → gateway `fetchStreamsByUserIds` |
| `fetch_channels` | channel/user id, handle/login, or URL | Normalize → canonical id → gateway `fetchChannels` |
| `fetch_videos` / `fetch_videos_chunked` | raw video/VOD ids | **No** profile resolve → gateway as-is |
| `resolve_channel` | `handle` (or single id) | Gateway `resolveChannel` |

Example: Twitch `ids=2xrakai` on `fetch_streams` resolves to `997729120` then streams. Response includes `resolved: [{ input, external_id, handle }]` for debug transparency.

**Body (API / form):**

| Field | Required | Notes |
|-------|----------|-------|
| `platform` | yes | `youtube` \| `twitch` |
| `method` | yes | see above |
| `ids` | for multi-id methods | array or comma/space string; max 50 videos/channels, 100 streams |
| `handle` | for `resolve_channel` | YouTube `@handle` or Twitch login |
| `parts` | optional | YouTube only; comma list or array |
| `chunked` | optional | if true and method is `fetch_videos`, maps to `fetch_videos_chunked` |

Web UI: link from credentials index → **API probe**. SPA-side form conventions for that screen (per-platform method filtering, dependent `parts` / `chunked` visibility, JSON result rendering) live in the sibling SPA docs — [ui-patterns.md — Dependent field visibility](../../../../monolith-vue-workers/docs/ui-patterns.md).

### Tests

`tests/Domain/Vtual/Unit/Integrations/PlatformApiGatewayTest.php`, `BatchIdChunkerTest.php` — `Http::fake`, batch caps, parts whitelist, auth headers, 401 → `markFailed` + ErrorLog (no secrets), unsupported ops.

`PlatformApiProbeApiTest` — admin probe gate, multi-id path, Twitch streams/channels profile normalize (login → user_id), classification preview signals (`content_type_signal`, `content_category_signal`, catalog resolution).

`VtualLivePredicateTest` — the guest `is_live` predicate over content **appearances**: appearance-only live creator reads live on the public profile, sorts first in the directory, shows the LIVE badge on a guest management roster, and reads false when the appearance's channel is archived.

`PromoteDiscoveredChannelTest` — `promoteDiscoveredChannel` in isolation (8): owner promote + hydrate enqueue, staff bypass, non-member 403, and five guard rejections (already public, not Discord-discovered, archived, disabled platform, Kick even when the platform is enabled).

`ContentIngestionPoolTest` — feed poll + content pool + live CCV integration.

**Blade-era web tests are gone.** `VtuberProfileWebTest`, `PlatformCredentialWebTest`, `CreatorProfileManageWebTest`, `VtualBladeWebTest`, and `AccessibilityBladeTest` were deleted with the in-repo frontend; `tests/_archive/` no longer exists. The screens they covered live in the SPA, and their behavior is covered by the API feature tests above plus the SPA Vitest suite. Do not cite them as evidence.

## Known limitations (each closed, decided, or implemented)

| Item | Status |
|------|--------|
| YouTube WebSub / PubSubHubbub | Implemented — `YoutubeWebSubSubscriptionService`, public webhook, lease renew; subscribe on channel activate; hardened verify/signature/topic lookup (see ingestion **WebSub hardening**) |
| YouTube/Twitch content upsert into `vtual_contents` | **Implemented** — scheduled ingestion (`ContentService::upsertFromPrimary` + feed poll + content pool + WebSub) |
| Twitch EventSub | **Implemented** — `TwitchEventSubSubscriptionService` (Helix `POST /eventsub/subscriptions`, subscribe on channel activate, daily lease renew + `--repair-missing`), public webhook `POST /api/v1/vtual/webhooks/twitch-eventsub` (challenge verification, per-subscription HMAC, message-id dedupe), types `channel.update` v2 / `stream.online` v1 / `stream.offline` v1 (see **Twitch EventSub (webhook push)**) |
| Live CCV poll | **Implemented** — `vtual:poll-live-contents` (`LiveContentPollService`): YouTube per-channel `videos.list` CCV/ended detection + Twitch branch (batch `fetchStreamsByUserIds`; present stream refreshes the live row, missing stream ends it — backstop for missed `stream.offline`) |
| Twitch live marking | **Implemented** — EventSub `stream.online` → Helix `/streams` fetch → live `vtual_contents` row (`TwitchLiveStateService::markOnline`, stream session id = content external_id); `stream.offline` → ends live rows (`ContentService::endLiveStreamsForChannel`, idempotent, `Archive/Ended` + `ended_at`). `is_live` on public profiles derives from these rows; `channel.snapshot.updated` realtime fires on live-state change |
| WebSub tombstone / deleted video | **Implemented** — `ContentStatus::Unavailable` + `first_unavailable_at` from WebSub `at:deleted-entry` and `videos.list` omissions; hydrate clears; public listings hide unavailable |
| Soft credential failover (401/403) | Implemented default **OFF** (`vtual.credential.failover.enabled` in Settings → vTual) |
| Reveal plaintext credential endpoint | Explicitly out of scope |
| Kick integration | **Closed — out of scope**; disabled seed. Kick has no official public API for the read/webhook surfaces this domain needs |
| Multi-stream session correlation | **Closed — out of scope**. `fetchStreamsByUserIds` is consumed by EventSub live marking + the live poll and the stream session id is the content `external_id`; session-level aggregation is a product question (what counts as one session, what the aggregate is for) |
| Auto collab/network appearance discovery | **Cancelled** (no platform partner list; explicit `attachAppearance` only) |
| Creator claim Existing (bio token) | **Phase 1 implemented** (orphan seed + verify + Accept Anyway) |
| Channel claim (attach channel) | **Implemented** dual path (membership first): owner/editor **member** → pending claim + bio / Discord (`POST channel-claims/{id}/verify-discord`) / Accept Anyway; else staff `manage any` → pending channel row (batch resolve). Pending rows are **deleted** (`DELETE /channels/{id}`), not archived. |
| Claim Add New + survey + appeal | **Closed — out of scope** (needs a product spec: moderation flow, appeal SLA, reviewer). Non-staff path is orphan claim |
| Profile merge (absorb) | **Implemented** (same-owner or staff; source archived). Undo/field-pick merge is **closed — out of scope** |
| Viewer-only until claim (strip User create creator) | **Implemented** — `CreatorPolicy::create` is staff-gated (`manage any vtual resource`); non-staff `POST /creators` → 403 `creator.self_create_disabled`. The legacy `create vtual creator` permission stays on roles but is no longer sufficient; the non-staff path is orphan claim. |
| Public homepage / SEO creator & management profiles | **Implemented** — guest landing `/vtual` (directory + `#contents` feed) + two-column public profile `/vtual/vtubers/{slug}` (filterable, **URL-synced** content grid) + guest management profile `/vtual/managements/{slug}` (`stats` + roster LIVE badge), all Valkivid3 with OG/SEO runtime meta via `useHeadMeta`. Authenticated read/claim is SPA `/apps/vtual/vtubers/{slug}` (canonical plural); `/apps/vtual/vtuber/{slug}` aliases onto it. No OG campaign this wave. |
| Paid Service catalog SKUs for Vtual | **Closed — out of scope** (pricing/packaging is a business decision) |
| Data migration from broadercastv2 | Not planned in foundation |
| Feed poll vs content pool deadlock | **Mitigated** — hot-path transactions run with 3 attempts (MySQL 1213 retry); feed poll wraps stub/enqueue + watermark in one tx so channel-row lock order matches `ContentService` upserts |
| Pool `done` row retention | **Implemented** — `vtual:prune-fetch-pools` (`pool_retention.days` default 7) deletes old done/failed rows from both pool tables; scheduled daily |
| Metric / session history | **Implemented** — `vtual_channel_metrics` + `vtual_content_metrics` (append-only samples, bucketed per subject) and `vtual_content_status_events` (append-only **transitions**), written by `VtualMetricRecorder` / `VtualContentStatusRecorder` from the writers that already hold a fresh value, pruned by `vtual:prune-metrics` (default 90 days, scheduled daily). Decision: [reference-metrics.md](reference-metrics.md). |
| WebSub push E2E validation | Tooling shipped — `vtual:websub-status` reports `active_never_notified`; publish on staging + re-run to close the loop |
| Private discovered channels | Excluded from feed/WebSub until `POST channels/{id}/promote` |
| `verified_channel_id` API exposure | **Implemented** — `CreatorClaimResource` exposes `verified_channel_id` + nested `verified_channel` (platform eager-loaded) on list/show and `.claim.progress` realtime; the claim wizard renders the contract channel next to the proof channel. |
| Content classification — catalog artist test | **Bounded, kept.** The iTunes artist test can match a vTuber who publishes their **own** cover to streaming services under their own channel name (`artistName` == `channelTitle`) and report `original`. Explicit title / tag / provenance rules run first, and the AI audit now receives the catalogue verdict as evidence, so this only affects rows with no explicit signal — a documented limit, not open work. |
| Content classification — uploader tag signal | **Implemented** — `vtual_contents.tags` persists the uploader's `snippet.tags` on hydrate (`ContentService::resolveTags`), so `vtual:classify-contents --refresh` and the AI drainer both read the same signal hydrate had in hand. Tags are internal: never serialized by `ContentResource` / `PublicContentResource`. |
| Twitch live music signal | **Closed — source unavailable beyond the category.** Twitch exposes no `topicCategories` and no uploader tags; `game_name` is the only music signal a live row carries, and it is now forwarded as `category_name` (+ a derived tag when it matches `content_classification.twitch_music_categories`) so `ContentKindClassifier` can resolve a music stream without the AI audit. A **song list / now-playing** source does not exist on the public Twitch API — EventSub carries no track metadata and Helix `/streams` has no field for it — so per-song attribution inside a live stream is **not implementable** without a third-party service. Reopen only with such a service chosen. |

