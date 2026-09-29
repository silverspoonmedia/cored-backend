# API aggregation policy

Canonical rules for when and how multiple API reads are aggregated into one call. Lives alongside [cache-policy.md](cache-policy.md) and [configuration-policy.md](configuration-policy.md). The single aggregation transport is `POST /api/v1/batch` (`routes/api/v1/batch.php`, [BatchController](../../app/Http/Controllers/API/BatchController.php)); SPA contract in [../integration/vue-workers-spa.md](../integration/vue-workers-spa.md).

## Three rules

1. **Endpoints always stand alone.** Every resource keeps its own directly-callable endpoint. No endpoint may exist *only* inside an aggregated result. Aggregation is an optimization layered on top of standalone endpoints, never a replacement for them — the standalone endpoint stays the source of truth.

2. **Aggregation exists for the SPA.** The aggregation endpoint (`/api/v1/batch`) is built for the "Monolith-Vue-Workers" SPA. It is not a public contract for other consumers. Third-party / machine clients call the standalone endpoints.

3. **Aggregation lives in the Monolith backend.** Fan-in and dispatch happen in `BatchController` on the server. The SPA is a thin client: a hydrate composable sends a list of API-relative paths and unwraps positional results (`bodyOf`/`dataOf`). No response-merging or aggregation logic may live in the SPA.

## Gate condition — aggregate only when safe

Aggregate a read into `/batch` **only when every sub-request would pass its own gates unchanged** — middleware, throttle, and RBAC/permission checks. `BatchController` re-dispatches each sub-request through the full HTTP kernel (`App::handle`), so target routes keep their own auth and permission middleware; a sub-request that would be rejected standalone is rejected inside the batch too (per-entry 401/403, and `bodyOf()` returns `null`).

Do **not** bend a gate just to make a read fit aggregation. If a middleware, rule, or RBAC check would have to be relaxed or bypassed for the read to join a batch, keep that read on its standalone endpoint. Never overfit.

Concrete consequences:

- **Auth-only.** `/batch` itself requires `auth:api`. Guest-facing reads (e.g. anyone-with-link public file/folder) are **not** batched — relaxing batch auth to admit them would weaken the gate.
- **Read-only.** Only `GET`/`HEAD` sub-requests are allowed; mutations are rejected per-entry (`422`). Mutations stay one-request-per-action so throttles, audit logs, and idempotency keep working.
- **Per-entry failure falls back to standalone.** A sub-request that fails its gate inside the batch returns `null` from `bodyOf()`; the SPA caller then runs its normal standalone loader. Standalone is always the fallback path.

## Convention for new endpoints

When adding an endpoint, decide its aggregation candidacy:

- If a SPA view needs **2+ independent endpoints on mount**, aggregate them via `/batch` through a `use{Domain}Hydrate` composable — not a dedicated backend aggregate endpoint, not merging in the SPA.
- A single endpoint that a view needs alone is **not** wrapped in a batch (a 1-entry batch is pure overhead).
- Aggregation is optional. The standalone endpoint remains the primary contract; the batch is a transport-level fan-in for SPA hydration.

## Limits

- Max **10** sub-requests per batch call (`BatchController::MAX_REQUESTS`). The SPA `batch()` client **chunks** a larger fan-out into sequential 10-entry calls and re-joins the positional results — entries are never silently dropped.
- `GET`/`HEAD` only; paths locked to `/api/v1/*`.
- Throttled `30,1` (tighter than the global `60/min` API limit) because one call multiplies into several sub-requests. Additionally, each sub-request is **charged to the shared `api` limiter** server-side, so a batch cannot smuggle N reads into one throttle token.
- **Wall-clock budget:** one batch aborts after 10 seconds (`BatchController::WALL_CLOCK_SECONDS`); entries past the budget return per-entry `504` so a slow fan-in cannot pin a PHP worker.
- Sub-requests run sequentially in-process; each keeps its own auth/permission middleware.

## Partial failure contract

The outer response stays `200` even when some (or all) entries fail, because each entry carries its own `status`. To let callers distinguish partial failure without parsing every entry, the envelope exposes:

```json
{ "status": "success", "data": [ ... ], "meta": { "failed": 1, "total": 2 } }
```

`meta.failed` counts entries with `status >= 400`; `meta.total` counts all entries. A terminal entry (`401`/`403`/`429`) must **not** be retried standalone by the SPA — see the SPA contract (`bodyOfOrThrow` / `TerminalBatchError`).

## Related

- Pakem: [api-resources.mdc](../../.cursor/rules/api-resources.mdc) — the standalone-first rule and the `/batch` envelope an agent must follow
- [api-envelope.md](api-envelope.md) · [list-display-modes.md](list-display-modes.md)
