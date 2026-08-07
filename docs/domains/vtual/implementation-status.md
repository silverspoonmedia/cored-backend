# vTual — implementation status (living)

Tracks what the **vTual product domain** has shipped in Monolith versus the original aggregator brief ([`TASK.md`](../../../TASK.md) + product decisions in session). **Update this file when a milestone ships** so agents do not re-open closed work or invent gaps that no longer exist.

| | |
|--|--|
| Domain guide (behavior detail) | [README.md](README.md) |
| Code | `app/Domain/Vtual/` |
| Config | [`config/vtual.php`](../../../config/vtual.php), product key `Vtual` in [`config/products.php`](../../../config/products.php) |
| Last reviewed | 2026-08-05 |

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
| **Platform credentials** | Multi-key per platform, envelope secrets, Test gate, mask-only API, optional soft failover (default OFF) | `PlatformCredentialService`, probes, `PlatformCredentialServiceTest` |
| **Platform HTTP (manual)** | YouTube Data API + Twitch Helix clients; batch caps; gateway + credential bind | `PlatformApiGateway`, `YoutubeDataApiClient`, `TwitchHelixClient`, `config/vtual.php` `http.batch` |
| **Admin probe** | Web/API probe must use **production** gateway methods only | `PlatformApiProbeService`, `PlatformApiProbe*Test` |
| **Channel profile normalize** | UC… / `@handle` / URL → canonical `external_id` + handle | `ChannelProfileResolver`, `ChannelProfileResolverTest` |
| **Channel snapshot columns** | `description`, `avatar_url`, `banner_url`, `platform_created_at` on create/refresh apply | migration `…_add_profile_columns_to_vtual_channels`, `ChannelService` |
| **Creator claim Existing** | Orphan claim via bio token; Accept Anyway; race → `superseded`; audit rows kept | `CreatorClaimService`, `vtual_creator_claims`, `CreatorClaimServiceTest` |
| **Staff registry UX** | Orphan seed + pending claims; claimant on detail; platform on proof channel | `/apps/vtual/admin/registry`, `AdminRegistryController` (web) |
| **User claim UX** | Claimable list + my claims | `/apps/vtual/claims` |
| **Channel dual path** | Staff POST channel: deferred `registerPendingForStaff` (pending + batch resolve). Owner: `ChannelClaim` + bio / Accept Anyway → `activateFromDto`. `seedForStaff` kept for instant bind paths. | `ChannelClaimService`, `ChannelService`, `ChannelRegistrationFlushService`, tests |
| **Orphan admin UX** | `/apps/vtual/orphans`: minimal create (prefixed slug), redirect to edit, channel registration on edit; edit channels table shows per-channel avatar when resolved (`avatar_url`) | `OrphanVtuberController`, `vtual-orphans` views, `OrphanVtuberWebTest` |
| **YouTube content fetch POC** | Manual `vtual:fetch-channel-content` (last frontier) | `ChannelContentFetchService`, `FetchChannelContentCommand` |
| **YouTube initial content backfill** | Auto on YouTube activate (async job); manual `vtual:backfill-initial-content` for existing rows | `ChannelInitialContentBackfillService`, `RunChannelInitialContentBackfillJob`, `ChannelActivationService`, `content_initial_backfill_at` |
| **Channel activation hooks** | Single entry: WebSub + backfill job on all YouTube activate paths (including registration flush) | `ChannelActivationService` |
| **Feed poll ingestion** | Scheduled `vtual:poll-channel-feeds` + `vtual:flush-content-pool`; skips until initial backfill done | `YoutubeChannelVideoFeedClient`, `ChannelFeedPollService`, `ContentFetchPoolService` |
| **Live CCV poll** | Scheduled `vtual:poll-live-contents` | `LiveContentPollService` |
| **YouTube WebSub** | Push trigger + `vtual:renew-websub-subscriptions` (`--repair-missing`) | `YoutubeWebSubSubscriptionService`, `YoutubeWebSubNotificationService`, webhook route; subscribe on `ChannelActivationService` |
| **YouTube WebSub hardening** | Strict hub verify, dual sha1/sha256 signature, resilient topic lookup, body cap, structured logging + `last_error`, shared Atom parser | `YoutubeAtomFeedParser`, `YoutubeWebSubWebhookController`, `config/vtual.php` `websub.max_body_bytes`, registry `vtual.websub.max_body_bytes` |
| **vTuber profile (read)** | `/apps/vtual/vtuber/{slug}` — bio + active channels + indexed contents; any logged-in user (no RBAC/membership); UUID → 301 slug (web) | `VtuberProfileService`, `VtuberProfileController`, `ContentResource`, `VtuberProfileWebTest` |
| **Platform credentials UI** | `/apps/vtual/platform-credentials`: platform index table + per-platform CRUD + API probe | `PlatformCredentialController`, `PlatformApiProbeController`, `vtual-platform-credentials` views, `PlatformCredentialWebTest` |
| **My profiles manage** | List bound profiles; edit meta; external links CRUD; channel claims + active/archived channels | `/apps/vtual/profiles`, `CreatorProfileController`, `ExternalLinkService` |
| **Profile merge (absorb)** | Same-owner or manage-any; move channels/links/contents/appearances; cancel pendings `merged`; archive source + slug mangle | `CreatorMergeService`, `POST creators/{id}/merge`, `CreatorMergeServiceTest` |
| **Channel archive / restore** | Soft hide `archived`; list archived; restore → `active` | `ChannelService::archive` / `restore`, `ChannelServiceTest` |
| **Channel fetch pool** | Non-interactive refresh + scheduled resync; flush at **50** ids **or** **15 min** age; single lock/claim flusher | `ChannelFetchPoolService`, `vtual:flush-channel-pool`, schedule in `routes/console.php`, `ChannelFetchPoolServiceTest` |
| **Channel registration pool** | Staff pending channel adds; same 50 / 15m gate per platform; flush delegates activate to `ChannelActivationService` | `ChannelRegistrationFlushService`, `ChannelActivationService`, `ChannelRegistrationFlushServiceTest` |
| **Content model foundation** | Primary host + appearances; explicit attach; no auto collab from API | `ContentService`, appearances migration, README content section |
| **Error logging** | Platform HTTP failures can log to ErrorLog | platform client / gateway error path + docs |
| **i18n** | en / id / ja for API + web vtual strings | `lang/*/api/vtual.php`, `lang/*/web/vtual.php` |
| **Web UI/UX overhaul** | Full-suite Blade polish: AJAX forms (`x-adminlte4::form.ajax`), Bootstrap modal confirmations (shared `_confirm-modal`), WCAG 2.1 AA (aria-label, caption, aria-current, role=status), `_status-badge` component, `profiles/show` split to 5 partials, dead code removal (`creators/show`), inline JS extracted to Vite (`confirm-modal.js`, `credential-form.js`), 4 web controllers refactored to `ConsumesApi::respondFromApi` | `resources/views/adminlte4/vtual/**`, `resources/js/vtual/**`, `app/Domain/Vtual/Http/Controllers/Web/*`, `tests/Feature/Http/Controllers/Web/VtualBladeWebTest.php`, `tests/Feature/Http/Controllers/Web/AccessibilityBladeTest.php` (vtual assertions) |

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
| `ChannelServiceTest` | Staff pending register, seedForStaff, activate, backfill job dispatch, refresh enqueues pool, archive/restore |
| `ChannelInitialContentBackfillServiceTest` | One-time backfill, watermark, skip/fail paths |
| `ChannelFeedPollServiceTest` | Watermark enqueue, initial backfill guard |
| `ChannelClaimServiceTest` | Bio verify attach |
| `CreatorClaimServiceTest` | Orphan claim, supersede, Accept Anyway |
| `CreatorMergeServiceTest` | Absorb, clash, staff orphans |
| `PlatformApiGatewayTest` / client tests | Batch + ops |
| `PlatformCredentialServiceTest` | Test gate, tokens, failover knobs |
| `VtualApiTest` / probe web-api tests | HTTP surface smoke |
| `VtualBladeWebTest` | Web view rendering, AJAX form presence, modal confirm, a11y invariants after UI/UX overhaul |

---

## Not done / deferred

Mapped to original TASK / product vision. **Do not treat as bugs** unless a milestone is scheduled.

| Theme | Status | Notes |
|-------|--------|--------|
| **Claim Add New** | Not implemented | Survey + staff approval + appeal / permanent reject |
| **Public homepage** | Not implemented | Live / archive / VOD sections, SEO creator & management profiles |
| **Public read surfaces** | Partial | Per-creator profile at `/apps/vtual/vtuber/{slug}` (login required; slug canonical; guest SEO pending) |
| **Content ingestion workers** | Onboarding backfill + feed poll + content pool + live CCV + WebSub (scheduled); manual command = last frontier | See README ingestion table |
| **Twitch EventSub** | Not implemented | Same |
| **Live CCV / multi-stream session** | Not implemented | `fetchStreamsByUserIds` exists for future workers |
| **Video / stream fetch pool** | Not implemented | Only **channel** external_id pool exists |
| **Pool interactive paths** | Out of scope by design | Claim start, bio verify, Accept Anyway resolve stay **sync** single-call; staff channel POST is **deferred** (not sync) |
| **Auto appearance discovery** | Cancelled by design | Platform APIs lack safe multi-channel collab list |
| **Management product UI** | Not implemented | Schema/API skeleton only; no staff talent-org UI |
| **Viewer-only until claim** | Partial | Ideal: User cannot self-create creator until claim; API `POST creators` still exists for legacy |
| **Kick** | Catalog only | `enabled = false` until official API strategy |
| **Paid Service SKUs for vTual** | Not implemented | Product activations gate product; no vTual catalog SKUs |
| **OAuth login as ownership proof** | Not implemented | Bio token + Accept Anyway only |
| **Undo merge / field-pick merge** | Not implemented | Absorb is one-way; destination meta kept |
| **E2E / full TASK test matrix** | Not implemented | Unit + API feature tests only |
| **Realtime “pool flush done” push** | Not implemented | User reloads manage page after refresh queue |

---

## Next recommended order (opinionated)

Not a commitment — default backlog for the next sessions:

1. **Claim Add New** — form + staff queue + reject/appeal (closes chicken-egg for unknown profiles).
2. **Public read surfaces** — homepage live/archive sections + public creator profile (read-only).
3. **Twitch EventSub** — push notifications after poll path is stable.
4. **Hardening** — strip casual `POST creators` for non-staff; management UI; video/stream pooling if quota hurts; WebSub deleted-video / tombstone handling (mark unavailable on API 404).

---

## Ops cheatsheet

| Action | Command / URL |
|--------|----------------|
| Migrate | `php artisan migrate` (includes `vtual_channel_fetch_pool`, claims, appearances, …) |
| Roles / platforms | `php artisan db:seed --class=VtualRoleSeeder` |
| Menus | Seed `RoleSeeder`, `FileHostingRoleSeeder`, and `VtualRoleSeeder`, then `php artisan db:seed --class=MenuSeeder` (authoritative rebuild + post-commit cache flush) |
| Flush channel pool (on demand) | `php artisan vtual:flush-channel-pool` |
| Poll channel feeds | `php artisan vtual:poll-channel-feeds` (`--channel=`, `--force`) |
| Flush content pool | `php artisan vtual:flush-content-pool` (`--force`) |
| Poll live CCV | `php artisan vtual:poll-live-contents` |
| Renew / repair WebSub | `php artisan vtual:renew-websub-subscriptions` (`--repair-missing` for channels without a row) |
| Manual content recovery | `php artisan vtual:fetch-channel-content {channel}` (`--video`, `--discover-live`) |
| Force flush (ignore 50/15m gate) | `php artisan vtual:flush-channel-pool --force` |
| Enqueue stale actives then flush | `php artisan vtual:flush-channel-pool --resync` / `--resync --force` |
| Platform filter | `--platform=youtube` or `twitch` |
| Schedule | `routes/console.php`: every minute `vtual:flush-channel-pool`; hourly `--resync` (needs `schedule:work` / cron) |
| Admin probe | `/apps/vtual/admin/platform-api-probe` (Root/Admin) |
| Credentials UI | `/apps/vtual/platform-credentials` |
| Registry / pending claims | `/apps/vtual/admin/registry` |
| My profiles | `/apps/vtual/profiles` |
| Build Vite assets (after JS/CSS change) | `npm run build` |

### Config knobs (`config/vtual.php`)

| Key | Role |
|-----|------|
| `http.batch.channels` | Max ids per `fetchChannels` (50) |
| `channel_pool.threshold` | Size flush trigger (50) |
| `channel_pool.max_wait_minutes` | Age flush (15) |
| `channel_pool.resync_stale_hours` | Stale active enqueue (24) |
| `channel_pool.resync_enqueue_limit` | Cap per resync tick (200) |
| `claim.*` / `channel_claim.*` | Token TTL, cooldown, Accept Anyway |
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
13. Admin UI polish — registry stats + DataTable pending queue; credentials intro/stats/platform cards; per-platform credentials CRUD split (create/edit pages, shared `_form`, client-side DataTable index)  
14. **Web UI reset** — archive all legacy Blade (external + internal); new `/apps/vtual/orphans` staff CRUD (users-pattern DataTable + create/edit); API `GET creators/orphans`  

---

## Related

- [README.md](README.md) — full domain guide  
- [archive/vtual-legacy-audit.md](../../archive/vtual-legacy-audit.md) — broadercastv2 reference  
- [product-activations/README.md](../product-activations/README.md) — product gate  
- Root [`TASK.md`](../../../TASK.md) — original discovery/implementation brief  
