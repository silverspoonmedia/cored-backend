# File Hosting main + stackable addon plans

**Date:** 2026-07-18  
**Domain:** Service × File Hosting  
**Status:** pending (Monolith implemented; Gateway/Frontend consumers may need state rename)

## Summary

Replace unlimited File Hosting POC (`file-hosting.subscription.monthly` → `quota_bytes=0`) with:

- **Main plans** (exclusive): Standard 1 TB / 2 TB
- **Addon plans** (stackable quantity): +100 GB / +500 GB
- **Free 5 GiB always stacked** from `file-hosting.default_quota_bytes`

## API contract notes

| Before | After |
|--------|-------|
| Quota `state`: `unlimited` \| `grace` \| `free` | `paid` \| `grace` \| `free` |
| `quota_bytes: 0` means unlimited | Numeric effective bytes only for tiers |
| Service resource: no plan fields | `plan_kind`, `storage_bytes` |
| Entitlement resource: no quantity | `quantity` |

Catalog codes (seed):

- `file-hosting.plan.free` (display only)
- `file-hosting.plan.1tb.monthly` / `file-hosting.plan.2tb.monthly`
- `file-hosting.addon.100gb.monthly` / `file-hosting.addon.500gb.monthly`

Legacy `file-hosting.subscription.monthly` archived.

## Delegation

| Repo | Action |
|------|--------|
| **Monolith** | Schema, recompute, fulfillment, lifecycle, Blade, i18n, tests, domain docs |
| **Gateway** | If proxy exposes quota/catalog, pass through `plan_kind` / `storage_bytes` / `quantity` / state `paid` |
| **Frontend** | Replace unlimited UI with numeric paid quota; map `state=paid` |

## Why

POC proved pay → fulfill → grace → expire. Product needs tiered exclusive mains + stackable addons without double-counting free tier.
