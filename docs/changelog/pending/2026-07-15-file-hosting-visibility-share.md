# File Hosting — visibility, public share links, list rename

**Date:** 2026-07-15  
**Monolith:** Drive-lite `private` / `anyone_with_link` on files and folders; guest public API + web `/s/*` routes; list rename/share actions; download remains 302-to-signed-URL only.

## Delegation

| Consumer | Action |
|----------|--------|
| **Gateway** | Proxy public share endpoints (or leave public on Monolith host). Forward new visibility fields. |
| **Frontend** | Share UI (Restricted / Anyone with the link), copy link, public browse; list rename + share. |

## Schema

`file_hosting_files.visibility`, `file_hosting_folders.visibility` — default `private`. Values: `private` \| `anyone_with_link`.

## Authenticated API

| Method | Path | Notes |
|--------|------|-------|
| PATCH | `/api/v1/file-hosting/files/{id}/visibility` | `{ visibility, user_id? }` |
| PATCH | `/api/v1/file-hosting/folders/{id}/visibility` | `{ visibility, user_id? }` |

File/folder JSON: `visibility`, `share_url` (null unless this item is `anyone_with_link`).

## Public API (no auth)

| Method | Path |
|--------|------|
| GET | `/api/v1/file-hosting/public/files/{id}` |
| GET | `/api/v1/file-hosting/public/files/{id}/download` → `{ url, expires_at }` |
| GET | `/api/v1/file-hosting/public/folders/{id}` |

Inherit: folder link-shared → children browsable; private files inside still downloadable.

## Web

| Route | Name |
|-------|------|
| `GET /s/f/{file}` | `file-hosting.public.files.show` |
| `GET /s/f/{file}/download` | `file-hosting.public.files.download` — **Laravel `signed` middleware**; show page issues `URL::temporarySignedRoute`; bare URL → 403; valid signature → 302 S3 |
| `GET /s/d/{folder}` | `file-hosting.public.folders.show` |
| list + show | rename/share modals; visibility badge |

## Download contract

Still **not** a byte proxy. Client (or browser redirect) hits signed S3 URL only.
