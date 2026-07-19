# FH billing: list credit, fixed 30d period, block due unit buy

**Date:** 2026-07-18  
**Domain:** Service × File Hosting  
**Status:** pending (Monolith implemented)

## Summary

Fixes from billing use-case matrix:

1. **B1** — Unused-gap credit uses last paid **`list_amount`** (not net charge / `$0` display).
2. **B2/B3** — Addon **unit checkout blocked** (422) when any addon `ends_at <= now` or a pending `service_addon_renewal` exists. Pay stack first.
3. **A05** — FH storage entitlement periods are fixed **+30 days** (`FileHostingBillingPeriod`), aligned with credit divisor 30 (no calendar `addMonth`).

## API contract notes

| Change | Notes |
|--------|--------|
| Checkout errors | New i18n keys: `api/services.addon_renewal_due`, `api/services.addon_renewal_pending` (HTTP 422) |
| Purchase quote | Credit math base = list; zero-charge purchases still store positive `list_amount` |
| Entitlement ends | FH main/addon: `+30 days` per period (renew/switch/extend stack) |

## Delegation

| Repo | Action |
|------|--------|
| **Monolith** | Calculator, entitlement period, checkout gate, tests, docs |
| **Gateway** | Pass through 422 messages if proxied |
| **Frontend** | Surface block reason; prompt pay stack renewal before more units |

## Why

Net-charge credit under-valued periods after discounted upgrades and collapsed after `$0` switches. Due-day unit buys skipped stack invoices. Calendar months vs ÷30 credit split clocks.
