# Archivd — product domain

> **UI:** The in-repo Blade + Vue island admin is **removed**. Archivd UI lives in **Monolith-Vue-Workers** (`ArchivdView` and related routes). This guide documents API behavior and historical Blade notes where still relevant — see [web/blade-admin.md](../../web/blade-admin.md).

> **Deep dives:** [reference-upload.md](reference-upload.md) - upload pipeline, S3/Gateway, checksums, public share · [reference-realtime.md](reference-realtime.md) - live listing channels, SPA shell, soft-nav rules.

## Agent entry

Open the rest of this README only when changing Archivd **behavior**. For a one-file fix, read the file under edit plus its test.

| Need | Path |
|------|------|
| Quota / public share | `QuotaService` (container-scoped; `storageStateFor` memo per owner per request). `ServiceEntitlementService` scoped `exists()` probes for paid/grace; `WorkspaceAccessService` slot gates reuse `storageStateFor`. Variant committed bytes use a files join (soft-deleted files excluded). |
| Workspaces list | `WorkspaceResourceCollection` primes `my_role` membership once per request |
| Upload control plane | init / sign-parts / complete / abort (SDK); data plane is Gateway or direct S3 |
| sha256 / object I/O | `Sha256Checksum`, `ObjectStorage` (`get` / `put` / `exists` / `readStream` / `deleteMany`) |
| Do not | `Read` this whole guide, all 7 locale JSON files, or `storage/logs` |

Realtime: private `archivd.user.{id}` — [vue-workers-spa.md — Channel/event reference](../../integration/reference-vue-workers-spa.md#channelevent-reference). Contract: [vue-workers-spa.md](../../integration/vue-workers-spa.md) only if the API shape moves.

Product domain (`kind => product`, `locked => true`) for personal nested folders, S3-compatible chunk upload, Drive-lite link sharing, and **Collaborative Works** (paid team workspaces).

| | |
|--|--|
| Code | `app/Domain/Archivd/` |
| Config | [`config/archivd.php`](../../../config/archivd.php) |
| API | `routes/api/v1/archivd.php` (`/api/v1/archivd/*` — unchanged) |
| SPA (auth) | Vue Router names `archivd.*` in [`config/spa-routes.php`](../../../config/spa-routes.php) → **`/apps/archivd`** (index, folder, show, trash, blocklist, workspaces, microstock credentials/usage). No server `web` route for the authenticated app |
| Web (guest) | `routes/web/archivd-public.php` → **`/s/f/{uuid}`**, **`/s/d/{uuid}`** (site root, not under `/apps`) |
| Public landing | Root **`/archivd`** reserved for future promo landing (not the logged-in app) |
| Seeder | `ArchivdRoleSeeder` (after core `RoleSeeder`) |
| Product gate | `product.active:Archivd` middleware (see [Product Activations](../product-activations/README.md)) |
| Menu visibility | Products → Archivd parent (`archivd.index`) gates My Files, Trash, and DMCA Blocklist; global Domain Access and authenticated-user activation both propagate through the parent |
| Analytics | `HostedFile` Eloquent events (`created`/`deleted`/`restored`) + `WorkspaceService::create` + `FolderRepository::restore` write to `product_analytics` via `ProductAnalyticsService` (see [Product Activations § Stored analytics](../product-activations/reference.md#stored-analytics-v3)) |

### Current product surface

What Archivd is **today** (auth browse + guest share). Deep dives live in sections below.

| Surface | Behavior |
|---------|----------|
| Auth UI | **Monolith-Vue-Workers SPA** — consumes `/api/v1/archivd/*` directly. See [vue-workers-spa.md](../../integration/vue-workers-spa.md). Historical Blade island notes below are archived reference only. |
| Browse gestures | Drive-like select / context menu / drag-move; bulk bar **Move / share / unshare / delete**. Single and bulk stay in lockstep — [Drive-like listing](reference-realtime.md#drive-like-listing-interactions-browse-only), [Action surface parity](reference-realtime.md#action-surface-parity-single--bulk). |
| Nested folders | Virtual first row/card **`...`** (parent) + breadcrumb chips as drop targets (`data-ar-drop-folder`). Root has no `...`. |
| Live listing | Dedicated private Echo channels + soft-refetch (listing/quota/trash/show). **Not** the notification bell bus. See [Realtime / live listing](reference-realtime.md#realtime--live-listing). |
| Display names | Upload complete + **file/folder move** auto-rename on sibling collision (`UniqueDisplayNameAllocator`). Manual rename still rejects exact collisions. |
| Public share | Guest `/s/f` / `/s/d` only when storage owner is **paid** (`QuotaService::ownerCanPublicShare`). Free/grace → 404 even if DB says `anyone_with_link`. See [Visibility](#visibility). |
| Storage regions | Multi-disk `s3Archivd*`. Region column/badge only when multi-region config **or** historical multi-disk usage. |
| Hard navigation | **Download hops only** (auth + signed public → 302 storage URL: CF Worker when proxy on, else S3). In-shell mutations and folder/file/trash/show soft-nav do **not** rely on `location.reload`. |
| Busy / upload UX | Mutations toggle `.is-ar-busy` spinner overlay; upload queue shows human file size. Successful (**Done**) rows auto-dismiss after ~5s; failed rows stay. |

## Concepts

| Concept | Behavior |
|---------|----------|
| Virtual root | `folder_id = null` = “My Files” (no auto DB folder row) |
| Nested folders | `parent_id` tree per owner; breadcrumb via `FolderAncestorChain` (one owner-folder query, walk in memory) |
| Files | `ARCHIVD_files` — denormalized `user_id`, optional `folder_id` |
| Upload sessions | `ARCHIVD_uploads` — init -> browser PUT (direct S3 **or** CF Worker proxy) -> complete promotes row. **Control plane** (init / sign-parts / complete / abort) always Monolith->S3 SDK; **data plane** (part bytes) only is Worker or direct. Seeing B2 host on Create/Complete is expected even when proxy is on. Complete uses row lock (`lockForUpdate`); pending non-expired sizes **reserve** owner quota; `max_concurrent_uploads` enforced server-side on pending owner sessions. Archive aborts pending team uploads. **No resume**: a dead upload restarts from scratch. Chunk/part layout frozen at init (`chunk_size`/`part_count`). **Prune scheduler** aborts orphaned **multipart** sessions past grace or with no recent S3 part activity (Backblaze has no S3 lifecycle for incomplete multiparts). |
| Part size | Default **8 MB** chunk + multipart threshold (`archivd.chunk_size_bytes` in **Settings → Archivd**). 8 MB is a precaution for ISP-shaped consumer links: a stalled 8 MB part recovers ~3× faster than a stalled 25 MB part. On the Worker proxy keep it **≤ 32 MB** — the Gateway buffers each UploadPart (cap 32 MB) so it can retry transient upstream 5xx instead of returning a raw 500. See Gateway [upload-performance.md](../../../../gateway/docs/upload-performance.md) for A/B tuning and RTT analysis. |
| DB `path` | Object id only: 40 lowercase alnum chars (`Str::random(40)`). **Not** a full S3 key. |
| S3 object key | `archivd/{user_id}/{objectId}` — resolved at runtime via `ObjectKeyResolver` from `user_id` + `path` + config `directory`. No `folder_id`, no extension, no upload session id in the key. |
| Display name | Preferred name kept if free in that owner+folder/parent (soft-deleted ignored). If taken on **upload complete** or **file/folder move** → auto DB rename to `{stem} ({maxN+1}).{ext}` (`A.zip` = 1, `A (3).zip` = 3 → next `A (4).zip`; folders without ext → `Docs (2)`). Shared allocator: `UniqueDisplayNameAllocator`. **Manual rename** still rejects exact sibling collisions (user chose the name). S3 object key stays random and is never renamed. Move/rename are **DB-only**. |
| Download bytes | Always **302 (or client follow)** to a storage URL — **not** Monolith streaming. Proxy on → CF Worker HMAC GetObject (B2 **Bandwidth Alliance** path), with **edge caching** of immutable object bytes at Cloudflare (`X-Cache` HIT/MISS/SKIP). Proxy off → direct S3 temporary URL |
| Signed download URL cache | Columns `signed_download_url`, `signed_download_expires_at`; reuse until near expiry **and** URL host matches current proxy mode (stale B2 URL reminted when proxy enabled; stale Worker URL reminted when proxy off). `expires_at` mirrors the `exp` embedded in the URL — final expiry is enforced by the Worker, the column is only the Monolith cache hint. **Cleared on visibility change** (and rename/delete) so private revoke cannot reuse a still-valid link |
| Download filename | `response-content-disposition` (Worker) / `ResponseContentDisposition` (direct S3) from DB `name` (random key stays on object) |
| Stats | `view_count` / `download_count` + last_* timestamps (no event log). `download_count` uses **atomic increment** (no lost-update under concurrent downloads); the realtime stats broadcast is **throttled** to ≤1 per file per 5s while the counter stays exact |
| Visibility | `private` \| `anyone_with_link` on folders and files |
| Link secret | Resource UUID (Drive “anyone with the link”; not world-searchable) |
| Inherit | Folder link-shared -> guest may browse that folder and download files inside even if file is `private` |
| Region | Per-file `disk` storage (Laravel disk name in DB); product region key derived via `RegionResolver::keyForDisk()` (resolves `disk_aliases` first). `disabled_regions` blocks new uploads only — downloads still served. UI region column/badge appears only when configured regions > 1 **or** historical multi-disk usage exists. |
| Ownership | Own resources; cross-user via product “any” + core `RoleService` hierarchy |
| Collaborative Works | Paid perk for **owners**; free users may **join** as members. Blade UX on Archivd page (not a separate dynamic menu). |

## Storage classification (core vs product)

Archivd (and its workspace model) is the **base storage layer** for anything
in this project that consumes disk space. Every feature that stores bytes is
classified into exactly one of two storage classes:

| Class | Effect on free-tier base | Rationale | Examples |
|-------|--------------------------|-----------|----------|
| **Core storage** | Does **not** consume the free-tier base | Belongs to a core feature; if it hits a storage limit the core feature risks breaking | User avatar (future) |
| **Product storage** | Consumes the free-tier base | Belongs to a product feature; unrelated to core operation | Archivd personal/team files, Microstock Management (upcoming) |

Free-tier base today: `config('archivd.default_quota_bytes')` (typically 5 GiB) —
see [Quota](#quota). Core storage is exempt from this budget; product storage is
metered against it.

### System workspaces (built-in, standalone slots)

Any consumer that stores bytes is treated as a **built-in system workspace** over
the Archivd workspace model. A system workspace:

- is provisioned implicitly by the owning feature (not created via the team
  workspace UI),
- is **not counted** toward the paid included team workspace slots
  (`owned_workspace_limit` / `base_owned_workspace_slots`) — it sits outside the
  [owned workspace slots](#owned-workspace-slots) accounting,
- has its **own standalone slot limit** per product (e.g. Microstock slots come
  from `microstock.plan.free` + `microstock.addon.workspace-slot.monthly`
  entitlements, entirely separate from Archivd team workspace slots),
- is the billing/quota boundary for the feature's bytes: product-class consumers
  meter into the free-tier base, core-class consumers do not.

> Status: **implemented by Microstock** — the Microstock product uses system
> workspaces (`ARCHIVD_workspaces.kind = 'microstock'`, standalone slots) as
> the first product-class consumer of this model. Its product-registry activation
> is implicit, while workspace entitlement remains opt-in via `/apps/services`
> (free entitlement + compounding slot addon); its slots never overlap with Ar
> team workspace slots. See [Microstock](../microstock/README.md).

**Provisioning trigger:** the Microstock system workspace is **not**
auto-provisioned on Archivd first paint. It is created on demand via
the SPA services card or workspace switcher (`POST /api/v1/microstock/workspaces`) when the user has a Microstock
entitlement — and is gated by
`WorkspaceAccessService::assertCanProvisionMicrostockWorkspace` (slot limit from
the Microstock entitlements). Until entitlement and on-demand provisioning it
simply does not appear in the switcher.

**Switcher UX (SPA):** `WorkspaceResource` exposes `kind`; `ArchivdView.vue` renders workspace items client-side from `GET /api/v1/archivd/workspaces` (refetched after create/switch). Team and Microstock workspace creation use SPA modals (`ArCreateWorkspaceModal`, Microstock create modal) that POST directly to `/api/v1/archivd/workspaces` or `/api/v1/microstock/workspaces`. Switching workspaces rebinds Echo and refreshes members/activity.

## Collaborative Works (team storage)

Paid owners (active **main or storage addon** Archivd entitlement, not free-only) may own **multiple** team workspaces. Members invited by email; **free collaborators allowed** if owner is paid. **Multi-membership is first-class:** a user may own several workspaces and simultaneously join others as admin/editor/viewer. Owning does not block joining.

| Role | Capabilities |
|------|----------------|
| Owner | Create/archive workspace, invite/remove, all file ops, full activity |
| Admin | Manage members (invite/role/remove), all file ops, activity |
| Editor | Upload/rename/move/delete; no member mgmt |
| Viewer | Browse + download only |

| Plan state | Can create / own team | Can join team | Personal files | Public link share |
|------------|----------------------|---------------|----------------|------------------|
| Free | No | Yes (invite token) | Yes (free tier) | No (guest 404; enable blocked) |
| Paid | Yes — up to **owned slot limit** | Yes (unlimited joins) | Yes | Yes (`anyone_with_link` + active paid owner) |
| Grace | No new create / collab mutations | Existing membership may still view if owner not grace-blocked | Read; upload blocked | No (same as free; renew first) |

### Owned workspace slots

| Source | Slots |
|--------|-------|
| Base (any active paid **storage** plan — main or storage addon) | `config('archivd.base_owned_workspace_slots')` default **10** |
| Addon `archivd.addon.workspace-slot.monthly` | **+1 per quantity unit** ($1 USD POC); stackable; `storage_bytes = 0` |
| Formula | `owned_limit = (paid storage ? base : 0) + sum(active/grace slot qty)` |
| Used | Count of **Active** workspaces where `owner_user_id` = user |

Only **Active** workspaces consume slots. **Archive** frees a slot; create always inserts a new row (no reactivate-first-archived). Slot expiry that leaves `used > limit` does **not** auto-archive existing teams — only blocks **new** create (known edge).

Workspace-slot SKU is **not** a storage plan: does not raise GiB quota, does not alone grant “paid” collab (create still needs `hasActivePaidArchivd`). It participates in the shared addon anniversary stack with storage addons.

| Context | Role source |
|---------|-------------|
| Personal drive (`workspace_id` null) | Owner of own files only |
| Workspace you own | Always `owner` (`roleFor` + member row) |
| Workspace you joined | Invite role (`admin` / `editor` / `viewer`) |

**Switcher:** `GET workspaces` returns **active** workspaces only (owned **or** active member). Payload includes `my_role` and `is_owner` for UI labels (Yours vs Joined). Blade: toolbar **dropdown** (Personal + teams + create/join + slot counter) and **Team** offcanvas when in team context (members, invite, activity, archive). Personal tree never mixes with any team tree.

**Billing / quota:** Team files use **that workspace owner’s** storage pool (`user_id` = owner, `workspace_id` set). S3 key stays `archivd/{owner_user_id}/{objectId}`. `created_by` records uploader for audit. When browsing joined B, quota bar still reflects the logged-in user’s personal usage (owner pool for B is separate).

**Paid gate:** `WorkspaceAccessService::assertOwnerEligibleForCollab` — requires `hasActivePaidArchivd` on the **workspace owner** for **create / invite / accept** only. Create also runs `assertCanCreateOwnedWorkspace` (slot limit). **Upload/content collaboration is not paid-gated** — `DirectUploadService::resolveUploadScope` only requires `assertCanMutate` (owner or active member), so invited free members and free Microstock owners can upload. Joiner need not be paid.

**Quota API flags:** `GET /api/v1/archivd/quota` includes `state` (`free`\|`paid`\|`grace`), `collaborative_works`, `can_create_workspace` (paid **and** under slot limit), `owned_workspace_used`, `owned_workspace_limit`, plus raw `used_bytes` / `pending_bytes` / `reserved_bytes` / `quota_bytes` / `remaining_bytes` and matching `*_human` companions (`BytePresenter`). `HostedFileResource` exposes `size` + `size_human` and dual-field timestamps. Blade uses companions only — no view-layer `$formatBytes`.

**Blade UX:** Compact toolbar controls (not a full-width card). Free -> upgrade CTA inside switcher dropdown + accept invite. Paid -> create (while under limit), invite (token flash; no email delivery in MVP), members (owner **or** admin), activity in offcanvas. Web routes: `POST archivd/workspaces`, invites accept/store, member remove, archive.

**Activity:** Spatie `log_name = archivd.workspace`, properties include `workspace_id`. Explicit recorder (`WorkspaceActivityRecorder`) on membership + file/folder mutations. Download events off in v1. API: `GET /api/v1/archivd/workspaces/{id}/activity` (any active member).

**Tables:** `ARCHIVD_workspaces` (no unique on `owner_user_id`; index `(owner_user_id, status)`; `kind` = `team` user-created team-slot-counted vs `microstock` built-in standalone-slotted), `ARCHIVD_workspace_members`, `ARCHIVD_workspace_invites`; nullable `workspace_id` on folders/files/uploads; `created_by` on files.

**File payload contract:** `HostedFileResource` exposes `workspace: { id, kind, name } | null` (when the relation is loaded — `FileService::show()` and `FolderService::browse()` both eager-load it) and emits the `microstock` key **only** when `workspace.kind === 'microstock'` — team-workspace and personal files never carry the key, so the SPA detail view gates its Microstock card on `file.workspace?.kind === 'microstock'` instead of key presence. `FileService::show()` additionally eager-loads `microstockMetadata` only for microstock-workspace files.

**Browse:** `GET browse?workspace_id=` for team tree; omit for personal. Never leak personal through workspace.

## Visibility

```
folderLinkShared(folder) =
  folder.visibility == anyone_with_link
  OR any ancestor.visibility == anyone_with_link

fileLinkShared(file) =
  file.visibility == anyone_with_link
  OR (file.folder_id && folderLinkShared(file.folder))

folderAccessible(folder) =
  folderLinkShared(folder)
  AND QuotaService::ownerCanPublicShare(folder.user_id)   // storage owner state === paid

fileAccessible(file) =
  fileLinkShared(file)
  AND QuotaService::ownerCanPublicShare(file.user_id)
```

- Guest public show/download (API + `/s/f` / `/s/d`) uses `fileAccessible` / `folderAccessible`. Free or grace storage owners get **404** `share_not_found` even if DB still says `anyone_with_link` (downgrade kills links without rewriting rows).
- Guest API payloads are **not** the owner `HostedFileResource` / `FolderResource`. `PublicHostedFileResource` / `PublicFolderResource` expose id, name, mime, size (files) — no `path` / `disk` / `checksum` / `user_id` / `owner_id`. Authenticated `HostedFileResource` gates storage topology (`path`, `disk`, `checksum`, `region`) to the storage owner or staff (`view any` / `manage any` Archivd). Workspace invite `token` is only on the create response (`WorkspaceInviteResource` `includeToken: true`). Member emails are owner/admin/staff only.
- Enabling `anyone_with_link` (single + bulk file/folder) requires paid owner; free/grace → **422** `public_share_requires_paid`. Setting `private` always allowed.
- Mutations never public.
- **Hash blocklist (DMCA):** enabling `anyone_with_link` on a file whose `checksum` is on `ARCHIVD_hash_blocklist` → **422** `public_share_blocked_hash` (single + bulk; bulk rejected when **any** targeted file is blocked). Guest show/download returns **404** for a blocked checksum even if the file was already public. See [Content checksum (sha256) + hash blocklist (DMCA)](reference-upload.md#content-checksum-sha256--hash-blocklist-dmca).
- `share_url` only when **this** item is `anyone_with_link` **and** storage owner is paid. Minted by `SpaPublicUrl::publicFileShare` / `publicFolderShare`: `{SPA_URL}/s/f/{id}` and `/s/d/{id}` when `SPA_URL` is set; otherwise Blade `route('archivd.public.files/folders.show')`. Paths match. Guest pages stay noindex on the SPA (`PublicLayout` + `/s/*` `X-Robots-Tag`).
- Workspace files: paid check is on **storage `user_id`** (quota pool owner), not the guest member’s plan.

## Permissions (`domain_kind = product`)

```
view|create|edit|delete Archivd folder
view|create|edit|delete Archivd file
view any Archivd resource
manage any Archivd resource
```

| Role | Scope |
|------|--------|
| Archivd Admin / Editor / Viewer | Levels 1100 / 1200 / 1300 (unique with other product roles) |
| Core `User` | Own CRUD |
| Core Root / Admin / Moderator / Management | Own + any (hierarchy still applies) |


## Config highlights

### Multi-disk storage

| Disk | Role |
|------|------|
| `s3ArchivdCe` | Primary CE region (`ARCHIVD_S3_CE_*`) |
| `s3ArchivdUs` | Optional US region (`ARCHIVD_S3_US_*`) |

Region keys for new uploads: `ce` (Central European), `us`. Client field name is still `disk` but the **value is the region key**; `DirectUploadService::resolveDiskName()` maps to the Laravel disk name stored in DB.

Region UI (list column, detail badge, soft-refetch): `RegionResolver::shouldDisplayRegion()` — true when `count(configured regions) > 1` **or** historical distinct disks on `HostedFile` (with trashed) > 1 after alias collapse.

### Tier A — env / deploy

| Key | Role |
|-----|------|
| `disk` / `directory` | Default Laravel disk for new uploads (`ARCHIVD_DISK`, default `s3ArchivdCe`) + key prefix (`ARCHIVD_DIRECTORY`, default `archivd`). Prefix is **not** stored in DB `path`. |
| `regions` | Region key -> disk name map (`ce` -> `s3ArchivdCe`, optional `us`); only regions with non-empty disk appear in upload dropdown. Labels: `lang/*/web/archivd.php` (`Central European`, etc.). |
| `disk_aliases` | Optional disk name -> canonical disk map for region reverse-lookup and historical multi-disk collapse |
| `upload_proxy.*` | Optional CF Worker hybrid byte path for **upload + download** (`base_url`, `token`, `disk_targets`) — bound from `ARCHIVD_UPLOAD_PROXY_*` env vars |

### Tier B — Settings → Archivd

Edit via `/apps/archivd/settings`; runtime reads `setting_config()`. See [reference-settings.md — Archivd](../../platform/reference-settings.md#archivd-archivd).

| Registry key | Default | Role |
|--------------|---------|------|
| `archivd.disabled_regions` | `[]` | Region keys blocked for new uploads only (downloads still served) |
| `archivd.chunk_size_bytes` / `archivd.multipart_threshold_bytes` | 8 MiB each | Upload strategy |
| `archivd.max_file_size_bytes` | 5 GiB | Per-file upload cap |
| `archivd.max_files_per_batch` | 20 | Batch upload cap |
| `archivd.default_quota_bytes` | 5 GiB | Free-tier base always stacked; also used when no `UserQuota` denorm row |
| `archivd.base_owned_workspace_slots` | 10 | Owned team workspaces included with paid storage |
| `archivd.max_concurrent_uploads` | 2 | Server-side cap on pending non-expired upload sessions per storage owner |
| `archivd.max_concurrent_parts` | 3 | Concurrent part XHR streams within one file; lower to 2 if 520s increase |
| `archivd.max_presigned_parts_per_response` | 100 | Part URLs signed per init response |
| `archivd.presign_ttl_minutes` | 60 | Presigned part-URL lifetime; init signs every part upfront |
| `archivd.session_ttl_hours` | 24 | Pending upload session TTL |
| `archivd.stale_upload_grace_hours` | 6 | Extra grace after session expiry before `archivd:prune-stale-uploads` aborts and reclaims multipart storage |
| `archivd.orphan_inactivity_minutes` | 180 | Reclaim pending **multipart** sessions with no recent S3 part activity (`ListParts` `LastModified` is truth; error skips candidate). Single-PUT sessions never pruned by this arm |
| `archivd.signed_download_ttl_minutes` | 60 | Cached download URL lifetime (Worker HMAC `exp` or S3 temp URL) |
| `archivd.signed_download_cache_margin_seconds` | 60 | Treat cache expired early |
| `archivd.public_download_signature_ttl_minutes` | 5 | Laravel signed hop for `/s/f/.../download` (Monolith hop only) |
| `archivd.allowed_mime_prefixes` | image/application/text/audio/video | Allowed MIME prefixes (empty = allow all non-empty client MIME) |
| `archivd.download_vault.enabled` | `false` | Vault download path when hybrid proxy is on (`true` on local via `settings-local.php`) |

## Quota

| Source | Behavior |
|--------|----------|
| `config('archivd.default_quota_bytes')` | Free tier **always stacked** on paid main + addons (typically 5 GiB) |
| `ARCHIVD_user_quotas` | Denorm after recompute: `quota_bytes` = free + main + Σ(addon×qty), `source=service_recompute` |
| `ArchivdQuotaRecomputeService` | Single source of truth write on fulfill / expire |

### Effective quota formula

```
effective_bytes =
  free_tier_bytes
  + (active_or_grace paid main.storage_bytes)   # at most one
  + sum(active_or_grace addon.storage_bytes * quantity)
```

| `state` | Condition | Upload |
|---------|-----------|--------|
| `paid` | Any active paid Archivd main/addon entitlement | OK (numeric cap) |
| `grace` | Any paid Archivd line in grace | **blocked** (`quota_grace_blocked`); files kept |
| `free` | No paid active/grace lines | free tier only |

Runtime `QuotaService::quotaBytesFor` reads denorm `UserQuota` (written on fulfill/expire). No unlimited short-circuit (`quota_bytes === 0` reserved only if true unlimited is reintroduced later).

**Committed bytes** = `SUM(ARCHIVD_files.size)` + `SUM(ARCHIVD_file_variants.size)` where variant `status = ready` (Microstock AI JPEG sidecars `ai_512` / `ai_1024`). Pending/failed variants do not reserve quota until promoted to `ready`.

On paid [Service](../service/README.md) fulfill: main replace / addon quantity + recompute. On grace expire: recompute remaining lines + `EnforceFreeTierQuotaService::enforceToLimit(effective)` (oldest-first; soft-delete DB + hard-delete S3).

**Purge rule (example):** files A 2 GiB (oldest), B 3 GiB, C 5 GiB with limit 5 GiB -> keep A+B (exactly 5 GiB), soft-delete C in DB and hard-delete C object on S3. Under-limit users are no-op. Re-run is idempotent.

Scheduler entry point: `php artisan service:process-entitlements` (hourly).

## Tests

**Production qualification suite (49 methods, automated):** `tests/Domain/Archivd/Qualification/ArchivdQualification{Low,Normal,Medium,Hard,Anomaly}Test.php` — method names `test_f_h_l01_…` … `test_f_h_a10_…`; Low carries 9 (`l09` is unused), Normal/Medium/Hard/Anomaly carry 10 each. Run: `composer test -- --filter=ArchivdQualification`.

```bash
composer test -- --filter=Archivd
npm run test:js
```

| Suite | What it covers |
|-------|----------------|
| PHPUnit `--filter=Archivd` | API feature tests (`ArchivdTest` — object-id `path`, resolved S3 key on complete/delete, display-name auto-rename), unit tests for `ObjectKeyResolver`, `S3DirectUploadEngine` proxy rewrite + 8 MiB defaults, `AllocateUniqueDisplayNameTest`, `PublicDownloadSignedRouteTest` (unsigned 403, signed 302, public show JSON + paid gate 404). Collab: `WorkspaceCollaborativeWorksTest` (multi-own slots, join, activity). Realtime: `ArchivdRealtimeEventsTest`, `ArchivdRealtimeTest`, `ArchivdRealtimeEmitTest`, `ArchivdChannelAuthTest` (channel routing, gate off when unconfigured, service emit after mutations). |
| Vitest `npm test` (SPA) | Client-side helpers and composables under `monolith-vue-workers/src/` (e.g. `utils/archivd/arCollectionDescribe.ts`, `utils/archivd/arMicrostockBadge.ts`, BFF helpers in `tests/`). The removed Monolith `resources/js/**/*.test.js` island tests are historical only — see [UI architecture / SPA shell](reference-realtime.md#ui-architecture--spa-shell-historical). |
| CORS / quota probes | `QuotaThrottleProbeTest` + `QuotaPreflightProbeTest` (`tests/Domain/Archivd/.../API/V1/`) — regression guards confirming `archivd/quota` responses (throttled 429 and `OPTIONS` preflight) carry the correct CORS headers. Added after a transient browser "CORS error" report that traced to infra, not Laravel. |
| Service / Worker | Paid / grace / purge: `composer test -- --filter=Service` and `--filter=EnforceFreeTierQuota`. Gateway uploader tests: `c:\laragon\www\gateway` (`npm test`). |
| Qualification | `composer test -- --filter=ArchivdQualification` — 49 methods (Low 9 / Normal 10 / Medium 10 / Hard 10 / Anomaly 10) |

## Related

- [service/README.md](../service/README.md) — main/addon catalog, quantity, grace, recompute
- [microstock/README.md](../microstock/README.md) — first system-workspace consumer (`kind='microstock'`)
- [identity/README.md](../identity/README.md) — H-RBAC / `domain_kind`
- [notification/README.md](../notification/README.md) — bell + listing registry `table_id` (Archivd listing does **not** use this bus)
- [archive/ui-patterns-blade.md](../../archive/ui-patterns-blade.md) — **archived** Blade a11y / responsive notes (Archivd dropzone, modals); the removed island is recorded under [UI architecture / SPA shell](reference-realtime.md#ui-architecture--spa-shell-historical)
- [platform/architecture.md](../../platform/architecture.md)

Archivd SPA shell is **product-scoped** (this domain only). It is not a monorepo-wide AdminLTE SPA migration.
