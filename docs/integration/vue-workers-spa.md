# Leading SPA frontend — Cored Frontend

Cored Frontend is the **sole browser UI**: a Vue 3 + Vite SPA deployed to Cloudflare Workers as static assets, talking straight to the Laravel API origin — never through a proxy.

This file is the **cross-repo contract**: cookies, CSRF, headers, envelopes, `SPA_URL` minting, route-name sync, type alignment and realtime channels. Screen-level UX lives in the sibling repo's topic docs — open those first. Open this brief only when the contract moves; the tables, gotchas and channel inventory live in the deep dive.

| Topic | SPA doc (sibling `cored-frontend/docs/`) |
|-------|-----------------------------------------------|
| Layout, direct-to-API, `src/` map | [architecture.md](../../../cored-frontend/docs/architecture.md) |
| Cookie, CSRF, hydrate, logout, BroadcastChannel | [auth-session.md](../../../cored-frontend/docs/auth-session.md) |
| `client.ts`, headers, 401/419, batch helper | [api-client.md](../../../cored-frontend/docs/api-client.md) |
| Locales, RTL, `check:i18n` | [i18n.md](../../../cored-frontend/docs/i18n.md) |
| AdminLTE4 layout, Swal, skeletons, loading/error/empty states | [ui-patterns.md](../../../cored-frontend/docs/ui-patterns.md) |
| Vue Router names ↔ `config/spa-routes.php` | [routes.md](../../../cored-frontend/docs/routes.md) |
| Worker guard-only stub | [worker.md](../../../cored-frontend/docs/worker.md) |
| Deploy / Wrangler envs | [deployment.md](../../../cored-frontend/docs/deployment.md) |

| Need | Path |
|------|------|
| Backend envelope | [api-envelope.md](../platform/api-envelope.md) · aggregation rules [api-aggregation.md](../platform/api-aggregation.md) |
| Response minimization | [reference-security.md — response data minimization](../platform/reference-security.md#response-data-minimization-mandatory) |
| Per-domain API surfaces | [payment-gateway](../domains/payment-gateway/README.md) · [identity](../domains/identity/README.md) · [navigation](../domains/navigation/README.md) · [inference](../domains/inference/README.md) · [ai](../domains/ai/README.md) |
| Orientation | [project-overview.md](project-overview.md) |
| Pakem | [context-budget.mdc](../../.cursor/rules/context-budget.mdc) · [task-scope.mdc](../../.cursor/rules/task-scope.mdc) |
| Deep dive | [reference-vue-workers-spa.md](reference-vue-workers-spa.md) — architecture, direct API + CORS, invocation budget, `SPA_URL` minting, contract gotchas, type alignment, realtime channel inventory |
