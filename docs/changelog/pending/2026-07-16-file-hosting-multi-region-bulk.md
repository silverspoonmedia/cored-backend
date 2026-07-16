# File Hosting — multi-region upload selector + bulk actions (session close)

## Summary

Monolith ships a multi-region S3 upload selector: users pick an upload region from a dropdown, the chosen region key is resolved to a disk per `config('file-hosting.regions')`, and the file row stores the resolved `disk`. Regions can be disabled for new uploads (`disabled_regions`) while still serving downloads from already-stored objects — downloads are disk-aware and never check the disabled list. Each file/folder displays a region badge derived via `RegionResolver::keyForDisk()`. A region-dropdown usability bug (dropzone click guard opening the file picker when the `<select>` was clicked) is fixed. Bulk select/action is added: per-row and select-all checkboxes for files AND folders, with bulk Share (set `anyone_with_link`), Unshare (set `private`), and Delete (soft-delete) via four new bulk API endpoints. Bulk operations are owner-scoped and capped at 100 ids per request.

## Session metadata

| Item | Detail |
|------|--------|
| Date | 2026-07-16 |
| Keyword trigger | `upstream documentation session` |
| Related commits / PR | Uncommitted — multi-step session (multi-region selector + region display + disabled regions + bulk actions) |

## Delegation

### Monolith (done in this session)

- `app/Domain/FileHosting/Support/RegionResolver.php` (new) — `keyForDisk()` reverse-lookup, `uploadableKeys()` (regions minus disabled), `isDisabled()`.
- `config/file-hosting.php` — `regions` (key → disk map) + `disabled_regions` (comma-separated keys blocked for uploads only).
- `config/filesystems.php` — `s3Customeu` disk (EU S3-compatible).
- `app/Domain/FileHosting/Services/DirectUploadService.php` — `initiate()` accepts `?string $disk`; new `resolveDiskName()` maps region key → disk, falls back to `file-hosting.disk`.
- `app/Domain/FileHosting/Http/Requests/InitFileUploadRequest.php` — `disk` validated against `RegionResolver::uploadableKeys()` (`in:` rule); disabled regions rejected with 422.
- `app/Domain/FileHosting/Http/Resources/HostedFileResource.php` — `region` field (nullable, derived via `RegionResolver::keyForDisk()`).
- `app/Domain/FileHosting/Http/Controllers/Web/FileManagerController.php` — forwards `disk` to upload init; 4 new bulk proxy methods.
- `app/Domain/FileHosting/Http/Controllers/API/V1/UploadController.php` — passes `disk` to `DirectUploadService::initiate()`.
- `app/Domain/FileHosting/Http/Requests/BulkVisibilityRequest.php` (new) — `ids[]` (max 100, uuid), `visibility` (private|anyone_with_link).
- `app/Domain/FileHosting/Http/Requests/BulkDestroyRequest.php` (new) — `ids[]` (max 100, uuid).
- `app/Domain/FileHosting/Services/FileService.php` — `bulkSetVisibility()` (mass update, owner-scoped) + `bulkSoftDelete()` (clears signed URL cache, soft-deletes).
- `app/Domain/FileHosting/Services/FolderService.php` — `bulkSetVisibility()` + `bulkSoftDelete()` (cascades via existing `softDeleteCascade`).
- `app/Domain/FileHosting/Http/Controllers/API/V1/FileController.php` — `bulkSetVisibility()` + `bulkDestroy()` (RBAC via route permission middleware; no per-instance policy authorize since bulk has no single model).
- `app/Domain/FileHosting/Http/Controllers/API/V1/FolderController.php` — `bulkSetVisibility()` + `bulkDestroy()`.
- `routes/api/v1/file-hosting.php` — 4 new routes under existing permission groups: `files/bulk-visibility`, `files/bulk`, `folders/bulk-visibility`, `folders/bulk`.
- `routes/web/file-hosting.php` — 4 web proxy routes.
- `resources/views/adminlte4/file-hosting/index.blade.php` — region dropdown (filtered to uploadable regions), region badge column (table + grid + detail), bulk action bar, per-row + select-all checkboxes (table + grid).
- `resources/views/adminlte4/file-hosting/show.blade.php` — region badge in file detail.
- `resources/js/file-hosting/file-manager.js` — dropzone click guard excludes `select`/`input`; bulk selection state (`Set` of `type:id`), select-all sync, bulk action buttons (share/unshare/delete with confirm), row highlight on select.
- `resources/js/file-hosting/direct-upload.js` — forwards selected region key as `disk` in upload init body.
- `resources/css/file-hosting/file-hosting.css` — `.fh-bulk-bar`, `.fh-table__select-cell`, `.is-selected` row/card highlight, `.fh-file-card__select` positioning.
- `lang/{en,id,ja}/api/file-hosting.php` — `bulk_visibility_updated`, `bulk_files_deleted`, `bulk_folders_deleted`.
- `lang/{en,id,ja}/web/file-hosting.php` — `bulk.*` (selected, select_all, select_item, share, unshare, delete, confirm_*), `upload.browse`, `upload.region`, `columns.region`, `regions.*`.
- `tests/Domain/FileHosting/Feature/Http/Controllers/API/V1/FileHostingTest.php` — init accepts/rejects disk, disabled region upload reject + download serve, browse includes region key, bulk visibility (owner-scoped), bulk delete, bulk validation 422.
- `tests/Domain/FileHosting/Feature/Http/Controllers/Web/FileManagerControllerTest.php` — bulk visibility + destroy forward to API.
- `tests/Fakes/FakeFileHostingDirectUploadService.php` — `?string $disk` param + `resolveDiskName()`.
- `.env.example` — `FILE_HOSTING_REGION_*_DISK`, `FILE_HOSTING_DISABLED_REGIONS`, `CUSTOM_S3_BUCKET_PUBLIC_EU`, `CUSTOM_S3_ENDPOINT_EU`.
- `docs/domains/file-hosting/README.md` — concepts, API, web routes, config, UI architecture updates.

### Monolith Gateway

- [ ] Proxy `POST /api/v1/file-hosting/files/bulk-visibility`, `DELETE /api/v1/file-hosting/files/bulk`, `POST /api/v1/file-hosting/folders/bulk-visibility`, `DELETE /api/v1/file-hosting/folders/bulk` for SPA.
- [ ] Pass through optional `disk` param on upload init (`POST /api/v1/file-hosting/uploads`).
- [ ] Forward new `region` field in file JSON (nullable string) — no transform needed, just ensure it is not stripped.
- [ ] Tests for new proxy routes.

### Monolith Frontend

- [ ] Upload UI: region selector dropdown (filtered to uploadable regions; hidden when only one region).
- [ ] File manager: per-row + select-all checkboxes (table + grid); bulk action bar (Share / Unshare / Delete) with confirm dialogs.
- [ ] File list/grid: region badge per file (derived from `region` field; fall back to disk lookup).
- [ ] File detail: region badge.
- [ ] i18n en/id/ja: `web/file-hosting.bulk.*`, `upload.region`, `upload.browse`, `columns.region`, `regions.*`.
- [ ] Unit/E2E smoke for bulk flows + region selection.

## API contract

### Upload init — new optional `disk` param

`POST /api/v1/file-hosting/uploads`

**Before:**

```json
{ "name": "report.pdf", "mime": "application/pdf", "size": 1024, "folder_id": null, "user_id": null }
```

**After:**

```json
{ "name": "report.pdf", "mime": "application/pdf", "size": 1024, "folder_id": null, "user_id": null, "disk": "sea" }
```

`disk` is optional. When omitted, falls back to `config('file-hosting.disk')`. When provided, must be a key in `config('file-hosting.regions')` and not in `disabled_regions` — otherwise 422. The resolved disk name is stored on the `file_hosting_files.disk` column.

### File JSON — new `region` field

**Before:**

```json
{ "id": "uuid", "name": "report.pdf", "disk": "s3Custom", "path": "...", "visibility": "private" }
```

**After:**

```json
{ "id": "uuid", "name": "report.pdf", "disk": "s3Custom", "region": "sea", "path": "...", "visibility": "private" }
```

`region` is nullable (null when the disk is not mapped to any region key).

### New bulk endpoints

| Method | Path | Body | Response |
|--------|------|------|----------|
| POST | `/api/v1/file-hosting/files/bulk-visibility` | `{ "ids": ["uuid", ...], "visibility": "private\|anyone_with_link", "user_id": null }` | `{ "status": "success", "data": { "affected": 3 } }` |
| DELETE | `/api/v1/file-hosting/files/bulk` | `{ "ids": ["uuid", ...], "user_id": null }` | `{ "status": "success", "data": { "affected": 3 } }` |
| POST | `/api/v1/file-hosting/folders/bulk-visibility` | `{ "ids": ["uuid", ...], "visibility": "private\|anyone_with_link", "user_id": null }` | `{ "status": "success", "data": { "affected": 2 } }` |
| DELETE | `/api/v1/file-hosting/folders/bulk` | `{ "ids": ["uuid", ...], "user_id": null }` | `{ "status": "success", "data": { "affected": 2 } }` |

All bulk endpoints: `ids` required (min 1, max 100, uuid), owner-scoped (non-owner ids silently ignored), RBAC via route permission middleware (`edit`/`delete file hosting file|folder`).

## Supersedes

- None. Adjacent to `2026-07-16-file-hosting-product-domain.md` (domain init) — does not replace it.

## Breaking change?

| Breaking? | Mitigation |
|-----------|------------|
| No | Additive: new optional `disk` param, new nullable `region` field, new endpoints. Existing clients continue to work unchanged. |

## Test plan

| Repo | Command |
|------|---------|
| Monolith | `composer test -- --filter=FileHosting` (58 tests pass) |
| Gateway | `php artisan test` after proxy routes added |
| Frontend | `npm run test:unit -- --run`; `npm run build` after UI added |

### Scenarios

1. Upload init with `disk=sea` → file row `disk` = resolved disk name; browse response includes `region: "sea"`.
2. Upload init with `disk=mars` (unknown) → 422 validation error.
3. Region in `disabled_regions` → upload init rejected (422); download from existing file on that disk still 200.
4. Bulk visibility: 3 file ids → all updated; cross-owner id in request → not affected (owner-scoped).
5. Bulk delete: 3 file ids → all soft-deleted (`deleted_at` set); signed URL cache cleared.
6. Bulk folders: visibility + soft-delete (cascade to descendants).
7. Bulk with `ids: []` → 422 validation error.

## Status

| Field | Value |
|-------|-------|
| Status | `pending` |
| Synced | — |
