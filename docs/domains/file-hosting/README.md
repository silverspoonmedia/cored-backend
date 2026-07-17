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
| Docs close-out | [multi-region + bulk](../../changelog/pending/2026-07-16-file-hosting-multi-region-bulk.md) · [product domain](../../changelog/pending/2026-07-16-file-hosting-product-domain.md) |

## Concepts

| Concept | Behavior |
|---------|----------|
| Virtual root | `folder_id = null` = “My Files” (no auto DB folder row) |
| Nested folders | `parent_id` tree per owner; breadcrumb via `FolderAncestorChain` |
| Files | `file_hosting_files` — denormalized `user_id`, optional `folder_id` |
| Upload sessions | `file_hosting_uploads` — init → browser PUT (direct S3 **or** CF Worker proxy) → complete promotes row |
| Part size | Default **10 MB** chunk + multipart threshold (`FILE_HOSTING_CHUNK_SIZE`) |
| S3 object key | `file-hosting/{user_id}/{upload_id}/{random40}.{ext}` — display name only in DB |
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
| Upload | init (optional `disk` = region key, resolved via `RegionResolver`; rejects disabled regions), status, sign-parts, complete, abort |
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
| `DirectUploadService` | Domain adapter: ownership, quota, folder, multi-region disk, promote → `HostedFile` |

Config for thresholds/TTL/proxy: `config/file-hosting.php`.

#### Hybrid proxy env

| Env | Role |
|-----|------|
| `FILE_HOSTING_UPLOAD_PROXY_URL` | Worker origin (no trailing slash). Empty = direct S3 |
| `FILE_HOSTING_UPLOAD_PROXY_TOKEN` | Same value as Worker `S3_TARGETS.uploadToken` (Bearer) |
| `FILE_HOSTING_UPLOAD_PROXY_TARGET_S3CUSTOM` | Worker target alias for disk `s3Custom` |
| `FILE_HOSTING_UPLOAD_PROXY_TARGET_S3CUSTOMEU` | Alias for disk `s3Customeu` (optional) |

**Fail closed:** if base URL is set but token or disk alias is missing → init/sign-parts error (`proxy_misconfigured`), not silent fall-back to direct S3.

**Worker project:** `c:\laragon\www\workers` — streaming SigV4 proxy; see that repo’s README. Spec history: [chore-now.md](./chore-now.md).

**Hybrid protocol (parts[] response):**

- `url` = `{proxy}/t/{alias}/{encoded-key}` or with `?partNumber=&uploadId=` for multipart
- `headers` = multi-value map: `Authorization: Bearer …`, `X-S3-Target`, `Content-Type`
- Create/complete/abort still use Monolith disk credentials against real S3 (not the Worker)

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
| `resources/js/file-hosting/direct-upload.js` | Chunked upload: PUT to server-provided part URLs (direct S3 or Worker proxy headers) |
| `resources/js/file-hosting/upload-queue.js` | Concurrency limiter (unchanged) |
| `resources/views/adminlte4/file-hosting/partials/` | Reusable Blade partials: `_file-icon`, `_visibility-badge`, `_quota-bar`, `_move-modal` |

All mutations go through `FileManagerController` web routes via `fetch`; the controller forwards to the API via `ConsumesApi`. AJAX responses use the standard JSON envelope (`status`, `message`, `redirect`).

File type icons map MIME → Bootstrap Icons via `_file-icon.blade.php` partial: image, PDF, video, audio, archive, spreadsheet, document, code, fallback — each with a color modifier (`.fh-icon--*`).

Grid/list view toggle persists preference in `localStorage` key `fh-view-mode`. Default: list (table).

Menu: **My Files** → `file-hosting.index`.

## Config highlights

| Key | Role |
|-----|------|
| `disk` / `directory` | S3-compatible disk + key prefix |
| `regions` | Region key → disk name map (e.g. `sea` → `s3Custom`); only regions with non-empty disk appear in upload dropdown |
| `disabled_regions` | Comma-separated region keys blocked for new uploads only (downloads still served) |
| `chunk_size_bytes` / `multipart_threshold_bytes` | Upload strategy (default **10 MiB** each) |
| `upload_proxy.*` | Optional CF Worker hybrid byte path (`base_url`, `token`, `disk_targets`) |
| `default_quota_bytes` | Fallback per-user quota when no override row (0 = unlimited) |
| `signed_download_ttl_minutes` | S3 GET temporary URL lifetime |
| `signed_download_cache_margin_seconds` | Treat cache expired early |
| `public_download_signature_ttl_minutes` | Laravel signed hop for `/s/f/.../download` |

## Quota

| Source | Behavior |
|--------|----------|
| `config('file-hosting.default_quota_bytes')` | Free-tier default (typically 5 GiB) when no active/grace unlimited entitlement |
| `file_hosting_user_quotas` | Denorm override: `quota_bytes` (`0` = unlimited), `source` / `source_id` from Service fulfillment |

### Quota resolution (Service-aware)

`QuotaService` source of truth order:

| Priority | Condition | Effective quota | Upload |
|----------|-----------|-----------------|--------|
| 1 | Active `ServiceEntitlement` for `file_hosting` + `unlimited_quota` | unlimited (`0`) | OK |
| 2 | Same entitlement in **grace** | unlimited display; files kept | **blocked** (`quota_grace_blocked`) |
| 3 | Else | `UserQuota` row or `default_quota_bytes` | enforce free tier |

On paid [Service](../service/README.md) subscription: entitlement active + denorm `UserQuota` unlimited. On grace expire: revoke override + `EnforceFreeTierQuotaService` (oldest-first retain free limit; soft-delete DB + hard-delete S3).

**Purge rule (example):** files A 2 GiB (oldest), B 3 GiB, C 5 GiB with free limit 5 GiB → keep A+B (exactly 5 GiB), soft-delete C in DB and hard-delete C object on S3. Under-quota users are no-op. Re-run is idempotent.

Scheduler entry point: `php artisan service:process-entitlements` (hourly).

## Tests

```bash
composer test -- --filter=FileHosting
```

Includes API feature tests (`FileHostingTest`), web controller tests (`FileManagerControllerTest`), and `PublicDownloadSignedRouteTest` (unsigned 403, signed 302). Web controller tests mock API responses via `Http::fake()`. Service paid / grace / purge: `composer test -- --filter=Service` and `--filter=EnforceFreeTierQuota`.

## Related

- [service/README.md](../service/README.md) — entitlements, grace, renew, paid unlimited
- [identity/README.md](../identity/README.md) — H-RBAC / `domain_kind`
- [platform/architecture.md](../../platform/architecture.md)
- [integration/spa-gateway.md](../../integration/spa-gateway.md) — SPA path via Gateway
