# File Hosting — product domain

Product domain (`kind => product`, `locked => true`) for personal nested folders, S3-compatible chunk upload, and Drive-lite link sharing.

| | |
|--|--|
| Code | `app/Domain/FileHosting/` |
| Config | [`config/file-hosting.php`](../../../config/file-hosting.php) |
| API | `routes/api/v1/file-hosting.php` |
| Web (auth) | `routes/web/file-hosting.php` |
| Web (guest) | `routes/web/file-hosting-public.php` |
| Seeder | `FileHostingRoleSeeder` (after core `RoleSeeder`) |
| Docs close-out | [upload progress speed + Finalizing](../../changelog/done/2026-07-18-file-hosting-upload-progress-speed.md) · [hybrid proxy + display rename](../../changelog/done/2026-07-17-file-hosting-hybrid-proxy-display-rename.md) · [multi-region + bulk](../../changelog/pending/2026-07-16-file-hosting-multi-region-bulk.md) · [product domain](../../changelog/pending/2026-07-16-file-hosting-product-domain.md) |

## Concepts

| Concept | Behavior |
|---------|----------|
| Virtual root | `folder_id = null` = “My Files” (no auto DB folder row) |
| Nested folders | `parent_id` tree per owner; breadcrumb via `FolderAncestorChain` |
| Files | `file_hosting_files` — denormalized `user_id`, optional `folder_id` |
| Upload sessions | `file_hosting_uploads` — init → browser PUT (direct S3 **or** CF Worker proxy) → complete promotes row |
| Part size | Default **10 MB** chunk + multipart threshold (`FILE_HOSTING_CHUNK_SIZE`) |
| DB `path` | Object id only: 40 lowercase alnum chars (`Str::random(40)`). **Not** a full S3 key. |
| S3 object key | `file-hosting/{user_id}/{objectId}` — resolved at runtime via `ObjectKeyResolver` from `user_id` + `path` + config `directory`. No `folder_id`, no extension, no upload session id in the key. |
| Display name on upload | Preferred client name kept if free in that owner+folder (soft-deleted ignored). If taken → auto DB rename to `{stem} ({maxN+1}).{ext}` where `maxN` is highest active sibling index (`A.zip` = 1, `A (3).zip` = 3 → next `A (4).zip`). S3 object key stays random and is never renamed. Manual rename/move still rejects exact sibling collisions. Move/rename are **DB-only** (folder tree lives in `folder_id` / `name`). |
| Download bytes | Always **302 (or client follow)** to S3 temporary URL — **not** Monolith streaming |
| S3 signed URL cache | Columns `signed_download_url`, `signed_download_expires_at`; reuse until near expiry |
| Download filename | `ResponseContentDisposition` from DB `name` (random key stays on object) |
| Stats | `view_count` / `download_count` + last_* timestamps (no event log) |
| Visibility | `private` \| `anyone_with_link` on folders and files |
| Link secret | Resource UUID (Drive “anyone with the link”; not world-searchable) |
| Inherit | Folder link-shared → guest may browse that folder and download files inside even if file is `private` |
| Region | Per-file `disk` storage; region key derived via `RegionResolver::keyForDisk()`. `disabled_regions` blocks new uploads only — downloads still served. |
| Ownership | Own resources; cross-user via product “any” + core `RoleService` hierarchy |

## Visibility

```
folderAccessible(folder) =
  folder.visibility == anyone_with_link
  OR any ancestor.visibility == anyone_with_link

fileAccessible(file) =
  file.visibility == anyone_with_link
  OR (file.folder_id && folderAccessible(file.folder))
```

- Mutations never public.
- `share_url` only when **this** item is `anyone_with_link`.

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

| Area | Endpoints |
|------|-----------|
| Browse | `GET browse?folder_id=&user_id=` |
| Folders | create, rename, move, **visibility**, soft/force delete, restore |
| Files | show (view++), rename, move, **visibility**, soft/force delete, restore |
| Bulk | `POST files/bulk-visibility` + `POST folders/bulk-visibility` (`ids[]`, `visibility`); `DELETE files/bulk` + `DELETE folders/bulk` (`ids[]`) — owner-scoped, max 100 ids |
| Download | `GET files/{id}/download` → `{ url, expires_at }` (download++; cache S3 URL) |
| Upload | init (optional `disk` = region key; rejects disabled regions), status, sign-parts, complete (auto-renames duplicate display name), abort |
| Search / trash / quota | as named |

### Direct upload (S3 engine + optional Worker proxy)

Control plane (init / sign-parts / complete / abort) always stays on Monolith. Browser **byte** path is either:

| Mode | When | Browser PUT target |
|------|------|--------------------|
| **Direct S3** (default) | `FILE_HOSTING_UPLOAD_PROXY_URL` empty | Presigned S3 URL from `temporaryUploadUrl` / `UploadPart` |
| **Hybrid proxy** | base URL + token + disk→alias set | CF Worker `/t/{alias}/{key}` (+ `partNumber`/`uploadId` for multipart); Worker re-signs + streams to S3 |

Shared kernel pieces (not duplicated per domain):

| Piece | Role |
|-------|------|
| `App\Support\Storage\S3DirectUploadEngine` | Strategy, direct presign **or** Worker proxy URLs, merge/assert parts, create/complete/abort multipart, size/mime guards |
| `App\Support\Storage\SerializesDirectUploadSession` | Shared JSON session shape for init/status (`upload_id`, `strategy`, `parts`, …) |
| `DirectUploadService` | Domain adapter: ownership, quota, folder, multi-region disk, **auto display-name allocation** on complete, promote → `HostedFile` |

Config for thresholds/TTL/proxy: `config/file-hosting.php`.

#### Display name on complete (auto-rename)

Upload **init** no longer rejects a display name that already exists in the same owner+folder. On **complete**, `DirectUploadService::allocateUniqueDisplayName()` picks the DB `name`:

| Active siblings (soft-deleted ignored) | Client preferred | Stored `name` |
|----------------------------------------|------------------|---------------|
| none | `A.zip` | `A.zip` |
| `A.zip` | `A.zip` | `A (2).zip` |
| `A.zip` + `A (3).zip` | `A.zip` | `A (4).zip` (max index + 1; holes not reused) |
| base soft-deleted, `A (3).zip` remains | `A.zip` | `A.zip` (base free again) |

S3 object keys stay unique (`file-hosting/{user_id}/{objectId}`) and are never renamed or moved when the display name / folder changes. Manual rename/move still enforces exact sibling uniqueness. Clients must show **`file.name` from complete**, not the original client filename. API `path` is the object id only.

#### Hybrid proxy env

| Env | Role |
|-----|------|
| `FILE_HOSTING_UPLOAD_PROXY_URL` | Worker origin (no trailing slash). Empty = direct S3. Example production custom domain: `https://s3.silverspoon.me` |
| `FILE_HOSTING_UPLOAD_PROXY_TOKEN` | Same value as Worker `S3_TARGETS.uploadToken` (Bearer) |
| `FILE_HOSTING_UPLOAD_PROXY_TARGET_S3CUSTOM` | Worker target **alias** for disk `s3Custom` (must match a key under Worker `S3_TARGETS.targets`, e.g. `sbs`) |
| `FILE_HOSTING_UPLOAD_PROXY_TARGET_S3CUSTOMEU` | Alias for disk `s3Customeu` (optional) |

**Where does `/t/{alias}/…` come from?** Monolith maps the upload session disk (e.g. `s3Custom`) → env alias via `upload_proxy.disk_targets`, then builds:

`{FILE_HOSTING_UPLOAD_PROXY_URL}/t/{alias}/{object-key}?partNumber=&uploadId=`

plus headers `Authorization: Bearer …`, `X-S3-Target: {alias}`, `Content-Type`. The alias is **not** the B2 bucket name and **not** part of the S3 key; it only selects credentials/endpoint inside the Worker.

**Fail closed:** if base URL is set but token or disk alias is missing → init/sign-parts error (`proxy_misconfigured`), not silent fall-back to direct S3. Upstream S3 failures map to `upstream_failed` where applicable.

**B2 / path-style:** Monolith disk `s3Custom` must use `CUSTOM_S3_USE_PATH_STYLE=true` (default in `config/filesystems.php`) so CreateMultipart / Complete on the server hit the same path-style host as the Worker (`forcePathStyle: true` in `S3_TARGETS`). Virtual-hosted Create + path-style UploadPart can look fine until complete fails with `NoSuchUpload`.

**Worker project:** `c:\laragon\www\workers` — Wrangler name **`cf-workers-s3-proxy`**. Streaming SigV4 proxy; production custom domain example `https://s3.silverspoon.me`. See that repo’s README. Spec history: [chore-now.md](./chore-now.md).

**Deploy / secrets (ops):**

| Step | Note |
|------|------|
| Runtime secret | `S3_TARGETS` is a **Worker secret**, not git, not `.dev.vars` on CF. Set with `npx wrangler secret put S3_TARGETS --name cf-workers-s3-proxy` (name must match deployed Worker). |
| Dashboard Variables | Must be on the **same** Worker that serves the public hostname. UI “Variables” on another Worker / build env only → `/health` returns config missing. |
| Alias rename | Change key + `defaultTarget` in `S3_TARGETS` **first**, then Monolith `FILE_HOSTING_UPLOAD_PROXY_TARGET_*`, then `php artisan config:clear`. In-flight sessions still using the old `/t/old-alias/…` may 400. |
| Error bodies | Production Worker returns generic messages only (`Configuration invalid or missing`, `Internal server error`). Details stay in Workers Logs (`console.error`). |

**Hybrid protocol (parts[] response):**

- `url` = `{proxy}/t/{alias}/{encoded-key}` or with `?partNumber=&uploadId=` for multipart
- `headers` = multi-value map: `Authorization: Bearer …`, `X-S3-Target`, `Content-Type`
- Create/complete/abort still use Monolith disk credentials against real S3 (not the Worker)
- Client `direct-upload.js` accepts header values as string or single-element array

### S3 provider CORS (browser-direct multipart)

When proxy is **off**, browser uploads **directly** to the S3-compatible endpoint (presigned PUT / UploadPart). That is a cross-origin request from the app origin (Blade admin or SPA) to the bucket host. Without bucket CORS, the browser blocks the PUT and may hide `ETag` — multipart **complete** then cannot send part ETags.

When proxy is **on**, browser talks to the **Worker** origin — Worker CORS must allow app origin(s) and **expose `ETag`**. Bucket CORS is still needed for downloads (and for direct mode).

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

Add every origin that performs browser-direct upload (local dev, staging, production). Multiple origins = multiple entries in `AllowedOrigins`.

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

## Web Blade

| Route name | Auth | Purpose |
|------------|------|---------|
| `file-hosting.index` | yes | Browse + chunk upload; list/grid view, search, Rename / Move / Share / Delete |
| `file-hosting.files.show` | yes | Detail, stats tiles, visibility, download CTA |
| `file-hosting.files.download` | yes | Auth download → **302** S3 |
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
| `file-hosting.trash` | yes | Soft-deleted items + restore / force-delete |
| `file-hosting.search` | yes | AJAX JSON search endpoint (`GET ?q=`) |
| `file-hosting.public.files.show` | no | `/s/f/{uuid}` guest page |
| `file-hosting.public.files.download` | no | **Temporary signed** hop → **302** S3 (unsigned **403**) |
| `file-hosting.public.folders.show` | no | `/s/d/{uuid}` guest folder list |

Share link users copy = show URL (`/s/f/{uuid}` or `/s/d/{uuid}`). Download button on the show page is a short-lived Laravel signed URL (`public_download_signature_ttl_minutes`, default 5).

### UI architecture

| Asset | Role |
|-------|------|
| `resources/css/file-hosting/file-hosting.css` | BEM-scoped styles (`.fh-*`), theme-aware via `--fh-*` tokens + `[data-bs-theme="dark"]` |
| `resources/js/file-hosting/file-manager.js` | Page controller: AJAX CRUD, live search, grid/list view toggle (localStorage), move modal tree, drag-drop, region selector, bulk select/action (per-row + select-all checkboxes, share/unshare/delete), toast |
| `resources/js/file-hosting/direct-upload.js` | Chunked upload: XHR PUT to server-provided part URLs (direct S3 or Worker proxy headers); **byte-level** progress via `xhr.upload.onprogress` + **session-average transfer rate** (shown next to bar); status **Finalizing…** while `POST …/complete` runs |
| `resources/js/file-hosting/upload-progress.js` | Pure helpers: `computeTransferRate`, `formatTransferRate` (B/s–GB/s, 1024 base), `computeByteProgressPercent` — no DOM |
| `resources/js/file-hosting/upload-queue.js` | Concurrency limiter (unchanged) |
| `resources/views/adminlte4/file-hosting/partials/` | Reusable Blade partials: `_file-icon`, `_visibility-badge`, `_quota-bar`, `_move-modal` |

**Upload progress UI (client-only):** Monolith never sees PUT body bytes (direct S3 or Worker). Progress % = `uploadedBytes / file.size`; speed = `uploadedBytes / elapsed` from first byte PUT to now (not a rolling window). Parts still upload sequentially per file. After the last part PUT, the row shows **Finalizing…** (`upload.completing`) at 100% with speed cleared while the client calls `POST …/complete` (merge parts / promote `HostedFile`) — then **Done**. API session payloads do not include progress or speed fields.

All mutations go through `FileManagerController` web routes via `fetch`; the controller forwards to the API via `ConsumesApi`. AJAX responses use the standard JSON envelope (`status`, `message`, `redirect`).

File type icons map MIME → Bootstrap Icons via `_file-icon.blade.php` partial: image, PDF, video, audio, archive, spreadsheet, document, code, fallback — each with a color modifier (`.fh-icon--*`).

Grid/list view toggle persists preference in `localStorage` key `fh-view-mode`. Default: list (table).

Menu: **My Files** → `file-hosting.index`.

## Config highlights

| Key | Role |
|-----|------|
| `disk` / `directory` | S3-compatible disk + key prefix (`FILE_HOSTING_DIRECTORY`, default `file-hosting`). Prefix is **not** stored in DB `path`. |
| `regions` | Region key → disk name map (e.g. `sea` → `s3Custom`); only regions with non-empty disk appear in upload dropdown |
| `disabled_regions` | Comma-separated region keys blocked for new uploads only (downloads still served) |
| `chunk_size_bytes` / `multipart_threshold_bytes` | Upload strategy (default **10 MiB** each) |
| `upload_proxy.*` | Optional CF Worker hybrid byte path (`base_url`, `token`, `disk_targets`) |
| `default_quota_bytes` | Free-tier base always stacked (typically 5 GiB); also used when no `UserQuota` denorm row |
| `signed_download_ttl_minutes` | S3 GET temporary URL lifetime |
| `signed_download_cache_margin_seconds` | Treat cache expired early |
| `public_download_signature_ttl_minutes` | Laravel signed hop for `/s/f/.../download` |

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

On paid [Service](../service/README.md) fulfill: main replace / addon quantity + recompute. On grace expire: recompute remaining lines + `EnforceFreeTierQuotaService::enforceToLimit(effective)` (oldest-first; soft-delete DB + hard-delete S3).

**Purge rule (example):** files A 2 GiB (oldest), B 3 GiB, C 5 GiB with limit 5 GiB → keep A+B (exactly 5 GiB), soft-delete C in DB and hard-delete C object on S3. Under-limit users are no-op. Re-run is idempotent.

Scheduler entry point: `php artisan service:process-entitlements` (hourly).

## Tests

```bash
composer test -- --filter=FileHosting
npm run test:js
```

| Suite | What it covers |
|-------|----------------|
| PHPUnit `--filter=FileHosting` | API feature tests (`FileHostingTest` — object-id `path`, resolved S3 key on complete/delete, display-name auto-rename), unit tests for `ObjectKeyResolver`, `S3DirectUploadEngine` proxy rewrite + 10 MiB defaults, `AllocateUniqueDisplayNameTest`, web controller tests (`FileManagerControllerTest`), `PublicDownloadSignedRouteTest` (unsigned 403, signed 302). Web controller tests mock API responses via `Http::fake()`. |
| Vitest `npm run test:js` | Pure client helpers in `resources/js/file-hosting/upload-progress.test.js` (transfer rate, format, byte percent). Pattern: `resources/js/**/*.test.js`. |
| Service / Worker | Paid / grace / purge: `composer test -- --filter=Service` and `--filter=EnforceFreeTierQuota`. Worker unit tests: `c:\laragon\www\workers` (`npm test`). |

## Related

- [service/README.md](../service/README.md) — main/addon catalog, quantity, grace, recompute
- [identity/README.md](../identity/README.md) — H-RBAC / `domain_kind`
- [platform/architecture.md](../../platform/architecture.md)
- [integration/spa-gateway.md](../../integration/spa-gateway.md) — SPA path via Gateway
