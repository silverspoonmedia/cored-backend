# vTual — delegated per-creator capability grants

Decision record for the per-resource capability matrix (`bootstrap-task-2026-08.md:239` / `:996` phase 2). Code today: `app/Domain/Vtual/Services/ManagementAccessService.php`, `CreatorAccessService.php`.

Management access is **role-based and org-wide** today: any org role may view the org, `Owner`/`Admin` may mutate it, and an active `vtual_management_creators` assignment (with membership in that org) is what makes a linked creator visible. `CreatorAccessService::managementRoleForCreator()` grants edit on **every** creator the org holds, to every `Owner`/`Admin` of that org. There is no way to say "this member may edit *these* creators only".

That is the gap: a large management cannot delegate one creator to one staff member without also handing them the whole roster.

## The blocking decision

**Does a grant *replace* the role, or *add* to it?**

This is the whole decision. Everything else (table shape, UI, endpoints) follows from it, and it is the part that changes RBAC semantics rather than extending them. Both answers are defensible, and they are not compatible — pick one.

| Option | Semantics | Consequence |
|--------|-----------|-------------|
| **A — Additive** *(recommended)* | A grant is an **additional** allow on top of the role. `Owner`/`Admin` still edit every creator in the org; a grant lets a `Staff` member (or a non-member?) edit one named creator. | Strictly widens access. No existing behaviour changes, no lock-out risk, and a grant can be revoked by deleting a row. |
| **B — Replacing** | When an org enables the matrix, the role stops implying per-creator edit: a member edits a creator **only** if granted. | Narrows access. Every existing org that turns it on must grant its creators back, or its `Admin`s silently lose edit rights. Needs a migration/backfill and a very visible toggle. |

Recommendation: **A**. The matrix exists to *delegate* work to people who currently cannot do it (a staff member who should manage one vTuber), not to take power away from org owners. Option B is a re-model of org permissions and would need its own rollout plan, a backfill for every existing `vtual_management_creators` row, and a UI that makes the "your Admins can no longer edit" moment explicit. Nothing in the backlog asks for that.

## Shape if A is approved

| Piece | Detail |
|-------|--------|
| Table | `vtual_management_creator_grants`: `id` (uuid7), `management_id` → cascade, `member_id` → `vtual_management_members` cascade, `creator_id` → `vtual_management_creators` cascade, `capability` (`view`\|`edit`), `granted_by` → `users` nullOnDelete, timestamps. Unique `(member_id, creator_id)` — one row per pair, the higher capability wins on upsert. |
| Capability enum | `VtualGrantCapability` (`view`, `edit`) with an `atLeast()` helper, so a future `manage` tier is a case, not a schema change. |
| Read | `CreatorAccessService::canView/canMutate` consult the grant **after** the role check and only when the role did not already allow it. The request-primed lookup (`primeRequestLookup`) gains the same batch query so a roster page stays one query, not N. |
| Write | Grant/revoke gated by `manage vtual management members` + org `Owner`/`Admin` — the same gate as member management, since this is member management. A member cannot grant themselves. |
| UI | `VtualManagementEditView` — a capability control per row of the linked-creators table, visible only to `Owner`/`Admin`. |
| Realtime | Reuses `ManagementUpdated` with a new `kind=creator_grant`; the SPA subscriber for the management channel already refetches on any kind it recognizes. |

## Explicitly out of scope

- **No replace-mode toggle.** If B is chosen later it is a separate decision with its own backfill; the table above serves both, so nothing built now is wasted.
- **No grants on the org itself.** Capabilities are per creator; org-level power stays the role.
- **No grants outside the org.** A grant targets a member of the same management, never an arbitrary user — otherwise the matrix becomes a second, shadowy membership model.
- **No permission-name changes.** This is not a `Permission` row; it is a per-assignment allow, and the RBAC catalog stays as it is.

## Status

**Decided: A (additive) — implemented.**

| Piece | Where it shipped |
|-------|------------------|
| Table | `vtual_management_creator_grants` (migration `2026_09_27_000005_create_vtual_management_creator_grants_table.php`) — `id`, `management_id`, `member_id`, `creator_id`, `capability`, `granted_by`, unique `(member_id, creator_id)`. |
| Enum | `App\Domain\Vtual\Enums\VtualGrantCapability` (`view` \| `edit`) with `atLeast()` / `canMutate()` / `best()`. |
| Model | `App\Domain\Vtual\Models\ManagementCreatorGrant` (`log_name = vtual`, so a grant change is part of the management audit trail). |
| Read | `CreatorAccessService::grantForCreator()` + `grantForCreatorRequest()`; the grant is consulted **after** the role checks, so it only ever widens. `primeGrantLookup()` batches a page of creators into one query. `CreatorResource` exposes `my_grant_capability`. |
| Write | `ManagementService::grantCreatorCapability()` / `revokeCreatorGrant()`, gated by `manage vtual management members` + org `Owner`/`Admin` — the same gate as member management. `POST/DELETE /managements/{id}/creator-grants[/{grantId}]`; grants also ride the org payload as `creator_grants[]`. |
| UI | `VtualManagementGrantsSection.vue` — a grant form + table inside `VtualManagementEditView`, rendered only for `Owner`/`Admin`. |
| Realtime | `ManagementUpdated` `kind=creator_grant` (actions `granted` / `revoked`); the SPA union in `useVtualRealtime.ts` carries the value. |

Two consequences worth keeping in mind, both deliberate:

- **Unassigning drops the grants.** `unassignCreator()` deletes the delegation rows for that creator, and the read joins `vtual_management_creators` on `left_at IS NULL`. A grant can never outlive the roster entry it was scoped to, and a later re-assign cannot silently resurrect an old delegation.
- **`canManageMembers` is the gate, not a new permission.** Delegating one creator *is* member management, so it reuses that permission rather than adding a `Permission` row.

Tests: `ManagementCreatorGrantsApiTest` (8 cases — grant/revoke, Staff-with-grant edits exactly one creator, `view` grant reads but cannot write, an Admin is unaffected by the matrix existing, unassign makes a grant inert, unassigned creator 422, unknown member / self-grant 422, plain member 403).

