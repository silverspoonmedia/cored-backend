# File Hosting — product domain

> **UI:** The in-repo Blade + Vue island admin is **removed**. File Hosting UI lives in **Monolith-Vue-Workers** (`FileHostingView` and related routes). This guide documents API behavior and historical Blade notes where still relevant — see [web/blade-admin.md](../../web/blade-admin.md).

Product domain (`kind => product`, `locked => true`) for personal nested folders, S3-compatible chunk upload, Drive-lite link sharing, and **Collaborative Works** (paid team workspaces).

| | |
|--|--|
| Code | `app/Domain/FileHosting/` |
| Config | [`config/file-hosting.php`](../../../config/file-hosting.php) |
| API | `routes/api/v1/file-hosting.php` (`/api/v1/file-hosting/*` — unchanged) |
| SPA (auth) | Vue Router names `file-hosting.*` in [`config/spa-routes.php`](../../../config/spa-routes.php) → **`/apps/file-hosting`** (index, folder, show, trash, blocklist, workspaces, microstock credentials/usage). No server `web` route for the authenticated app |
| Web (guest) | `routes/web/file-hosting-public.php` → **`/s/f/{uuid}`**, **`/s/d/{uuid}`** (site root, not under `/apps`) |
| Public landing | Root **`/file-hosting`** reserved for future promo landing (not the logged-in app) |
| Seeder | `FileHostingRoleSeeder` (after core `RoleSeeder`) |
| Product gate | `product.active:FileHosting` middleware (see [Product Activations](../product-activations/README.md)) |
| Menu visibility | Products → File Hosting parent (`file-hosting.index`) gates My Files, Trash, and DMCA Blocklist; global Domain Access and authenticated-user activation both propagate through the parent |
| Analytics | `HostedFile` Eloquent events (`created`/`deleted`/`restored`) + `WorkspaceService::create` + `FolderRepository::restore` write to `product_analytics` via `ProductAnalyticsService` (see [Product Activations § Stored analytics](../product-activations/README.md#stored-analytics-v3)) |

### Current product surface

What FH is **today** (auth browse + guest share). Deep dives live in sections below.

| Surface | Behavior |
|---------|----------|
| Auth UI | **Monolith-Vue-Workers SPA** — consumes `/api/v1/file-hosting/*` directly. See [vue-workers-spa.md](../../integration/vue-workers-spa.md). Historical Blade island notes below are archived reference only. |
| Browse gestures | Drive-like select / context menu / drag-move; bulk bar **Move / share / unshare / delete**. Single and bulk stay in lockstep — [Drive-like listing](#drive-like-listing-interactions-browse-only), [Action surface parity](#action-surface-parity-single--bulk). |
| Nested folders | Virtual first row/card **`...`** (parent) + breadcrumb chips as drop targets (`data-fh-drop-folder`). Root has no `...`. |
| Live listing | Dedicated private Echo channels + soft-refetch (listing/quota/trash/show). **Not** the notification bell bus. See [Realtime / live listing](#realtime--live-listing). |
| Display names | Upload complete + **file/folder move** auto-rename on sibling collision (`UniqueDisplayNameAllocator`). Manual rename still rejects exact collisions. |
| Public share | Guest `/s/f` / `/s/d` only when storage owner is **paid** (`QuotaService::ownerCanPublicShare`). Free/grace → 404 even if DB says `anyone_with_link`. See [Visibility](#visibility). |
| Storage regions | Multi-disk `s3FileHosting*`. Region column/badge only when multi-region config **or** historical multi-disk usage. |
| Hard navigation | **Download hops only** (auth + signed public → 302 storage URL: CF Worker when proxy on, else S3). In-shell mutations and folder/file/trash/show soft-nav do **not** rely on `location.reload`. |
| Busy / upload UX | Mutations toggle `.is-fh-busy` spinner overlay; upload queue shows human file size. Successful (**Done**) rows auto-dismiss after ~5s; failed rows stay. |

## Concepts

| Concept | Behavior |
|---------|----------|
| Virtual root | `folder_id = null` = “My Files” (no auto DB folder row) |
| Nested folders | `parent_id` tree per owner; breadcrumb via `FolderAncestorChain` |
| Files | `file_hosting_files` — denormalized `user_id`, optional `folder_id` |
| Upload sessions | `file_hosting_uploads` — init -> browser PUT (direct S3 **or** CF Worker proxy) -> complete promotes row. **Control plane** (init / sign-parts / complete / abort) always Monolith->S3 SDK; **data plane** (part bytes) only is Worker or direct. Seeing B2 host on Create/Complete is expected even when proxy is on. Complete uses row lock (`lockForUpdate`); pending non-expired sizes **reserve** owner quota; `max_concurrent_uploads` enforced server-side on pending owner sessions. Archive aborts pending team uploads. **No resume**: a dead upload restarts from scratch. Chunk/part layout frozen at init (`chunk_size`/`part_count`). **Prune scheduler** aborts orphaned **multipart** sessions past grace or with no recent S3 part activity (Backblaze has no S3 lifecycle for incomplete multiparts). |
| Part size | Default **8 MB** chunk + multipart threshold (`file-hosting.chunk_size_bytes` in **Settings → File Hosting**). 8 MB is a precaution for ISP-shaped consumer links: a stalled 8 MB part recovers ~3× faster than a stalled 25 MB part. On the Worker proxy keep it **≤ 32 MB** — the Gateway buffers each UploadPart (cap 32 MB) so it can retry transient upstream 5xx instead of returning a raw 500. See Gateway [upload-performance.md](../../../gateway/docs/upload-performance.md) for A/B tuning and RTT analysis. |
| DB `path` | Object id only: 40 lowercase alnum chars (`Str::random(40)`). **Not** a full S3 key. |
| S3 object key | `file-hosting/{user_id}/{objectId}` — resolved at runtime via `ObjectKeyResolver` from `user_id` + `path` + config `directory`. No `folder_id`, no extension, no upload session id in the key. |
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
| Collaborative Works | Paid perk for **owners**; free users may **join** as members. Blade UX on File Hosting page (not a separate dynamic menu). |

## Storage classification (core vs product)

File Hosting (and its workspace model) is the **base storage layer** for anything
in this project that consumes disk space. Every feature that stores bytes is
classified into exactly one of two storage classes:

| Class | Effect on free-tier base | Rationale | Examples |
|-------|--------------------------|-----------|----------|
| **Core storage** | Does **not** consume the free-tier base | Belongs to a core feature; if it hits a storage limit the core feature risks breaking | User avatar (future) |
| **Product storage** | Consumes the free-tier base | Belongs to a product feature; unrelated to core operation | File Hosting personal/team files, Microstock Management (upcoming) |

Free-tier base today: `config('file-hosting.default_quota_bytes')` (typically 5 GiB) —
see [Quota](#quota). Core storage is exempt from this budget; product storage is
metered against it.

### System workspaces (built-in, standalone slots)

Any consumer that stores bytes is treated as a **built-in system workspace** over
the File Hosting workspace model. A system workspace:

- is provisioned implicitly by the owning feature (not created via the team
  workspace UI),
- is **not counted** toward the paid included team workspace slots
  (`owned_workspace_limit` / `base_owned_workspace_slots`) — it sits outside the
  [owned workspace slots](#owned-workspace-slots) accounting,
- has its **own standalone slot limit** per product (e.g. Microstock slots come
  from `microstock.plan.free` + `microstock.addon.workspace-slot.monthly`
  entitlements, entirely separate from FH team workspace slots),
- is the billing/quota boundary for the feature's bytes: product-class consumers
  meter into the free-tier base, core-class consumers do not.

> Status: **implemented by Microstock** — the Microstock product uses system
> workspaces (`file_hosting_workspaces.kind = 'microstock'`, standalone slots) as
> the first product-class consumer of this model. Its product-registry activation
> is implicit, while workspace entitlement remains opt-in via `/apps/services`
> (free entitlement + compounding slot addon); its slots never overlap with FH
> team workspace slots. See [Microstock](../microstock/README.md).

**Provisioning trigger:** the Microstock system workspace is **not**
auto-provisioned on File Hosting first paint. It is created on demand via
the SPA services card or workspace switcher (`POST /api/v1/microstock/workspaces`) when the user has a Microstock
entitlement — and is gated by
`WorkspaceAccessService::assertCanProvisionMicrostockWorkspace` (slot limit from
the Microstock entitlements). Until entitlement and on-demand provisioning it
simply does not appear in the switcher.

**Switcher UX (SPA):** `WorkspaceResource` exposes `kind`; `FileHostingView.vue` renders workspace items client-side from `GET /api/v1/file-hosting/workspaces` (refetched after create/switch). Team and Microstock workspace creation use SPA modals (`FhCreateWorkspaceModal`, Microstock create modal) that POST directly to `/api/v1/file-hosting/workspaces` or `/api/v1/microstock/workspaces`. Switching workspaces rebinds Echo and refreshes members/activity.

## Collaborative Works (team storage)

Paid owners (active **main or storage addon** FH entitlement, not free-only) may own **multiple** team workspaces. Members invited by email; **free collaborators allowed** if owner is paid. **Multi-membership is first-class:** a user may own several workspaces and simultaneously join others as admin/editor/viewer. Owning does not block joining.

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
| Base (any active paid **storage** plan — main or storage addon) | `config('file-hosting.base_owned_workspace_slots')` default **10** |
| Addon `file-hosting.addon.workspace-slot.monthly` | **+1 per quantity unit** ($1 USD POC); stackable; `storage_bytes = 0` |
| Formula | `owned_limit = (paid storage ? base : 0) + sum(active/grace slot qty)` |
| Used | Count of **Active** workspaces where `owner_user_id` = user |

Only **Active** workspaces consume slots. **Archive** frees a slot; create always inserts a new row (no reactivate-first-archived). Slot expiry that leaves `used > limit` does **not** auto-archive existing teams — only blocks **new** create (known edge).

Workspace-slot SKU is **not** a storage plan: does not raise GiB quota, does not alone grant “paid” collab (create still needs `hasActivePaidFileHosting`). It participates in the shared addon anniversary stack with storage addons.

| Context | Role source |
|---------|-------------|
| Personal drive (`workspace_id` null) | Owner of own files only |
| Workspace you own | Always `owner` (`roleFor` + member row) |
| Workspace you joined | Invite role (`admin` / `editor` / `viewer`) |

**Switcher:** `GET workspaces` returns **active** workspaces only (owned **or** active member). Payload includes `my_role` and `is_owner` for UI labels (Yours vs Joined). Blade: toolbar **dropdown** (Personal + teams + create/join + slot counter) and **Team** offcanvas when in team context (members, invite, activity, archive). Personal tree never mixes with any team tree.

**Billing / quota:** Team files use **that workspace owner’s** storage pool (`user_id` = owner, `workspace_id` set). S3 key stays `file-hosting/{owner_user_id}/{objectId}`. `created_by` records uploader for audit. When browsing joined B, quota bar still reflects the logged-in user’s personal usage (owner pool for B is separate).

**Paid gate:** `WorkspaceAccessService::assertOwnerEligibleForCollab` — requires `hasActivePaidFileHosting` on the **workspace owner** for **create / invite / accept** only. Create also runs `assertCanCreateOwnedWorkspace` (slot limit). **Upload/content collaboration is not paid-gated** — `DirectUploadService::resolveUploadScope` only requires `assertCanMutate` (owner or active member), so invited free members and free Microstock owners can upload. Joiner need not be paid.

**Quota API flags:** `GET /api/v1/file-hosting/quota` includes `state` (`free`\|`paid`\|`grace`), `collaborative_works`, `can_create_workspace` (paid **and** under slot limit), `owned_workspace_used`, `owned_workspace_limit`, plus raw `used_bytes` / `pending_bytes` / `reserved_bytes` / `quota_bytes` / `remaining_bytes` and matching `*_human` companions (`BytePresenter`). `HostedFileResource` exposes `size` + `size_human` and dual-field timestamps. Blade uses companions only — no view-layer `$formatBytes`.

**Blade UX:** Compact toolbar controls (not a full-width card). Free -> upgrade CTA inside switcher dropdown + accept invite. Paid -> create (while under limit), invite (token flash; no email delivery in MVP), members (owner **or** admin), activity in offcanvas. Web routes: `POST file-hosting/workspaces`, invites accept/store, member remove, archive.

**Activity:** Spatie `log_name = file_hosting.workspace`, properties include `workspace_id`. Explicit recorder (`WorkspaceActivityRecorder`) on membership + file/folder mutations. Download events off in v1. API: `GET /api/v1/file-hosting/workspaces/{id}/activity` (any active member).

**Tables:** `file_hosting_workspaces` (no unique on `owner_user_id`; index `(owner_user_id, status)`; `kind` = `team` user-created team-slot-counted vs `microstock` built-in standalone-slotted), `file_hosting_workspace_members`, `file_hosting_workspace_invites`; nullable `workspace_id` on folders/files/uploads; `created_by` on files.

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
- Guest API payloads are **not** the owner `HostedFileResource` / `FolderResource`. `PublicHostedFileResource` / `PublicFolderResource` expose id, name, mime, size (files) — no `path` / `disk` / `checksum` / `user_id` / `owner_id`. Authenticated `HostedFileResource` gates storage topology (`path`, `disk`, `checksum`, `region`) to the storage owner or staff (`view any` / `manage any` file hosting). Workspace invite `token` is only on the create response (`WorkspaceInviteResource` `includeToken: true`). Member emails are owner/admin/staff only.
- Enabling `anyone_with_link` (single + bulk file/folder) requires paid owner; free/grace → **422** `public_share_requires_paid`. Setting `private` always allowed.
- Mutations never public.
- **Hash blocklist (DMCA):** enabling `anyone_with_link` on a file whose `checksum` is on `file_hosting_hash_blocklist` → **422** `public_share_blocked_hash` (single + bulk; bulk rejected when **any** targeted file is blocked). Guest show/download returns **404** for a blocked checksum even if the file was already public. See [Content checksum (sha256) + hash blocklist (DMCA)](#content-checksum-sha256--hash-blocklist-dmca).
- `share_url` only when **this** item is `anyone_with_link` **and** storage owner is paid. Minted by `SpaPublicUrl::publicFileShare` / `publicFolderShare`: `{SPA_URL}/s/f/{id}` and `/s/d/{id}` when `SPA_URL` is set; otherwise Blade `route('file-hosting.public.files/folders.show')`. Paths match. Guest pages stay noindex on the SPA (`PublicLayout` + `/s/*` `X-Robots-Tag`).
- Workspace files: paid check is on **storage `user_id`** (quota pool owner), not the guest member’s plan.

## Permissions (`domain_kind = product`)

```
view|create|edit|delete file hosting folder
view|create|edit|delete file hosting file
view any file hosting resource
manage any file hosting resource
```

| Role | Scope |
|------|--------|
| File Hosting Admin / Editor / Viewer | Levels 1100 / 1200 / 1300 (unique with other product roles) |
| Core `User` | Own CRUD |
| Core Root / Admin / Moderator / Management | Own + any (hierarchy still applies) |

## API

### Authenticated — `/api/v1/file-hosting/*` (`auth:api`)

Middleware stack: `auth:api` -> `product.active:FileHosting` (runtime activation gate, see [Product Activations](../product-activations/README.md)) -> `permission:<perm>` (Spatie RBAC). A user with a suspended File Hosting activation gets 403 before any permission check runs.

**Sidebar menu:** Products → **File Hosting** stores `file-hosting.index` on the parent in the DB (Domain Access + activation ancestor gates) and exposes **My Files**, **Trash**, and **DMCA Blocklist** children. While those children are visible, the presented tree masks the parent to `#` (expand-only); if every child is pruned, the stored route is shown again as a navigable leaf. DMCA Blocklist is limited to Root, Admin, Moderator, Management, and File Hosting Admin. See [Navigation — Visibility](../navigation/README.md#visibility-navigation-tree).

| Area | Endpoints |
|------|-----------|
| Browse | `GET browse?folder_id=&user_id=&workspace_id=` |
| Folders | create, rename, move, **visibility**, soft/force delete, restore (optional `workspace_id`) |
| Files | show (view++), rename, move, **visibility**, soft/force delete, restore (optional `workspace_id`) |
| Bulk | `POST files/bulk-visibility` + `POST folders/bulk-visibility` (`ids[]`, `visibility`); `DELETE files/bulk` + `DELETE folders/bulk` (`ids[]`) — owner/workspace-scoped, max 100 ids |
| Download | `GET files/{id}/download` -> `{ url, expires_at }` (download++; cache Worker HMAC URL or S3 temp URL) |
| Upload | init (optional `disk`, `workspace_id`), status, sign-parts, complete (auto-renames duplicate display name; dispatches post-upload sha256 job), abort. No resume: a dead upload restarts from scratch. |
| Workspaces | `GET/POST workspaces`, show, archive, members, invite, accept invite, update/remove member, activity |
| Search / trash / quota | as named (trash/search accept `workspace_id`) |

### Direct upload/download (S3 engine + optional Worker proxy)

Control plane (init / sign-parts / complete / abort / mint download URL) always stays on Monolith. Browser **byte** path is either:

| Mode | When | Browser data plane |
|------|------|--------------------|
| **Direct S3** (default) | `FILE_HOSTING_UPLOAD_PROXY_URL` empty | Presigned S3 PUT/UploadPart + S3 temporary GET URL |
| **Hybrid proxy** | base URL + token + disk->alias set | Upload: Gateway Uploader `/uploader/{alias}/{key}` (+ multipart query) + Bearer. Download: same path + HMAC query (`exp`, `sig`, optional `disp`/`ctype`) — no raw token |

Shared kernel pieces (not duplicated per domain):

| Piece | Role |
|-------|------|
| `App\Support\Storage\S3DirectUploadEngine` | Strategy, direct presign **or** Worker proxy URLs, merge/assert parts, create/complete/abort multipart, `listParts` (with `last_modified` - orphan-prune activity truth), size/mime guards |
| `App\Support\Storage\SerializesDirectUploadSession` | Shared JSON session shape for init/status (`upload_id`, `strategy`, `parts`, …) |
| `DirectUploadService` | Domain adapter: ownership, quota, folder, multi-region disk, **auto display-name allocation** on complete, promote -> `HostedFile` |
| `SignedDownloadService` | Caches download URL: Worker HMAC GetObject when proxy on (B2 **Bandwidth Alliance**), else S3 `temporaryUrl`; remints when cached host disagrees with proxy mode |

**Bandwidth Alliance:** client-direct B2 temporary URLs bill B2 egress after free tier. With proxy on, the browser only hits the Worker origin; the Worker streams GetObject from B2 over Cloudflare. Monolith never streams file bytes.

**Why `signed_download_url` stores a Worker URL, not a B2 URL (by design):** the older idea was for Monolith to presign a B2 temporary URL and have the Worker blindly proxy it. The current design wins for three reasons. (a) A presigned B2 URL embeds the B2 endpoint + bucket + `X-Amz-Signature`; if that reaches the browser it can bypass the Worker and hit B2 directly, billing B2 egress and defeating Bandwidth Alliance. Only the Worker URL ever reaches the browser. (b) The Worker needs the explicit object key to build its edge cache key — a B2 presigned URL changes on every mint, so it cannot key a cache. (c) The Worker verifies the HMAC on every request instead of trusting a bearer URL. The S3 `temporaryUrl` mode (proxy **off**) still uses the B2-URL pattern, so the old idea is not wrong — it is only the right shape when no Worker is involved.

**Role of `signed_download_expires_at` (changed):** the column and the `exp` query in the Worker URL are two copies of the same timestamp, stamped together in `mint()`. Final expiry authority moved to the Worker — `requireDownloadSignature` rejects a request once `exp` (+60s grace) passes. The DB column is now only a **cache-validity hint** for Monolith's reuse-vs-remint decision in `resolve()` / `hasUsableSignedDownloadUrl()`. It stays a real column (rather than parsing `exp` back out of the URL) so it is queryable and a single source for cache logic.

**Download edge caching (Worker):** object keys are random and immutable, so after the HMAC is verified the Worker caches object bytes at the Cloudflare edge keyed on bucket+object (never the signed query). Repeat downloads of a hot object in the same region are served from edge (`X-Cache: HIT`) instead of a fresh B2 stream. Auth is never bypassed — every request is HMAC-checked first. Range requests bypass the cache (no 206 poisoning); objects >512 MB stream from origin (`X-Cache: SKIP`).

**Download hot path (Monolith):** `GET /api/v1/file-hosting/files/{file}/download` calls `FileService::download` directly. `issueDownload` increments `download_count` atomically and skips the redundant `refresh()`. Signed download URLs default to **60 min** TTL (`signed_download_ttl_minutes`) to cut remints on repeat downloads.

**Preview (`?preview=1`):** download endpoints (auth API, guest `/s/f/{id}/download`) accept `preview=1`. For in-browser-safe MIME (`image/*`, `application/pdf`, `audio/*`, `video/*`, `text/*`) the Content-Disposition becomes `inline` so the browser previews instead of downloading; other types stay `attachment`. Preview URLs are minted on demand (not written to the cache columns). The HMAC payload already covers `disp`/`ctype`, so a tampered disposition fails signature verification.

Config for thresholds/TTL/proxy: `config/file-hosting.php`.

#### Display name uniqueness (auto-rename)

Shared helper: `App\Domain\FileHosting\Support\UniqueDisplayNameAllocator` (used by upload complete, **file move**, **folder move**). Soft-deleted siblings are ignored.

Upload **init** does not reject a display name that already exists in the same owner+folder. On **complete** / **move**, the allocator picks the DB `name`:

| Active siblings (soft-deleted ignored) | Preferred | Result |
|----------------------------------------|-----------|--------|
| none | `A.zip` | `A.zip` |
| `A.zip` | `A.zip` (upload or move into folder) | mover/upload becomes `A (2).zip` |
| `A.zip` + `A (3).zip` | `A.zip` | `A (4).zip` (max index + 1; holes not reused) |
| base soft-deleted, `A (3).zip` remains | `A.zip` | `A.zip` (base free again) |
| folder sibling `Docs` | move folder `Docs` into same parent | mover becomes `Docs (2)` |

S3 object keys stay unique (`file-hosting/{user_id}/{objectId}`) and are never renamed or moved when the display name / folder changes. **Manual rename** still enforces exact sibling uniqueness (422). Clients must show **`file.name` from complete/move response**, not the original client filename. API `path` is the object id only.

#### Hybrid proxy env (Tier A)

| Env | Role |
|-----|------|
| `FILE_HOSTING_UPLOAD_PROXY_URL` | Gateway Uploader origin (no trailing slash). Empty = direct S3 for **upload + download**. Example: `https://gateway.silverspoon.me` |
| `FILE_HOSTING_UPLOAD_PROXY_TOKEN` | Same value as Gateway `S3_TARGETS.uploadToken` — upload **Bearer** + download **HMAC secret** (never put raw token in download URLs) |
| `FILE_HOSTING_UPLOAD_PROXY_TARGET_CE` | Uploader target **alias** for disk `s3FileHostingCe` (must match a key under Gateway `S3_TARGETS.targets`, e.g. `sbs`) |
| `FILE_HOSTING_UPLOAD_PROXY_TARGET_US` | Alias for disk `s3FileHostingUs` (optional) |

**Vault download path (Tier B):** `file-hosting.download_vault.enabled` in **Settings → File Hosting** (`/apps/file-hosting/settings`). When enabled, download URLs use `/vault/{alias}/{userId}/{objectId}` (vault code = upload alias; `file-hosting/` prefix stripped from path). Gateway must have matching `vaultOrigins.{alias}` with `keyPrefix`. Local dev default `true` via [`config/settings-local.php`](../../../config/settings-local.php).

**Upload vs download paths (hybrid proxy on):**

- Upload: `{PROXY}/uploader/{alias}/file-hosting/{userId}/{objectId}?partNumber=&uploadId=` + Bearer / `X-S3-Target`
- Download (vault enabled): `{PROXY}/vault/{alias}/{userId}/{objectId}?exp=&disp=&ctype=&sig=`
- Download (vault disabled — legacy): `{PROXY}/uploader/{alias}/file-hosting/{userId}/{objectId}?exp=&disp=&ctype=&sig=`

#### Upload proxy — production (2026-07-30)

| Item | Value |
|------|-------|
| Production URL | `https://gateway.silverspoon.me` — upload **and** vault download on one domain |
| Part size / concurrency | 8 MB (`file-hosting.chunk_size_bytes`), 3 parallel parts (`file-hosting.max_concurrent_parts`) — **Settings → File Hosting** |
| Gateway Smart Placement | **Must stay off** — enabling `placement.mode: smart` roughly doubles upload latency per part |
| Dual-host workaround | **Not needed** — pointing upload at `s3.silverspoon.me` while Gateway handles vault was a fallback only; automated benchmark passed with Smart Placement disabled |
| Benchmark / tuning | [gateway/docs/upload-performance.md](../../../gateway/docs/upload-performance.md) |

HMAC always signs the **full** object key (`file-hosting/{userId}/{objectId}`) and upload **alias** (`sbs`). Gateway reassembles the key from `keyPrefix` + short vault path.

**Where does `/uploader/{alias}/…` come from?** Monolith maps disk → alias via `upload_proxy.disk_targets`. Upload always uses the full object key in the path. Download uses Vault when vault download is enabled in **Settings → File Hosting** (`file-hosting.download_vault.enabled`).

**Proxy URL path = S3 object key (keep `file-hosting` prefix)**

The `file-hosting` segment you see in browser Network (e.g. `…/uploader/sbs/file-hosting/{userId}/{objectId}`) is **not** a Gateway folder or route — it is the first segment of the **S3 object key** in the bucket.

| Point | Detail |
|-------|--------|
| What `file-hosting` is | Key prefix from `FILE_HOSTING_DIRECTORY` (default `file-hosting`). Config only — not stored in DB `path`. |
| Full key shape | `{directory}/{user_id}/{objectId}` via `ObjectKeyResolver` (e.g. `file-hosting/019fa89c-…/l8hft9wi…`) |
| Hybrid proxy URL | `{PROXY}/uploader/{alias}/` + **entire** object key, each path segment URL-encoded |
| Control vs data plane | Monolith SDK (create / complete / abort) and Gateway (PUT parts / GET bytes) must target the **same** key |
| Do not shorten the URL | `/uploader/sbs/{userId}/{objectId}` without Gateway key remapping writes parts to the wrong object vs `CreateMultipartUpload` on `file-hosting/…` — upload breaks |
| Download HMAC | Signed with the **full** `objectKey`; path after `{alias}` must match the payload |

Example (local):

```text
http://127.0.0.1:8787/uploader/sbs/file-hosting/{userId}/{objectId}?partNumber=1&uploadId=…
                                      └─ S3 key prefix (FILE_HOSTING_DIRECTORY), not a Gateway module
```

Shorter **download** URLs are supported via Vault `keyPrefix` remapping (`/vault/sbs/{userId}/{objectId}`). **Upload** URLs must still carry the full object key — do not shorten upload paths without coordinated Gateway remapping.

The alias is not the B2 bucket name and **not** part of the S3 key; it only selects credentials/endpoint inside the Worker. The browser never receives B2 application keys or the raw upload token on download links.

**Fail closed:** if base URL is set but token or disk alias is missing -> init/sign-parts error (`proxy_misconfigured`), not silent fall-back to direct S3. Upstream S3 failures map to `upstream_failed` where applicable.

**Debug upstream failures:** set `FILE_HOSTING_DEBUG_LOG_REQUESTS=true` to log the full AWS command name, params, and response headers to `storage/logs/laravel.log` when an `upstream_failed` exception fires. Never enable in production — headers may contain sensitive values.

**B2 / path-style:** Monolith FH disk `s3FileHostingCe` must use path-style endpoints (`FILE_HOSTING_S3_CE_USE_PATH_STYLE`, default true in `config/filesystems.php`) so CreateMultipart / Complete on the server hit the same path-style host as the Worker (`forcePathStyle: true` in `S3_TARGETS`). Virtual-hosted Create + path-style UploadPart can look fine until complete fails with `NoSuchUpload`.

**Gateway project:** `c:\laragon\www\gateway` — Wrangler name **`gateway`**, Uploader at `/uploader/<alias>/…`. Supersedes standalone `cf-workers-s3-proxy` (`workers` repo). See [gateway/docs/projects/uploader.md](../../../gateway/docs/projects/uploader.md).

**Deploy / secrets (ops):**

| Step | Note |
|------|------|
| Runtime secret | `S3_TARGETS` is a **Gateway Worker secret**, not git, not `.dev.vars` on CF. Set with `npx wrangler secret put S3_TARGETS --name gateway` (name must match deployed Worker). |
| Dashboard Variables | Must be on the **same** Worker that serves the public hostname (`gateway`). UI “Variables” on another Worker / build env only → `/uploader/health` returns config missing. |
| Alias rename | Change key + `defaultTarget` in Gateway `S3_TARGETS` **first**, then Monolith `FILE_HOSTING_UPLOAD_PROXY_TARGET_*`, then `php artisan config:clear`. In-flight sessions still using an old path may 400. |
| Error bodies | Production Worker returns generic messages only (`Configuration invalid or missing`, `Internal server error`). Details stay in Workers Logs (`console.error`). |

**Hybrid protocol (parts[] response):**

- `url` = `{proxy}/uploader/{alias}/{encoded-key}` or with `?partNumber=&uploadId=` for multipart
- `headers` = multi-value map: `Authorization: Bearer …`, `X-S3-Target`, `Content-Type`
- Create/complete/abort still use Monolith disk credentials against real S3 (not the Worker)
- Client `direct-upload.js` accepts header values as string or single-element array

**Hybrid protocol (download URL in `signed_download_url`):**

- Proxy on + vault enabled: `{proxy}/vault/{alias}/{userId}/{objectId}?exp=&disp=&ctype=&sig=`
- Proxy on, no vault code (legacy): `{proxy}/uploader/{alias}/{encoded-key}?exp=&disp=&ctype=&sig=`
- Proxy off: S3 temporary URL from disk `temporaryUrl`
- Cache reminted if host disagrees with current proxy mode (stale B2 URL after enabling proxy, or stale Worker URL after disabling)

**Download retrieve flow (HMAC)**

When `FILE_HOSTING_UPLOAD_PROXY_URL` is set, Monolith never streams file bytes. It mints a time-limited Gateway URL; the browser follows a **302** (web) or uses `{ url, expires_at }` (API), then Gateway verifies HMAC and streams from B2.

```mermaid
sequenceDiagram
  participant Browser
  participant Monolith
  participant Vault
  participant B2
  Browser->>Monolith: GET /apps/file-hosting/files/{id}/download
  Monolith->>Monolith: SignedDownloadService mint/cache HMAC URL, download_count++
  Monolith-->>Browser: 302 Location Gateway URL
  Browser->>Vault: GET /vault/sbs/{userId}/{objectId}?exp&sig&disp&ctype
  Vault->>Vault: code → target alias, requireDownloadSignature, optional edge cache
  Vault->>B2: SigV4 GetObject
  B2-->>Vault: object bytes
  Vault-->>Browser: attachment or inline
```

When `file-hosting.download_vault.enabled` is off, the Gateway hop is **Uploader** (`GET /uploader/{alias}/…`) with the same HMAC — backward compatible.

| Query param | Role |
|-------------|------|
| `exp` | Unix expiry (seconds). **Enforced by Gateway** (`exp` + 60s grace). `signed_download_expires_at` in DB is only Monolith's cache hint for reuse vs remint. |
| `sig` | Hex HMAC-SHA256 over LF-separated payload below. Secret = `uploadToken` / `FILE_HOSTING_UPLOAD_PROXY_TOKEN`. Raw token never appears in the URL. |
| `disp` | `Content-Disposition` passed to S3 as `response-content-disposition` (human filename from DB `name`). |
| `ctype` | `Content-Type` passed to S3 as `response-content-type`. |

HMAC payload (must match Gateway `buildDownloadSignaturePayload`):

```text
GET
{alias}
{objectKey}
{exp}
{disposition}
{contentType}
```

Entry points (all end at the same storage URL shape when proxy is on):

| Route | Hop |
|-------|-----|
| `GET /apps/file-hosting/files/{id}/download` | Auth web → `FileService::issueDownload` → 302 |
| `GET /api/v1/file-hosting/files/{id}/download` | Auth API → JSON `{ url, expires_at }` |
| `GET /s/f/{id}/download` | Laravel signed hop (short TTL) → then same HMAC storage URL |
| `GET /api/v1/file-hosting/public/files/{id}/download` | Guest API → same mint path |

**Not used for download:** Bearer / `X-Upload-Token` (browser navigation cannot set headers on a 302 follow). Upload ops still use Bearer.

**Preview (`?preview=1`):** safe MIME types get `inline` disposition → different `disp` in payload → different `sig`. Preview URLs are minted on demand and not cached in `signed_download_url`.

**Parity verification (workers → gateway port):** automated — Gateway `test/uploader.spec.ts` (40 tests: HMAC accept/reject/expiry, GetObject cache HIT/MISS/Range/SKIP); Monolith `SignedDownloadServiceTest` (11 tests: mint, sig match, cache remint). Manual smoke: tampered `sig` → Gateway **401**; repeat download → `X-Cache: HIT` on small objects.

### Orphan multipart cleanup + post-upload checksum

Big uploads that die mid-flight (crowded network, tab close, cancel) leave S3 multipart parts sitting on storage. Backblaze B2 exposes **no** S3 lifecycle rule for incomplete multiparts, so parts persist until we abort them. There is **no resume**: a dead upload restarts from scratch. Two mechanisms keep storage clean and files accountable: the **prune scheduler** (reclaim what is truly abandoned) and the **post-upload sha256 + hash blocklist** (dedup + DMCA).

**Prune — `file-hosting:prune-stale-uploads` (multipart-only, error-swallowing):**

1. **Expired + past grace** — `expires_at` in the past beyond `stale_upload_grace_hours` → abort directly (no S3 check needed; the session is contractually dead).
2. **Orphan-by-inactivity (S3-truth)** — for each pending multipart candidate, call `S3DirectUploadEngine::listParts()`; abort only when **no part has `LastModified >= now - orphan_inactivity_minutes`** (or `ListParts` is empty). This is the source of truth for "no new multipart data received". A `ListParts` **error skips the candidate** — never abort blind. Providers without `ListParts` make arm 2 a no-op (arm 1 still runs).

`listParts()` returns each part's `last_modified` (ISO) from S3 `Parts[].LastModified`; `latestPartModifiedAt()` collapses it to the newest timestamp. Default inactivity window is **180 minutes** — **Settings → File Hosting** (`file-hosting.orphan_inactivity_minutes`).

**Concurrent-slot fairness:** `max_concurrent_uploads` is enforced on the fresh-init path. With resume removed there is exactly one init path, so the cap is checked on every `initiate()`.

**Microstock collection gate:** in a `WorkspaceKind::Microstock` workspace, `initiate()` rejects (422) an upload with no `folder_id` (`web/file-hosting.microstock.error_requires_folder`) or into a folder whose `collection_status` is locked-for-upload (`web/file-hosting.microstock.error_collection_locked`). Microstock assets must live inside a folder (collection), and a locked collection no longer accepts files — see [Microstock — Collections](../microstock/README.md#collections-folder-as-collection-lifecycle). The dropzone mirrors this client-side via `data-workspace-kind` / `data-collection-locked`. During batch uploads the client re-checks the gate per queued file (`assertMicrostockUploadAllowed`) and infers MIME from extension when `file.type` is empty (`resolveMimeType`).

**Abort preserves single-PUT bytes:** `abort()` / `forceAbortPending()` call S3 `AbortMultipartUpload` for multipart sessions, but for a `single` strategy session they only mark the row `aborted` and **leave the object on S3**. A single PUT has no dangling multipart to clean, and the prune scheduler can fire while the client is mid-flight — deleting the object would destroy bytes a late `complete()` could still promote.

**Client abort-on-failure (slot hygiene):** a leaked `pending` row holds a `max_concurrent_uploads` slot until TTL/prune — after enough orphans, init is rejected with `too_many_concurrent` and no upload can start. `uploadFile()` **awaits** a best-effort `DELETE` abort (`abortUploadSession`) before rethrowing so the slot is released before the queue starts the next file. Init retries up to 3× with backoff when the server returns `too_many_concurrent`. Batch uploads snapshot folder/workspace context at `startBatch` so SPA navigation mid-batch cannot retarget files. Storage PUT retries transient network/5xx errors (and re-signs on 403). Tab-close orphans (no error path) are still reclaimed by the prune scheduler above.

### Content checksum (sha256) + hash blocklist (DMCA)

Resume upload was removed — its client-side fingerprint was a major throughput regression — so the checksum is now computed **after the file is fully uploaded**, off the request path. Purpose: **dedup + DMCA hash blocklist**, not resume identity.

**Post-upload checksum (queued):**

- `ComputeHostedFileChecksum` (queued job) is dispatched from `DirectUploadService::complete()` after commit, so a failure can never block or roll back the upload.
- The job streams the object (`ObjectStorage::readStream`) through `hash_init('sha256')` + `hash_update_stream` and stores the hex on `HostedFile.checksum`.
- **Idempotent**: skips a file that already has a checksum (safe to re-dispatch). `tries = 3`, `backoff = 30`; terminal failures are only `report()`ed — checksum is best-effort metadata, never worth alarming on the upload path.
- `file_hosting_files.checksum` has an index for fast exact-match lookups.

**Hash blocklist (DMCA):**

- Table `file_hosting_hash_blocklist`: `id` uuid, `sha256` (64) unique-indexed, `reason` nullable, `created_by` uuid nullable, timestamps. Model `FileHostingHashBlocklist`; `HashBlocklistService` exposes `isBlocked` / `isFileBlocked` / `block` / `unblock` (queries the DB directly — Octane-safe).
- **Enforcement:** `VisibilityService::assertCanEnableLinkShare` / `assertVisibilityChangeAllowed` (via `FileService::setVisibility` and `bulkSetVisibility`) reject enabling `anyone_with_link` for a blocked checksum with **422 `public_share_blocked_hash`**; bulk share is rejected when **any** targeted file is blocked. Guest show/download (`fileAccessible`) returns **404** for a blocked checksum even if the file was already public.
- **Manage:** Artisan `file-hosting:blocklist-hash {sha256} {--reason=} {--remove}` and an admin page (`file-hosting.blocklist.*` routes, gated by `manage any file hosting resource`) to list/add/remove entries.
- `HostedFileResource` exposes `is_hash_blocked` so the UI can badge blocked files.

**Background processes (must be running):**

This feature leans on two always-on Laravel processes. If either is down, the corresponding behavior silently stops.

| Process | Command | Drives | If it stops |
|---------|---------|--------|-------------|
| Queue worker | `php artisan microstock:queue-work` (see [microstock README — Operations](../microstock/README.md#operations-queue-worker)) | `ComputeHostedFileChecksum` (post-upload sha256); Microstock AI jobs (`ProcessCollection`, `AnalyzeAssetVision`, `AnalyzeAssetThinking`, `AnalysisWatchdog`) | `checksum` stays `null` → blocklist never matches new files; Microstock analysis stalls until workers run on the correct queues |
| Scheduler | `php artisan schedule:work` (dev) or cron/Task Scheduler `php artisan schedule:run` every minute (prod) | `file-hosting:prune-stale-uploads` (hourly, `withoutOverlapping`) | Orphaned multipart uploads are never aborted → parts pile up on storage |

- Queue connection defaults to `database` (`QUEUE_CONNECTION` unset in `.env` → `config/queue.php` fallback). Set `QUEUE_CONNECTION=redis` in production if you run Redis.
- Windows/Laragon note: `php artisan queue:work` exits after a job instead of staying alive (Windows PHP lacks `pcntl`/`posix`) — use `php artisan microstock:queue-work --listen` for local dev. See [microstock/README.md](../microstock/README.md#operations-queue-worker).
- Schedule entries live in `routes/console.php` (`file-hosting:prune-stale-uploads` hourly). Verify with `php artisan schedule:list`.
- A quick liveness check for the checksum path: upload a file, then confirm `file_hosting_files.checksum` is populated shortly after and `jobs` is not backing up.

### Throughput + 520 troubleshooting (large uploads)

Why a large upload used to start ~20 MB/s then sag to 10 then ~1 MB/s, and why ~1 GB files sporadically failed (Cloudflare **520**):

| Cause | Fix |
|-------|-----|
| **Sequential single-stream parts** — one XHR at a time, no pipelining, collapses to per-connection throughput | Parts upload **in parallel** (`max_concurrent_parts`, default 3) via `uploadPartsParallel` |
| **Presigned URL expired mid-upload** — init signs every part upfront with `presign_ttl_minutes`; a slow upload outlived the TTL and late parts 403'd with no recovery | TTL raised to **60 min**; a **403** PUT is re-signed via `sign-parts` and retried once per part. **Single-PUT sessions skip the re-sign** — `sign-parts` is multipart-only (`multipart_only`), so the SPA surfaces the original 403 instead of retrying. Each part PUT has a **90s XHR timeout** (fail-fast; the Worker's own UploadPart deadline is 60s, so it retries first) |
| **Worker had no error-handling/timeout/retry on the streamed fetch** — a mid-transfer B2 reset / edge timeout tore the response into a Cloudflare **520** with no log | `signedFetchToS3` now retries transient 5xx/network errors on **bodyless** requests (GetObject/create/complete/abort) and returns a **graceful 502 + `upstream_error` log** instead of throwing; **UploadPart stream is not retried** (single-use body) — the client re-PUTs the part |
| **Speed meter was a cumulative average** — one stall dragged the displayed rate down for the rest of the upload | Speed is now a **sliding-window (5s) instantaneous rate** (`createRateSampler`) |

Gateway Uploader changes go live after `wrangler deploy` on the `gateway` Worker; Monolith changes only need `php artisan config:clear` (and `npm run build` if Blade/JS touched).

### S3 provider CORS (browser-direct multipart)

When proxy is **off**, browser uploads **directly** to the S3-compatible endpoint (presigned PUT / UploadPart). That is a cross-origin request from the SPA origin (or server public-share endpoints) to the bucket host. Without bucket CORS, the browser blocks the PUT and may hide `ETag` — multipart **complete** then cannot send part ETags.

When proxy is **on**, browser upload PUTs talk to the **Worker** origin — Worker CORS must allow app origin(s) and **expose `ETag`**. Full-page download navigation does not need CORS (302 → Worker GET with HMAC). Bucket CORS is still needed for **direct** mode (upload + download) and any client that talks to B2 without the Worker.

| Requirement | Why |
|-------------|-----|
| Allow app origin(s) | Preflight + actual PUT/GET from browser |
| Methods include `PUT`, `GET`, `HEAD` (and `DELETE` if abort/cleanup uses it) | Match presign strategy |
| Allow `Content-Type` (or `*`) | Signed part uploads send a content type |
| **Expose `ETag`** | JS must read part ETag after each PUT to finish multipart |

Replace origin placeholders with real front-end hosts (e.g. admin URL, SPA URL). Do **not** leave a bare `"https"` in production — that is not a valid browser origin.

#### Cloudflare R2

R2 supports paste-in CORS under the bucket **CORS Policy** setting.

```json
[
  {
    "AllowedOrigins": [
      "https://your-app.example"
    ],
    "AllowedMethods": [
      "PUT",
      "GET",
      "HEAD",
      "DELETE"
    ],
    "AllowedHeaders": [
      "Content-Type"
    ],
    "ExposeHeaders": [
      "ETag"
    ],
    "MaxAgeSeconds": 3600
  }
]
```

Add every origin that performs browser-direct upload (Blade `APP_URL`, Vue-Workers `SPA_URL`, local Vite). Multiple origins = multiple entries in `AllowedOrigins`. Production SPA cutover: the Worker origin must be listed **and** `ETag` must stay in `ExposeHeaders`.

#### Backblaze B2

B2 bucket CORS is not a simple dashboard paste for all setups — use the [B2 CLI](https://www.backblaze.com/b2/docs/quick_command_line.html).

1. Install the B2 CLI.
2. Authenticate (`b2 account authorize` / equivalent for your CLI version).
3. Apply CORS rules on the bucket (replace `replaceWithBucketName` and origins). Windows example with `b2.exe`:

```bash
b2.exe bucket update --cors-rules "[{\"corsRuleName\":\"ETagUploader\",\"allowedOrigins\":[\"https://your-app.example\"],\"allowedHeaders\":[\"*\"],\"allowedOperations\":[\"b2_download_file_by_id\",\"b2_download_file_by_name\",\"b2_upload_file\",\"b2_upload_part\",\"s3_put\",\"s3_post\",\"s3_get\",\"s3_head\"],\"exposeHeaders\":[\"ETag\",\"x-bz-content-sha1\"],\"maxAgeSeconds\":3600}]" replaceWithBucketName allPublic
```

| Field | Notes |
|-------|--------|
| `corsRuleName` | Label only (`ETagUploader`) |
| `allowedOrigins` | Real app origin(s); JSON array inside the escaped string |
| `allowedOperations` | Native B2 + S3-compatible ops used by download and multipart upload |
| `exposeHeaders` | `ETag` required for multipart; `x-bz-content-sha1` is B2-specific |

CLI version, auth subcommand names, and bucket visibility flags differ by install — if the update fails, check `b2 bucket update --help` for your binary. Success = browser multipart PUT returns readable `ETag`; failure usually surfaces as CORS preflight errors or missing `ETag` in JS.

Other S3-compatible vendors (Wasabi, MinIO, custom): same idea — allow origin + PUT/GET/HEAD + expose `ETag`. Exact JSON/CLI shape is vendor-specific.

### Public — no auth

| Method | Path |
|--------|------|
| GET | `/api/v1/file-hosting/public/files/{id}` |
| GET | `/api/v1/file-hosting/public/files/{id}/download` |
| GET | `/api/v1/file-hosting/public/folders/{id}` |

## Vue-Workers SPA consumer

The sibling Vue-Workers SPA **is** the File Hosting UI. It consumes `/api/v1/file-hosting/*` and uses `useFhHydrate` for initial reads. `hydrateFileHosting()` batches browse, quota, workspaces, and Microstock slots; `hydrateWorkspaceDetail()` batches workspaces plus members and activity for workspace detail. Per-entry failures fall back to the individual loaders. Public share routes are `/s/f/:id` and `/s/d/:id`. Admin DMCA blocklist is Vue route `file-hosting.blocklist.index.view` so MenuSeeder `file-hosting.blocklist.index` resolves in `NavTree`. See [vue-workers-spa.md — Invocation budget controls](../../integration/vue-workers-spa.md#invocation-budget-controls).

## Historical Blade web (removed)

The tables and Vue-island notes below describe the **deleted** in-repo Blade + Vite island. Do not recreate them in Monolith. Live browse/trash/show/workspaces are SPA routes (`file-hosting.index`, `.trash`, `.show`, `.workspaces`, `.blocklist.index`). Guest share stays on Laravel `web`: `/s/f/{uuid}`, `/s/d/{uuid}`.

| Route name | Auth | Purpose |
|------------|------|---------|
| `file-hosting.index` | yes | Browse + chunk upload; list/grid; Drive-like select/context/drag; bulk **Move**/share/unshare/delete; SPA soft-nav (no hard reload on mutations) |
| `file-hosting.files.show` | yes | Detail, stats tiles, visibility, download CTA |
| `file-hosting.files.download` | yes | Auth download -> **302** storage (Worker or S3). Calls `FileService::download` directly (no HTTP loopback); `?preview=1` yields `inline` disposition for safe MIME |
| `file-hosting.files.rename` | yes | Rename file (AJAX or form) |
| `file-hosting.files.move` | yes | Move file to folder (AJAX) |
| `file-hosting.files.visibility` | yes | Toggle private / anyone_with_link |
| `file-hosting.files.bulk-visibility` | yes | Bulk set visibility (`ids[]`, `visibility`) — AJAX |
| `file-hosting.files.bulk-destroy` | yes | Bulk soft-delete (`ids[]`) — AJAX |
| `file-hosting.files.restore` | yes | Restore from trash |
| `file-hosting.files.force-destroy` | yes | Permanently delete |
| `file-hosting.folders.rename` | yes | Rename folder |
| `file-hosting.folders.move` | yes | Move folder |
| `file-hosting.folders.visibility` | yes | Toggle folder visibility |
| `file-hosting.folders.bulk-visibility` | yes | Bulk set visibility (`ids[]`, `visibility`) — AJAX |
| `file-hosting.folders.bulk-destroy` | yes | Bulk soft-delete (`ids[]`) — AJAX |
| `file-hosting.folders.restore` | yes | Restore from trash |
| `file-hosting.folders.force-destroy` | yes | Permanently delete |
| `file-hosting.trash` | yes | Soft-deleted items + restore / force-delete; JSON when `Accept: application/json` |
| `file-hosting.quota` | yes | JSON soft-refetch for the quota bar (`Accept: application/json`) |
| `file-hosting.workspaces.members.index` | yes | JSON members list for team offcanvas soft-refetch |
| `file-hosting.workspaces.activity` | yes | JSON activity feed for team offcanvas soft-refetch |
| `file-hosting.search` | yes | AJAX JSON search endpoint (`GET ?q=`) |
| `file-hosting.public.files.show` | no | `/s/f/{uuid}` guest page |
| `file-hosting.public.files.download` | no | **Temporary signed** hop -> **302** storage (unsigned **403**); `?preview=1` yields `inline` for safe MIME |
| `file-hosting.public.folders.show` | no | `/s/d/{uuid}` guest folder list |

| `public_download_signature_ttl_minutes` | Laravel signed hop for `/s/f/.../download` (Monolith hop only; storage URL has its own TTL) |

### UI architecture / SPA shell (historical)

The in-repo Vite Vue 3 island (`#vue-file-hosting`, `FileManagerController` BFF) has been **removed**. The notes below are archive-only; the live shell is `monolith-vue-workers`.

**Fair Vue boundary (as reasoned then):** a *Vue island* was a page-level mount with its own lifecycle — not every helper. Auth browse was one island; pure helpers (router, upload math, `moveItemsRequest`, Echo glue, selection helpers) stayed plain JS, and interactive leftovers under auth browse became components/composables on that island (`useFhUpload`, `useFhDragMove`). Public `/s/*` stayed vanilla. The standalone Microstock usage page was never an island.

**First paint (Blade era):** Blade supplied AdminLTE chrome plus an empty `#vue-file-hosting` mount and a JSON bootstrap script; listing/toolbar/upload were client Vue, not SSR rows. All of that is gone — the SPA owns first paint now.

The asset table below records what the removed island was made of. **None of these paths exist**; they are here only to explain how behavior migrated.

| Asset (deleted) | Role |
|-------|------|
| `resources/css/file-hosting/file-hosting.css` | BEM-scoped styles (`.fh-*`), theme-aware via `--fh-*` tokens + `[data-bs-theme="dark"]` |
| `resources/js/file-hosting/router.js` | Client router: parse auth FH paths via `/file-hosting` segment (prefix-agnostic, works under `/apps/…`) + public `/s/*`; `resolveFhBase()` (Blade `fhBase` → `browseUrl` → location → `''`); `joinFhPath()` fail-closed joins; intercept in-app links; **pending-nav queue** while busy (latest intent wins — rapid clicks / back-forward are not swallowed) |
| `resources/js/file-hosting/selection.js` | Pure Drive selection helpers (`listingKeys`, `rangeKeys`, `applyBrowseClick`) — used by Vue `App.vue` |
| `resources/js/vue/islands/file-hosting/main.js` | Vite entry: bootstrap guard + `mountIsland('#vue-file-hosting', App)` — loaded from index/trash/show Blade |
| `resources/js/vue/islands/file-hosting/App.vue` | **Vue 3 SFC shell:** `bootstrap` prop (`config`, `browse`, `trash`, `showFile`, `quota`, `workspaces`, `ui`, `upload`). `useFhShell` + `useFhMutations` + `useFhDragMove`; FH router; Echo via `initFileHostingRealtime`; Microstock panel/usage when Blade offcanvas DOM exists; soft-nav browse/trash/show; bulk bar; context menu; Vue modals (folder/rename/share/move); Drive select/gestures + drag-move onto folder/breadcrumb/parent `...`. Blade Bootstrap surfaces (`#fhCollectionDescribeModal`, `#fhMicrostockPanel`, `#fhTeamOffcanvas`, `#fhCreateWorkspaceModal`) via `window.bootstrap` |
| `resources/js/vue/islands/file-hosting/composables/` | `useFhApi` (BFF fetch), `useFhShell` (`hasBrowseChrome` / soft-nav gate), `useFhMutations`, `useFhUpload` (Vue queue UI), `useFhDragMove` (gestures → `moveItemsRequest`) |
| `resources/js/vue/islands/file-hosting/components/` | `FhIcon.vue`, `FhDropzone.vue` (Vue-owned queue + OS drop; props `softRefresh` / `upsertFile` — no `window.fileHosting*` bridge) |
| `resources/js/file-hosting/realtime.js` | Dedicated FH Echo subscribe (user/workspace private channels), health + fallback poll; `browseUrlWithContext` / `fetchJson` |
| `resources/js/file-hosting/direct-upload.js` | Pure helpers + legacy `initDirectUpload` (auto-init skips `[data-fh-vue-upload]`); auth island uses `run-direct-upload.js` via `useFhUpload` |
| `resources/js/file-hosting/run-direct-upload.js` | Framework-agnostic init→PUT→complete protocol shared by Vue upload |
| `resources/js/file-hosting/drag-move.js` | `FH_ITEMS_MIME`, `canDropOnFolder`, `moveItemsRequest` — stay JS; gestures live in Vue |
| `resources/js/file-hosting/microstock-panel.js` / `microstock-usage.js` / `microstock-ui.js` / `collection-describe.js` | Microstock review/usage/helpers — wired from Vue when DOM present (Blade offcanvas factories OK; not separate islands) |
| `resources/js/file-hosting/public-app.js` | Public soft SPA for `/s/f/{id}` + `/s/d/{id}` (vanilla; optional future island only if deliberately invested) |
| `resources/js/file-hosting/upload-progress.js` / `upload-queue.js` / `part-uploader.js` | Pure upload helpers — stay JS |
| `resources/views/adminlte4/file-hosting/partials/` | Blade shells that surrounded the island: `workspace-modals`, `microstock-review-panel`, `usage-offcanvas`, `file-folder-modals`, `team-offcanvas`, `move-modal`, `file-icon`, `visibility-badge`, `microstock-badge`, `quota-bar`, `workspace-switcher`. Inventory of the whole removed set: [partial-manifest.md](../../web/partial-manifest.md) |

#### Soft-nav rules (auth)

| Route | View panel | Data |
|-------|------------|------|
| `/apps/file-hosting` (+ `folder_id`, `workspace_id`, `user_id`) | `browse` | `GET file-hosting` Accept JSON (existing browse payload) |
| `/apps/file-hosting/trash` | `trash` | `GET file-hosting/trash` Accept JSON |
| `/apps/file-hosting/files/{uuid}` | `show` | `GET file-hosting/files/{uuid}` Accept JSON (**dual response**) |
| Workspace switcher links | browse + Echo rebind | same browse JSON; channel flips `user.*` ↔ `workspace.*` |

**Always full navigation:** file download hops (`…/download` → signed storage URL: Worker HMAC or S3), non-FH routes, plans/services links, and **cold trash/show → browse** when `hasBrowseChrome(config)` is false — gate is **non-empty `config.workspacesUrl` only** (`useFhShell`). Cold trash/show bootstraps omit that URL (and strip upload/ui chrome); soft-nav to browse would keep stripped config, so the island forces a full document load. Warm path (land on index first — index injects `workspacesUrl` — then soft-nav trash/show and back) keeps soft-nav.

**Mutations (historical Blade island):** all FH forms/buttons used `fetch` + `Accept: application/json`. The island forwarded API `data` so the shell could soft-update. Live mutations hit `/api/v1/file-hosting/*` from the SPA.


#### Drive-like listing interactions (browse only)

| Gesture | Behavior |
|---------|----------|
| Click row/card (not checkbox/actions) | Select only that item |
| Ctrl/Cmd+click | Toggle multi-select |
| Shift+click | Range select in visible list/grid order |
| Click name/icon | Select (does **not** open) |
| Double-click name/icon/row | Open (folder browse / file show via SPA router) |
| Right-click item | Select if needed + context menu (Open, Rename, Move, Share, Delete) |
| Drag selected onto a **folder** row/card | Move into that folder (server auto-renames display-name collisions) |
| Drag selected onto **breadcrumb** parent / root chip | Move up (`data-fh-drop-folder`; root → `folder_id`/`parent_id` null) |
| Nested listing first row/card **`...`** | Virtual parent folder (not a real model): open = go up; drag onto it = move up; no checkbox/bulk/rename/share/delete |
| Escape | Clear selection + close context menu |

Checkbox + bulk bar (**Move** / share / unshare / delete) and per-row toolbar remain. Trash/public pages are out of scope.

#### Action surface parity (single ↔ bulk)

Listing mutations must stay **in lockstep** across surfaces. When shipping a new mutating action, implement **both** per-row/context **and** bulk (bar + multi context) unless the action is inherently single-entity — then mark **N/A** here.

| Action | Single row / context (1 item) | Bulk bar / multi context | Notes |
|--------|-------------------------------|--------------------------|--------|
| Open | yes | N/A | navigation only |
| Rename | yes | N/A | needs one name |
| Move | yes | yes (bar + multi context + drag) | sequential `PATCH …/move`; display-name auto-rename on collision |
| Share / Make private | yes | yes | bulk visibility endpoints |
| Delete | yes | yes | bulk destroy endpoints |

**Agent/dev rule:** do not add a row-only mutator and leave the bulk bar lagging. Open/Rename stay N/A by design; everything else that changes storage state needs a multi-select path (or an explicit table update explaining why not).

**Busy UI:** mutations (move/delete/forms/bulk/context share-delete) toggle `.is-fh-busy` + centered spinner overlay (distinct from soft-nav `.is-fh-loading`).

**Upload queue:** staged rows show human file size next to the name (`File.size` at pick time). Successful (**Done**) rows leave the queue after ~5s (`scheduleDoneRowDismiss` + `.is-dismissing` fade); **failed** rows stay so the user can see what went wrong.

**Manual QA:** single select highlight; ctrl multi; shift range; dblclick open; context rename/move/share/delete; bulk bar Move multi-select; drag file→folder; drag onto parent/root breadcrumb or listing `...` parent row; nested empty still shows `...`; root has no `...`; drag multi; refuse drop on self; spinner on move/delete; upload row shows size; OS file drop still uploads.
**Shell markers:** `[data-file-manager][data-fh-spa]`, panels `[data-fh-panel="browse|trash|show"]`, breadcrumb `[data-fh-breadcrumb]`, trash body `[data-fh-trash-body]`.

#### Soft-nav rules (public)

| Route | Soft? | Notes |
|-------|-------|-------|
| `/s/f/{uuid}` | Yes | `PublicShareController::showFile` dual JSON |
| `/s/d/{uuid}` | Yes | `PublicShareController::showFolder` dual JSON |
| `/s/f/{uuid}/download` | No | signed hop → storage (Worker or S3) |
| Unpaid owner / private | 404 | paid gate stays server-side (`VisibilityService`) |

#### Web BFF JSON contracts

| Endpoint | Accept JSON |
|----------|-------------|
| `GET file-hosting` | `{ data: browse }` |
| `GET file-hosting/trash` | `{ data: trash }` |
| `GET file-hosting/files/{id}` | `{ data: file resource }` |
| `GET file-hosting/workspaces` | `{ data: workspace[] }` (switcher soft rebuild) |
| `GET file-hosting/quota` | `{ data: quota }` |
| Mutations (`POST`/`PATCH`/`DELETE` …) | `{ status, message, data?, redirect? }` — SPA ignores redirect when shell bound |
| `GET /s/f/{id}`, `GET /s/d/{id}` | `{ data: … }` guest payload |

Auth index/trash/show Blade injects JSON bootstrap (`data-vue-file-hosting`) with permissions/labels/URLs and first-paint snapshots. After mount, listing/trash/show paint through **one Vue path** (`useFhShell` soft-refetch helpers). Permissions/labels come from that bootstrap `config` (not a Blade `data-config` listing shell).

**Upload progress UI (client-only):** Monolith never sees PUT body bytes (direct S3 or Worker). Progress % = `uploadedBytes / file.size` where `uploadedBytes` is the **sum of per-part loaded bytes** (a Map keyed by part number, so parallel re-PUTs and out-of-order completion never double-count). Speed = a **sliding-window (5s) instantaneous rate** via `createRateSampler` — not a cumulative average — so it recovers after a stall instead of drifting down for the rest of the upload. Multipart parts upload **in parallel** (`max_concurrent_parts`, default 3 XHR streams via `uploadPartsParallel` + `runWithConcurrency`) — a strictly sequential single stream collapses to per-connection throughput on long files (the 20→10→1 MB/s decay). Init presigns **every** part upfront (`presign_ttl_minutes`, default 60); a PUT that returns **403 (expired URL)** is re-signed and retried once per part. After the last part PUT, the row shows **Finalizing…** (`upload.completing`) at 100% with speed cleared while the client calls `POST …/complete` (merge parts / promote `HostedFile`) — then **Done**. Successful rows stay ~**5s** (`DONE_ROW_DISMISS_MS` via `scheduleDoneRowDismiss`) with a short fade (`.is-dismissing`), then leave the queue and free the `active` slot so the list stays uncluttered; **failed** rows stay so the user can see what went wrong. Ongoing (pending / uploading / finalizing) rows always stay. API session payloads do not include progress or speed fields.

Live mutations go through `/api/v1/file-hosting/*`. The historical Blade island used `FileManagerController` + `ConsumesApi` as a web BFF — that path is gone.

File type icons map MIME -> Bootstrap Icons via `file-icon.blade.php` partial: image, PDF, video, audio, archive, spreadsheet, document, code, fallback — each with a color modifier (`.fh-icon--*`).

Grid/list view toggle persists preference in `localStorage` key `fh-view-mode`. Default: list (table).

Row identity for live patch: `data-fh-entity` (`file`|`folder`) + `data-fh-id` on table rows and grid cards. Quota mount: `[data-fh-quota]`. Show counters: `[data-fh-stat="views|downloads"]`. Team offcanvas: `[data-fh-members-body]`, `[data-fh-activity-list]`.

Sidebar: **Products → File Hosting → My Files** (`file-hosting.index`). The File Hosting parent stores the same product-bound route for Domain Access and activation; presented navigation masks it to `#` while children remain visible.

## Realtime / live listing

File Hosting listing stays live via **dedicated private channels**. It does **not** use the notification bell, `NotificationType`, or DataTable `table_id` (those remain for `/apps/users` and similar admin lists — see [notification/README.md](../notification/README.md)).

**With the SPA shell:** Echo events invalidate the current view; the client soft-refetches JSON and re-paints via the same helpers as post-mutation success ([UI architecture / SPA shell](#ui-architecture--spa-shell)). Router owns in-app navigation; download hops stay full page.

### Gate

| Check | Behavior |
|-------|----------|
| `BroadcastConnection::isConfigured()` | Pusher/Reverb credentials present -> events may broadcast |
| Platform notification delivery | **Independent.** FH listing can use websockets while the bell stays on HTTP polling (`platform.notifications.delivery` in Settings → Platform) |
| Client config | `BroadcastConnection::socketClientConfig()` / `FileHostingRealtime::clientConfig()` — same public fields as the bell, without requiring notification realtime mode |

When unconfigured, services still complete business work; no broadcast is dispatched. The browser falls back to a visible-tab soft-refetch poll (~60s after ~30s degrade grace), same health shape as the notification docs.

### Channels (`routes/channels.php`)

| Channel | Auth | Payload fan-out |
|---------|------|-----------------|
| `private-file-hosting.user.{userId}` | `$user->id === $userId` | Personal drive listing + storage-owner **quota** pings |
| `private-file-hosting.workspace.{workspaceId}` | `WorkspaceAccessService::canView` (active member/owner/staff view) | Team listing, members, activity, team file stats |

Personal resources (`workspace_id` null) -> user channel of storage owner. Team resources -> workspace channel only (not every member's user channel).

### Events (`app/Domain/FileHosting/Events/`, `ShouldBroadcastNow`)

| Event | `broadcastAs` | Payload (contract) |
|-------|---------------|--------------------|
| `ListingChanged` | `listing.changed` | `action`, `entity_type` (`file`\|`folder`), `entity_id`, `entity_ids[]` (bulk), `owner_user_id`, `workspace_id`, `folder_id`, `from_folder_id`, `actor_id`, optional `resource` snapshot |
| `QuotaChanged` | `quota.changed` | `owner_user_id` (client soft-refetches quota JSON) |
| `WorkspaceMembersChanged` | `members.changed` | `workspace_id`, `action` (`invited`\|`joined`\|`removed`\|`role_changed`\|`archived`), `user_id`, `actor_id` |
| `WorkspaceActivityAppended` | `activity.appended` | `workspace_id`, `actor_id` (invalidation; client refetches activity) |
| `FileStatsChanged` | `file.stats.changed` | `file_id`, `view_count`, `download_count` (show page only) |
| `CollectionAnalysisProgress` (Microstock) | `collection.analysis.progress` | `folder_id`, `workspace_id`, `owner_user_id`, `status`, `phase`, `billing_tier`, `total`, `processed`, `failed`, … |
| `AssetMetadataProgress` (Microstock) | `asset.metadata.progress` | `file_id`, `folder_id`, `microstock` snapshot (`status`, `stages`, `phase`, …) |

**Bulk ops:** one `listing.changed` with `action: bulk_*` + `entity_ids[]` (invalidation / soft-refetch), not N broadcasts.

Helper: `App\Domain\FileHosting\Support\FileHostingRealtime` — single gate + resource snapshot + activity fan. Microstock AI progress uses `App\Domain\Microstock\Support\MicrostockRealtime` on the same channels (throttled ~1 s per folder/asset). Services emit **after successful persist only** (no Eloquent observers for business mutations).

### Emit points (side-effect only; business rules unchanged)

| Service | When |
|---------|------|
| `FileService` | visibility, rename, move, soft/force delete, restore, bulks; view/download -> `FileStatsChanged` only |
| `FolderService` | create, visibility, rename, move, soft/force delete, restore, bulks |
| `DirectUploadService` | initiate/abort -> quota; complete (after commit) -> listing `create` + quota |
| `WorkspaceService` | invite, accept, role change, remove, archive -> members + activity |

### Client strategy

1. Inject `realtime` (`broadcast`, `userId`, `enabled`, `page`, …) into the Vue island bootstrap (`config.realtime` on index / trash / show) when socket config exists.
2. Prefer **one** browser Echo (`window.__monolithEcho`) shared with the notification bell and payment checkout (`checkout.js` / `PaymentRealtime`) when those surfaces are active; FH creates its own Echo when the bell is absent.
3. Subscribe to personal or workspace channel based on `workspaceId` / `ownerUserId`.
4. **Apply:** soft-refetch browse/trash/show/quota/members/activity JSON via `useFhShell` helpers (correctness floor). Listing patch is **not** used — Vue wires `onListingPatch: () => false`. Row identity attrs (`data-fh-entity` / `data-fh-id`) remain for drag-move / a11y, not for a live DOM-patch path.
5. Replace hard `location.reload()` after mutations/upload complete with soft-refetch when the island is bound.
6. Health: WS primary → 30s grace → visible-tab poll ~60s; reconnect stops poll + one catch-up soft-refetch.

### Not live

- S3 **byte** upload progress (still client-only XHR; peers do not see mid-upload bytes).
- Public share pages (`/s/f`, `/s/d`) — guest SSR, no authenticated Echo.
- Notification bell unread / `table_id` DataTables.

## Config highlights

### Multi-disk storage

| Disk | Role |
|------|------|
| `s3FileHostingCe` | Primary CE region (`FILE_HOSTING_S3_CE_*`) |
| `s3FileHostingUs` | Optional US region (`FILE_HOSTING_S3_US_*`) |

Region keys for new uploads: `ce` (Central European), `us`. Client field name is still `disk` but the **value is the region key**; `DirectUploadService::resolveDiskName()` maps to the Laravel disk name stored in DB.

Region UI (list column, detail badge, soft-refetch): `RegionResolver::shouldDisplayRegion()` — true when `count(configured regions) > 1` **or** historical distinct disks on `HostedFile` (with trashed) > 1 after alias collapse.

### Tier A — env / deploy

| Key | Role |
|-----|------|
| `disk` / `directory` | Default Laravel disk for new uploads (`FILE_HOSTING_DISK`, default `s3FileHostingCe`) + key prefix (`FILE_HOSTING_DIRECTORY`, default `file-hosting`). Prefix is **not** stored in DB `path`. |
| `regions` | Region key -> disk name map (`ce` -> `s3FileHostingCe`, optional `us`); only regions with non-empty disk appear in upload dropdown. Labels: `lang/*/web/file-hosting.php` (`Central European`, etc.). |
| `disk_aliases` | Optional disk name -> canonical disk map for region reverse-lookup and historical multi-disk collapse |
| `upload_proxy.*` | Optional CF Worker hybrid byte path for **upload + download** (`base_url`, `token`, `disk_targets`) — bound from `FILE_HOSTING_UPLOAD_PROXY_*` env vars |

### Tier B — Settings → File Hosting

Edit via `/apps/file-hosting/settings`; runtime reads `setting_config()`. See [settings.md — File Hosting](../../platform/settings.md#file-hosting-file-hosting).

| Registry key | Default | Role |
|--------------|---------|------|
| `file-hosting.disabled_regions` | `[]` | Region keys blocked for new uploads only (downloads still served) |
| `file-hosting.chunk_size_bytes` / `file-hosting.multipart_threshold_bytes` | 8 MiB each | Upload strategy |
| `file-hosting.max_file_size_bytes` | 5 GiB | Per-file upload cap |
| `file-hosting.max_files_per_batch` | 20 | Batch upload cap |
| `file-hosting.default_quota_bytes` | 5 GiB | Free-tier base always stacked; also used when no `UserQuota` denorm row |
| `file-hosting.base_owned_workspace_slots` | 10 | Owned team workspaces included with paid storage |
| `file-hosting.max_concurrent_uploads` | 2 | Server-side cap on pending non-expired upload sessions per storage owner |
| `file-hosting.max_concurrent_parts` | 3 | Concurrent part XHR streams within one file; lower to 2 if 520s increase |
| `file-hosting.max_presigned_parts_per_response` | 100 | Part URLs signed per init response |
| `file-hosting.presign_ttl_minutes` | 60 | Presigned part-URL lifetime; init signs every part upfront |
| `file-hosting.session_ttl_hours` | 24 | Pending upload session TTL |
| `file-hosting.stale_upload_grace_hours` | 6 | Extra grace after session expiry before `file-hosting:prune-stale-uploads` aborts and reclaims multipart storage |
| `file-hosting.orphan_inactivity_minutes` | 180 | Reclaim pending **multipart** sessions with no recent S3 part activity (`ListParts` `LastModified` is truth; error skips candidate). Single-PUT sessions never pruned by this arm |
| `file-hosting.signed_download_ttl_minutes` | 60 | Cached download URL lifetime (Worker HMAC `exp` or S3 temp URL) |
| `file-hosting.signed_download_cache_margin_seconds` | 60 | Treat cache expired early |
| `file-hosting.public_download_signature_ttl_minutes` | 5 | Laravel signed hop for `/s/f/.../download` (Monolith hop only) |
| `file-hosting.allowed_mime_prefixes` | image/application/text/audio/video | Allowed MIME prefixes (empty = allow all non-empty client MIME) |
| `file-hosting.download_vault.enabled` | `false` | Vault download path when hybrid proxy is on (`true` on local via `settings-local.php`) |

## Quota

| Source | Behavior |
|--------|----------|
| `config('file-hosting.default_quota_bytes')` | Free tier **always stacked** on paid main + addons (typically 5 GiB) |
| `file_hosting_user_quotas` | Denorm after recompute: `quota_bytes` = free + main + Σ(addon×qty), `source=service_recompute` |
| `FileHostingQuotaRecomputeService` | Single source of truth write on fulfill / expire |

### Effective quota formula

```
effective_bytes =
  free_tier_bytes
  + (active_or_grace paid main.storage_bytes)   # at most one
  + sum(active_or_grace addon.storage_bytes * quantity)
```

| `state` | Condition | Upload |
|---------|-----------|--------|
| `paid` | Any active paid FH main/addon entitlement | OK (numeric cap) |
| `grace` | Any paid FH line in grace | **blocked** (`quota_grace_blocked`); files kept |
| `free` | No paid active/grace lines | free tier only |

Runtime `QuotaService::quotaBytesFor` reads denorm `UserQuota` (written on fulfill/expire). No unlimited short-circuit (`quota_bytes === 0` reserved only if true unlimited is reintroduced later).

**Committed bytes** = `SUM(file_hosting_files.size)` + `SUM(file_hosting_file_variants.size)` where variant `status = ready` (Microstock AI JPEG sidecars `ai_512` / `ai_1024`). Pending/failed variants do not reserve quota until promoted to `ready`.

On paid [Service](../service/README.md) fulfill: main replace / addon quantity + recompute. On grace expire: recompute remaining lines + `EnforceFreeTierQuotaService::enforceToLimit(effective)` (oldest-first; soft-delete DB + hard-delete S3).

**Purge rule (example):** files A 2 GiB (oldest), B 3 GiB, C 5 GiB with limit 5 GiB -> keep A+B (exactly 5 GiB), soft-delete C in DB and hard-delete C object on S3. Under-limit users are no-op. Re-run is idempotent.

Scheduler entry point: `php artisan service:process-entitlements` (hourly).

## Tests

**Production qualification suite (50 IDs, automated):** `tests/Domain/FileHosting/Qualification/FileHostingQualification{Low,Normal,Medium,Hard,Anomaly}Test.php` — method names `test_FH_L01_…` … `test_FH_A10_…`. Run: `composer test -- --filter=FileHostingQualification`.

```bash
composer test -- --filter=FileHosting
npm run test:js
```

| Suite | What it covers |
|-------|----------------|
| PHPUnit `--filter=FileHosting` | API feature tests (`FileHostingTest` — object-id `path`, resolved S3 key on complete/delete, display-name auto-rename), unit tests for `ObjectKeyResolver`, `S3DirectUploadEngine` proxy rewrite + 8 MiB defaults, `AllocateUniqueDisplayNameTest`, web controller tests (`FileManagerControllerTest` — HTML + SPA JSON contracts for index/trash/show/workspaces + mutation `data` forward), `PublicDownloadSignedRouteTest` (unsigned 403, signed 302, public show JSON + paid gate 404). Web controller tests mock API responses via `Http::fake()`. Collab: `WorkspaceCollaborativeWorksTest` (multi-own slots, join, activity). Realtime: `FileHostingRealtimeEventsTest`, `FileHostingRealtimeTest`, `FileHostingRealtimeEmitTest`, `FileHostingChannelAuthTest` (channel routing, gate off when unconfigured, service emit after mutations). |
| Vitest `npm test` (SPA) | Client-side helpers and composables under `monolith-vue-workers/src/` (e.g. `utils/file-hosting/fhCollectionDescribe.ts`, `utils/file-hosting/fhMicrostockBadge.ts`, BFF helpers in `tests/`). The removed Monolith `resources/js/**/*.test.js` island tests are historical only — see [UI architecture / SPA shell](#ui-architecture--spa-shell). |
| CORS / quota probes | `QuotaThrottleProbeTest` + `QuotaPreflightProbeTest` (`tests/Domain/FileHosting/.../API/V1/`) — regression guards confirming `file-hosting/quota` responses (throttled 429 and `OPTIONS` preflight) carry the correct CORS headers. Added after a transient browser "CORS error" report that traced to infra, not Laravel. |
| Service / Worker | Paid / grace / purge: `composer test -- --filter=Service` and `--filter=EnforceFreeTierQuota`. Gateway uploader tests: `c:\laragon\www\gateway` (`npm test`). |
| Qualification | `composer test -- --filter=FileHostingQualification` — 50 IDs (Low/Normal/Medium/Hard/Anomaly) |

## Related

- [service/README.md](../service/README.md) — main/addon catalog, quantity, grace, recompute
- [microstock/README.md](../microstock/README.md) — first system-workspace consumer (`kind='microstock'`)
- [identity/README.md](../identity/README.md) — H-RBAC / `domain_kind`
- [notification/README.md](../notification/README.md) — bell + DataTable `table_id` (FH listing does **not** use this bus)
- [web/ui-patterns.md](../../web/ui-patterns.md) — **archived** Blade a11y / responsive notes (FH dropzone, modals); the removed island is recorded under [UI architecture / SPA shell](#ui-architecture--spa-shell)
- [platform/architecture.md](../../platform/architecture.md)

FH SPA shell is **product-scoped** (this domain only). It is not a monorepo-wide AdminLTE SPA migration.