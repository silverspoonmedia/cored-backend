# Remove Approval matrix engine

## Summary

Monolith fully removes the Approval matrix engine (domain code, API/Web surfaces, schedules, seeders, menus, Blade, lang, tests, and `approval_*` tables). Playground `OrderService` no longer initiates workflows; order `status` stays at default `pending` unless changed elsewhere. Downstream Gateway/Frontend still expose or call these endpoints until a **`downstream sync session`**.

## Session metadata

| Item | Detail |
|------|--------|
| Date | 2026-07-16 |
| Keyword trigger | mid-task adaptation (HTTP contract removed) |
| Related commits / PR | uncommitted — Approval domain product removal |

## Delegation

### Monolith (done in this session)

- Deleted `app/Domain/Approval/`, `ApprovalServiceProvider`, route files, Blade views, lang files, `ApprovalWorkflowSeeder`, `tests/Domain/Approval/`, debug scripts.
- Unregistered provider, event/command discovery, schedules, `PLATFORM_CORE` / `config/domains.php`.
- Detached `OrderService` from `ApprovalService` (create no longer auto-initiates).
- Dropped menu items My Approvals + Approval Matrix from `MenuSeeder`.
- Migration `2026_07_16_220001_drop_approval_tables` drops all `approval_*` tables.
- Docs: removed domain README + legacy dump; updated architecture, api-routing, blade-admin, spa-gateway, activity-log, root README, docs index.
- Routing test asserts `/api/v1/approvals/my-pending` returns 404.

### Monolith Gateway

- [ ] Stop proxying `/api/v1/approvals/*` and `/api/v1/admin/approval-workflows/*` (or let 404 pass-through after Monolith removal — prefer remove route map/transforms if explicit).
- [ ] Remove approval entries from `config/vue-routes.php` (or equivalent route/name map) if present.
- [ ] Remove any Gateway aggregates/bootstrap fields that surface pending approvals or workflow admin.
- [ ] Drop Gateway tests that assert approval proxy parity.

### Monolith Frontend

- [ ] Remove Vue routes, API clients, stores, i18n, and views for My Approvals and Approval Matrix admin.
- [ ] Remove navigation/menu entries that deep-link to approval screens.
- [ ] Remove unit/e2e tests covering approval flows.
- [ ] Ensure order create UI no longer expects approval side-effects (status stays `pending`).

## API contract

**Before:** authenticated JWT surface for runtime approvals and workflow admin under `/api/v1`.

| Method | Path |
|--------|------|
| GET | `/api/v1/approvals/my-pending` |
| GET | `/api/v1/approvals/instances/{instance}` |
| POST | `/api/v1/approvals/slots/{slot}/approve` |
| POST | `/api/v1/approvals/slots/{slot}/reject` |
| POST | `/api/v1/approvals/slots/{slot}/reassign` |
| POST | `/api/v1/approvals/slots/batch-approve` |
| GET | `/api/v1/admin/approval-workflows` |
| POST | `/api/v1/admin/approval-workflows` |
| GET | `/api/v1/admin/approval-workflows/{workflow}` |
| PUT | `/api/v1/admin/approval-workflows/{workflow}` |
| POST | `/api/v1/admin/approval-workflows/{workflow}/activate` |
| POST | `/api/v1/admin/approval-workflows/{workflow}/deactivate` |
| POST | `/api/v1/admin/approval-workflows/{workflow}/steps` |
| DELETE | `/api/v1/admin/approval-workflows/{workflow}/steps/{step}` |
| POST | `/api/v1/admin/approval-workflows/{workflow}/steps/{step}/approvers` |
| DELETE | `/api/v1/admin/approval-workflows/{workflow}/steps/{step}/approvers/{approver}` |

**After:** all of the above are **removed** (404). Order CRUD APIs unchanged except create no longer starts an approval instance; order `status` default remains `pending` (no auto `completed`/`cancelled` from approval).

## Supersedes

- None

## Breaking change?

| Breaking? | Mitigation |
|-----------|------------|
| Yes | Product removal. Downstream must drop clients/proxies. Existing DBs run drop migration (not `migrate:fresh` unless `ALLOW_DESTRUCTIVE_DB=true`). |

## Test plan

| Repo | Command |
|------|---------|
| Monolith | `composer test -- --filter=ApiRoutingRegistration`; `composer test -- --filter=Playground`; `composer test` |
| Gateway | `php artisan test` (after removing approval proxy coverage) |
| Frontend | `npm run test:unit -- --run`; `npm run build` (after removing approval UI) |

Monolith checks:

1. `GET /api/v1/approvals/my-pending` with Admin JWT → 404
2. Order create still creates order + pending invoice; no approval instance
3. `php artisan route:list` has no `approvals` / `approval-workflows` names
4. Menu seed has no My Approvals / Approval Matrix

## Status

| Field | Value |
|-------|-------|
| Status | `pending` |
| Synced | — |
