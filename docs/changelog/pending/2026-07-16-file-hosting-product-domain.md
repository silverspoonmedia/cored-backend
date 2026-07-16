# File Hosting product domain (session close)

## Summary

Monolith ships a locked **File Hosting** product domain: nested personal folders, direct-to-S3 chunk uploads, ownership + core hierarchy for internal roles, trash/search/quota, file stats and cached signed download URLs, Drive-lite visibility (`private` | `anyone_with_link`) with guest share pages, and download hops that **302 to S3** (no byte proxy). Product catalog Items API/web remain stubbed. Guest web download uses a Laravel **temporary signed route** so bare `/s/f/{uuid}/download` is rejected.

## Session metadata

| Item | Detail |
|------|--------|
| Date | 2026-07-16 |
| Keyword trigger | `upstream documentation session` |
| Related commits / PR | Uncommitted / multi-step session work (FileHosting domain + polish + visibility + signed download hop) |

## Delegation

### Monolith (done in this session)

- Domain `app/Domain/FileHosting/`: models, services (`FolderService`, `FileService`, `DirectUploadService`, `SignedDownloadService`, `VisibilityService`, `AccessService`, `QuotaService`), policies, API + web controllers, resources, requests.
- Schema: `file_hosting_folders`, `file_hosting_files`, `file_hosting_uploads`; stats + signed URL cache columns; `visibility` on folders/files.
- Config: `config/file-hosting.php` (disk, chunks, quota, MIME, S3 signed TTL, cache margin, public download signature TTL).
- Auth API `/api/v1/file-hosting/*` (browse, folders, files, uploads, trash, search, quota, visibility).
- Public API `/api/v1/file-hosting/public/*` (file show/download, folder browse) — no auth; visibility gate.
- Blade: authenticated manager (list rename/share/delete, show, download 302); guest `/s/f/*`, `/s/d/*`; guest download middleware `signed`.
- S3 object keys: `file-hosting/{user_id}/{upload_id}/{random40}.{ext}`; download filename via `ResponseContentDisposition` from DB `name`.
- Product Items public routes stubbed; menu “My Files”; `FileHostingRoleSeeder` (product `domain_kind`, levels 1100/1200/1300).
- Tests under `tests/Domain/FileHosting/` (API + signed public download).
- Living docs: `docs/domains/file-hosting/README.md`, architecture/README index updates.
- Fix: `ApiClient::patch()` for web PATCH proxies.

### Monolith Gateway

- [ ] Proxy authenticated `/api/v1/file-hosting/*` for SPA (browse, CRUD, upload init/sign/complete, download JSON, visibility).
- [ ] Decide public share strategy: proxy `/api/v1/file-hosting/public/*` **or** document that guest share stays on Monolith origin (`/s/*` Blade / public API).
- [ ] No byte-proxy of file bodies; download responses are `{ url, expires_at }` only — clients follow S3 URL.
- [ ] Drop any product-items aggregates if still present.
- [ ] Tests for new proxy routes when added.

### Monolith Frontend

- [ ] File manager: nested browse, breadcrumb, upload queue (presign + complete), trash, quota.
- [ ] File detail: stats (`view_count`, `download_count`), rename, download via API then open/redirect to `url`.
- [ ] Share UI: Restricted vs Anyone with the link; copy `share_url`; folder inherit semantics.
- [ ] Guest share UX if SPA hosts public pages (or deep-link to Monolith `/s/f|d/{uuid}`).
- [ ] i18n en/id/ja for new copy.
- [ ] Unit/E2E smoke when UI lands.

## API contract

### Authenticated base

`/api/v1/file-hosting` · `auth:api` + product permissions.

| Method | Path | Notes |
|--------|------|-------|
| GET | `/browse` | `folder_id?`, `user_id?` |
| GET | `/trash`, `/quota`, `/search` | |
| POST/PATCH/DELETE | `/folders…` | create, rename, move, visibility, soft/force/restore |
| GET | `/files/{id}` | increments `view_count` |
| GET | `/files/{id}/download` | `{ url, expires_at }`; increments `download_count`; caches S3 signed URL |
| PATCH | `/files/{id}/rename` | clears disposition-bound URL cache |
| PATCH | `/files/{id}/visibility` | `private` \| `anyone_with_link` |
| POST… | `/uploads…` | direct/multipart initiate, sign-parts, complete, abort |

### Public (no auth)

| Method | Path | Notes |
|--------|------|-------|
| GET | `/public/files/{id}` | view++; effective link access |
| GET | `/public/files/{id}/download` | `{ url, expires_at }` |
| GET | `/public/folders/{id}` | browse children if folder accessible |

### File / folder JSON (additions)

- File: `visibility`, `share_url`, `download_count`, `view_count`, `last_downloaded_at`, `last_viewed_at`
- Folder: `visibility`, `share_url`
- `share_url` set only when **this** row is `anyone_with_link` (not merely inherited)

### Visibility inherit

```
folderAccessible = self or any ancestor is anyone_with_link
fileAccessible   = file anyone_with_link OR folderAccessible(parent)
```

### Web guest download hop

| Path | Behavior |
|------|----------|
| `GET /s/f/{uuid}` | Public file page; mints temporary signed download URL |
| `GET /s/f/{uuid}/download?expires=&signature=` | Middleware `signed`; then 302 to S3 |
| Bare `/s/f/{uuid}/download` | **403** |
| `GET /s/d/{uuid}` | Public folder listing |

Config: `public_download_signature_ttl_minutes` (default 5).

## Supersedes

- `2026-07-15-file-hosting-domain.md`
- `2026-07-15-file-hosting-download-stats.md`
- `2026-07-15-file-hosting-visibility-share.md`

## Breaking change?

| Breaking? | Mitigation |
|-----------|------------|
| Yes for SPA expecting Product Items routes | Items API/web stubbed 404; use File Hosting |
| Additive for file JSON / new endpoints | Gateway/Frontend adopt when SPA ships manager |
| Guest download bare URL no longer works | Always open show page or mint temporary signed route |

## Test plan

| Repo | Command |
|------|---------|
| Monolith | `composer test -- --filter=FileHosting` (includes `PublicDownloadSignedRoute`) |
| Gateway | `php artisan test` after proxy routes |
| Frontend | `npm run test:unit -- --run`; `npm run build` after UI |

### Scenarios

1. Nested folders + breadcrumb; upload complete → random object key, DB `name` original.
2. Show increments views; download increments downloads and reuses cached S3 URL within TTL.
3. Visibility toggle; guest public show/download; private peer 404; folder share inherits to private children.
4. Unsigned `/s/f/{id}/download` → 403; signed → 302 Location storage host.

## Status

| Field | Value |
|-------|-------|
| Status | `pending` |
| Synced | — |
