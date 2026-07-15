# File Hosting — product domain

Locked product domain for personal nested folders + S3-compatible chunk upload + Drive-lite link sharing.

Code: `app/Domain/FileHosting/`. Routes: `routes/api/v1/file-hosting.php`, `routes/web/file-hosting.php`, `routes/web/file-hosting-public.php`. Config: `config/file-hosting.php`.

## Concepts

| Concept | Behavior |
|---------|----------|
| Virtual root | `folder_id = null` = “My Files” root (no auto DB row) |
| Nested folders | `parent_id` tree per owner; breadcrumb via `FolderAncestorChain` |
| Files | `file_hosting_files` with denormalized `user_id` + optional `folder_id` |
| Upload sessions | `file_hosting_uploads` — direct/multipart presign → complete promotes to file |
| S3 key | `file-hosting/{user_id}/{upload_id}/{random40}.{ext}` — display name only in DB |
| Download | Signed temporary URL + `ResponseContentDisposition`; **302 away** (no Monolith byte proxy) |
| Signed URL cache | `signed_download_url` + `signed_download_expires_at`; reuse until near expiry |
| Stats | `view_count` / `download_count` (+ last_* timestamps); no event log |
| Visibility | `private` \| `anyone_with_link` on folders and files (UUID = link secret) |
| Inheritance | Folder `anyone_with_link` → guest may browse that folder and download files inside even if file is `private` |
| Ownership | Own: actor owns resource. Cross-user: product “any” perm + core `RoleService` hierarchy |

## Visibility (Drive-lite)

```
folderAccessible(folder) =
  folder.visibility == anyone_with_link
  OR any ancestor.visibility == anyone_with_link

fileAccessible(file) =
  file.visibility == anyone_with_link
  OR (file.folder_id && folderAccessible(file.folder))
```

- Not world-searchable: no public index; you need the UUID link.
- Mutations (upload/rename/delete/visibility change) never public.
- `share_url` exposed only when **this** item is `anyone_with_link`.

## Permission catalog (`domain_kind = product`)

```
view|create|edit|delete file hosting folder
view|create|edit|delete file hosting file
view any file hosting resource
manage any file hosting resource
```

| Role | Scope |
|------|--------|
| File Hosting Admin / Editor / Viewer | Product ladder (levels 1100/1200/1300) |
| Core `User` | Own CRUD (seeder pivot) |
| Core Root / Admin / Moderator / Management | Own + any (hierarchy still filters) |

Seeder: `FileHostingRoleSeeder` (after `RoleSeeder`).

## API (`/api/v1/file-hosting/*`)

### Authenticated (`auth:api`)

| Area | Endpoints |
|------|-----------|
| Browse | `GET browse?folder_id=&user_id=` |
| Folders | `POST folders`, `PATCH …/rename`, `PATCH …/move`, `PATCH …/visibility`, soft/force delete, restore |
| Files | `GET files/{id}` (view++), `PATCH …/rename`, `PATCH …/move`, `PATCH …/visibility`, soft/force delete, restore |
| Download | `GET files/{id}/download` → `{ url, expires_at }` (download++; cache signed URL) |
| Upload | `POST uploads`, `GET uploads/{id}`, `POST …/sign-parts`, `POST …/complete`, `DELETE` abort |
| Search / Trash / Quota | as before |

### Public (no auth)

| Method | Path |
|--------|------|
| GET | `/api/v1/file-hosting/public/files/{id}` |
| GET | `/api/v1/file-hosting/public/files/{id}/download` |
| GET | `/api/v1/file-hosting/public/folders/{id}` |

## Web Blade

| Route | Auth | Purpose |
|-------|------|---------|
| `file-hosting.index` | yes | Browse + upload; list View / Rename / Share / Delete |
| `file-hosting.files.show` | yes | Detail + download 302 + visibility |
| `file-hosting.files.download` | yes | API download → **302** signed S3 |
| `file-hosting.public.files.show` | no | `/s/f/{uuid}` guest file page |
| `file-hosting.public.files.download` | no | **temporary signed** `/s/f/{uuid}/download?expires=&signature=` → **302** S3 (unsigned = 403) |
| `file-hosting.public.folders.show` | no | `/s/d/{uuid}` guest folder browse |

Guest show page mints the signed download hop (`public_download_signature_ttl_minutes`, default 5). Share link stays the unguessable show URL (`/s/f/{uuid}`); bare download path is not a stable public entry.
| `file-hosting.trash` | yes | Soft-deleted list |

Chunk widget: `resources/js/file-hosting/direct-upload.js`.

## Config highlights

- `signed_download_ttl_minutes`
- `signed_download_cache_margin_seconds`

## Tests

`tests/Domain/FileHosting/` — `composer test -- --filter=FileHosting`

## Related

- [product/README.md](../product/README.md) — catalog stub
- [identity/README.md](../identity/README.md) — H-RBAC / domain_kind
- [platform/architecture.md](../../platform/architecture.md)
