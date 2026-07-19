# File Hosting hybrid CF Worker proxy + display-name auto-rename

## Summary

Monolith File Hosting supports an **optional hybrid upload path**: control plane stays on Monolith (init, quota, Create/Complete/Abort multipart, promote to `HostedFile`); browser part bytes may go through a **Cloudflare Worker** (`c:\laragon\www\workers`, Wrangler name `cf-workers-s3-proxy`) when `FILE_HOSTING_UPLOAD_PROXY_*` is configured. Default part/chunk size is **10 MiB**. Sibling **display names** on complete auto-rename to `{stem} (N).{ext}` instead of rejecting duplicates at init. B2 multipart requires **path-style** alignment (`CUSTOM_S3_USE_PATH_STYLE` + Worker `forcePathStyle`). Production Worker secret is `S3_TARGETS` bound to the correct Worker name; public hostname may be a custom domain (e.g. `https://s3.silverspoon.me`).

## Session metadata

| Item | Detail |
|------|--------|
| Date | 2026-07-17 |
| Keyword trigger | `upstream documentation session` |
| Related commits / PR | Session work: hybrid proxy wiring, Worker scaffold, display-name allocation, path-style fix, ops docs |

## Delegation

### Monolith (done in this session)

- Config: `config/file-hosting.php` — default chunk/multipart threshold **10 MiB**; `upload_proxy` (`base_url`, `token`, `disk_targets`).
- `S3DirectUploadEngine`: optional `proxyUploadParts` (`/t/{alias}/{key}`, Bearer + `X-S3-Target`); fail closed on misconfig; ETag normalize; upstream errors mapped.
- `DirectUploadService`: remove unique-name reject on **init**; **complete** uses `allocateUniqueDisplayName` (max active sequence + 1; soft-deleted ignored; base reused when free).
- `filesystems.php` / env: `CUSTOM_S3_USE_PATH_STYLE` for `s3Custom` (B2 Create/Complete must match Worker path-style).
- Blade client: `direct-upload.js` accepts string or array part headers.
- API messages: `proxy_misconfigured`, `upstream_failed` (+ lang `en`/`id`/`ja`).
- Tests: `S3DirectUploadEngineTest` proxy cases; `AllocateUniqueDisplayNameTest`; `FileHostingTest` complete rename scenarios.
- Living docs: `docs/domains/file-hosting/README.md`, `chore-now.md` status, `.env.example` hybrid keys.
- Sibling Worker repo (out of Monolith tree): Wrangler project, SigV4 stream proxy, CORS, secrets ops, name `cf-workers-s3-proxy`.

### Monolith Gateway

- None required for this session’s delta. Authenticated File Hosting upload routes remain covered by earlier pending FH entries; hybrid mode only changes **part `url`/`headers`** inside existing init/sign-parts JSON (same envelope). Complete still returns `file` resource — pass through as-is.

### Monolith Frontend

- None required for this session’s delta beyond existing pending FH SPA work. When building upload UI: after complete, display **`file.name` from the complete response** (may differ from the original client filename after auto-rename). Part PUTs must send server-provided `headers` and read `ETag` (Worker or S3).

## API contract

**Unchanged URLs** for upload control plane. Behavioral notes:

| Surface | Before (session start) | After |
|---------|------------------------|--------|
| `POST …/uploads` (init) | Could 422 `file_name_taken` for sibling duplicate display name | Accepts preferred name; collision resolved at complete |
| `POST …/uploads/{id}/complete` | Stored `original_name` as-is (or failed uniqueness) | May store `A (N).ext`; response `file.name` is final |
| Init/sign-parts `parts[].url` | S3 presigned only | When proxy on: `{proxy}/t/{alias}/{key}[?partNumber&uploadId]` |
| Init/sign-parts `parts[].headers` | S3 signed headers | When proxy on: `Authorization`, `X-S3-Target`, `Content-Type` |

**After (illustrative complete file name):**

```json
{
  "file": {
    "name": "A (2).zip",
    "path": "file-hosting/{user_id}/{upload_id}/{random40}.zip"
  }
}
```

## Supersedes

- None (extends File Hosting product; does not replace multi-region/bulk/product-domain pending entries).

## Breaking change?

| Breaking? | Mitigation |
|-----------|------------|
| Soft | Clients that assumed init would reject duplicate names, or that DB name always equals upload `name`, must use complete `file.name`. No URL version bump. |

## Test plan

| Repo | Command |
|------|---------|
| Monolith | `composer test -- --filter=FileHosting`; `composer test -- --filter=S3DirectUploadEngine`; `composer test -- --filter=AllocateUniqueDisplayName` |
| Workers | `cd c:\laragon\www\workers && npm test` |
| Gateway | N/A this entry |
| Frontend | N/A this entry |

Manual ops checks:

- `GET {proxy}/health` → `ok: true` and target aliases (no secrets).
- Hybrid: Network tab shows `{proxy}/t/{alias}/file-hosting/…` part PUTs; complete promotes file; re-upload same display name → `(N)` rename.
- B2: `CUSTOM_S3_USE_PATH_STYLE=true` and Worker `forcePathStyle: true` or complete may `NoSuchUpload`.

## Status

| Field | Value |
|-------|-------|
| Status | `done` |
| Synced | 2026-07-17 (Monolith-only; Gateway/Frontend None required for this delta) |
