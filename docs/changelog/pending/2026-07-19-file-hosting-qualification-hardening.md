# File Hosting production qualification + race hardening

## Summary

File Hosting domain is covered by an automated **50-case qualification suite** (Low / Normal / Medium / Hard / Anomaly). Product races and gaps found during qualification were hardened: complete row-lock, pending-byte quota reservation, workspace create slot lock, archive aborts pending team uploads, visibility clears signed-download cache, server-side concurrent upload cap. Temporary matrix doc removed; living product README documents the new rules.

## Session metadata

| Item | Detail |
|------|--------|
| Date | 2026-07-19 |
| Keyword trigger | `upstream documentation session` |
| Related commits / PR | Uncommitted session work: FH qualification suite + race/quota/visibility/concurrency fixes; multi-owned + collab already tracked in sibling `pending/` entries |

## Delegation

### Monolith (done in this session)

- **Product hardening**
  - `FileService::setVisibility` — always nulls `signed_download_url` / `signed_download_expires_at` on visibility change (H05 / A06).
  - `DirectUploadService::complete` — `FileUpload::lockForUpdate()`; pending-only promote; re-assert ACL + quota under lock (A01).
  - `QuotaService` — `committedBytes()`, `pendingUploadBytes()`; `assertCanAccept` uses reserved = committed + pending (exclude current upload id on complete); `usage()` exposes `pending_bytes`, `reserved_bytes`; `remaining_bytes` against reserved (A03).
  - `WorkspaceService::create` — lock owner user row + re-assert slot limit inside transaction (A02).
  - `WorkspaceService::archive` — `DirectUploadService::abortPendingForWorkspace` (best-effort S3 abort + status aborted) (A04).
  - `DirectUploadService::initiate` — server-side `max_concurrent_uploads` on pending non-expired sessions for storage owner (A10).
  - Fake upload test double mirrors concurrent limit, lock complete, force abort.
  - i18n `api/file-hosting.upload.too_many_concurrent` (en/id/ja).
- **Qualification suite**
  - `tests/Domain/FileHosting/Qualification/` — harness + Low/Normal/Medium/Hard/Anomaly (`test_FH_L01_…` … `test_FH_A10_…`).
  - Intentional edges asserted without product change: MIME client-trust (A09), no auto-archive when slots drop below used (A07), proxy fail-closed (A08).
- **Docs**
  - `docs/domains/file-hosting/README.md` — visibility cache clear; pending quota; concurrent server cap; archive abort; qualification filter; deleted `qualification-test-matrix.md`.
- **Verify**
  - `composer test -- --filter=FileHostingQualification` → 50 passed.
  - `composer test -- --filter=FileHosting` → 192 passed (includes collab / billing journey / purge / API / web).

### Monolith Gateway

- [ ] Pass through expanded `GET /api/v1/file-hosting/quota` fields if any transform/aggregate strips unknown keys: `pending_bytes`, `reserved_bytes` (and existing slot flags from multi-own entry).
- [ ] No new routes. Upload control-plane paths unchanged; error body may include `too_many_concurrent` on init when over cap.
- [ ] None beyond proxy transparency if Gateway already returns Monolith JSON as-is for `file-hosting/*`.

### Monolith Frontend

- [ ] Quota UI: treat `remaining_bytes` as **after reserved** (committed + pending uploads), not committed-only. Optionally show pending reservation if product wants.
- [ ] Upload UX: handle 422 concurrent-limit on init (`too_many_concurrent`) — disable extra parallel starts; align client queue with server `max_concurrent_uploads`.
- [ ] Share revoke: after set-private, do not keep client-cached download URLs; re-fetch download after visibility change.
- [ ] Workspace create at slot cap: already covered by multi-own entry (`can_create_workspace` / `slot_limit`); no new fields required for create lock.

## API contract

**Before (quota `data` excerpt):**

```json
{
  "used_bytes": 1024,
  "quota_bytes": 10485760,
  "remaining_bytes": 10484736,
  "state": "free",
  "collaborative_works": false,
  "can_create_workspace": false,
  "owned_workspace_used": 0,
  "owned_workspace_limit": 0
}
```

**After:**

```json
{
  "used_bytes": 1024,
  "pending_bytes": 2048,
  "reserved_bytes": 3072,
  "quota_bytes": 10485760,
  "remaining_bytes": 10482688,
  "state": "free",
  "collaborative_works": false,
  "can_create_workspace": false,
  "owned_workspace_used": 0,
  "owned_workspace_limit": 0
}
```

| Path | Change |
|------|--------|
| `GET /api/v1/file-hosting/quota` | + `pending_bytes`, `reserved_bytes`; `remaining_bytes` = max(0, quota − reserved) |
| `POST …/uploads` (init) | May 422 with `upload.too_many_concurrent` when pending sessions ≥ config |
| `POST …/uploads/{id}/complete` | Second complete → 422 already_promoted / not_pending (locked) |
| `POST …/files/{id}/visibility` | Clears signed download cache (response still file resource) |
| `POST …/workspaces/{id}/archive` | Side effect: pending uploads for workspace → aborted |

## Supersedes

- None. Complements (does not replace):
  - `2026-07-19-file-hosting-collaborative-works.md`
  - `2026-07-19-file-hosting-multi-owned-workspaces.md`
- Temporary review doc `docs/domains/file-hosting/qualification-test-matrix.md` **deleted** after automation green (not a changelog entry).

## Breaking change?

| Breaking? | Mitigation |
|-----------|------------|
| Soft | Clients that assumed `remaining_bytes = quota − used` only may see lower remaining while uploads are in flight. Additive fields are backward-compatible if ignored. |

## Test plan

| Repo | Command |
|------|---------|
| Monolith | `composer test -- --filter=FileHostingQualification` |
| Monolith | `composer test -- --filter=FileHosting` |
| Gateway | After sync: proxy smoke on `GET file-hosting/quota` fields present |
| Frontend | After sync: quota remaining with pending upload; concurrent init 422; set-private then download |

## Status

| Field | Value |
|-------|-------|
| Status | `pending` |
| Synced | — |
