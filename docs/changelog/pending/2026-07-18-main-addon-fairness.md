# Main plan fairness + fixed addon anniversary

**Date:** 2026-07-18  
**Domain:** Service × File Hosting  
**Status:** pending (Monolith implemented)

## Summary

- Remove free catalog SKU (`file-hosting.plan.free`); free = config behaviour only.
- Main checkout classifies `new` / `renew` / `upgrade` / `downgrade` with **unused-gap credit** (30-day fixed math). Surplus credit → **extra days** on new plan. `$0` charge fulfills immediately (no PG).
- Addon unit buys: `quantity++` without moving `ends_at`; shared anniversary across addon SKUs.
- Scheduler creates pending **full-stack** addon renewal invoice (`service_addon_renewal` morph); pay extends all addon periods.

## API contract notes

| Field | Notes |
|-------|--------|
| Purchase `change_type` | `new` \| `renew` \| `upgrade` \| `downgrade` \| `addon_unit` |
| `list_amount`, `credit_amount`, `charge_amount`, `surplus_days` | Quote breakdown; `display_amount` = charge |
| Checkout charge 0 | Purchase `paid` immediately; `invoice` may be null |
| Morph `service_addon_renewal` | New invoiceable for stack renewals |

Quota `state` remains `paid` \| `grace` \| `free` (unchanged from prior main/addon work).

## Delegation

| Repo | Action |
|------|--------|
| **Monolith** | Schema, calculator, fulfill, addon renewal, Blade quote, tests, docs |
| **Gateway** | Pass through new purchase quote fields if proxied |
| **Frontend** | Show credit/surcharge/extra days; handle paid zero-invoice checkout |

## Why

Hard main replace burned unused time; addon unit buys incorrectly stacked calendar months. Fairness + fixed anniversary match product billing intent without using PG demo `/subscriptions`.
