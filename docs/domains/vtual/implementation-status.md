# vTual — implementation status (living)

Tracks what the **vTual product domain** has shipped in Monolith versus the original aggregator brief ([`TASK.md`](../../../TASK.md) + product decisions in session). **Update this file when a milestone ships** so agents do not re-open closed work or invent gaps that no longer exist.

| | |
|--|--|
| Domain guide (behavior detail) | [README.md](README.md) |
| Code | `app/Domain/Vtual/` |
| Config | [`config/vtual.php`](../../../config/vtual.php), product key `Vtual` in [`config/products.php`](../../../config/products.php) |
| Last reviewed | 2026-08-09 |

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
| **Staff orphan + pending claims (API)** | Orphan seed via `/apps/vtual/orphans`; pending claims via API `GET claims/pending` (legacy registry web archived) | `OrphanVtuberController`, `GET /api/v1/vtual/claims/pending`, `AdminRegistryController` in `_archive/` |
| **User claim UX (API only)** | Claimable list + my claims + verify/cancel | `/api/v1/vtual/claims/*` (web `/apps/vtual/claims` archived) |
| **Channel dual path** | Membership first: owner/editor member POST → `ChannelClaim` + bio / Accept Anyway → `activateFromDto`. Else staff `manage any` (no mutate membership) → deferred `registerPendingForStaff` (pending + batch resolve). Pending deleted (`DELETE channels/{id}`), not archived. `seedForStaff` kept for instant bind paths. | `ChannelClaimService`, `ChannelService`, `CreatorAccessService::memberRoleFor`, `ChannelRegistrationFlushService`, tests |
| **Orphan admin UX** | Blade **and** SPA `/apps/vtual/orphans`: minimal create (prefixed slug), redirect to edit, archive confirm, pending channel queue on edit; channels table shows per-channel avatar when resolved (`avatar_url`). SPA uses Vue `DataTable` (`display_mode=query`), not Yajra. | `OrphanVtuberController`, `vtual-orphans` views, `VtualOrphansView.vue`, `VtualOrphanEditView.vue`, `OrphanVtuberWebTest` |
| **YouTube content fetch POC** | Manual `vtual:fetch-channel-content {channel-uuid}`; `--limit`, `--video`, `--discover-live` options; upserts into `vtual_contents` | `ChannelContentFetchService`, `FetchChannelContentCommand` |
| **YouTube initial content backfill** | Auto on YouTube activate (async job); manual `vtual:backfill-initial-content` for existing rows; demo probe fetched 50 videos into public profile | `ChannelInitialContentBackfillService`, `RunChannelInitialContentBackfillJob`, `ChannelActivationService`, `content_initial_backfill_at` |
| **Channel activation hooks** | Single entry: WebSub + backfill job on all YouTube activate paths (including registration flush) | `ChannelActivationService` |
| **Feed poll ingestion** | Scheduled `vtual:poll-channel-feeds` + `vtual:flush-content-pool`; polls **active + archived** public channels; archived → inline **stub** upsert (no content-pool enqueue for hydrate); skips until initial backfill done | `YoutubeChannelVideoFeedClient`, `ChannelFeedPollService`, `ContentFetchPoolService` |
| **Live CCV poll** | Scheduled `vtual:poll-live-contents` | `LiveContentPollService` |
| **YouTube WebSub** | Push trigger + `vtual:renew-websub-subscriptions` (`--repair-missing`); archive **keeps** subscription; restore calls **`resubscribe()`**; ops `vtual:repair-archived-websub` for legacy rows | `YoutubeWebSubSubscriptionService`, `YoutubeWebSubNotificationService`, webhook route; subscribe on `ChannelActivationService` |
| **YouTube WebSub hardening** | Strict hub verify, dual sha1/sha256 signature, resilient topic lookup, body cap, structured logging + `last_error`, shared Atom parser | `YoutubeAtomFeedParser`, `YoutubeWebSubWebhookController`, `config/vtual.php` `websub.max_body_bytes`, registry `vtual.websub.max_body_bytes` |
| **vTuber profile (read)** | `/apps/vtual/vtuber/{slug}` — col-4/8 AdminLTE layout; Claim button when orphan (`is_claimable`); uniform content cards; public contents via **`listForPublicProfile`** (hides primary-channel-archived rows); any logged-in user; UUID → 301 slug (web) | `VtuberProfileService`, `VtuberProfileController`, `VtuberProfileResource`, `VtuberProfileWebTest` |
| **Claim process (interactive)** | `/apps/vtual/vtuber/{slug}/claim` — channel pick → token → queued verify (POST start/verify/cancel); WebSocket `claim.progress` on `private-vtual.claim.{id}` (`VtualClaimRealtime`); no polling; `claim_blocked_until` on profile API | `VtuberProfileController`, `claim.blade.php`, `claim-realtime.js`, `CreatorClaimVerifyPoolService`, `VtuberProfileWebTest`, `VtualClaimRealtimeTest`, `CreatorClaimProgressChannelTest` |
| **Owned profiles list** | `/apps/vtual/profiles` — owner membership table; `GET api/v1/vtual/creators/owned` emits query-mode `actions[]` (`edit` → `vtual.profiles.show`, `view` → public `profile_url`); Products menu (`Vtual vTuber`+); claim success assigns role + `activateImplicit(Vtual)` | `CreatorProfilesController`, `CreatorService::listOwnedForUser`, `OwnedCreatorQueryRowActions`, `MenuSeeder`, `CreatorProfilesWebTest` |
| **Claimed profile manage (web)** | `/apps/vtual/profiles/{slug}` — meta, links, merge, channel add (bio + `POST channel-claims/{id}/verify-discord`), active/archived channels | `CreatorProfilesController`, lifted Blade partials, `ChannelClaimService::verifyViaDiscord`, `CreatorProfileManageWebTest` |
| **Claim verify hardening** | Bio proof-channel-only; Discord discovery bind + `verified_channel_id`; full connections snapshot | `DiscordConnectionDiscoveryService`, `UserOAuthAccountService::connectionsForOwnershipVerify`, `CreatorClaimServiceTest` |
| **Discord connection discovery** | Orphan claim Discord: bind extra yt/twitch private (`visibility=private`, `discovery_source=discord_connection`); external links from other types; public profile hides private; promote queues hydrate + backfill | `DiscordConnectionDiscoveryService`, `ChannelService::promoteDiscoveredChannel`, `VtuberProfileService` |
| **vTual product hub (placeholder)** | `/apps/vtual/vtuber` — blank AdminLTE shell for upcoming product surfaces; any logged-in user | `VtuberProfileController::index`, `vtual/vtuber/index.blade.php`, `vtual.vtuber.index` |
| **Management naming guide** | Core role `Management` vs `vtual_managements` talent org — canonical vocabulary | [naming-conventions.md](naming-conventions.md) |
| **Platform credentials UI** | `/apps/vtual/platform-credentials`: platform index table + per-platform CRUD + probe at `/probe` | `PlatformCredentialController`, `PlatformApiProbeController`, `vtual-platform-credentials` views, `PlatformCredentialWebTest` |
| **My profiles manage (API only)** | List bound profiles; edit meta; external links CRUD; channel claims + active/archived channels | API `/creators/*`, `ExternalLinkService`; web manage restored at `/apps/vtual/profiles/{slug}` |
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
| **i18n** | en / id / ja for API strings; UI strings live in the SPA JSON locales | `lang/*/api/vtual.php`, `monolith-vue-workers/src/i18n/locales/*.json` |
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
| **Public homepage** | Implemented | SPA `/vtual` (Valkivid3): hero, sponsors, countdown, live directory, all-creator `#contents` feed (`GET /api/v1/vtual/public/contents`), about, events; en/id/ja localized. Header switcher owns `html.dark` (class `dark:` variant). Tailwind utilities nested under `.vk3-root` so AdminLTE is not restyled after visiting `/vtual`. OG/SEO still pending. |
| **Public read surfaces** | Implemented | Guest profile `/vtual/vtubers/{slug}` (`GET /api/v1/vtual/public/vtubers/{slug}`): banner, two-column sidebar (avatar, live badge, bio, follower/channel stats, channels, links, claim CTA) + featured live card and **paginated** video grid (`contents: { items, pagination }`, Prev/Next pager; `?page` + `?per_page` default 12 max 24; live-first ordering; `is_live` from a whole-catalogue check, not the current page). Authenticated AdminLTE read/claim stays `/apps/vtual/vtuber/{slug}`. Management public profiles still pending. |
| **Twitch EventSub** | Not implemented | YouTube WebSub done; Twitch push deferred |
| **Multi-stream session correlation** | Not implemented | `fetchStreamsByUserIds` exists for future workers; live CCV poll (`vtual:poll-live-contents`) is done |
| **WebSub tombstone / deleted video** | Not implemented | Mark content unavailable when `videos.list` returns 404 during content pool flush |
| **Pool `done` row retention** | Not implemented | `vtual_channel_fetch_pool` / `vtual_content_fetch_pool` processed rows grow without prune |
| **Feed poll vs content pool deadlock** | Fix backlog | MySQL 1213 observed under concurrent feed poll + content pool insert; retry works |
| **WebSub push E2E validation** | Partial | Hub verify OK; `last_notify_at` may stay null until real publish in staging |
| **Private discovered channels** | By design | Excluded from feed/WebSub until `POST channels/{id}/promote` |
| **`verified_channel_id` API exposure** | Not implemented | Stored on `vtual_creator_claims`; not in API resources or claim UI |
| **`promoteDiscoveredChannel` dedicated test** | Gap | Behavior covered indirectly; no isolated feature test |
| **Archived user web UI** | Web archived, API live | Profiles, claims, registry hub — restore or rebuild is backlog; see [ui-archive.md](ui-archive.md) |
| **Pool interactive paths** | Partial by design | Creator **profile** claim verify is **queued** (`claim_verify_pool`); Accept Anyway stays sync; staff channel POST remains **deferred** |
| **Auto appearance discovery** | Cancelled by design | Platform APIs lack safe multi-channel collab list |
| **Management product UI** | Not implemented | Schema/API skeleton only; no staff talent-org UI |
| **Viewer-only until claim** | Partial | Ideal: User cannot self-create creator until claim; API `POST creators` still exists for legacy |
| **Kick** | Catalog only | `enabled = false` until official API strategy |
| **Paid Service SKUs for vTual** | Not implemented | Product activations gate product; no vTual catalog SKUs |
| **OAuth login as ownership proof** | Partial | Discord **connected accounts** implemented for orphan claim + channel attach (`verify-discord`, discovery bind); bio token + Accept Anyway remain; not Discord login-as-auth |
| **Undo merge / field-pick merge** | Not implemented | Absorb is one-way; destination meta kept |
| **E2E / full TASK test matrix** | Not implemented | Unit + API feature tests only |
| **Realtime “pool flush done” push** | Not implemented | Snapshots land via hourly `--resync`; manage page has no refresh button. Reload to see updated avatar/name/subs. |

---

## Next recommended order (opinionated)

Not a commitment — default backlog for the next sessions:

1. **Claim Add New** — form + staff queue + reject/appeal (closes chicken-egg for unknown profiles).
2. **Restore archived user web** — standalone claims hub (or rebuild on orphans pattern); API already complete.
3. **Public surface follow-ups** — OG/SEO tags, management public profiles, Twitch player/chat on the public profile, public events data source. Guest landing (directory + content feed) + two-column public profile already ship.
4. **YouTube hardening (assessment-driven)** — feed poll deadlock fix; pool `done` retention job; WebSub tombstone / deleted-video 404; staging WebSub push validation; strip casual `POST creators` for non-staff.
5. **Twitch EventSub** — push notifications after YouTube path is stable (explicitly deferred).

---

## Ops cheatsheet

| Action | Command / URL |
|--------|----------------|
| Migrate | `php artisan migrate` (includes `vtual_channel_fetch_pool`, claims, appearances, …) |
| Roles / platforms | `php artisan db:seed --class=VtualRoleSeeder` |
| Menus | Seed `RoleSeeder`, `FileHostingRoleSeeder`, and `VtualRoleSeeder`, then `php artisan db:seed --class=MenuSeeder` (authoritative rebuild + post-commit cache flush) |
| Flush claim verify pool | `php artisan vtual:flush-claim-verify-pool` (`--force`) |
| Flush channel pool (on demand) | `php artisan vtual:flush-channel-pool` |
| Poll channel feeds | `php artisan vtual:poll-channel-feeds` (`--channel=`, `--force`) |
| Flush content pool | `php artisan vtual:flush-content-pool` (`--force`) |
| Poll live CCV | `php artisan vtual:poll-live-contents` |
| Renew / repair WebSub | `php artisan vtual:renew-websub-subscriptions` (`--repair-missing` for channels without a row) |
| Repair archived WebSub | `php artisan vtual:repair-archived-websub` — re-subscribe archived channels missing active/pending subscription (legacy pre-policy rows) |
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
| **YouTube ops stability assessment** | [ops/youtube-stability-assessment-2026-08-07.md](ops/youtube-stability-assessment-2026-08-07.md) — live probe rubric (credentials, gateway, DB, quota, scheduler); re-run after major ingestion changes |

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
28. **YouTube ops stability assessment** — [ops/youtube-stability-assessment-2026-08-07.md](ops/youtube-stability-assessment-2026-08-07.md)  
29. **Archived channel content policy** — `hydrated_at`, stub ingest while archived, public profile hide via `listForPublicProfile`  
30. **Stub restore backfill job** + `vtual:repair-archived-websub` for legacy archived WebSub rows  
31. **WebSub resub on restore** + channel lifecycle rate limits (`channel_lifecycle` config, 429 on archive/restore)  
32. **Test coverage** — profile API/service archived content; feed poll archived stub; content pool stub flush  

---

## Related

- [README.md](README.md) — full domain guide  
- [ops/youtube-stability-assessment-2026-08-07.md](ops/youtube-stability-assessment-2026-08-07.md) — YouTube ops probe rubric  
- [archive/vtual-legacy-audit.md](../../archive/vtual-legacy-audit.md) — broadercastv2 reference  
- [product-activations/README.md](../product-activations/README.md) — product gate  
- Root [`TASK.md`](../../../TASK.md) — original discovery/implementation brief  
