# File Hosting multi-owned workspaces + collab UI declutter

**Date:** 2026-07-19  
**Status:** pending (Monolith implemented; Gateway/Frontend not synced)

## Summary

Paid subscribers may own **multiple** team workspaces (base 10 + stackable $1 slot addon). Collab UI moved off the full-width card into toolbar dropdown + team offcanvas.

## Monolith changes

### Schema
- Migration `2026_07_19_140000_allow_multiple_owned_workspaces`: drop `unique(owner_user_id)` on `file_hosting_workspaces`; add index `(owner_user_id, status)`.

### Service catalog
- New SKU `file-hosting.addon.workspace-slot.monthly` — PlanKind addon, `storage_bytes = 0`, entitlement `workspace_slots`, **$1 USD/mo**, stackable quantity.
- `Service::isFileHostingStoragePlan()` now requires `storage_bytes > 0` (slot excluded from storage recompute / paid storage gate).
- `Service::isAddonPlan()` includes slot SKUs; `isWorkspaceSlotAddon()` helper.
- Addon anniversary / stack renewal / unit-buy block include **all** FH addons (storage + slots).

### Domain (File Hosting)
- `owned_limit = (paid storage ? base : 0) + sum(slot qty)`; base from `config('file-hosting.base_owned_workspace_slots')` (default 10).
- `WorkspaceService::create` asserts slot limit; always inserts new row (archive frees slot; no reactivate-first).
- `QuotaService::usage` adds `owned_workspace_used`, `owned_workspace_limit`; `can_create_workspace` = paid **and** under limit.

### API
| Field / path | Notes |
|--------------|--------|
| `GET file-hosting/quota` | + `owned_workspace_used`, `owned_workspace_limit`; `can_create_workspace` respects slots |
| `POST file-hosting/workspaces` | Multi-create until limit; 422 `workspace.slot_limit` when full |

### Blade UX
- Toolbar: workspace dropdown (Personal + teams + create/join + slot counter) + **Team** button → offcanvas (members, invite, activity, archive).
- Removed full-width collab panel and activity card above file list.

### Config / i18n
- `FILE_HOSTING_BASE_OWNED_WORKSPACE_SLOTS` (optional env).
- API/web strings: `slot_limit`, slots counter, team settings, etc. (en/id/ja).

## Delegation

| Consumer | Action |
|----------|--------|
| Gateway | Pass through new quota fields if proxied; no path change |
| Frontend (Vue) | Multi-owned switcher + slot counter; hide create when `!can_create_workspace`; surface slot addon SKU on Services catalog |
| Workers | None |

## Tests
- `WorkspaceCollaborativeWorksTest` — multi-create cap, slot addon raises limit without storage, archive frees + new row
- `FileManagerControllerTest` — toolbar switcher strings / paid create CTA
