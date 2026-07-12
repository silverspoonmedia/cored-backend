# Downstream changelog entry

> **Origin:** cross-repo desync audit (2026-07-12). Not triggered by a single Monolith PR — backlog discovered retroactively.

## Summary

Vue router defines payment, invoice, subscription, and iPaymu return routes, but `src/router/breadcrumbs.ts` has no trail builders for them. `resolveBreadcrumbs()` falls back to dashboard-only for those screens. Users see incorrect navigation context on payment admin and billing pages compared to users, orders, menus, and approvals.

## Monolith reference

| Item | Detail |
|------|--------|
| Date | 2026-07-12 |
| Commit / PR | Audit only — no Monolith change |
| Files | `routes/web/payment-gateway.php` (web route names); `database/seeders/MenuSeeder.php` (menu labels); `lang/{locale}/` payment copy |
| Routes | Web names: `payment-channels.index`, `payment-gateways.index`, `invoices.index`, `invoices.show`, `subscriptions.index`, `subscriptions.create`, `subscriptions.show`, `subscriptions.edit` |

## API contract

No API change. UX-only alignment with existing Vue route names:

| Vue route `name` | Path |
|------------------|------|
| `payment-channels.index` | `/payment-channels` |
| `payment-gateways.index` | `/payment-gateways` |
| `invoices.index` | `/invoices` |
| `invoices.show` | `/invoices/:invoice` |
| `subscriptions.index` | `/subscriptions` |
| `subscriptions.create` | `/subscriptions/create` |
| `subscriptions.show` | `/subscriptions/:subscription` |
| `subscriptions.edit` | `/subscriptions/:subscription/edit` |
| `payments.ipaymu.return` | `/payments/ipaymu/return` |
| `payments.ipaymu.cancel` | `/payments/ipaymu/cancel` |

## Gateway actions

- [x] None required (Frontend-only UX).

## Frontend actions

- [x] Add trail builders in `src/router/breadcrumbs.ts` for all payment/billing route names listed above (follow pattern: dashboard → section index → detail/edit).
- [x] Reuse existing i18n keys where possible (`payments.channels.title`, `payments.gateways.title`, `payments.invoices.*`, `payments.subscriptions.*`, `payments.ipaymuReturn.title`) — add keys only if missing in `en`, `id`, `ja`.
- [ ] Verify `AppLayout` breadcrumb bar on each payment screen shows correct hierarchy.
- [x] Optional: add Vitest for `resolveBreadcrumbs()` on `invoices.show` and `subscriptions.edit`.

## Breaking change?

| Breaking? | Mitigation |
|-----------|------------|
| No | Additive breadcrumb trails only |

## Test plan (downstream sync session)

| Repo | Command |
|------|---------|
| Monolith | `composer test` (unchanged) |
| Gateway | `php artisan test` (unchanged) |
| Frontend | `npm run test:unit -- --run`; `npm run build` |

**Manual checks:**

1. Visit `/payment-channels`, `/payment-gateways`, `/invoices`, `/subscriptions` — breadcrumb shows Dashboard → section title (not dashboard-only).
2. Open invoice show and subscription edit — breadcrumb includes parent list + current item label.
3. Land on `/payments/ipaymu/return` after checkout — breadcrumb shows sensible trail (dashboard → payments context or dedicated return label).

## Status

| Field | Value |
|-------|-------|
| Status | `done` |
| Synced | 2026-07-12 |
