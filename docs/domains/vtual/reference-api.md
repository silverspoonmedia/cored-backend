# vTual - API & SPA surfaces

> Part of the vTual deep dive. Siblings: [reference.md](reference.md) - identity, claims, credentials · [reference-ingestion.md](reference-ingestion.md) - platform clients, pools, ops · [reference-classification.md](reference-classification.md) - music layer, cover detection, merge.

## API (`/api/v1/vtual/*`)

### Public (guest-safe) — `routes/api/v1/vtual-public.php`

No auth, no `domain.access`, no `product.active` — the SPA `/vtual` landing and `/vtual/vtubers/{slug}` profile are guest-accessible. Throttled `60,1`. Slug-addressed (show also accepts UUID). Never exposes claim tokens, `claim_process_url`, or `blocked_until`. `is_claimable` is a public directory fact (orphan vs owned), not a secret.

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/vtual/public/vtubers` | Directory of active creators having ≥1 active+public channel. Query: `search` (display_name like), `per_page` (max 24), `page`. Sorted **live first**, then `display_name`. Returns `items` + `pagination`. Item fields include `bio_snippet` (`Str::limit` 140), `is_live`, `total_subscribers` / `total_subscribers_formatted`. |
| GET | `/vtual/public/contents` | All-creator content feed. Query: `page`, `per_page` (default 12, max 24). Same public listing contract as the directory (active creator with ≥1 active+public channel; primary channel active+public). Hides stub rows with empty `title`/`url`. Sorted **live first**, then `published_at` desc. Guest-safe card: `type`, `status`, `title`, `thumbnail_url`, `url`, `published_at`, `platform_key`, `creator: {slug, display_name, avatar_url}`, `channel: {display_name, avatar_url}` — **no** UUID. |
| GET | `/vtual/public/vtubers/{creator}` | Public profile: bio, `total_subscribers` / `total_subscribers_formatted`, active+public channels (includes `banner_url`), `links[]` `{kind,label,url}` (no id), `contents` as `{ items, pagination, filters }` (paginated; query `page` + `per_page` default 12, max 24; `platform` = platform key, `type` = `live\|archive\|vod`, `content_type` = `music\|non_music` (music layer; unclassified rows match neither), `sort` = `newest` (default) \| `oldest`; **unknown filter values clamp to the default, never 422**; live rows first then `published_at` desc, except `sort=oldest` which is pure `published_at` asc with no live pinning; `filters` echoes the applied `{platform,type,content_type,sort}`; each item has `status` + `platform_key` + `content_type`; `is_live` reflects the whole catalogue, not just the current page), `is_claimable` (boolean only). **404** unless the creator is active **and** has ≥1 active+public channel. |
| GET | `/vtual/public/managements/{management}` | Public management profile: `slug`, `name`, `description`, `logo_url`, `links[]` `{kind,label,url}`, `creators[]` (publicly listed creators — active + ≥1 active public channel, same shape as directory items, with `is_live` from `LiveContentScope::applyIsLiveSelect()`), `stats` `{creators, live, channels, subscribers_total, subscribers_total_formatted}` over that same roster. **404** unless the management is active. No UUID, no members. |
| GET | `/vtual/public/landing` | One-shot `/vtual` home payload: `{ event, live_now[], schedule[], events[], stats }`. `event` = Tier B `vtual.landing.event.*` settings (null when `starts_at` empty/invalid); `live_now` = up to 8 live contents; `schedule` = up to 6 future `scheduled_at` streams; `events` = up to 6 upcoming management-submitted events; `stats` = aggregate counts. Forever-cached via `PublicLandingCache` (`vtual.public.landing`), flushed on Content/Creator/Channel/**VtualEvent** writes and on `vtual.landing.*` settings upsert. |

**These five are the whole public surface.** The former `/vtual/public/stats`, `/vtual/public/live`, `/vtual/public/schedule` and `/vtual/public/events` routes were strict subsets of `landing` with zero SPA callers and were **deleted** (milestone 57) — `landing` already carries `stats`, `live_now`, `schedule` and `events`, and the service methods behind them (`publicStats()`, `liveNow()`, `upcomingSchedule()`, `upcomingEvents()`) stay because the landing payload calls them. Add a new public route only when a screen needs a payload `landing` cannot serve.

Each guest content card (feed, profile grid, landing strips) also carries the **region hint** `region_allowed` / `region_blocked` (ISO 3166-1 alpha-2 arrays, `null` = unrestricted) read from YouTube `contentDetails.regionRestriction` at hydrate time. It is a notice, never a filter: restricted content stays listed on every public surface.

Service: `VtuberProfileService::directory()` + `paginatePublicContents()` (`ContentService::paginateForPublicDirectory`) + `show()` (`resolvePubliclyListed`) + `showForPublic()` (accepts an optional clamped `{platform,type,content_type,sort}` filter array forwarded to `ContentService::paginateForPublicProfile()`, echoed back by the resource under `contents.filters`) + `liveNow()` / `upcomingSchedule()` / `upcomingEvents()` / `publicStats()` / `landingEvent()`; `ManagementService::showForPublic()` resolves active managements (slug or UUID) and loads the roster as publicly listed creators only. The guest-facing "is this creator live" predicate lives in one place — `Support\LiveContentScope` — used by the directory cards, the public profile `is_live`, the landing live strip, and the management roster, so they cannot drift. A creator counts as live when a **non-archived** channel has a live content row either as the **primary host** (`vtual_contents.creator_id`) **or as an appearance** (`vtual_content_appearances.creator_id` — the collaborator/network role). The creator-side clause cannot use `withExists('contents as is_live')`: that helper ANDs its own `creator_id = creators.id` correlation onto the subquery, so an appearance-only row could never satisfy it. `applyIsLiveSelect()` therefore owns its correlation and ORs both paths in a single `exists` subquery (no N+1; `vtual_content_appearances(creator_id, role)` is indexed for the appearance branch). Resources: `PublicVtuberResource` (directory) / `PublicContentResource` (feed, landing strips — also exposes `scheduled_at` + the region hint) / `PublicEventResource` (landing `events[]`) / `PublicVtuberProfileResource` (profile) / `PublicManagementProfileResource` (management) — all strip `id`; profile also strips `claim_process_url` / `claim_blocked_until`, and its `channels[]` carries `last_synced_at` / `_formatted` / `_human` for the freshness label. These guest GETs are fetched directly by the SPA through the Laravel API; the current SPA Worker is not a BFF and does not stamp their response headers. There is **no** guest `POST /claims`.

**Landing settings (Tier B — Settings → vTual, config `vtual.landing.*`):** `event.title`, `event.starts_at` (ISO 8601; empty hides the SPA countdown banner), `event.body`. Seeded via `SettingsSeeder`; editable in the Settings UI; read at runtime via `setting()` (never `config()`/`env()`).

### Authenticated

| Method | Path | Permission | Purpose |
|--------|------|------------|---------|
| GET | `/platforms` | view vtual creator | Platform catalog (includes disabled Kick) — **no credentials** |
| GET/POST | `/creators` | view / create + `manage any vtual resource` (staff-only) | List (scoped) / create (actor becomes owner; non-staff → 403, use orphan claim) |
| GET | `/vtubers/{key}` | auth only | Read-only profile aggregate (bio + active channels + contents); `{key}` = UUID or slug; no membership/RBAC |
| GET/PATCH/DELETE | `/creators/{id}` | view / edit / delete | Show / update / archive |
| POST | `/creators/{id}/merge` | edit creator | **Absorb** the source profile into a destination (dual ownership or staff). One-way; no undo — see [reference-classification.md — Merge](reference-classification.md) |
| GET/POST | `/creators/{id}/channels` | view channel / edit creator | List (non-archived) / register: owner/editor **member** → channel claim; else staff `manage any` → pending row |
| GET | `/creators/{id}/channel-claims` | view vtual creator | Channel claims for one creator — the SPA profile manage reads it directly (`useVtual.fetchChannelClaims`) and in the hydrate batch |
| GET | `/channels/{id}` | view channel | Show channel |
| POST | `/channels/{id}/refresh` | edit creator | Ops/API: enqueue pool refresh by `external_id` (async batch). SPA manage UI does not call this — snapshots use hourly `--resync`. |
| POST | `/channels/{id}/promote` | edit creator | Private discovered channel → `public` + channel fetch pool + initial content backfill |
| GET | `/creators/{id}/channels/archived` | view channel | List archived only |
| POST | `/channels/{id}/archive` | edit creator | Soft archive (`archived`); **422** if pending (`channel.pending_not_archivable`); public profile content hidden; stub ingest continues; **429** on lifecycle rate limit (`channel.lifecycle_cooldown` / `channel.lifecycle_rate_limited`) |
| DELETE | `/channels/{id}` | create channel + mutate | Force-delete **pending** registration only (`channel.not_pending_delete` otherwise) |
| POST | `/channels/{id}/restore` | edit creator | Restore archived → `active`; **WebSub resubscribe**; stub backfill job; same **429** lifecycle keys |
| GET/POST | `/creators/{id}/links` | view / edit creator | List / create external links |
| PATCH/DELETE | `/links/{id}` | edit creator | Update / delete external link |
| GET/POST | `/managements` | view / create | List / create management |
| GET/PATCH | `/managements/{id}` | view / edit | Show / update |
| DELETE | `/managements/{id}` | view management route gate + **org Owner/Admin membership policy** (`canArchive` — no internal-staff bypass) | Archive (soft delete); broadcasts `ManagementUpdated` action `deleted` |
| POST | `/managements/{id}/creators` | edit management | Assign creator |
| DELETE | `/managements/{id}/creators/{creatorId}` | edit management | Unassign (`left_at`). Drops every delegated grant for that creator in the same call |
| POST | `/managements/{id}/creator-grants` | manage vtual management members + owner/admin membership | Delegate a per-creator capability — body `member_id` (a `vtual_management_members` row), `creator_id`, `capability` (`view`\|`edit`). **Additive**: it widens access on top of the role and never narrows it. Rejects a self-grant and a creator not actively assigned to the org (422). Upserts on `(member_id, creator_id)`. Fires `ManagementUpdated` (`kind=creator_grant`, action `granted`) |
| DELETE | `/managements/{id}/creator-grants/{grantId}` | manage vtual management members + owner/admin membership | Revoke a delegated grant (404 when unknown). Fires `ManagementUpdated` (`kind=creator_grant`, action `revoked`) |
| GET | `/managements/{id}/members` | view management | List members (with user) |
| PATCH | `/managements/{id}/members/{memberId}` | manage vtual management members + owner/admin membership | Change member role (no self-edit, no owner target, last-owner guard) |
| GET | `/managements/{id}/invites` | view management | List invites (token never exposed) |
| POST | `/managements/{id}/invites` | manage vtual management members + owner/admin membership | Send email invite (registered users only; role `admin`/`staff`) |
| DELETE | `/managements/{id}/invites/{inviteId}` | manage vtual management members + owner/admin membership | Cancel invite — pending → cancelled; accepted → revoked (removes membership). **Unified removal path**: member removal happens only here; no separate `DELETE /members/{memberId}` endpoint. UI uses a single Cancel term |
| GET | `/vtual/invites/respond/{token}` | any authenticated (email match) | Prefill invite (org, role, expiry) |
| POST | `/vtual/invites/respond/{token}/accept` | any authenticated (email match) | Accept → creates membership (idempotent) |
| POST | `/vtual/invites/respond/{token}/decline` | any authenticated (email match) | Decline invite |
| POST | `/managements/orphan` | manage any vtual resource | Staff create owner-less (claimable) management — no Owner member |
| GET | `/managements/{id}/creator-invites` | view management (+org role) | List vTuber profile invites (token never exposed) |
| GET | `/managements/{id}/creator-invite-candidates` | view management + Owner/Admin/staff | vTuber profiles owned by the account behind `?email=` (mode `existing` picker) |
| POST | `/managements/{id}/creator-invites` | manage vtual management members + owner/admin membership | Send vTuber profile invite — `mode=existing` (`creator_id` owned by invitee) or `mode=new` (`new_display_name`) |
| DELETE | `/managements/{id}/creator-invites/{inviteId}` | manage vtual management members + owner/admin membership | Cancel pending vTuber invite |
| GET | `/vtual/creator-invites/respond/{token}` | any authenticated (email match) | Prefill vTuber invite (org, mode, target profile, expiry) |
| POST | `/vtual/creator-invites/respond/{token}/accept` | any authenticated (email match) | Accept → link existing profile or mint new one (invitee becomes Owner member; ACTIVE `vtual_management_creators` assignment) |
| POST | `/vtual/creator-invites/respond/{token}/decline` | any authenticated (email match) | Decline vTuber invite |
| GET | `/events` | view vtual management | Cross-management event list for the SPA `/apps/vtual/events` page — staff sees all; org members see events of their active orgs (`ManagementEventService::listForActor`). Paged `{items, pagination}`; row actions via `VtualEventQueryRowActions` (edit always for a viewer who can see the row, cancel for scheduled/live) |
| GET | `/events/{eventId}` | view vtual management | Single event for the SPA edit form (`ManagementEventService::findForActor` — same visibility scope as the list, so an outsider gets 404, not 403) |
| GET | `/managements/{id}/events` | view management (+org role) | Per-management event list |
| POST | `/managements/{id}/events` | manage vtual management members + Owner/Admin membership (`assertCanAssignCreators`; staff bypass) | Create event — publish direct, no moderation. Validation: `title` + `starts_at` required, `ends_at >= starts_at`, optional `description/thumbnail_url/url/timezone/creator_id/is_featured` |
| PATCH | `/managements/{id}/events/{eventId}` | manage vtual management members + Owner/Admin membership | Update event (`UpdateManagementEventRequest` — every field optional, same rules; only while `scheduled|live`, else 422 `event_not_actionable`). Fires `ManagementUpdated` (`kind=event`, action `updated`) |
| DELETE | `/managements/{id}/events/{eventId}` | manage vtual management members + Owner/Admin membership | Cancel event (`scheduled|live → cancelled`; ended/cancelled 422). Fires `ManagementUpdated` (`kind=event`) realtime |
| GET | `/admin/platforms/{id}/credentials` | view vtual platform credentials | List credentials (masked) |
| POST | `/admin/platforms/{id}/credentials` | manage vtual platform credentials | Create credential (`pending`) |
| GET/PATCH/DELETE | `/admin/credentials/{id}` | view / manage | Show / update / delete (`active` delete blocked until revoked) |
| POST | `/admin/credentials/{id}/revoke` | manage | Soft-revoke `active` → `revoked` |
| POST | `/admin/credentials/{id}/test` | manage | Manual Test gate; returns masked credential + `test.{ok,message,platform}` |
| POST | `/admin/platform-api/probe` | manage vtual platform credentials | Live gateway probe — see [Admin probe](reference-ingestion.md#admin-probe-permanent-rootadmin-debug) |
| GET | `/admin/incidents` | view vtual incident | Integration-incident triage queue — paged `{items, pagination}` (`display_mode=query`, `actions[]` via `VtualIncidentQueryRowActions`: `acknowledge` / `resolve` / `link`). Filters: `status`, `severity`, `kind`, `platform_id` |
| GET | `/admin/incidents/{incident}` | view vtual incident | Single incident with its `context` |
| PATCH | `/admin/incidents/{incident}/status` | manage vtual incident | Triage transition (`open` → `acknowledged` → `resolved`, or reopen). Fires `VtualIncidentChanged` on `private-vtual.incidents` (`ids + enums only` — title/detail are platform free text and never travel) |
| GET | `/claims/claimable-creators` | claim vtual creator | Orphan creators the actor may claim (API-only, see below) |
| GET | `/claims/mine` | claim vtual creator | Actor's pending claims — the **owner tab** of the SPA claims hub. Paged `{items, pagination}` (`per_page` default 15, max 50, `has_more`) with `actions[]` (`verify` / `cancel` / `view_profile`) |
| POST | `/claims` | claim vtual creator | Start orphan creator claim |
| GET | `/claims/{id}` | claim vtual creator | Show claim row |
| POST | `/claims/{id}/verify` | claim vtual creator | Queue bio verify (claim verify pool) |
| POST | `/claims/{id}/verify-discord` | claim vtual creator | Discord discovery verify (`DiscordConnectionDiscoveryService`) |
| POST | `/claims/{id}/cancel` | claim vtual creator | Cancel pending claim |
| POST | `/claims/{id}/accept-anyway` | claim vtual creator | **Staff Accept Anyway** — approve a claim whose bio verify did not pass; synchronous, gated by the Tier B `vtual.claim.accept_anyway_enabled` setting (422 `api/vtual.claim.accept_anyway_disabled`). Sets `verified_via=accept_anyway`. Surfaced in the SPA as the staff-only `accept_anyway` key in the claims queue `actions[]` |
| POST | `/channel-claims/{id}/verify` | edit creator | Bio verify channel attach claim |
| POST | `/channel-claims/{id}/verify-discord` | edit creator | Discord verify channel attach claim. SPA profile manage wires this plus `GET auth/discord/authorize-url` (`intent=channel_claim_verify`, `return_path=/apps/vtual/profiles/{id}`) when Discord is not connected. |
| POST | `/channel-claims/{id}/cancel` | edit creator | Cancel pending channel claim |
| POST | `/channel-claims/{id}/accept-anyway` | claim vtual creator | **Staff Accept Anyway** for a channel attach claim — same semantics as the creator-claim route; gated by `vtual.channel_claim.accept_anyway_enabled`, falling back to `vtual.claim.accept_anyway_enabled`. Called directly by the SPA profile manage (`useVtual.acceptAnywayChannelClaim`) |
| GET | `/claims/pending` | manage any vtual resource | Staff pending claims queue — the **queue tab** of the SPA claims hub. Same envelope + `actions[]` (`verify` / `cancel` / `accept_anyway` / `view_profile`); 403 without the permission. Mutations ping `private-vtual.claims` |

### API-only — no SPA consumer

These endpoints ship, are RBAC-gated, and are covered by tests, but **no SPA screen calls them**. They are deliberate surface, not dead code — the first four back flows the SPA reaches through another route, the rest are kept for staff/ops and for the next screen. Do not delete them on a `grep` alone; if you remove one, remove the policy branch and the test with it.

| Method | Path | Why it stays |
|--------|------|--------------|
| GET | `/creators` | The scoped creator list (staff sees all, a user sees owned). The SPA reads owned profiles via `/creators/owned` and orphans via `/creators/orphans`; the generic index is the API consumer's entry point. |
| POST | `/creators` | Staff-only owner-bound create (`manage any vtual resource`; non-staff 403 `creator.self_create_disabled`). The SPA uses `/creators/orphan` because it never wants to self-bind staff as owner. |
| GET | `/channels/{id}` | Single channel read. Every SPA surface gets channels nested in a creator payload, so this is the REST-correct read for an API client. |
| GET | `/channel-claims/{id}` | Single channel-claim read. The SPA polls nothing — it lists claims inside the profile manage payload and reacts to `claim.progress`. |
| GET | `/claims/claimable-creators` | Orphan directory for a claim wizard that starts from a *list* instead of a profile URL. The SPA wizard is entered from `/vtual/vtubers/{slug}`, so it needs no list; kept for the API contract and for a future "browse claimable" screen. |
| GET | `/managements/{id}/members` | Member list. The SPA edit view receives `members` inside `fetchOne`, so this is the standalone read (also the shape an external client wants). |
| GET | `/managements/{id}/events` | Per-management event list. The SPA uses the cross-management `/events` page, which is the screen the product asked for; this stays as the org-scoped read. |
| POST | `/managements/{id}/creators` | Assign an existing creator to an org. vTuber linking moved to **invite-only** in the SPA (milestone 46) — the endpoint stays because assignment is a legitimate API operation and invite accept uses the same service path. |
| DELETE | `/managements/{id}/creators/{creatorId}` | Unassign (`left_at`). Same reason as the assign above; no SPA caller since milestone 46. |

## Vue-Workers SPA consumer

**Public SPA (`/vtual`, `/vtual/vtubers/:slug`)**: guest-facing Valkivid3 `str1` under `src/valkivid3/` (route names `vtual.public` / `vtual.public.vtuber`, `meta.template: 'valkivid3'`, `public: true`). Layout `VkStr1Layout.vue` (Tailwind v4 under `.vk3-root`: no preflight, `@tailwind utilities` nested so they compile as `.vk3-root .mb-3`, class `dark:` variant on `html.dark`, lazy CSS chunk). Directory + content feed + profile via `useVtualPublic` → `vtual/public/vtubers`, `vtual/public/contents`, `vtual/public/vtubers/{slug}`; the home view also fetches `vtual/public/landing` once (`fetchLanding`) for the post-hero unified live directory (`VkStr1VtuberLiveDirectory` — directory grid + live status/stream rows from `landing.live_now`) + stats band and events schedule; the hero slider is 3 static promotional slides (`hero.one.*` hub intro, `hero.two.*` claim profile, `hero.three.*` events promo) with no live-data binding. The profile grid carries a chip filter bar (platform chips from the already-loaded `profile.channels` — no extra request — plus type, music-layer and newest/oldest rows; the music row renders only once the page has a classified row, so a chip that can never match is never offered); the SWR key is built by a single `profileCacheKey(slug, page, perPage, filters)` that includes the filter tuple and is shared with `prefetchVtualProfile` (which warms the default first-page slot), so a filtered request can never serve the unfiltered payload. The applied filters + page are **mirrored into the URL query** by `useVtualProfileQuery` (`?platform=&type=&content_type=&sort=&page=`, `router.replace` with normalization; `sort=newest` and `page=1` stay out of the URL), and the view seeds its state from `route.query` on mount — so a filtered grid is linkable and a back/forward navigation restores it. A `loadToken` guard drops a stale payload when filters switch faster than the requests resolve. Guest claim CTA is `/login?redirect=/apps/vtual/vtubers/{slug}/claim` (register uses the same `redirect` query; `safeInternalRedirect` rejects `//`, `://`, off-origin). Authed + `is_claimable` goes straight to the existing claim wizard. Public 404 stays **inside** `VkStr1Layout` — do not dump guests into `AppLayout`. Source template: `HTML/str1/index.html` (Valkivid 3.1.1). Isolation contract: SPA README "Template namespaces".

**Public SPA theme (dark/light)**: class-based (`html.dark`), `localStorage['str1-color-theme']`, default **dark** (original template behavior; OS preference intentionally ignored). `index.html` carries an inline head script that restores the class before first paint — prevents the light-mode FOUC flash; inert for `/apps/*` since valkivid3 utilities are `.vk3-root`-scoped. Composable `useVkStr1Theme` (read/apply/toggle), switcher with sun/moon sprite icons in `VkStr1Header` + `VkStr1MobileMenu`. Hero slider `VkStr1HeroSlider` is a Swiper fade carousel (3 static promo slides: `hero.one.*` hub intro, `hero.two.*` claim profile, `hero.three.*` events; no props, no live-data binding; staggered entrance animations gated by `group-[.swiper-slide-active]` + `vv-slide-played` replay, vertical bar pagination `.vv-hero-swiper-pagination`, prev/next). Live content is surfaced through the unified `VkStr1VtuberLiveDirectory` (directory cards annotated with live status + stream rows from `landing.live_now`); the standalone `VkStr1LiveNow` and `VkStr1VtuberDirectory` components were removed in milestone 56 — nothing imported them.

**Authenticated app**: staff orphan CRUD lives at SPA `/apps/vtual/orphans` (list + create + **create→edit** + archive confirm + pending channels) — Vue `DataTable` + `display_mode=query`. Query-mode `actions[]`: `edit` + `delete` (archive) when `manage any vtual resource`. Create `POST vtual/creators/orphan` lands on `/orphans/:id/edit` so staff can register YouTube/Twitch; save stays on edit; `DELETE vtual/creators/{id}` archives (confirm copy comes from the backend row action — `web/vtual-orphans.datatable.confirm_delete`, so it is not an SPA key). Staff `POST vtual/creators/{id}/channels` on an **orphan** (no owner/editor membership) is `registerPendingForStaff` (pending channel row, in-place append) — not a channel claim. The same POST on an owned profile where the actor is an owner/editor member starts a **channel claim**. Create and edit share `VtualOrphanEditView`; `AppLayout` has no `router-view` `:key`, so the view watches `route.params.id` and reloads. `useVtualHydrate.hydrateOrphanEdit` batches orphan + channels + platforms on edit (individual-loader fallback). Same hydrate helper covers profile show, platform credentials, and vTuber claim. Authenticated vTuber read/claim is canonical **plural** `/apps/vtual/vtubers/:slug` (+ `/claim`) so it does not collide with the hub `/apps/vtual/vtuber`. UUID in the path is replaced with the slug after fetch. See [vue-workers-spa.md — Domain SPA surfaces](../../integration/reference-vue-workers-spa.md#domain-spa-surfaces) and [vTual orphan staff admin](../../integration/reference-vue-workers-spa.md#domain-spa-surfaces).

## SPA admin (Blade web removed)

Legacy hub/claims/registry Blade UIs are archived under [`ui-archive.md`](ui-archive.md). Live admin is Monolith-Vue-Workers `/apps/vtual/*`. Domain API unchanged.

| Route | View |
|-------|------|
| `GET vtual/claims` (`vtual.claims.index`) | Claims hub — own pending claims + staff queue in one `VtualClaimsView` (tabs; queue tab needs `manage any vtual resource`) → `GET api/v1/vtual/claims/mine` / `claims/pending` |
| `GET vtual/profiles` (`vtual.profiles.index`) | Owned profiles list → `GET api/v1/vtual/creators/owned` |
| `GET vtual/profiles/{slug}` (`vtual.profiles.show`) | Manage profile: stacked meta card; separate channels card (orphan-style table, claims above table, Delete pending / Archive / Promote); links; merge |
| `POST vtual/profiles/{slug}/channels/{id}/promote` (`vtual.profiles.channels.promote`) | → `POST api/v1/vtual/channels/{id}/promote` (private discovered → public + fetch pool + backfill) |

**Profiles manage** is SPA (`vtual.profiles.show`). Full-width cards: stacked meta; a separate channels card (orphan-style 3-col add form + table; member add starts a **claim** with verify above the table). Leftover pending channel rows use **Delete**, bound channels use Archive. Links and merge are separate cards. Live updates go through API + Echo, not Blade `profiles-live.js`.

| `GET vtual/vtuber` (`vtual.vtuber.index`) | Product hub card (`hub_title` / `hub_subtitle`) with three links: my profiles, orphan creators, platform credentials |
| `POST api/v1/vtual/claims/{id}/verify` | Queue bio verify — called by the claim wizard (not a route of its own) |
| `POST api/v1/vtual/claims/{id}/cancel` | Cancel pending claim — called by the claim wizard (not a route of its own) |
| `GET vtual/vtuber/{slug}/claim` (`vtual.vtuber.claim`) | Interactive claim: channel pick, token, queued verify UI (orphan only; owned → redirect to show) |
| `GET vtual/managements` (`vtual.managements.index`) | Org list (member-only via rule-7; Root/Admin reach the staff branch) → `GET api/v1/vtual/managements` |
| `GET/POST/PATCH vtual/managements/*` (`vtual.managements.create` / `.edit`) | Org CRUD + member management (invite by email, role change, revoke) + vTuber invites; `vtual.invites.respond` is the accept/decline page |
| `GET vtual/events` (`vtual.events.index`) | Cross-management event list (staff sees all, members scoped to their active orgs) with edit + cancel row actions |
| `GET/POST/PATCH vtual/events/*` (`vtual.events.create` / `.edit`) | Create and update an event → `POST` / `PATCH api/v1/vtual/managements/{management}/events[/{event}]` |
| `GET vtual/orphans` (`vtual.orphans.index`) | Orphan vTuber index (Vue DataTable; claimable profiles only) |
| `GET vtual/orphans/create` (`vtual.orphans.create`) | Create orphan form |
| `POST vtual/orphans` (`vtual.orphans.store`) | → `POST api/v1/vtual/creators/orphan`; redirects to edit (not index) |
| `GET vtual/orphans/{id}/edit` (`vtual.orphans.edit`) | Edit form + deferred channel registration UI |
| `GET vtual/incidents` (`vtual.incidents.index`) | Integration-incident triage queue (Root/Admin) → `GET api/v1/vtual/admin/incidents`; status/severity/platform filters, `acknowledge` / `resolve` / `link` row actions, realtime `.incident.changed` on `private-vtual.incidents` |
| `POST vtual/orphans/{id}/channels` (`vtual.orphans.channels.store`) | → `POST api/v1/vtual/creators/{id}/channels` (staff pending queue) |
| `PATCH vtual/orphans/{id}` (`vtual.orphans.update`) | → `PATCH api/v1/vtual/creators/{id}` |
| `DELETE vtual/orphans/{id}` (`vtual.orphans.destroy`) | → `DELETE api/v1/vtual/creators/{id}` (archives) |
| `GET vtual/vtuber/{slug}` (`vtual.vtuber.show`) | Read-only profile: bio + channels + contents → `GET api/v1/vtual/vtubers/{key}`. Canonical **plural** URL `/apps/vtual/vtubers/{slug}`; the singular `/apps/vtual/vtuber/{slug}` redirects onto it (same for `/claim`). UUID in the path is replaced with the slug after fetch; a stale slug 404s (slug redirect history — future). |

**Platform credentials (Root/Admin):**

| Route | Behavior |
|-------|----------|
| `GET vtual/platform-credentials` (`vtual.platform-credentials.index`) | Platform list table + Manage action; probe link |
| `GET vtual/platform-credentials/platforms/{id}` (`vtual.platform-credentials.platform`) | Credential list (Test / Revoke / Delete by status) |
| `GET/POST …/platforms/{id}/create` / `store` | Create credential (pending until Test) |
| `GET/PATCH …/credentials/{id}/edit` / `update` | Edit credential |
| `GET/POST vtual/platform-credentials/probe` | Manual Platform API gateway smoke test |

Live UI: SPA `vtual.platform-credentials.*` (index, platform, create, edit, probe). Blade layouts and `ConsumesApi` proxies are removed.

### API — orphan staff list (new)

| Method | Path | Notes |
|--------|------|-------|
| GET | `vtual/creators/orphans` | `listClaimable()`; `display_mode=query` |
| GET | `vtual/creators/orphans/{id}` | 403 if profile is no longer claimable |

### UI/UX patterns (orphan admin)

- **SPA DataTable**: Vue `DataTable` + API `display_mode=query`.
- **Create slug**: auto `{random6}-{slugified-display-name}` when slug omitted (orphan only); create form is display-name minimal.
- **Deferred channels**: edit page registers platform profile without API call; scheduler resolves in batch (50/platform or 15 min). Form resets after add; the SPA pushes the returned pending row inline (no websocket). Duplicate profiles per creator+platform rejected via parser-based `profile_fingerprint` (`@handle` vs profile URL normalize to same key). **Platform URL guard:** profile URLs with a recognizable YouTube/Twitch host must match the platform dropdown; bare handles/ids follow the selected platform. The add-channel row is `align-items-start` so the submit button stays on the control baseline when `FormField` shows `invalid-feedback`.
- **Claimed profiles excluded**: list uses `CreatorService::listClaimable()` — active creators with no owner member.

### UI/UX patterns (platform credentials)

- **Row actions**: server-driven `actions[]` from `PlatformCredentialQueryRowActions` — `test` / `revoke` for active rows, `edit` (nav to `vtual.platform-credentials.edit`), `delete` for pending & revoked; the whole set is gated on `manage vtual platform credentials` and returns `[]` otherwise. The SPA maps the keys into `RowActions` and never invents one; confirm copy comes from `lang/*/list_actions.php`.
- **Status refresh**: after Test or Revoke, the acting tab patches the row from `data.credential` (Test) / the resource (Revoke). Other staff viewers of `/apps/vtual/platform-credentials` (index counts — `VtualPlatformCredentialsView.vue`) and `/apps/vtual/platform-credentials/{platformId}` (row list — `VtualPlatformCredentialPlatformView.vue`) silent-refetch on Echo `.platform.credential.changed`. Delete removes the acting-tab row immediately; other tabs catch up via Echo. No polling.

### UI/UX patterns (vTuber profile read)

The read profile is a guest Valkivid3 surface (`VkStr1VtuberShowView.vue` under `monolith-vue-workers/src/valkivid3/`); its layout, chip filter bar and cache-key rule are in [Vue-Workers SPA consumer](#vue-workers-spa-consumer). What survives the Blade removal as a backend invariant:

- **Content filter**: `VtuberProfileService::show()` → `ContentService::paginateForPublicProfile` — excludes rows whose **primary channel** is `archived` (read profile shows 0 contents when all channels archived; manage surfaces may still list archived channels separately). Same ordering rule as `listForPublicProfile`, paginated for the SPA.
- **Auth**: guest-readable; the claim CTA sends an anonymous visitor to `/login?redirect=/apps/vtual/vtubers/{slug}/claim`. A UUID in the path is resolved to the canonical slug by the SPA after fetch — there is no server 301.

### Removed Blade UI patterns (2026-07 overhaul, see ui-archive.md)
- **i18n**: the Blade era kept every user-facing string in a per-locale `web/vtual` PHP file (`ajax.*`, `confirm.*`, `table.*`). That file is deleted; SPA strings live in `monolith-vue-workers/src/i18n/locales/*.json`. Surviving server-side vTual PHP strings are `web/vtual-vtuber.php` (Discord claim flash), `web/vtual-orphans.php` (orphan archive confirm on query-mode `actions[]`), and `list_actions.php` (owned-profile view, platform credential chrome/confirms, management archive).

## Management audit trail

bootstrap-task-2026-08.md:228 asks for an audit trail on actions taken by management parties. The management-scoped models use Spatie `LogsActivity` with log name **`vtual`** (original brief: [archive/bootstrap-task-2026-08.md](../../archive/bootstrap-task-2026-08.md)):

| Model | Notes |
|-------|-------|
| `Management` | Org create / update / archive |
| `ManagementMember` | Membership + role changes |
| `ManagementCreator` | Creator assignment / unassignment (`left_at`) |
| `ManagementInvite` | Member invite lifecycle — `logExcept(['token'])` |
| `CreatorManagementInvite` | vTuber profile invite lifecycle — `logExcept(['token'])` |
| `VtualEvent` | Event create / update / cancel |

Options are uniform: `logFillable()` + `logOnlyDirty()` + `dontLogEmptyChanges()` + `useLogName('vtual')`, so a no-op save writes nothing and each row records only the changed attributes. The two invite models exclude `token` because it is the bearer secret of the respond endpoint — the log records that an invite was sent / cancelled / accepted, never the credential that acts on it.

**Deliberately excluded:** `Creator`, `Channel`, `Content` and the platform-credential models. Those are personal-data or ops surfaces, not the management-party trail this requirement is about; adding them would bury the org changes in ingestion noise. If a creator-level trail is ever needed, give it its own log name rather than widening `vtual`.

Read it through the Activity Log domain: `GET api/v1/activity-log` accepts `log_name=vtual` (`IndexActivityLogRequest` allow-list) — see [activity-log/README.md](../activity-log/README.md).

## Realtime

vTual pings via dedicated private channels (`routes/channels.php`), events `ShouldBroadcastNow`, payloads ids + `action` (plus `kind` on the management channel):

| Channel | Auth | Event(s) |
|---------|------|----------|
| `vtual.claim.{claimId}` | owner only | `.claim.progress` (`VtualClaimRealtime`) — full snapshot incl. the bio token, which is only safe because the channel is owner-only |
| `vtual.claims` | `manage any vtual resource` | `.claim.changed` (`CreatorClaimStaffChanged` — `claim_id` + `creator_id` + `action` + `status` **only**) |
| `vtual.creator.{creatorId}` | `CreatorAccessService::canView` | `.channel.snapshot.updated` (`VtualChannelRealtime`, after fetch-pool flush / batch profile resolve) |
| `vtual.management.{managementId}` | `ManagementAccessService::canView` | `.management.updated` (`VtualManagementRealtime` — payload carries `kind` + `action`: `management` / `member` / `invite` / `creator` for org, membership, invite and creator-assignment changes, `creator_grant` for delegation grant/revoke, and `event` for event `created` / `updated` / `cancelled`) |
| `vtual.platform-credentials` | `view vtual platform credentials` | `.platform.credential.changed` (`VtualPlatformCredentialRealtime`) |
| `vtual.incidents` | `view vtual incident` | `.incident.changed` (`VtualIncidentChanged`) — ids + enums (`incident_id`, `kind`, `severity`, `status`, `action`) **only**; the incident `title`/`detail` are assembled from platform error text and are deliberately not broadcast |

SPA: `useVtualRealtime.ts` (`subscribeVtualCreatorSnapshots` / `subscribeVtualPlatformCredentialsRealtime` / `subscribeVtualManagement` / `syncVtualManagementChannels` + `releaseVtualManagementChannels` for the multi-org event list / `subscribeVtualIncidentsRealtime` for the incident queue), `useVtualClaimRealtime.ts` (`subscribeVtualClaim`) for `.claim.progress` (consumed by the claim view), `useVtualClaims.ts` for the staff queue ping (`subscribeVtualClaimsStaffRealtime`) plus per-claim owner channels synced to the visible page. All are `createKeyedRealtimeChannel` / `createRealtimeChannel` bindings on `src/composables/echo/channelRegistry.ts` — one ref-counted channel per entity id.

**Why the staff queue gets its own channel instead of widening `vtual.claim.{claimId}`:** `VtualClaimRealtime::claimSnapshot()` sets the request's user resolver to the claim **owner** before resolving `CreatorClaimResource`, so `token` is always present in the payload. Widening that channel to staff would ship the bio token to every moderator; the owner-only auth is the only thing keeping it safe today. The staff ping therefore carries ids + action + status, and the SPA refetches — a pushed row is never merged locally.

**`kind=event` has a live consumer.** `useVtualRealtime.ts` exports `syncVtualManagementChannels` / `releaseVtualManagementChannels` for this case: the cross-management events list has no channel of its own (there is no "all managements" private channel), so `VtualEventsView` binds one management channel per org visible on the page and refetches when `kind=event` arrives. `VtualEventEditView` binds the single org of the open event and refetches too — but never overwrites form fields while the actor is typing, and **locks the form read-only** when the refetched event left an editable status (`ended`/`cancelled`), because the backend would only answer such a submit with `event_not_actionable`. The `kind` union in `useVtualRealtime.ts` carries all seven backend values (`management|member|invite|creator|creator_grant|creator_invite|event`), so a push the SPA cannot name is a type error rather than a silently ignored event.

## Seeders

1. `RoleSeeder` — core permissions `view/manage vtual platform credentials` and `view/manage vtual incident` for Root/Admin.
2. `ArchivdRoleSeeder` — required because authoritative `MenuSeeder` also builds the Archivd branch.
3. `VtualRoleSeeder` — product roles/permissions + `PlatformService::seedDefaults()`.
4. `MenuSeeder` — Administration → **vTual** parent (`vtual.orphans.index`) with Orphan vTubers (Root/Admin/Moderator), Platform credentials (Root/Admin) and Incidents (Root/Admin, the same audience as credentials because most incidents are credential and subscription failures).
5. `DatabaseSeeder` preserves this order. Running `MenuSeeder` before the role seeders now **fails fast** — `idsForRoles()` throws a `RuntimeException` naming the missing roles instead of silently dropping the node's RBAC.

