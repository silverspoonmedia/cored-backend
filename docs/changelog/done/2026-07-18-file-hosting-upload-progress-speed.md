# File Hosting Blade upload progress — transfer rate + Finalizing status

## Summary

Blade File Hosting direct upload now shows **byte-level progress** and a **session-average transfer rate** next to the bar, then a distinct **Finalizing…** status while the client calls `POST …/complete` (merge parts / promote `HostedFile`). Progress and speed are **client-only** (XHR `upload.onprogress`); Monolith API session payloads and control-plane URLs are unchanged.

## Session metadata

| Item | Detail |
|------|--------|
| Date | 2026-07-18 |
| Keyword trigger | `upstream documentation session` |
| Related commits / PR | `cf275ae` (speed + Vitest helpers); uncommitted at close: `upload.completing` i18n + status wire + docs polish |

## Delegation

### Monolith (done in this session)

- `resources/js/file-hosting/upload-progress.js` — pure helpers: `computeTransferRate`, `formatTransferRate` (B/s–GB/s, 1024 base), `computeByteProgressPercent`.
- `resources/js/file-hosting/upload-progress.test.js` — Vitest (`npm run test:js`, pattern `resources/js/**/*.test.js`).
- `resources/js/file-hosting/direct-upload.js` — part PUT via **XHR** (not `fetch`) so `xhr.upload.onprogress` drives mid-part % + rate; `[data-upload-speed]` label; after last part, status **completing** at 100% (speed cleared) before `POST complete`, then **Done**.
- Lang `en` / `id` / `ja`: `web/file-hosting.upload.completing` (`Finalizing…` / `Menyelesaikan…` / `仕上げ中…`).
- Blade `adminlte4/file-hosting/index.blade.php` — pass `completing` in `data-messages`.
- Living docs: `docs/domains/file-hosting/README.md` (UI architecture + Tests).

### Monolith Gateway

- None required. Upload control-plane URLs and JSON envelopes unchanged; no new fields for progress or speed (browser cannot report storage PUT throughput through Monolith).

### Monolith Frontend

- None required for this Monolith-only Blade delta. When SPA implements File Hosting chunk upload, mirror client-side behavior: byte progress + transfer rate from part PUTs, and a distinct “finalizing” state during complete (do not invent API progress fields). Reuse or add `src/i18n` strings equivalent to Monolith `upload.completing`.

## API contract

N/A — no URL or JSON shape change. Progress % and transfer rate are not part of init/status/complete responses.

## Supersedes

- None (UX polish on existing direct-upload client; does not replace hybrid-proxy / multi-region / product-domain pending entries).

## Breaking change?

| Breaking? | Mitigation |
|-----------|------------|
| No | Header normalize for proxy parts unchanged; still requires CORS `ExposeHeaders: ETag`. |

## Test plan

| Repo | Command |
|------|---------|
| Monolith | `npm run test:js` |
| Monolith (optional smoke) | `composer test -- --filter=FileHosting` |
| Gateway | N/A this entry |
| Frontend | N/A this entry |

Manual: upload single + multipart file in Blade My Files; bar moves mid-part; speed shows e.g. `1.2 MB/s`; after last part status **Finalizing…** then **Done**.

## Status

| Field | Value |
|-------|-------|
| Status | `done` |
| Synced | 2026-07-18 (Monolith-only; Gateway/Frontend None required for this delta) |
