# File Hosting product domain

**Date:** 2026-07-15  
**Monolith:** FileHosting locked domain — nested folders, chunk S3 upload, ownership + internal hierarchy, trash, search, quota.  
**Product catalog:** public Items API/web routes stubbed; primary product example is FileHosting.

## Delegation

| Consumer | Action |
|----------|--------|
| **Gateway** | Proxy `/api/v1/file-hosting/*` if SPA needs it; drop product items aggregates if any. |
| **Frontend** | File manager UI against new contract (or Blade-only until SPA). |

## New API surface

Base: `/api/v1/file-hosting` · middleware: `auth:api` + product permissions.

| Method | Path | Notes |
|--------|------|-------|
| GET | `/browse` | `folder_id?`, `user_id?` → breadcrumb + folders + files |
| GET | `/trash` | soft-deleted folders + files |
| GET | `/quota` | `used_bytes`, `quota_bytes`, `remaining_bytes` |
| GET | `/search` | `q`, `user_id?` |
| POST | `/folders` | `{ name, parent_id?, user_id? }` |
| PATCH | `/folders/{id}/rename` | `{ name, user_id? }` |
| PATCH | `/folders/{id}/move` | `{ parent_id?, user_id? }` |
| DELETE | `/folders/{id}` | soft delete cascade |
| POST | `/folders/{id}/restore` | |
| DELETE | `/folders/{id}/force` | permanent + S3 cleanup of contained files |
| GET | `/files/{id}` | |
| GET | `/files/{id}/download` | temporary signed URL |
| PATCH | `/files/{id}/rename` | |
| PATCH | `/files/{id}/move` | `{ folder_id? }` |
| DELETE | `/files/{id}` | soft |
| POST | `/files/{id}/restore` | |
| DELETE | `/files/{id}/force` | S3 delete |
| POST | `/uploads` | init direct/multipart |
| GET | `/uploads/{id}` | status + presigns |
| POST | `/uploads/{id}/sign-parts` | `{ part_numbers: [] }` |
| POST | `/uploads/{id}/complete` | creates `file_hosting_files` row |
| DELETE | `/uploads/{id}` | abort |

## Removed / stubbed

- Product Items public routes under `/api/v1/product/items` (empty group).
- Web `product-items.*` routes empty; menu entry removed.

## Config

- `config/file-hosting.php` — disk, directory, chunk, quota, MIME prefixes.

## Permissions (product)

- `view|create|edit|delete file hosting folder`
- `view|create|edit|delete file hosting file`
- `view any file hosting resource` / `manage any file hosting resource`
