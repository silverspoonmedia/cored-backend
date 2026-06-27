# Documentation audit log (2026-06-27)

Verification of README and `docs/` against the codebase. **Code is the source of truth**; docs updated after automated tests passed.

| Document | Finding | Action |
|----------|---------|--------|
| `routes/api.php` | Playground production guard commented out | **Fixed code** — guard re-enabled |
| `docs/playground.md`, README Security | Claimed prod guard active | Verified after code fix; added ping-always note |
| `SECURITY_AUDIT.md` | Indonesian prose; FINDING-003 body said Open; stale residual risks | Translated to English; status corrected; test reference added |
| `docs/api-routing.md` | Missing approval routes + playground guard | Expanded global routes section |
| `docs/list-display-modes.md` | Bootstrap 4 (actual: Bootstrap 5.3.7) | Updated to Bootstrap 5 / jQuery 3.7 |
| `docs/web-frontend.md`, `routes/web.php` | No role middleware (exception: approval admin) | Documented `role:Root\|Admin` exception |
| `README.md` | Missing approval engine; incomplete seeder table | Updated intro + 4-user seeder table |
| `docs/user-management-hrbac.md` | Indonesian-only error examples | Added en/id comparison tables |
| `docs/approval-matrix-engine.md` | Wrong route file (`routes/api_v1.php`) | Aligned with `routes/api.php` |
| `routes/web.php` | Duplicate register routes | Removed duplicate |
| Other `docs/*` | Spot-checked jwt, notifications, auth-activity, localization, uuid, octane, pint | **Verified accurate** — no changes |

**Tests added:** `PlaygroundProductionGuardTest`, `ApiRoutingRegistrationTest`, `WebApprovalWorkflowRoleMiddlewareTest` (333 tests passing).
