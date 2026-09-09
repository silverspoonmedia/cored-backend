# vTual — implementation status (living)

Tracks what the **vTual product domain** has shipped in Monolith versus the original aggregator brief ([`TASK.md`](../../../TASK.md) + product decisions in session). **Update this file when a milestone ships** so agents do not re-open closed work or invent gaps that no longer exist.

| | |
|--|--|
| Domain guide (behavior detail) | [README.md](README.md) |
| Code | `app/Domain/Vtual/` |
| Config | [`config/vtual.php`](../../../config/vtual.php), product key `Vtual` in [`config/products.php`](../../../config/products.php) |
| Last reviewed | 2026-09-07 |

---

## Purpose

| Question | Answer |
|----------|--------|
| What is vTual? | Product domain for **content creator aggregation** (vTuber-oriented): profiles, channels, platform HTTP, claims, management skeleton. |
| What is this file? | **Progress matrix**: done / not done / recommended next — with evidence paths. |
| What is it not? | Not a claim-validation ledger for broadercastv2. Not a substitute for [README.md](README.md) API tables. |

Original long-form process/requirements remain in root [`TASK.md`](../../../TASK.md). That file is **input history**, not the living source of truth for current code.

---

## Identity model (locked)

```text
User (Identity login)
  └── CreatorMember (owner|editor|viewer) ──► Creator (vTuber profile)
                                                  ├── ExternalLink (morph)
                                                  ├── Channel ──► Content (primary host)
                                                  │                 └── ContentAppearance ──► other Channel
                                                  └── claims (creator / channel)

Management (talent org) ── ManagementMember / ManagementCreator
Platform + PlatformCredential ──► HTTP clients / pool
```

| Term | Means |
|------|--------|
| **User** | Monolith login (`users`). |
| **vTuber profile / Creator** | Public profile (`vtual_creators`). Membership is private; UI must not advertise that two profiles share one User. |
| **Channel** | Platform channel bound to a Creator; unique `(platform_id, external_id)`. |
| **Content** | One row per platform video/stream id; `channel_id` = **primary host** only. |
| **Appearance** | Extra registered channel on the same content (collab/network); **not** multi-ownership. |

Full glossary and relations: [README Concepts](README.md#concepts).

---

## Done (verified in codebase)

| Theme | Behavior (short) | Evidence |
|-------|------------------|----------|
| **Product foundation** | Locked product domain, activation gate, grouped menu branch, RBAC seed, platforms catalog (Kick disabled) | `config/domains.php`, `config/products.php`, `VtualRoleSeeder`, `product.active:Vtual`, authoritative `MenuSeeder` |
| **Creator CRUD + membership** | Multi-creator per user; self-create binds `owner`; staff orphan has no members | `CreatorService`, `CreatorAccessService`, `CreatorServiceTest` |
| **Platform credentials** | Multi-key per platform, envelope secrets, Test gate, mask-only API, optional soft failover (default OFF); Echo `private-vtual.platform-credentials` / `.platform.credential.changed` after Test/CRUD | `PlatformCredentialService`, `VtualPlatformCredentialRealtime`, probes, `PlatformCredentialServiceTest` |
| **Platform HTTP (manual)** | YouTube Data API + Twitch Helix clients; batch caps; gateway + credential bind | `PlatformApiGateway`, `YoutubeDataApiClient`, `TwitchHelixClient`, `config/vtual.php` `http.batch` |
| **Admin probe** | Web/API probe must use **production** gateway methods only | `PlatformApiProbeService`, `PlatformApiProbe*Test` |
| **Channel profile normalize** | UC… / `@handle` / URL → canonical `external_id` + handle | `ChannelProfileResolver`, `ChannelProfileResolverTest` |
| **Channel snapshot columns** | `description`, `avatar_url`, `banner_url`, `platform_created_at`, `subscriber_count` (YouTube) on create/refresh apply | migration profile columns + `…_add_subscriber_count_to_vtual_channels`, `ChannelService` / pool flush |
| **Creator claim Existing** | Orphan claim via bio token; Accept Anyway; race → `superseded`; audit rows kept | `CreatorClaimService`, `vtual_creator_claims`, `CreatorClaimServiceTest` |
| **Staff orphan + pending claims (API)** | Orphan seed via `/apps/vtual/orphans`; pending claims via API `GET claims/pending` (legacy registry web archived) | `OrphanCreatorController`, `GET /api/v1/vtual/claims/pending` |
| **User claim UX (API only)** | Claimable list + my claims + verify/cancel | `/api/v1/vtual/claims/*` (web `/apps/vtual/claims` archived) |
| **Channel dual path** | Membership first: owner/editor member POST → `ChannelClaim` + bio / Accept Anyway → `activateFromDto`. Else staff `manage any` (no mutate membership) → deferred `registerPendingForStaff` (pending + batch resolve). Pending deleted (`DELETE channels/{id}`), not archived. `seedForStaff` kept for instant bind paths. | `ChannelClaimService`, `ChannelService`, `CreatorAccessService::memberRoleFor`, `ChannelRegistrationFlushService`, tests |
| **Orphan admin UX** | SPA `/apps/vtual/orphans`: minimal create (prefixed slug), redirect to edit, archive confirm, pending channel queue on edit; channels table shows per-channel avatar when resolved (`avatar_url`). Vue `DataTable` (`display_mode=query`). | `OrphanCreatorController`, `VtualOrphansView.vue`, `VtualOrphanEditView.vue`, `OrphanCreatorApiTest` |
| **YouTube content fetch POC** | Manual `vtual:fetch-channel-content {channel-uuid}`; `--limit`, `--video`, `--discover-live` options; upserts into `vtual_contents` | `ChannelContentFetchService`, `FetchChannelContentCommand` |
| **YouTube initial content backfill** | Auto on YouTube activate (async job); persist watermark seeds `feed_last_entry_updated_at`; manual `vtual:backfill-initial-content` for existing rows; demo probe fetched 50 videos into public profile | `ChannelInitialContentBackfillService`, `RunChannelInitialContentBackfillJob`, `ChannelActivationService`, `content_initial_backfill_at` |
| **Channel activation hooks** | Single entry: WebSub + backfill job on all YouTube activate paths (including registration flush) | `ChannelActivationService` |
| **Feed poll ingestion** | Scheduled `vtual:poll-channel-feeds` + `vtual:flush-content-pool`; polls **active + archived** public channels; archived → inline **stub** upsert (no content-pool enqueue for hydrate); skips until initial backfill done | `YoutubeChannelVideoFeedClient`, `ChannelFeedPollService`, `ContentFetchPoolService` |
| **Live CCV poll** | Scheduled `vtual:poll-live-contents`; YouTube per-channel CCV/ended detection + Twitch batch branch (see **Twitch live marking**) | `LiveContentPollService` |
| **YouTube WebSub** | Push trigger + `vtual:renew-websub-subscriptions` (`--repair-missing`); archive **keeps** subscription; restore calls **`resubscribe()`**; ops `vtual:repair-archived-websub` for legacy rows | `YoutubeWebSubSubscriptionService`, `YoutubeWebSubNotificationService`, webhook route; subscribe on `ChannelActivationService` |
| **YouTube WebSub hardening** | Strict hub verify, dual sha1/sha256 signature (hex **and** base64 digests), resilient topic lookup, body cap, structured logging + `last_error`, shared Atom parser (incl. `at:deleted-entry` tombstones) | `YoutubeAtomFeedParser`, `YoutubeWebSubWebhookController`, `config/vtual.php` `websub.max_body_bytes`, registry `vtual.websub.max_body_bytes` |
| **Deleted-video tombstones** | `ContentStatus::Unavailable` + `first_unavailable_at`; set when `videos.list` omits an id during pool flush or WebSub pushes `at:deleted-entry`; successful hydrate clears the marker; public profile/directory queries exclude unavailable rows | `ContentService::markUnavailable`, `ChannelContentFetchService::persistVideos`, `YoutubeWebSubNotificationService`, migration `2026_09_03_100001` |
| **Ingestion deadlock mitigation** | All hot-path `DB::transaction` calls run with 3 attempts (MySQL 1213 retry); feed poll wraps stub/enqueue + watermark update in one transaction so channel-row lock order matches `ContentService` upserts | `ContentService`, `ContentFetchPoolService`, `ChannelFetchPoolService`, `ChannelFeedPollService` |
| **Fetch pool retention** | `vtual:prune-fetch-pools {--days=}` (config `pool_retention.days` default 7) deletes old done/failed rows from both pool tables; scheduled daily | `PruneFetchPoolsCommand`, `routes/console.php`, `PruneFetchPoolsCommandTest` |
| **WebSub ops tooling** | `vtual:websub-status {--channel=}` per-channel report incl. `active_never_notified` summary for staging push validation; `vtual:repair-archived-websub` now scheduled weekly | `WebsubStatusCommand`, `routes/console.php`, `WebsubStatusCommandTest` |
| **vTuber profile (read)** | `/apps/vtual/vtuber/{slug}` — col-4/8 AdminLTE layout; Claim button when orphan (`is_claimable`); uniform content cards; public contents via **`listForPublicProfile`** (hides primary-channel-archived rows); any logged-in user; UUID → 301 slug (web) | `VtuberProfileService`, `VtuberProfileController`, `VtuberProfileResource`, `VtuberProfileWebTest` |
| **Claim process (interactive)** | `/apps/vtual/vtuber/{slug}/claim` — channel pick → token → queued verify (POST start/verify/cancel); WebSocket `claim.progress` on `private-vtual.claim.{id}` (`VtualClaimRealtime`); no polling; `claim_blocked_until` on profile API | `VtuberProfileController`, `claim.blade.php`, `claim-realtime.js`, `CreatorClaimVerifyPoolService`, `VtuberProfileWebTest`, `VtualClaimRealtimeTest`, `CreatorClaimProgressChannelTest` |
| **Owned profiles list** | `/apps/vtual/profiles` — owner membership table; `GET api/v1/vtual/creators/owned` emits query-mode `actions[]` (`edit` → `vtual.profiles.show`, `view` → public `profile_url`); Products menu (`Vtual vTuber`+); claim success assigns role + `activateImplicit(Vtual)` | `CreatorProfilesController`, `CreatorService::listOwnedForUser`, `OwnedCreatorQueryRowActions`, `MenuSeeder`, `CreatorProfilesWebTest` |
| **Claimed profile manage (web)** | `/apps/vtual/profiles/:id` — SPA `VtualProfileShowView`: stacked meta card; separate channels card (orphan-style table, claims above table, Delete pending / Archive / Promote / Restore); links; merge | `VtualProfileShowView`, `useVtual`, `ChannelClaimService::verifyViaDiscord`, `ChannelService::deletePending` |
| **Claim verify hardening** | Bio proof-channel-only; Discord discovery bind + `verified_channel_id`; full connections snapshot | `DiscordConnectionDiscoveryService`, `UserOAuthAccountService::connectionsForOwnershipVerify`, `CreatorClaimServiceTest` |
| **Discord connection discovery** | Orphan claim Discord: bind extra yt/twitch private (`visibility=private`, `discovery_source=discord_connection`); external links from other types; public profile hides private; promote queues hydrate + backfill | `DiscordConnectionDiscoveryService`, `ChannelService::promoteDiscoveredChannel`, `VtuberProfileService` |
| **vTual product hub (placeholder)** | `/apps/vtual/vtuber` — blank AdminLTE shell for upcoming product surfaces; any logged-in user | `VtuberProfileController::index`, `vtual/vtuber/index.blade.php`, `vtual.vtuber.index` |
| **Management naming guide** | Core role `Management` vs `vtual_managements` talent org — canonical vocabulary | [naming-conventions.md](naming-conventions.md) |
| **Platform credentials UI** | `/apps/vtual/platform-credentials`: platform index table + per-platform CRUD + probe at `/probe` | `PlatformCredentialController`, `PlatformApiProbeController`, `vtual-platform-credentials` views, `PlatformCredentialWebTest` |
| **My profiles manage** | List bound profiles; edit meta; external links CRUD; channel claims + table (Delete pending / Archive); merge | SPA `/apps/vtual/profiles/:id` (`VtualProfileShowView`) + API `/creators/*`, `ExternalLinkService` |
| **Profile merge (absorb)** | Same-owner or manage-any; move channels/links/contents/appearances; cancel pendings `merged`; archive source + slug mangle | `CreatorMergeService`, `POST creators/{id}/merge`, `CreatorMergeServiceTest` |
| **Channel archive / restore** | Soft hide `archived` (bound channels only; pending → 422); list archived; restore → `active` + **WebSub resubscribe** + stub backfill job; **public profile hides all content** from archived primary channels; **lifecycle rate limit** (per-channel cooldown + per-user burst) | `ChannelService::archive` / `restore` / `deletePending`, `YoutubeWebSubSubscriptionService::resubscribe`, `ChannelServiceTest` |
| **Archived channel stub ingest** | While archived: feed poll + WebSub/content pool persist **stubs** (`hydrated_at` null) only; WebSub stays subscribed; API hydrate blocked | `ContentService::upsertStubFromPrimary`, `ChannelFeedPollService`, `ContentFetchPoolService`, `hydrated_at` migration |
| **Stub restore backfill** | On restore: `RunChannelStubContentBackfillJob` batch-hydrates stubs via API | `ChannelStubContentBackfillService`, `ChannelStubContentBackfillServiceTest` |
| **Archived WebSub legacy repair** | One-shot `vtual:repair-archived-websub` for pre-policy archived channels missing active/pending subscription | `RepairArchivedWebsubCommand`, `YoutubeWebSubSubscriptionService::subscribeMissingArchivedChannels` |
| **Claim verify pool** | Queued creator-claim bio verify; flush at **50** pending **or** **15 min** oldest age; max **3** attempts then **24h** block per user+creator | `CreatorClaimVerifyPoolService`, `vtual:flush-claim-verify-pool`, `CreatorClaimVerifyPoolServiceTest` |
| **Channel fetch pool** | Non-interactive refresh + scheduled resync; flush at **50** ids **or** **15 min** age; `--resync` also enqueues when `last_synced_at` stale **or** `subscriber_count IS NULL`; single lock/claim flusher | `ChannelFetchPoolService`, `vtual:flush-channel-pool`, schedule in `routes/console.php`, `ChannelFetchPoolServiceTest` |
| **Content fetch pool** | Video id hydrate batch after feed/WebSub discovery; `vtual:flush-content-pool` every 1m | `ContentFetchPoolService`, `ChannelContentFetchService`, `ContentIngestionPoolTest` |
| **Channel registration pool** | Staff pending channel adds; same 50 / 15m gate per platform; flush delegates activate to `ChannelActivationService` | `ChannelRegistrationFlushService`, `ChannelActivationService`, `ChannelRegistrationFlushServiceTest` |
| **Content model foundation** | Primary host + appearances; explicit attach; no auto collab from API; **`hydrated_at`** null = stub, full hydrate sets timestamp | `ContentService`, appearances migration, README content section |
| **Error logging** | Platform HTTP failures can log to ErrorLog | platform client / gateway error path + docs |
| **i18n** | Seven locales (`en`, `id`, `ja`, `ko`, `zh_CN`, `ar`, `ru`) for API strings; UI strings live in the SPA JSON locales (`zh-CN` in browser) | `lang/*/api/vtual.php`, `monolith-vue-workers/src/i18n/locales/*.json` |
| **Web UI/UX overhaul (removed)** | Full-suite Blade polish from 2026-07 (AJAX forms, modal confirmations, WCAG 2.1 AA, Vite JS). Deleted with the in-repo frontend; the SPA carries the equivalent screens | [ui-archive.md](ui-archive.md) |

### Tests (unit / feature present)

Representative suite under `tests/Domain/Vtual/`:

| Test | Focus |
|------|--------|
| `ChannelFetchPoolServiceTest` | Threshold / age / force / coalesce / single HTTP |
| `ChannelRegistrationFlushServiceTest` | Staff pending registration: 50/platform flush, 15m age, WebSub + backfill job on activate |
| `ChannelActivationServiceTest` | YouTube hooks on pending activate; Twitch skips hooks |
| `YoutubeWebSubSubscriptionServiceTest` | `subscribeMissingActiveChannels` repair path; `attemptMarkVerified` state guards; `resolveSubscriptionForTopic` |
| `YoutubeWebSubNotificationServiceTest` | sha1/sha256 signature, bad signature + `last_error`, topic fallback |
| `YoutubeAtomFeedParserTest` / `YoutubeFeedTopicParserTest` | Shared Atom parse + topic URL `channel_id` extraction |
| `YoutubeWebSubWebhookTest` | GET verify echo, invalid mode 403, POST valid/invalid signature 204 |
| `ChannelServiceTest` | Staff pending register, seedForStaff, activate, backfill job dispatch, refresh enqueues pool, archive/restore, pending not archivable, deletePending, WebSub kept on archive, restore resub from unsubscribed, lifecycle rate limits |
| `ChannelStubContentBackfillServiceTest` | Stub hydrate on restore; skip paths |
| `VtuberProfileApiTest` | Public profile API; archived channel hides contents; restore + backfill shows contents |
| `VtuberProfileServiceTest` | `show` excludes archived-channel content; all-archived → 0 contents |
| `ChannelInitialContentBackfillServiceTest` | One-time backfill, watermark, skip/fail paths |
| `ChannelFeedPollServiceTest` | Watermark enqueue, initial backfill guard; archived channel stub upsert (no pool enqueue); archived included in due poll |
| `ChannelClaimServiceTest` | Bio verify attach |
| `CreatorClaimServiceTest` | Orphan claim, queued verify + pool flush, supersede, Accept Anyway, max attempts block, progress event on queue |
| `CreatorClaimVerifyPoolServiceTest` | Enqueue coalesce, skip below threshold, force flush, progress events |
| `VtualClaimRealtimeTest` | Event dispatch when broadcast configured; no-op when null driver |
| `CreatorClaimProgressChannelTest` | Private channel auth: claimant allowed, other user denied |
| `CreatorMergeServiceTest` | Absorb, clash, staff orphans |
| `PlatformApiGatewayTest` / client tests | Batch + ops |
| `PlatformCredentialServiceTest` | Test gate, tokens, failover knobs |
| `VtualApiTest` / `PlatformApiProbeApiTest` | HTTP surface smoke; staff-as-owner claim vs orphan pending; delete pending; archive pending 422; admin probe gate |
| `CreatorAccessServiceTest` | `memberRoleFor` vs staff `roleFor` override |
| `VtuberProfileWebTest` | Profile layout, canonical, UUID 301, claim button, interactive claim page |
| `PlatformCredentialWebTest` | Active credentials CRUD web |
| `ContentIngestionPoolTest` | Feed poll + content pool + live CCV integration; content pool flush upserts stubs for archived channel |
| `DiscordConnectionDiscoveryServiceTest` | Private bind, promote, external links |
| `DiscordConnectionsClientTest` | Snapshot normalize, ownershipEligible, externalLinkCandidates |
| `CreatorProfileManageWebTest` | Manage profile; Discord verify; promote discovered channel |
| `VtualBladeWebTest` | **Archived** — `tests/_archive/Feature/Http/Controllers/Web/` |

---

## Not done / deferred

Mapped to original TASK / product vision. **Do not treat as bugs** unless a milestone is scheduled.

| Theme | Status | Notes |
|-------|--------|--------|
| **Claim Add New** | Not implemented | Survey + staff approval + appeal / permanent reject |
| **Public homepage** | Implemented | SPA `/vtual` (Valkivid3): hero, sponsors, countdown, live directory, all-creator `#contents` feed (`GET /api/v1/vtual/public/contents`), about, events; en/id/ja localized. Header switcher owns `html.dark` (class `dark:` variant). Tailwind utilities nested under `.vk3-root` so AdminLTE is not restyled after visiting `/vtual`. OG/SEO runtime meta via `useHeadMeta` (SPA-side, client-only DOM; no Worker function). |
| **Public read surfaces** | Implemented | Guest profile `/vtual/vtubers/{slug}` (`GET /api/v1/vtual/public/vtubers/{slug}`): banner, two-column sidebar (avatar, live badge, bio, follower/channel stats, channels, links, claim CTA gated to authenticated viewers) + featured live card and **paginated** video grid (`contents: { items, pagination }`, Prev/Next pager; `?page` + `?per_page` default 12 max 24; live-first ordering; `is_live` from a whole-catalogue check, not the current page). OG/SEO runtime meta via `useHeadMeta`. Authenticated AdminLTE read/claim stays `/apps/vtual/vtuber/{slug}`. Guest management profile `/vtual/managements/{slug}` (`GET /api/v1/vtual/public/managements/{slug}`): logo header, description, links, public roster grid; `useHeadMeta` (type `profile`). |
| **Twitch EventSub** | Implemented | Webhook push for Twitch channels: `POST /api/v1/vtual/webhooks/twitch-eventsub` (`vtual.webhooks.twitch-eventsub`, exempt from `RequireSpaRequestHeader`, `throttle:120,1`). New table `vtual_eventsub_subscriptions` (one row per channel × event type `channel.update` v2 / `stream.online` v1 / `stream.offline` v1; unique `(channel_id, event_type)` + `twitch_subscription_id`; status enum `TwitchEventSubStatus`) + `EventsubSubscription` model. `TwitchEventSubSubscriptionService` (subscribes via Helix `POST /eventsub/subscriptions` with the active Twitch `OauthApp` app token; missing credential → graceful skip; `renewDueSubscriptions`, `subscribeMissingActiveChannels`, `unsubscribe` deletes at Twitch) + `TwitchEventSubNotificationService` (`webhook_callback_verification` challenge; `sha256=` HMAC over `message_id + message_timestamp + body` with per-subscription secret; `message_id` dedupe 6h; `revocation` → `unsubscribed`; all notifications → `ChannelFetchPoolService::enqueue(Twitch)` → snapshot refresh + `ChannelSnapshotUpdated` realtime). Activation hooks (`ChannelActivationService`) subscribe Twitch channels on activate. Cron: `vtual:renew-eventsub-subscriptions` daily (`--repair-missing`), `vtual:eventsub-status` report. Config `vtual.eventsub`. Tests: `TwitchEventSubWebhookTest` (12), `TwitchEventSubSubscriptionServiceTest` (5). |
| **Twitch live marking** | Implemented | EventSub notifications now drive live content rows: `stream.online` → sync Helix `GET /streams` fetch → `TwitchLiveStateService::markOnline` → `ContentService::upsertFromPrimary` live row (`PlatformVideoDtoMapper::toUpsertPayloadFromStream`; Twitch stream session id = content `external_id`, repeated notifications refresh CCV/title in place); `stream.offline` → `markOffline` → `ContentService::endLiveStreamsForChannel` (idempotent, `Archive/Ended` + `ended_at`); `channel.update` touches no live rows. `is_live` on public profiles derives from these rows; `channel.snapshot.updated` realtime on live-state change; no credential / fetch error → graceful skip. Live-contents poll gains a Twitch branch (batch `fetchStreamsByUserIds` ≤100 user ids/call; present stream refreshes, missing stream ends — backstop for missed webhooks). Tests: webhook live marking (5), `ContentServiceTest::end_live_streams_for_channel` idempotency, `ContentIngestionPoolTest::live_poll_twitch_branch_refreshes_live_and_ends_missing`, mapper `maps_stream_to_live_payload`. |
| **Multi-stream session correlation** | Partial | `fetchStreamsByUserIds` consumed by EventSub live marking + live poll; stream session id is the content external_id; session-level correlation/aggregation still pending |
| **WebSub push E2E validation** | Tooling shipped | `vtual:websub-status` reports `active_never_notified`; publish on staging + re-run to close the loop |
| **Private discovered channels** | By design | Excluded from feed/WebSub until `POST channels/{id}/promote` |
| **`verified_channel_id` API exposure** | Implemented | `CreatorClaimResource` exposes `verified_channel_id` + nested `verified_channel` (platform eager-loaded) on list/show/realtime; claim wizard renders the Discord contract channel |
| **`promoteDiscoveredChannel` dedicated test** | Gap | Behavior covered indirectly; no isolated feature test |
| **Management product UI** | Implemented | SPA org CRUD + **org member management** (`/apps/vtual/managements*`): onboarding is **invite-only by email** (no user-listing endpoint — privacy); invites notify via mail + in-app bell → accept/decline page; cancel pending / revoke accepted (removes membership); role-change/remove gated by perm `manage vtual management members` + org Owner/Admin role; guards: assignable roles admin/staff, no self-edit, last-owner protection. vTuber linking is **invite-only in the SPA** (vTuber profile invites); the "Linked creators" table is read-only and backend `POST/DELETE /managements/{id}/creators` has no SPA caller. Public management profile `/vtual/managements/{slug}` ships (guest, roster of publicly listed creators) |
| **Viewer-only until claim** | Implemented | `CreatorPolicy::create` is staff-gated (`manage any vtual resource`); non-staff `POST /creators` → 403 `creator.self_create_disabled` (legacy `create vtual creator` permission retained on roles but insufficient). Non-staff path = orphan claim. Staff keeps `creators.store` (owner-bound) + `creators/orphan` |
| **Kick** | Catalog only | `enabled = false` until official API strategy |
| **Paid Service SKUs for vTual** | Not implemented | Product activations gate product; no vTual catalog SKUs |
| **OAuth login as ownership proof** | Partial | Discord **connected accounts** implemented for orphan claim + channel attach (`verify-discord`, discovery bind); bio token + Accept Anyway remain; not Discord login-as-auth |
| **Undo merge / field-pick merge** | Not implemented | Absorb is one-way; destination meta kept |
| **E2E / full TASK test matrix** | Not implemented | Unit + API feature tests only |
| **Realtime “pool flush done” push** | Implemented | `ChannelSnapshotUpdated` (`.channel.snapshot.updated` on `private-vtual.creator.{id}`, one aggregated event per creator per flush) + manage-page auto-refetch toast and manual **Refresh** button on the channels card |
| **Public events entity** | Implemented | `vtual_events` table + `VtualEvent` model (`VtualEventStatus` `scheduled|live|ended|cancelled`, `is_featured`, optional `creator_id` host credit). **Management submit**: `POST/GET /managements/{id}/events` + `DELETE /managements/{id}/events/{id}` (`ManagementEventService` — create/cancel gated by `assertCanAssignCreators` org Owner/Admin + `manage vtual management members` perm; publish direct, no moderation; cancel only while scheduled/live; `ManagementUpdated` `kind=event` realtime). **Cross-management list** `GET /vtual/events` for the SPA page (`ManagementEventController::index` — staff all, members scoped to active orgs; row actions via `VtualEventQueryRowActions`). **Public read**: `GET /vtual/public/events` + landing `events[]` (`VtuberProfileService::upcomingEvents`, scheduled/live + future, cap 6); landing cache flush includes `VtualEvent`. **SPA**: `/apps/vtual/events` (`vtual.events.index/create/edit` — `VtualEventsView` + `VtualEventEditView` + `useVtualEvents`); `VkStr1SectionEvents` renders `landing.events` events-first with fallback to the derived scheduled-streams view. **Menu**: "Events" node under Products → vTual, rule-7 member-only (`roles: []` + `vtual.events.index` in `MANAGEMENT_MEMBER_ROUTES`); not in `products.php` `menu_routes` (membership-gated). i18n `api/vtual` + `web.vtual-events` in 7 locales. Tests: `ManagementEventsApiTest` (11), `VtualPublicApiTest` events + landing flush, menu visibility. |

---

## Next recommended order (opinionated)

Not a commitment — default backlog for the next sessions:

1. **Claim Add New** — form + staff queue + reject/appeal (closes chicken-egg for unknown profiles).
2. **Restore archived user web** — standalone claims hub (or rebuild on orphans pattern); API already complete.
3. **Public surface follow-ups** — Twitch player/chat on the public profile. Guest landing (directory + content feed) + two-column public profile + guest management profile + OG/SEO runtime meta + **events entity** already ship.
4. ~~**Twitch EventSub**~~ — **shipped** (webhook push + live marking via `stream.online/offline` + Twitch branch in `vtual:poll-live-contents`; Twitch channels now show `is_live` on public profiles). Small gaps from prior #4 all shipped (verified_channel_id exposure, realtime pool-flush push + manage-page refresh, staff-gated `POST creators`).

---

## Ops cheatsheet

| Action | Command / URL |
|--------|----------------|
| Migrate | `php artisan migrate` (includes `vtual_channel_fetch_pool`, claims, appearances, …) |
| Roles / platforms | `php artisan db:seed --class=VtualRoleSeeder` |
| Menus | Seed `RoleSeeder`, `ArchivdRoleSeeder`, and `VtualRoleSeeder`, then `php artisan db:seed --class=MenuSeeder` (authoritative rebuild + post-commit cache flush) |
| Flush claim verify pool | `php artisan vtual:flush-claim-verify-pool` (`--force`) |
| Flush channel pool (on demand) | `php artisan vtual:flush-channel-pool` |
| Poll channel feeds | `php artisan vtual:poll-channel-feeds` (`--channel=`, `--force`) |
| Flush content pool | `php artisan vtual:flush-content-pool` (`--force`) |
| Poll live CCV | `php artisan vtual:poll-live-contents` |
| Renew / repair WebSub | `php artisan vtual:renew-websub-subscriptions` (`--repair-missing` for channels without a row) |
| WebSub status report | `php artisan vtual:websub-status` (`--channel=`) — per-channel state + `active_never_notified` count for staging push validation |
| Repair archived WebSub | `php artisan vtual:repair-archived-websub` — re-subscribe archived channels missing active/pending subscription (legacy pre-policy rows); also scheduled weekly |
| Prune fetch pools | `php artisan vtual:prune-fetch-pools` (`--days=`, default config `pool_retention.days` = 7) — delete old done/failed rows; scheduled daily |
| Manual content recovery | `php artisan vtual:fetch-channel-content {channel}` (`--video`, `--discover-live`) |
| Force flush (ignore 50/15m gate) | `php artisan vtual:flush-channel-pool --force` |
| Enqueue stale actives then flush | `php artisan vtual:flush-channel-pool --resync` / `--resync --force` |
| Platform filter | `--platform=youtube` or `twitch` |
| Schedule | `routes/console.php`: every minute `vtual:flush-channel-pool` + `vtual:flush-claim-verify-pool`; hourly channel `--resync` (needs `schedule:work` / cron) |
| Admin probe | `/apps/vtual/platform-credentials/probe` (Root/Admin) |
| Credentials UI | `/apps/vtual/platform-credentials` |
| Public landing (SPA) | `/vtual` (guest, Valkivid3) |
| Public vTuber profile (SPA) | `/vtual/vtubers/{slug}` (guest; claim CTA → auth wizard) |
| vTuber profile (read) | `/apps/vtual/vtuber/{slug}` (any logged-in user) |
| Claim process (interactive) | `/apps/vtual/vtuber/{slug}/claim` (orphan / `is_claimable`) |
| vTual product hub (placeholder) | `/apps/vtual/vtuber` |
| Orphan admin / staff seed | `/apps/vtual/orphans` |
| Pending claims (API) | `GET /api/v1/vtual/claims/pending` (registry web archived) |
| My profiles (API) | `/api/v1/vtual/creators` etc. (web `/apps/vtual/profiles` archived) |
| Build Vite assets (after JS/CSS change) | `npm run build` |
| **YouTube ops stability assessment** | [archive/vtual-youtube-stability-assessment-2026-08-07.md](../../archive/vtual-youtube-stability-assessment-2026-08-07.md) — live probe rubric (credentials, gateway, DB, quota, scheduler); re-run after major ingestion changes |

### Config knobs (`config/vtual.php`)

| Key | Role |
|-----|------|
| `http.batch.channels` | Max ids per `fetchChannels` (50) |
| `channel_pool.threshold` | Size flush trigger (50) |
| `channel_pool.max_wait_minutes` | Age flush (15) |
| `channel_pool.resync_stale_hours` | Stale active enqueue (24) |
| `channel_pool.resync_enqueue_limit` | Cap per resync tick (200) |
| `claim.*` / `channel_claim.*` | Token TTL, cooldown, max attempts (3), 24h block, Accept Anyway |
| `claim_verify_pool.*` | Claim bio verify batch gate (50 / 15m) |
| `channel_lifecycle.cooldown_seconds` | Per-channel archive/restore cooldown (default 30) |
| `channel_lifecycle.max_per_minute` | Per-user archive/restore burst (default 10/min) |
| `pool_retention.days` | Prune window for done/failed pool rows (default 7) |
| `credential.failover.*` | Soft demote on 401/403 (default OFF) |

---

## Session milestones (recent)

High-level order of work that landed in this long implementation arc (not exhaustive):

1. Domain foundation, credentials, Test gate  
2. Platform HTTP clients + probe parity rule  
3. Channel profile normalize + snapshot columns  
4. Creator claim Existing + staff orphan + UI split  
5. Content primary host + appearances (explicit)  
6. My profiles + external links + channel claim dual path  
7. Profile merge absorb  
8. Channel archive restore  
9. Channel fetch pool (50 / 15m) + artisan/schedule  
10. This status doc + README gap close  
11. Web UI/UX overhaul — AJAX forms, Bootstrap modal confirmations, WCAG 2.1 AA, partial extraction, dead code removal, respondFromApi refactor  
12. Internal vs external surface split — separate routes (`vtual.php` / `vtual-admin.php`), nav partials (`nav-app` / `nav-admin`), Administration menu branch (Root/Admin/Moderator)  
13. Admin UI polish — registry stats + DataTable pending queue; credentials intro/stats/platform cards; per-platform credentials CRUD split (create/edit pages, shared `platform-credentials/partials/form`, client-side DataTable index)  
14. **Web UI reset** — archive all legacy Blade (external + internal); new `/apps/vtual/orphans` staff CRUD (users-pattern DataTable + create/edit); API `GET creators/orphans`  
15. **vTuber read profile** — `vtual-vtuber.php`, AdminLTE col-4/8 (sidebar + content grid)  
16. **YouTube `subscriber_count`** — migration + API `subscriber_count_formatted` + channel ID cards on profile  
17. **Channel resync backfill** — `--resync` enqueues when `subscriber_count IS NULL`  
18. **Uniform content card grid** — 3-slot flex cards (`vtuber-content-card__*`) + `monolith-overrides.css`  
19. **YouTube WebSub hardening** — strict verify, dual signature, shared Atom parser, body cap  
20. **Management naming guide** — [naming-conventions.md](naming-conventions.md) (core `Management` vs `vtual_managements`)  
21. **vTual product hub placeholder** — `/apps/vtual/vtuber` blank AdminLTE shell (`vtual.vtuber.index`)  
22. **Profile claim UX (info)** — Claim button on orphan profile; `/apps/vtual/vtuber/{slug}/claim` informational page; API `is_claimable` + `claim_process_url`  
23. **Interactive claim + verify pool** — Web start/verify/cancel; `claim_verify_pool` strict 50/15m; max 3 attempts + 24h block; `claim_blocked_until` on profile API  
24. **Claim page WebSocket realtime** — `CreatorClaimProgressChanged` / `claim.progress` on `private-vtual.claim.{id}`; `claim-realtime.js` (no polling); degraded AJAX + manual reload when broadcast off; `vtual.claim.max_attempts` = 3 in config + Settings  
25. **Claim verify hardening** — bio proof-channel-only; Discord contract `verified_channel_id`  
26. **Discord connection discovery** — private YT/Twitch bind, external links, promote → public + backfill  
27. **Channel visibility / discovery columns** — migration `2026_08_07_200001`  
28. **YouTube ops stability assessment** — [archive/vtual-youtube-stability-assessment-2026-08-07.md](../../archive/vtual-youtube-stability-assessment-2026-08-07.md)  
29. **Archived channel content policy** — `hydrated_at`, stub ingest while archived, public profile hide via `listForPublicProfile`  
30. **Stub restore backfill job** + `vtual:repair-archived-websub` for legacy archived WebSub rows  
31. **WebSub resub on restore** + channel lifecycle rate limits (`channel_lifecycle` config, 429 on archive/restore)  
32. **Test coverage** — profile API/service archived content; feed poll archived stub; content pool stub flush  
33. **Membership-first channel POST** — owner/editor member → claim; else staff pending; pending **DELETE** (not archive); owned-profile manage is full-width stacked cards (separate Profile vs Channels) + orphan-style table  
34. **Ingestion hardening (Tier 1)** — deadlock retry (3 attempts on hot-path transactions + single tx per feed poll), `vtual:prune-fetch-pools` retention (daily), deleted-video tombstones (`ContentStatus::Unavailable` + `first_unavailable_at` + Atom `at:deleted-entry`), WebSub hex signature fix + `vtual:websub-status` + weekly `vtual:repair-archived-websub` schedule
35. **Public surface polish (Tier 2)** — claim CTA on guest profile gated to authenticated viewers (`VkStr1ProfileSidebar`); OG/SEO runtime meta via SPA `useHeadMeta` composable (client-only DOM, no Worker function) on `/vtual` landing + `/vtual/vtubers/{slug}` profile; `web.vtual.public.meta.*` i18n keys across 7 locales
36. **Tier 3 gaps + management public profile** — `verified_channel_id` + nested `verified_channel` on `CreatorClaimResource` (list/show/realtime, platform eager-loaded) + claim wizard block; guest management profile `GET /api/v1/vtual/public/managements/{slug}` (`showForPublic` active-only, roster = publicly listed creators) + SPA `/vtual/managements/{slug}` (logo header, links, roster grid, `useHeadMeta`); realtime `ChannelSnapshotUpdated` (`.channel.snapshot.updated`, one event per creator per flush) + manage-page auto-refetch + Refresh button
37. **Viewer-only until claim (Tier 4)** — `CreatorPolicy::create` staff-gated (`manage any vtual resource`); non-staff `POST /creators` → 403 `creator.self_create_disabled` (7 locales); legacy `create vtual creator` permission kept on roles but no longer sufficient; tests updated (`VtualApiTest` — 403 for User/vTuber/Editor, 201 for staff)
38. **Management member RBAC (phase 1)** — `manage vtual management members` permission + member endpoints (perm + org Owner/Admin via `ManagementAccessService::canManageMembers`); guards: assignable roles `admin|staff` (owner only via org create), no self edit/remove, last-owner protection (422 `last_owner`); `ManagementMembersApiTest`. **Amended 2026-09-04 — member onboarding is invite-only** (privacy: Select2 user lookup dropped, no user-listing endpoint): `vtual_management_invites` (48-char token, 7-day expiry, statuses `pending/accepted/declined/cancelled/revoked/expired`); `POST/GET managements/{id}/invites`, `DELETE managements/{id}/invites/{id}` (pending → cancelled; accepted → revoked + membership removed transactionally); respond endpoints `GET/POST vtual/invites/respond/{token}[/accept|/decline]` gated by case-insensitive email match (wrong account 403); registered-email-only MVP (404 `invite_user_not_found`); notifications: `ManagementMemberInvited` mail (SPA `/apps/vtual/invites/respond?token=`) + `ActivityNotification` type `vtual_management_invite_received` (`action_route=vtual.invites.respond`, token passed as query by SPA `notificationTarget.ts`); SPA edit view swaps Select2 for email input + Invitations section (cancel/revoke), new `VtualInviteRespondView`; `ManagementInvitesApiTest` 13 cases. Delegated access (management member → assigned creator rights) stays phase 2 (simple capability matrix per assignment)
39. **Management realtime live update** — `ManagementUpdated` (`ShouldBroadcastNow`) on `private-vtual.management.{managementId}` (`.management.updated`, ids-only payload `{management_id, kind, ref_id, action}`) fired by `VtualManagementRealtime` from all 10 `ManagementService` mutations (create/update/assign/unassign/member role/remove/invite/cancel/accept/decline); channel auth via `ManagementAccessService::canView`; SPA `useVtualRealtime.subscribeManagement` + `VtualManagementEditView` silent-refetch (`fetchOne` + `listInvites`) applying only collections (`creators`/`members`/`my_role`/`invites`) — form fields never overwritten while the actor is editing; management list view intentionally not subscribed; `VtualManagementRealtimeTest` (3 cases); `ui.actions` → `ui.labels.actions` header fix in `VtualManagementEditView`
40. **Invite cancel copy unified** — single Cancel term for pending and accepted invites (`invite_revoked` key removed, 7 locales backend + SPA); realtime action `revoked` → `cancelled`; `cancel_accepted_confirm` still warns that accepted-cancel removes membership. **Bell realtime fix** — `.env` `APP_ENV=lcoal` typo → `local` + `db:seed --class=LocalSettingsSeeder` flips `platform.notifications.delivery` to `realtime` so invitee bell updates via websocket
41. **Membership → dynamic menu refresh (targeted realtime)** — `MenuRepository` visibility rule 7: routes `vtual.profiles.index` / `vtual.orphans.index` / `vtual.managements.index` also granted to any `vtual_management_members` holder (no Spatie role needed); `MenuTreeChanged` gains optional `target_user_id` + `NavigationRealtime::userTreeChanged`; `ManagementService` accept/cancel-accepted/remove forgets the viewer's presented tree and fires the targeted broadcast; SPA `useNavigation` filters `.menu.tree.changed` by `target_user_id` and refetches sidebar/navbar only for that viewer; `VtualInviteRespondView` calls `refetchNavigation()` before redirect after accept. Tests: `ManagementMemberMenuVisibilityTest`, `ManagementMembershipNavigationTest`. **Amended 2026-09-05 — removal endpoint unified:** `DELETE /managements/{id}/members/{memberId}` removed (route, `ManagementController::removeMember`, `ManagementService::removeMember`); removing a member now always goes through `DELETE /managements/{id}/invites/{inviteId}` (accepted → revoked + membership removed transactionally), so invite status always mirrors membership; SPA members table no longer has a Remove button (`remove_button` / `member_remove_failed` / backend `member_removed` keys dropped, 7 locales both repos). **Amended 2026-09-05 — menu node relocated under vTuber:** "Managements" node moved from Administration → vTual to Products sidebar → **vTual** (`vtual.profiles.index`) as child of "My vTuber profiles"; `MANAGEMENT_MEMBER_ROUTES` now only `vtual.profiles.index` + `vtual.managements.index` (member no longer sees the Administration vTual branch); Workspace vTual parent gains Root/Admin so staff can reach the child; `MenuSeederTest` expected tree/roles updated. **Amended again 2026-09-05 — dual placement (final):** Products "Managements" child stores **no roles** (member-only via rule 7; empty roles are safe only because the route stays in `MANAGEMENT_MEMBER_ROUTES`); Workspace vTual parent reverts to Vtual roles only (plain staff no longer see the Products branch); Administration → vTual re-adds the **Managements** child (Root/Admin, between Orphan vTubers and Platform credentials) for staff-facing org management — members never see it (parent `vtual.orphans.index` not in `MANAGEMENT_MEMBER_ROUTES`, ancestor chain fails). Staff who are also org members see both. `MenuSeederTest` locks 48 rows + both `vtual.managements.index` role pivots; `ManagementMemberMenuVisibilityTest` rebuilt with Products/Administration branch assertions (member, plain user, admin without membership, admin with membership sees both, membership removal).
42. **Management list actions membership-aware + destroy endpoint** — `ManagementQueryRowActions` now membership-aware: **Edit** nav action for anyone passing `canView` (internal staff with `view any`/`manage any` AND every org member Owner/Admin/Staff); **Archive** mutate action (`DELETE vtual/managements/{id}`) only for org Owner/Admin. `ManagementAccessService::canArchive` + `assertCanArchive` check a real `vtual_management_members` row directly (bypasses `roleFor()`'s implicit internal-staff Owner) — internal staff without membership get Edit only and 403 on destroy, no superuser bypass. New endpoint: `DELETE /managements/{management}` (route gated `view vtual management`; real authz in `ManagementPolicy::delete` via `canArchive`) → `ManagementService::destroy` soft-deletes and fires `ManagementUpdated` action `deleted`; i18n `management.deleted` + `policy.archive_denied` (7 locales). SPA: `VtualManagementsView` actions column always rendered (content driven per-row by backend `row.actions`; `canManage` kept for the Add button only). Tests: `ManagementListActionsTest` (9 cases — Owner/Admin get edit+archive, Staff edit-only, internal Admin without membership edit-only, outsider no actions; destroy 200 + soft-delete for Owner, 403 for Staff/internal-Admin/outsider).
43. **Management claim (orphan → Owner handover)** — `ManagementService::createOrphan` (staff, `manage any vtual resource`) creates an org **without** an Owner member; `isClaimable` = active + no Owner member; `ManagementResource.is_claimable` exposes it. Handover = **Owner-role member invite**, allowed only while claimable and only by staff (`ManagementService::inviteMember` Owner branch; `StoreManagementInviteRequest` allows `owner`); self-invite guard skipped for the handover. `canArchive` gains a staff+claimable branch so a mis-made orphan can be archived. New endpoint `POST /managements/orphan`. SPA: staff "create without Owner" checkbox in `VtualManagementEditView` (create mode only, `manage any vtual resource` gate) → `useVtualManagements.createOrphan`; i18n `form.ownerless` + `form.ownerless_hint` (7 locales). Tests: `ManagementClaimApiTest` (orphan create/claimable flag/Owner invite rules/accept/staff archive).
44. **Authority of management over linked vTuber profiles** — `CreatorAccessService::managementRoleForCreator(creator, actor)` resolves the org role through ACTIVE `vtual_management_creators` × `vtual_management_members`; `canView` grants view for any org role, `canMutate` only org Owner/Admin. `CreatorPolicy::view/update` allow via management access without requiring Spatie creator permissions; route gates use pipe-OR: `creators.show` = `permission:view vtual creator|view vtual management`, `creators.update` = `permission:edit vtual creator|view vtual management`. `CreatorResource.my_management_role` exposes the resolved role to the SPA. Direct `roleFor()`/`vtual_creator_members` semantics untouched. Tests: `ManagementCreatorProfileAccessTest` (org Owner/Admin PATCH 200, Staff GET 200/PATCH 403, non-member 403).
45. **Invite vTuber (hybrid, mode at send)** — new table `vtual_creator_management_invites` (`mode` `existing`|`new`, reuses `ManagementInviteStatus`; migration `2026_09_05_100001`) + model `CreatorManagementInvite`; service `ManagementCreatorInviteService` (`candidates`, `inviteCreator`, `listInvites`, `cancelInvite`, `acceptInvite`, `declineInvite`, `showInviteByToken`). Mode `existing` pins a creator **owned by the invitee** (ownership re-verified at send and at accept); mode `new` mints an orphan profile + Owner `CreatorMember` + Spatie `Vtual vTuber` + implicit activation (`createNewCreatorFor`) at accept. Both end with an ACTIVE `vtual_management_creators` assignment + realtime `ManagementUpdated` `kind=creator_invite` + presented-menu refresh for the invitee. Endpoints: `GET/POST /managements/{id}/creator-invites`, `GET /managements/{id}/creator-invite-candidates?email=`, `DELETE /managements/{id}/creator-invites/{invite}`, respond `GET/POST /vtual/creator-invites/respond/{token}[/accept|/decline]` (any authenticated; case-insensitive email match). Notifications: `ManagementCreatorInvited` mail + in-app `NotificationType::VtualManagementCreatorInviteReceived` (deep link `vtual.invites.respond` with `kind=creator`); i18n `api/vtual.creator.not_found` + `management.creator_invite_*` (7 locales). SPA: `VtualManagementCreatorInvitesSection.vue` (email + mode toggle + candidates select / new-name input + invite table, realtime refetch on `creator_invite` pushes via `useVtualManagementEdit.refetchRealtime`), composable `useVtualCreatorInvites`, `VtualInviteRespondView` extended with `kind=creator` branch; i18n `web.vtual-managements.creator_invites.*` + `web.vtual-invites.creator_*` (7 locales). Tests: `ManagementCreatorInvitesApiTest` (9 cases — existing send/accept + assignment, ownership guard 422/404, duplicate pending 422, email-mismatch 403, new-accept creates orphan profile + owner + Spatie role + assignment, decline/cancel, staff 403, candidates scoping, org Owner edit linked profile 200 / Staff PATCH 403).

46. **"Managed creators" UI removed (SPA-only)** — `VtualManagementEditView` drops the assign form + unassign buttons: `VtualManagementMembersSection` loses `assignableCreators`/`assignErrors`/`assigning` props, `assignCreatorId` model and `assign`/`unassign` emits; the Linked creators table is **read-only** (no Actions column). `useVtualManagementEdit` drops `allCreators` (`GET vtual/creators` fetch), `assignableCreators`, `handleAssign`/`handleUnassign`; `useVtualManagements` drops `assignCreator`/`unassignCreator`. i18n `creators.section_help` rewritten to neutral read-only copy + `creators.creator`/`assign_button`/`unassign_button` keys dropped (7 locales). **Backend `POST/DELETE /managements/{id}/creators` endpoints stay alive** (no SPA caller) — vTuber linking happens via "vTuber profile invites" only.

47. **Twitch EventSub (webhook push)** — new table `vtual_eventsub_subscriptions` (`external_id`, `event_type` `channel.update` v2 | `stream.online` v1 | `stream.offline` v1, `twitch_subscription_id` unique nullable, per-row `secret`, status enum `TwitchEventSubStatus`; migration `2026_09_06_100001`) + `EventsubSubscription` model. `TwitchEventSubSubscriptionService`: subscribe = 3 rows per channel + Helix `POST /eventsub/subscriptions` (webhook transport, callback `route('api.v1.vtual.webhooks.twitch-eventsub')`) using the active Twitch `OauthApp` app token via `PlatformCredentialService::getAppAccessToken`; no credential → graceful skip; `resubscribe`/`unsubscribe` (Helix DELETE)/`renewDueSubscriptions` (due within `renew_before_hours`)/`subscribeMissingActiveChannels` repair. `TwitchEventSubNotificationService`: `webhook_callback_verification` → `attemptMarkVerified` + challenge echo; `notification` → `sha256=` HMAC over `message_id + message_timestamp + body` vs per-subscription secret, `message_id` dedupe (cache, 6h), row `last_notify_at` refresh, enqueue `ChannelFetchPoolService::enqueue(Twitch, broadcaster_user_id)` → existing flush applies snapshot + `ChannelSnapshotUpdated` realtime; `revocation` → row `Unsubscribed` (renewal recreates). Controller `TwitchEventSubWebhookController::handle` (single endpoint, body cap `vtual.eventsub.max_body_bytes` 1 MiB → 413, always 2xx). Route `POST api/v1/vtual/webhooks/twitch-eventsub` (exempt from `RequireSpaRequestHeader`, `throttle:120,1`). `ChannelActivationService` Twitch branch subscribes on activation (YouTube branch unchanged). Cron: `vtual:renew-eventsub-subscriptions` daily (`--repair-missing`); `vtual:eventsub-status` ops report. Config `vtual.eventsub` (`renew_before_hours`, `dedupe_ttl_hours`, `repair_limit`). Tests: `TwitchEventSubWebhookTest` (7 — challenge, signed notification + pool enqueue, bad signature, duplicate message id, revocation, 413, unknown subscription) + `TwitchEventSubSubscriptionServiceTest` (5 — 3 rows/subscribe, missing credential skip, HTTP 400 → failed rows, renewal scope, ineligible platform skip). Test infra: `ChannelFetchPoolServiceTest` setUp now pre-fakes the WebSub hub (was timing out against real pubsubhubbub pre-fake).

48. **Twitch live marking via EventSub + live poll** — EventSub notifications now drive live content rows so `is_live` works for Twitch on public profiles (derived from `vtual_contents` `Live/Live` rows — no new flag). New `TwitchLiveStateService` (`markOnline` = `ContentService::upsertFromPrimary` with `PlatformVideoDtoMapper::toUpsertPayloadFromStream` — Twitch stream session id = content `external_id`, repeated notifications refresh CCV/title in place; `markOffline` = `ContentService::endLiveStreamsForChannel`, idempotent `Archive/Ended` + `ended_at`; `hasLiveRow`). `TwitchEventSubNotificationService::applyLiveState` after signature+dedupe: `stream.online` → sync Helix `GET /streams` via `PlatformApiGateway::fetchStreamsByUserIds` → `markOnline` (or `markOffline` when the stream is already gone); `stream.offline` → `markOffline`; `channel.update` → no live change. Live-state change fires `VtualChannelRealtime::snapshotsUpdated` (`channel.snapshot.updated`); no credential / fetch error → graceful skip, pool enqueue still runs. `TwitchEventSubHandleResult` gains `live_action` (`created|updated|ended|null`). `LiveContentPollService` split per platform: YouTube branch unchanged (wrapped in try/log), new Twitch branch batches `fetchStreamsByUserIds` (≤100 user ids via `vtual.http.batch.streams_user_ids`); present stream refreshes the live row, missing stream ends it — backstop for missed `stream.offline`; per-platform failure logs + continues. Tests: `TwitchEventSubWebhookTest` +5 (online creates live row, repeat refreshes same row, offline ends rows, no-credential graceful skip with pool still enqueued, channel.update untouched); `PlatformVideoDtoMapperTest::maps_stream_to_live_payload`; `ContentServiceTest::end_live_streams_for_channel_flips_only_live_rows_and_is_idempotent`; `ContentIngestionPoolTest::live_poll_twitch_branch_refreshes_live_and_ends_missing` (2 polled / 1 updated / 1 ended).

49. **Public events entity + management submit + standalone SPA page** — new table `vtual_events` (uuid PK, `management_id`/`creator_id` FKs nullable, `slug` unique, `title`, `description`, `thumbnail_url`, `url`, `starts_at`, `ends_at`, `timezone`, `status` `VtualEventStatus` `scheduled|live|ended|cancelled`, `is_featured`, softDeletes, index `(status, starts_at)`; migration `2026_09_07_100001`) + `VtualEvent` model. **Management submit** (`ManagementEventService`): `create` (`assertCanAssignCreators` → org Owner/Admin, staff bypass; `SlugGenerator::unique`; publishes direct — no moderation flow, consistent with the domain), `listForManagement` (`assertCanView`), `listForActor` (cross-management for the SPA page: staff/`view any` sees all, members scoped to their active orgs' ids), `cancel` (only while `scheduled|live`, else 422 `event_not_actionable`; ended/cancelled immutable). Routes (`vtual.events.*` + `vtual.managements.events.*`): `GET /vtual/events` (`view vtual management`), `GET /managements/{id}/events` (`view vtual management`), `POST` + `DELETE /managements/{id}/events/{event}` (`manage vtual management members` + policy `update`). Controller `ManagementEventController` (index list attaches row actions via new `VtualEventQueryRowActions` — cancel action only for scheduled/live). Realtime `VtualManagementRealtime::changed(id, 'event', eventId, created|cancelled)`. **Public read**: `VtuberProfileService::upcomingEvents` (scheduled/live + future, soonest first, cap 6) → `PublicEventResource` (guest-safe, no UUID) → `GET /vtual/public/events` + landing payload gains additive `events[]` key; `VtualEvent` added to the `PublicLandingCache` flush model list (`VtualServiceProvider`). **SPA**: types `VtualPublicEvent`/`VtualManagementEvent`/`VtualEventPagedList`; `useVtualEvents` + `useVtualEventEdit` composables; views `VtualEventsView` (AdminLTE4 server DataTable, cancel row action) + `VtualEventEditView` (create only in practice — edit redirects readonly; management select from actor's orgs, datetime-local inputs); router trio `/apps/vtual/events[/create|/:id/edit]`; `VkStr1SectionEvents` now events-first (thumbnail-less rows with management host credit) falling back to the derived scheduled-streams view when no events exist. **Dynamic menu**: "Events" node duplicated under Products → vTual with the exact "Managements" rule-7 pattern (`roles: []` + `vtual.events.index` appended to `MenuRepository::MANAGEMENT_MEMBER_ROUTES`; NOT in `products.php` `menu_routes` — membership-gated); menu i18n en/id/ja/ko/zh_CN/ar/ru. `config/spa-routes.php` gains `vtual.events.*`. i18n: `api/vtual` `public.events_success` + `management.event_*` keys, `list_actions.confirm.cancel_event`, SPA `web.vtual-events.*` — all 7 locales; `check:i18n` pass. Tests: `ManagementEventsApiTest` (11 — owner create, staff 403, non-member 403, staff bypass, validation, cancel + ended 422, cross-management scoping, admin sees all, per-management list RBAC, landing cache flush on create), `VtualPublicApiTest` +2 (guest events list w/ ended+cancelled exclusion + no UUID, landing events key + flush rebuild), `MenuSeederTest` tree + count 49, `ManagementMemberMenuVisibilityTest` +1 (member sees Events, plain user does not).

---

## Related

- [README.md](README.md) — full domain guide  
- [archive/vtual-youtube-stability-assessment-2026-08-07.md](../../archive/vtual-youtube-stability-assessment-2026-08-07.md) — YouTube ops probe rubric  
- [archive/vtual-legacy-audit.md](../../archive/vtual-legacy-audit.md) — broadercastv2 reference  
- [product-activations/README.md](../product-activations/README.md) — product gate  
- Root [`TASK.md`](../../../TASK.md) — original discovery/implementation brief  





