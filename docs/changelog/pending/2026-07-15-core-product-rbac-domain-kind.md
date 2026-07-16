# Changelog entry

## Summary

Roles and permissions now carry `domain_kind` (`core` | `product`). Hierarchy levels are unique per kind; platform H-RBAC (user management list/view/edit/delete/assignable) uses **core** roles only. Role summary JSON includes `domain_kind`.

## Session metadata

| Item | Detail |
|------|--------|
| Date | 2026-07-15 |
| Keyword trigger | mid-task adaptation / session work |
| Related commits / PR | Uncommitted — local working tree changes |

## Delegation

### Monolith (done in this session)

- Migration: `domain_kind` on `roles` + `permissions`; level unique `(domain_kind, level)`.
- `DomainKind` enum; `Role` / `Permission` fillable, cast, `core()` / `product()` scopes.
- `RoleService` + user visibility queries (repository, lookups) scope hierarchy to core.
- `RoleSeeder` always seeds `domain_kind = core`.
- `UserRoleSummaryResource` exposes `domain_kind`.
- Unit tests: `RoleServiceTest`, `RoleSeederDomainKindTest`.
- Docs: identity README, platform architecture.

### Monolith Gateway

- [ ] If any transform/aggregate maps user roles or assignable roles, pass through new field `domain_kind` on role objects (additive; ignore if proxy is pass-through).
- [ ] No route or auth middleware changes required.

### Monolith Frontend

- [ ] If Vue types/stores map `roles[]` from user show/me/edit-context, add optional `domain_kind: 'core' | 'product'` on role summary.
- [ ] Do not use product roles for platform user-management UI; assignable list remains core-only.

## API contract

**Before:**

```json
{
  "roles": [
    { "name": "Admin", "level": 100 }
  ]
}
```

**After:**

```json
{
  "roles": [
    { "name": "Admin", "level": 100, "domain_kind": "core" }
  ]
}
```

Additive field on role summaries (`UserRoleSummaryResource`). Assignable-roles list remains core roles only (behavior unchanged for seeded data).

## Supersedes

- None

## Breaking change?

| Breaking? | Mitigation |
|-----------|------------|
| No (additive JSON) | Clients that ignore unknown fields need no change; typed clients should add optional `domain_kind`. |

## Test plan

| Repo | Command |
|------|---------|
| Monolith | `php vendor/bin/phpunit --filter "RoleServiceTest\|RoleSeederDomainKindTest\|UserManagementTest"` |
| Gateway | Pass-through role payloads after sync |
| Frontend | Type-check role summary if types exist |

## Status

| Field | Value |
|-------|-------|
| Status | pending |
| Downstream sync | not started |
