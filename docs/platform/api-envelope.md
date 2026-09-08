# API response envelope (canonical contract)

All `/api/*` JSON responses share one envelope, produced by `App\Support\ApiResponder` and the exception renderers in `bootstrap/app.php`. This file is the canonical contract — domain docs link here instead of restating shapes.

## Envelope

```json
{ "status": "success", "message": "…", "data": { … } }
```

| Key | Presence | Notes |
|-----|----------|-------|
| `status` | Always | `"success"` \| `"error"` (string, not boolean) |
| `message` | Always (nullable) | Translated via `__('api/…')` / `__('web/…')`; user-facing. On success often `null`. |
| `data` | Only when non-null | Resource payload, list, or error detail bag |
| `meta` | Only where the endpoint defines it (e.g. `/batch`) | Not a global envelope key |

`ApiResponder::success($data, $message, $code)` / `ApiResponder::error($message, $code, $data)` are the only builders. Domain code throws; controllers return Resources; the envelope is applied centrally — never hand-assemble a different shape.

## Error mapping (bootstrap/app.php)

All renderers below are scoped to `$request->is('api/*')` — server `web` endpoints keep Laravel defaults.

| Condition | HTTP | Body |
|-----------|------|------|
| `ModelNotFoundException` (User) | 404 | `api/users.not_found` |
| `ModelNotFoundException` (other) / `NotFoundHttpException` | 404 | `api/common.data_not_found` / `api/common.resource_not_found` |
| `AuthenticationException` | 401 | `api/auth.bearer_required` (no token) / `api/auth.token_invalid` (token present) |
| `UnauthorizedHttpException` / JWT exceptions | 401 | `token_expired` / `token_invalid` / `token_revoked` / `could_not_authenticate` |
| `PermissionUnauthorizedException` / `AuthorizationException` / `AccessDeniedHttpException` | 403 | `api/common.unauthorized` (or exception message) |
| `ThrottleRequestsException` | 429 | `api/common.too_many_requests` **+ `Retry-After` / `X-RateLimit-*` headers copied through** |
| `BusinessRuleException` (abstract, per-domain subclasses) | `statusCode()` per subclass | Exception message (already translated) via `ApiResponder::error` |
| Validation failure (FormRequest) | 422 | Laravel default shape: `{ "message": …, "errors": { field: […] } }` — **not** the `ApiResponder` envelope. Handlers must read `errors` |

CSRF double-submit mismatch on SPA mutations (`VerifySpaCsrf`) returns `ApiResponder::error(__('api/common.spa_csrf_mismatch'), 419)` as a **response** (not an abort) so `HandleCors` still stamps `Access-Control-Allow-*` and the SPA can read the 419 and retry with a fresh nonce. Exception/`abort()` responses skip the CORS wrap, so the renderers stamp the credentialed allowlist themselves — the SPA always sees real 401/403/419 statuses instead of a browser CORS `TypeError`.

## Batch (`POST /api/v1/batch`)

Read-only fan-in (`GET`/`HEAD` sub-requests only, ≤ 10, 10 s wall-clock; `BatchController`):

- Outer response is **always 200** `ApiResponder::success($responses)` with `meta: { failed, total }` — even when every sub-request failed. Callers must inspect per-entry status, not the outer code.
- Each entry: `{ "status": <sub HTTP code>, "body": <decoded JSON or raw string> }`. Sub-bodies are full envelopes (`status`/`message`/`data`).
- Entries past the wall-clock budget return `504` `api/common.batch_timeout`; a crashed sub-request returns `500` `api/common.batch_sub_failed` (and is `report()`ed).
- Outer validation failures (missing/too many `requests`) are normal 422 envelope errors.
- Throttle: one outer token (`throttle:30,1`) + each sub-request charged to the shared `api` limiter so batch cannot bypass the global budget.

Full consumer rules: [api-aggregation.md](api-aggregation.md) and [integration/vue-workers-spa.md — Invocation budget controls](../integration/vue-workers-spa.md#invocation-budget-controls).

## Related

- [api-routing.md](api-routing.md) — global vs versioned layers
- [security.md](security.md) — response data minimization (what must NOT appear in `data`)
- [list-display-modes.md](list-display-modes.md) — query-mode `actions[]` inside list payloads
