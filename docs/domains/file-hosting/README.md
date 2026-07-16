# File Hosting — product domain

Locked product domain for personal nested folders, S3-compatible chunk upload, and Drive-lite link sharing.

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
| Upload sessions | `file_hosting_uploads` — presign PUT (single/multipart) → complete promotes row |
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
| `resources/js/file-hosting/direct-upload.js` | Browser-direct chunked upload to S3 (unchanged) |
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
| `chunk_size_bytes` / `multipart_threshold_bytes` | Upload strategy |
| `default_quota_bytes` | Per-user quota (0 = unlimited) |
| `signed_download_ttl_minutes` | S3 GET temporary URL lifetime |
| `signed_download_cache_margin_seconds` | Treat cache expired early |
| `public_download_signature_ttl_minutes` | Laravel signed hop for `/s/f/.../download` |

## Tests

```bash
composer test -- --filter=FileHosting
```

Includes API feature tests (`FileHostingTest`), web controller tests (`FileManagerControllerTest`), and `PublicDownloadSignedRouteTest` (unsigned 403, signed 302). Web controller tests mock API responses via `Http::fake()`.

## Related

- [product/README.md](../product/README.md) — catalog stub (retired Items UI)
- [identity/README.md](../identity/README.md) — H-RBAC / `domain_kind`
- [platform/architecture.md](../../platform/architecture.md)
- [integration/spa-gateway.md](../../integration/spa-gateway.md) — SPA path via Gateway
