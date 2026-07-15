# File Hosting — show, download, stats, signed URL cache

**Date:** 2026-07-15  
**Monolith:** File detail page, download redirect, view/download counters, cached signed download URLs, random S3 object keys with download filename from DB name.

## Delegation

| Consumer | Action |
|----------|--------|
| **Gateway** | Proxy new fields on file JSON if SPA lists files; optional download aggregate. |
| **Frontend** | File detail + download + rename against updated contract. |

## API changes

| Method | Path | Notes |
|--------|------|-------|
| GET | `/api/v1/file-hosting/files/{id}` | Increments `view_count`; response includes stats fields |
| GET | `/api/v1/file-hosting/files/{id}/download` | `{ url, expires_at }`; reuses cached signed URL until near expiry; increments `download_count` |
| PATCH | `/api/v1/file-hosting/files/{id}/rename` | Clears cached signed URL (disposition bound to name) |

### File JSON additions

- `download_count`, `view_count`
- `last_downloaded_at`, `last_viewed_at`

### Upload object key

`file-hosting/{user_id}/{upload_id}/{random40}.{ext}` — not derived from original basename. Original name stored as file `name` for UI + Content-Disposition.

## Web routes

| Method | Path | Name |
|--------|------|------|
| GET | `/file-hosting/files/{file}` | `file-hosting.files.show` |
| GET | `/file-hosting/files/{file}/download` | `file-hosting.files.download` |
| PATCH | `/file-hosting/files/{file}/rename` | `file-hosting.files.rename` |

## Schema

`file_hosting_files`: counters, last_* timestamps, `signed_download_url`, `signed_download_expires_at`.
