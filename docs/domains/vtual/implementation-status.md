# vTual — implementation status (living)

Tracks what the **vTual product domain** has shipped in Monolith versus the original aggregator brief ([`TASK.md`](../../../TASK.md) + product decisions in session). **Update this file when a milestone ships** so agents do not re-open closed work or invent gaps that no longer exist.

| | |
|--|--|
| Domain guide (behavior detail) | [README.md](README.md) |
| Code | `app/Domain/Vtual/` |
| Config | [`config/vtual.php`](../../../config/vtual.php), product key `Vtual` in [`config/products.php`](../../../config/products.php) |
| Last reviewed | 2026-07-23 |

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
| **Staff registry UX** | Orphan seed + pending claims; claimant on detail; platform on proof channel | `/apps/vtual/admin/registry`, `CreatorClaimController` (web) |
| **User claim UX** | Claimable list + my claims | `/apps/vtual/claims` |
| **Channel dual path** | Staff: instant `seedForStaff` active. Owner: `ChannelClaim` + bio / Accept Anyway → `activateFromDto` | `ChannelClaimService`, `ChannelService`, `ChannelClaimServiceTest`, `ChannelServiceTest` |
| **My profiles manage** | List bound profiles; edit meta; external links CRUD; channel claims + active/archived channels | `/apps/vtual/profiles`, `CreatorProfileController`, `ExternalLinkService` |
| **Profile merge (absorb)** | Same-owner or manage-any; move channels/links/contents/appearances; cancel pendings `merged`; archive source + slug mangle | `CreatorMergeService`, `POST creators/{id}/merge`, `CreatorMergeServiceTest` |
| **Channel archive / restore** | Soft hide `archived`; list archived; restore → `active` | `ChannelService::archive` / `restore`, `ChannelServiceTest` |
| **Channel fetch pool** | Non-interactive refresh + scheduled resync; flush at **50** ids **or** **15 min** age; single lock/claim flusher | `ChannelFetchPoolService`, `vtual:flush-channel-pool`, schedule in `routes/console.php`, `ChannelFetchPoolServiceTest` |
| **Content model foundation** | Primary host + appearances; explicit attach; no auto collab from API | `ContentService`, appearances migration, README content section |
| **Error logging** | Platform HTTP failures can log to ErrorLog | platform client / gateway error path + docs |
| **i18n** | en / id / ja for API + web vtual strings | `lang/*/api/vtual.php`, `lang/*/web/vtual.php` |
| **Web UI/UX overhaul** | Full-suite Blade polish: AJAX forms (`x-adminlte4::form.ajax`), Bootstrap modal confirmations (shared `_confirm-modal`), WCAG 2.1 AA (aria-label, caption, aria-current, role=status), `_status-badge` component, `profiles/show` split to 5 partials, dead code removal (`creators/show`), inline JS extracted to Vite (`confirm-modal.js`, `credential-form.js`), 4 web controllers refactored to `ConsumesApi::respondFromApi` | `resources/views/adminlte4/vtual/**`, `resources/js/vtual/**`, `app/Domain/Vtual/Http/Controllers/Web/*`, `tests/Feature/Http/Controllers/Web/VtualBladeWebTest.php`, `tests/Feature/Http/Controllers/Web/AccessibilityBladeTest.php` (vtual assertions) |

### Tests (unit / feature present)

Representative suite under `tests/Domain/Vtual/`:

| Test | Focus |
|------|--------|
| `ChannelFetchPoolServiceTest` | Threshold / age / force / coalesce / single HTTP |
| `ChannelServiceTest` | Staff seed, activate, refresh enqueues pool, archive/restore |
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
| **Content ingestion workers** | Not implemented | No poll scheduler writing `vtual_contents` from live/VOD catalogs |
| **YouTube WebSub / PubSubHubbub** | Not implemented | Manual HTTP ready; push not wired |
| **Twitch EventSub** | Not implemented | Same |
| **Live CCV / multi-stream session** | Not implemented | `fetchStreamsByUserIds` exists for future workers |
| **Video / stream fetch pool** | Not implemented | Only **channel** external_id pool exists |
| **Pool interactive paths** | Out of scope by design | Claim start, bio verify, staff seed, handle resolve stay **sync** single-call |
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

1. **Content ingestion worker** — poll or scheduled batch using `fetchVideos` / streams → `ContentService::upsertFromPrimary`; optional explicit `attachAppearance` ops path.
2. **Claim Add New** — form + staff queue + reject/appeal (closes chicken-egg for unknown profiles).
3. **Public read surfaces** — homepage live/archive sections + public creator profile (read-only).
4. **Platform push** — YouTube WebSub and/or Twitch EventSub after poll path is stable.
5. **Hardening** — strip casual `POST creators` for non-staff; management UI; video/stream pooling if quota hurts.

---

## Ops cheatsheet

| Action | Command / URL |
|--------|----------------|
| Migrate | `php artisan migrate` (includes `vtual_channel_fetch_pool`, claims, appearances, …) |
| Roles / platforms | `php artisan db:seed --class=VtualRoleSeeder` |
| Menus | Seed `RoleSeeder`, `FileHostingRoleSeeder`, and `VtualRoleSeeder`, then `php artisan db:seed --class=MenuSeeder` (authoritative rebuild + post-commit cache flush) |
| Flush channel pool (on demand) | `php artisan vtual:flush-channel-pool` |
| Force flush (ignore 50/15m gate) | `php artisan vtual:flush-channel-pool --force` |
| Enqueue stale actives then flush | `php artisan vtual:flush-channel-pool --resync` / `--resync --force` |
| Platform filter | `--platform=youtube` or `twitch` |
| Schedule | `routes/console.php`: every minute `vtual:flush-channel-pool`; hourly `--resync` (needs `schedule:work` / cron) |
| Admin probe | `/apps/vtual/admin/platform-api-probe` (Root/Admin) |
| Credentials UI | `/apps/vtual/admin/platform-credentials` |
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

---

## Related

- [README.md](README.md) — full domain guide  
- [archive/vtual-legacy-audit.md](../../archive/vtual-legacy-audit.md) — broadercastv2 reference  
- [product-activations/README.md](../product-activations/README.md) — product gate  
- Root [`TASK.md`](../../../TASK.md) — original discovery/implementation brief  
