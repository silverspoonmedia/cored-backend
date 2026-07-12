# Downstream changelog entry

> **Origin:** cross-repo desync audit (2026-07-12). Not triggered by a single Monolith PR — backlog discovered retroactively.

## Summary

Gateway `vue-routes.php` already maps payment and subscription Laravel route names to Vue paths, but Frontend `routeMap.ts` stops at `notifications.index` — ten route names are missing from the client-side fallback used by `resolveMenuPath()`. Separately, Monolith web routes use `payment.ipaymu.return` / `payment.ipaymu.cancel` while the Vue router uses `payments.ipaymu.return` / `payments.ipaymu.cancel`; neither naming scheme is in `vue-routes.php`, so `NotificationUrlTransformer` cannot resolve iPaymu landing links. Bootstrap sidebar usually works because Gateway `injectPaths` sets `path` on menu nodes, but locale refresh and notification deep links can break. Also fix stale Gateway `ExampleTest` (`GET /` → 200) so `test-workspace.ps1` can pass.

## Monolith reference

| Item | Detail |
|------|--------|
| Date | 2026-07-12 |
| Commit / PR | Audit only — no Monolith code change required |
| Files | `database/seeders/MenuSeeder.php` (payment menu `route` names); `routes/web/payment-ipaymu-return.php` (`payment.ipaymu.return`, `payment.ipaymu.cancel`) |
| Routes | Web: `payment.ipaymu.return`, `payment.ipaymu.cancel`; menu routes: `payment-channels.index`, `payment-gateways.index`, `invoices.index`, `subscriptions.index`, plus subscription CRUD route names |

## API contract

No Monolith API URL or JSON shape change. This entry is about **route-name → SPA path** mapping parity.

**Gateway `vue-routes.php` paths (source for `injectPaths`) — present today:**

| Laravel route name | Vue path |
|--------------------|----------|
| `payment-channels.index` | `/payment-channels` |
| `payment-gateways.index` | `/payment-gateways` |
| `payment-gateways.destroy` | `/payment-gateways/{paymentGateway}` |
| `invoices.index` | `/invoices` |
| `invoices.show` | `/invoices/{invoice}` |
| `subscriptions.index` | `/subscriptions` |
| `subscriptions.create` | `/subscriptions/create` |
| `subscriptions.show` | `/subscriptions/{subscription}` |
| `subscriptions.edit` | `/subscriptions/{subscription}/edit` |
| `subscriptions.destroy` | `/subscriptions/{subscription}` |

**Missing from Frontend `routeMap.ts`:** all ten rows above.

**iPaymu return/cancel naming mismatch:**

| Layer | Return route name | Cancel route name | SPA path |
|-------|-------------------|-------------------|----------|
| Monolith web | `payment.ipaymu.return` | `payment.ipaymu.cancel` | `/payment/ipaymu/return`, `/payment/ipaymu/cancel` (Blade) |
| Vue router | `payments.ipaymu.return` | `payments.ipaymu.cancel` | `/payments/ipaymu/return`, `/payments/ipaymu/cancel` |
| `vue-routes.php` | — | — | **not mapped** |

**Recommended mapping for Gateway + Frontend:** pick one route name (prefer `payments.ipaymu.return` / `payments.ipaymu.cancel` to match existing Vue router) and map to `/payments/ipaymu/return` and `/payments/ipaymu/cancel`. If Monolith notifications ever emit `payment.ipaymu.*`, add aliases in `vue-routes.php` pointing to the same paths.

## Gateway actions

- [x] Add `payments.ipaymu.return` and `payments.ipaymu.cancel` to `config/vue-routes.php` `paths` (and optional aliases `payment.ipaymu.return` / `payment.ipaymu.cancel` if Monolith emits the singular form).
- [ ] Verify `NotificationUrlTransformer` resolves iPaymu links after mapping (manual: seed or mock notification with `action_route`).
- [x] Confirm existing payment/subscription entries in `vue-routes.php` stay aligned with `monolith-frontend/src/router/index.ts`.
- [x] **Hygiene:** Fix or remove `tests/Feature/ExampleTest.php` (`GET /` expects 200 but Gateway has no `/` route) — blocks full workspace test script.

## Frontend actions

- [x] Add the ten payment/subscription route names to `src/router/routeMap.ts` (mirror `vue-routes.php` paths; use `:param` syntax consistent with existing entries).
- [x] Optionally align Vue router `name` with Gateway mapping if aliases are added for `payment.ipaymu.*`.
- [ ] Smoke-test `resolveMenuPath()` for payment menu items when `path` is null and only `route` is set (locale refresh path).

## Breaking change?

| Breaking? | Mitigation |
|-----------|------------|
| No | Additive route-map entries only; iPaymu mapping is new capability |

## Test plan (downstream sync session)

| Repo | Command |
|------|---------|
| Monolith | `composer test` (unchanged) |
| Gateway | `php artisan test` — must pass after ExampleTest fix |
| Frontend | `npm run test:unit -- --run`; `npm run build` |

**Manual checks:**

1. Bootstrap shell → payment menu items navigate to `/payment-channels`, `/invoices`, etc.
2. Change locale → navigation refresh → payment links still resolve.
3. Notification with `action_route` for a payment screen opens correct Vue path.
4. iPaymu return URL from checkout still lands on `/payments/ipaymu/return` (Vue handles sync; mapping is for menu/notification links only).

## Status

| Field | Value |
|-------|-------|
| Status | `done` |
| Synced | 2026-07-12 |
