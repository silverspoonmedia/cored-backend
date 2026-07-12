# Downstream changelog entry

> Copy this file to `pending/YYYY-MM-DD-<slug>.md` when a Monolith task triggers the [adaptation contract](../internal-monorepo-assessment.md#adaptation-contract-gateway--frontend). Delete this instruction block after copying.

## Summary

<!-- What changed and why (one short paragraph). -->

## Monolith reference

| Item | Detail |
|------|--------|
| Date | YYYY-MM-DD |
| Commit / PR | <!-- hash or PR URL --> |
| Files | <!-- key paths --> |
| Routes | <!-- method + path --> |

## API contract

<!-- Request/response diff. Omit section if URLs and JSON are unchanged. -->

**Before:**

```json

```

**After:**

```json

```

## Gateway actions

- [ ] <!-- proxy route, transform, aggregate, vue-routes.php, tests -->

## Frontend actions

- [ ] <!-- src/api/*, router, stores, i18n, views, tests -->

## Breaking change?

| Breaking? | Mitigation |
|-----------|------------|
| No / Yes | <!-- version bump, feature flag, etc. --> |

## Test plan (downstream sync session)

| Repo | Command |
|------|---------|
| Monolith | `composer test` |
| Gateway | `php artisan test` |
| Frontend | `npm run test:unit -- --run`; `npm run build` |

<!-- Add scenario-specific checks. -->

## Status

| Field | Value |
|-------|-------|
| Status | `pending` |
| Synced | — |
