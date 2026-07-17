# Changelog entry

## Summary

Monolith adds core domain **ErrorLog**: reportable exceptions and `Log::error` / `critical` / `alert` / `emergency` persist in `error_logs`, grouped by fingerprint, with admin API + Blade UI (Root/Admin). File logs under `storage/logs/` remain dual-write. Distinct from Spatie `activity_log` (audit) and the notification bell.

## Session metadata

| Item | Detail |
|------|--------|
| Date | 2026-07-17 |
| Keyword trigger | `upstream documentation session` |
| Related commits / PR | Uncommitted working tree — ErrorLog domain + docs close-out |

## Delegation

### Monolith (done in this session)

- Core domain `ErrorLog` (`PLATFORM_CORE`, `config/domains.php`, provider, routes)
- Migration `error_logs` (UUID v7 PK, unique fingerprint, status, resolved_by)
- Capture: `bootstrap/app.php` `reportable` + `MessageLogged` listener; `BusinessRuleException` not stored; double-count guard for log+exception context
- Support: fingerprint, message normalizer, context redactor, `errorlog:clean`
- Config/env: `config/errorlog.php`, `ERRORLOG_*` on all `.env*.example` + `EnvironmentVariableCatalog`
- API: `GET /api/v1/error-logs`, `GET /api/v1/error-logs/{uuid}`, `PATCH /api/v1/error-logs/{uuid}/status` — permission `view error log`
- Blade: list/show/status filters; Administration menu; `en` / `id` / `ja` API + web strings
- Tests: `composer test -- --filter=ErrorLog` (18 passed)
- Docs: `docs/domains/error-log/README.md`, architecture, api-routing, security, environment, blade-admin, docs index, AGENTS, activity-log cross-link

### Monolith Gateway

- None required. Ops admin is Monolith Blade (`ConsumesApi` on the same app). No public SPA contract for error logs in this session. If a future SPA needs these endpoints via the BFF, proxy the three routes pass-through in a later changelog.

### Monolith Frontend

- None required. No Vue screens for error groups; administrators use Blade. SPA parity only if product later chooses Vue ops tooling.

## API contract

**Before:**

No error-log endpoints.

**After:**

```http
GET /api/v1/error-logs?status=open&level=error&type=exception&q=…&from=…&to=…&limit=50
Authorization: Bearer {jwt}
# permission: view error log
```

```json
{
  "status": "success",
  "message": "Error log entries retrieved successfully.",
  "data": [
    {
      "id": "0193…",
      "fingerprint": "a1b2…",
      "type": "exception",
      "level": "error",
      "exception_class": "RuntimeException",
      "message": "…",
      "file": "/path/to/file.php",
      "line": 42,
      "trace": "…",
      "context": { "url": "…", "method": "GET", "user_id": null },
      "occurrence_count": 3,
      "first_seen_at": "2026-07-17T00:00:00+00:00",
      "last_seen_at": "2026-07-17T01:00:00+00:00",
      "status": "open",
      "status_label": "Open",
      "resolved_at": null,
      "resolved_by": null,
      "created_at": "…",
      "updated_at": "…"
    }
  ]
}
```

```http
GET /api/v1/error-logs/{uuid}
PATCH /api/v1/error-logs/{uuid}/status
Content-Type: application/json

{ "status": "resolved" }
```

`status` ∈ `open` | `resolved` | `ignored`. Resolver user recorded on non-open statuses.

## Supersedes

- [pending/2026-07-17-error-log-domain.md](../pending/2026-07-17-error-log-domain.md) — early mid-task draft (optional GW/FE); formal close-out is Monolith-only `done/`

## Breaking change?

| Breaking? | Mitigation |
|-----------|------------|
| No | Additive domain, permission, routes, menu |

## Test plan

| Repo | Command |
|------|---------|
| Monolith | `composer test -- --filter=ErrorLog` |
| Gateway | N/A |
| Frontend | N/A |

## Status

| Field | Value |
|-------|-------|
| Status | `done` |
| Synced | 2026-07-17 (Monolith-only; no GW/FE actions) |
