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
| Docs close-out | [changelog pending session entry](../../changelog/pending/2026-07-16-file-hosting-product-domain.md) |

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
| Download | `GET files/{id}/download` → `{ url, expires_at }` (download++; cache S3 URL) |
| Upload | init, status, sign-parts, complete, abort |
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
| `file-hosting.index` | yes | Browse + chunk upload; list View / Rename / Share / Delete |
| `file-hosting.files.show` | yes | Detail, stats, visibility, download CTA |
| `file-hosting.files.download` | yes | Auth download → **302** S3 |
| `file-hosting.trash` | yes | Soft-deleted items |
| `file-hosting.public.files.show` | no | `/s/f/{uuid}` guest page |
| `file-hosting.public.files.download` | no | **Temporary signed** hop → **302** S3 (unsigned **403**) |
| `file-hosting.public.folders.show` | no | `/s/d/{uuid}` guest folder list |

Share link users copy = show URL (`/s/f/{uuid}` or `/s/d/{uuid}`). Download button on the show page is a short-lived Laravel signed URL (`public_download_signature_ttl_minutes`, default 5).

Chunk widget: `resources/js/file-hosting/direct-upload.js` (+ `upload-queue.js`).

Menu: **My Files** → `file-hosting.index`.

## Config highlights

| Key | Role |
|-----|------|
| `disk` / `directory` | S3-compatible disk + key prefix |
| `chunk_size_bytes` / `multipart_threshold_bytes` | Upload strategy |
| `default_quota_bytes` | Per-user quota (0 = unlimited) |
| `signed_download_ttl_minutes` | S3 GET temporary URL lifetime |
| `signed_download_cache_margin_seconds` | Treat cache expired early |
| `public_download_signature_ttl_minutes` | Laravel signed hop for `/s/f/.../download` |

## Tests

```bash
composer test -- --filter=FileHosting
```

Includes API feature tests and `PublicDownloadSignedRouteTest` (unsigned 403, signed 302).

## Related

- [product/README.md](../product/README.md) — catalog stub (retired Items UI)
- [identity/README.md](../identity/README.md) — H-RBAC / `domain_kind`
- [platform/architecture.md](../../platform/architecture.md)
- [integration/spa-gateway.md](../../integration/spa-gateway.md) — SPA path via Gateway
