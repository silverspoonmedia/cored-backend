# File Hosting Collaborative Works (team workspaces)

**Date:** 2026-07-19  
**Status:** pending (Monolith implemented; Gateway/Frontend not synced)

## Summary

Greenfield **Collaborative Works** on File Hosting: paid-owner workspace, invited members with roles, shared folder tree on owner quota, Spatie activity feed.

## Monolith changes

### Schema
- `file_hosting_workspaces` — multi-owned (see `2026-07-19-file-hosting-multi-owned-workspaces.md`)
- `file_hosting_workspace_members` — role `owner|admin|editor|viewer`, status `active|invited|removed`
- `file_hosting_workspace_invites` — email invite + token
- Nullable `workspace_id` on folders / files / uploads
- `created_by` on files (uploader audit)

### Domain
- `WorkspaceService`, `WorkspaceAccessService`, `WorkspaceActivityRecorder`
- Browse/upload/CRUD accept optional `workspace_id`
- Team files: `user_id` = owner (S3 + quota), `workspace_id` set
- Paid gate: `ServiceEntitlementService::hasActivePaidFileHosting` (main or storage addon); grace blocks collab
- Activity log_name: `file_hosting.workspace`

### API (`/api/v1/file-hosting/*`)
| Method | Path | Notes |
|--------|------|--------|
| GET | `workspaces` | Active owned **or** active-member workspaces only |
| POST | `workspaces` | Create (paid owner, under owned slot limit) |
| GET | `workspaces/{id}` | Show |
| POST | `workspaces/{id}/archive` | Owner only |
| GET | `workspaces/{id}/members` | List active members |
| POST | `workspaces/{id}/invites` | `{ email, role }` |
| POST | `workspaces/invites/accept` | `{ token }` — joiner need not be paid; owning another WS OK |
| PATCH | `workspaces/{id}/members/{user}` | `{ role }` |
| DELETE | `workspaces/{id}/members/{user}` | Remove |
| GET | `workspaces/{id}/activity` | Feed (viewer+) |
| GET | `browse?workspace_id=` | Team tree |
| POST | `uploads` / `folders` | optional `workspace_id` |

### Workspace JSON (list/show)
- `my_role`: `owner|admin|editor|viewer` for the authenticated user
- `is_owner`: boolean
- Multi-membership: same user may appear as owner on A and editor on B

### Blade UX (free vs paid)
- Collab panel on File Hosting index (**not** a separate dynamic menu item)
- Free: can/cannot lists, upgrade CTA, accept invite token
- Paid: create workspace modal, open owned team, invite (token flashed — no email in MVP), members table, archive
- Grace: renew CTA; collab create blocked
- Switcher: Personal + **all** active memberships with role + Yours/Joined badges
- Member mgmt UI for owner **or** admin; “You are: {role}” in team context
- Quota bar shows plan badge (`free`/`paid`/`grace`)
- Web consumer routes: `POST file-hosting/workspaces`, invites, member remove, archive

### Quota API
- `GET quota` adds `collaborative_works`, `can_create_workspace` (both true only when `state === paid`)

## Delegation

| Layer | Action |
|-------|--------|
| **Gateway** | Proxy new workspace routes if route allowlist is explicit; pass through query/body `workspace_id` |
| **Frontend** | Workspace switcher listing all memberships with `my_role` / `is_owner`; invite UI; activity drawer; include `workspace_id` on browse/upload/CRUD |
| **Workers** | None (S3 key still owner-based) |

## Tests
- `tests/Domain/FileHosting/Unit/Services/WorkspaceCollaborativeWorksTest.php` (own+join, free join, archive reactivate, `my_role` API)
