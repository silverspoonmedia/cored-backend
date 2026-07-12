# Downstream changelog entry

> **Origin:** cross-repo desync audit (2026-07-12). Not triggered by a single Monolith PR — backlog discovered retroactively.

## Summary

Monolith Blade invoice show combines `GET /api/v1/invoices/{id}` (including `checkout_session` on every response) with active `payment-channels` and `checkout_mode` from `ResolvesPaymentCheckoutContext`. The Vue `InvoiceShowView` passes only `:invoice` to `PaymentCheckoutPanel`, which skips `load()` when the invoice prop is present — so `channelOptions` stay empty, `checkoutMode` defaults to `snap_fallback`, and `checkout_session` is never hydrated. Channel checkout, resume-after-refresh, and sync-status polling on `/invoices/:id` do not match Blade parity. Order and subscription show pages are OK (they use `fetchCheckoutContext` via `order-id` / `subscription-id`).

## Monolith reference

| Item | Detail |
|------|--------|
| Date | 2026-07-12 |
| Commit / PR | Audit only — Monolith behavior is authoritative |
| Files | `app/Domain/PaymentGateway/Http/Resources/InvoiceResource.php`; `app/Http/Controllers/Web/Concerns/ResolvesPaymentCheckoutContext.php`; `app/Domain/PaymentGateway/Http/Controllers/Web/InvoiceController.php`; `resources/views/adminlte4/components/payments/checkout-panel.blade.php`; `resources/js/payments/checkout.js` |
| Routes | `GET /api/v1/invoices/{invoice}`; `GET /api/v1/payment-channels`; `POST /api/v1/invoices/{invoice}/channel-checkout`; `POST /api/v1/payments/providers/{provider}/invoices/{invoice}/sync-status` |

## API contract

**`GET /api/v1/invoices/{invoice}` — relevant fields (already returned by Monolith):**

```json
{
  "status": "success",
  "data": {
    "id": "uuid",
    "status": "pending",
    "checkout_session": {
      "channel_code": "qris_bca",
      "gateway": "ipaymu",
      "instructions": { },
      "expires_at": "2026-07-12T12:00:00+00:00"
    }
  }
}
```

`checkout_session` is `null` when no resumable channel session exists (see `InvoiceCheckoutInstructionResolver`).

**`GET /api/v1/payment-channels` — used by Blade `ResolvesPaymentCheckoutContext`:**

- Non-empty `data` array → `checkout_mode: "channel"`
- Empty → `checkout_mode: "snap_fallback"`

**Gateway aggregate today:** `GET /api/v1/payments/checkout-context?order_id=` or `?subscription_id=` only — **no `invoice_id`**. Frontend can fix without Gateway by combining invoice payload + separate `payment-channels` fetch; optional Gateway enhancement: add `invoice_id` query to reduce round trips.

**Blade parity behaviors to replicate in Vue:**

1. On load: if `checkout_session` present → render `PaymentInstructionsPanel` + start ~15s `sync-status` polling until paid/failed.
2. On load: fetch channels → set `checkout_mode` and populate `PaymentChannelPicker`.
3. After successful channel POST: reload invoice (Blade full page reload) or refetch invoice and re-hydrate session.

## Gateway actions

- [ ] **Optional (optimization):** Extend `PaymentCheckoutContextController` + `PaymentCheckoutContextAggregator` to accept `invoice_id` and return `{ invoice, payment_gateways, payment_channels, checkout_mode }` (invoice already includes `checkout_session` from Monolith). *Deferred — Frontend-only fix chosen for POC.*
- [ ] If aggregate added: document in `monolith-gateway/docs/aggregate-endpoints.md` and add PHPUnit feature test. *Deferred.*
- [x] If Frontend-only fix chosen: no Gateway code change required (catch-all already proxies invoice + payment-channels).

## Frontend actions

- [x] In `PaymentCheckoutPanel.vue`: when `invoice` prop is set (invoice-only mode), still initialize checkout context — fetch `GET /api/v1/payment-channels` and derive `checkout_mode` (mirror `ResolvesPaymentCheckoutContext`).
- [x] Hydrate `channelInstructions` from `invoice.checkout_session` on mount (map shape to `ChannelCheckoutResult` / `PaymentInstructionsPanel` props).
- [x] Start `sync-status` polling when active channel instructions are shown (mirror `checkout.js` `startPolling`).
- [x] After `channelCheckout` success on invoice page: refetch invoice (`@paid` / parent `execute`) and re-hydrate session.
- [x] Update `InvoiceShowView` if needed to pass `invoice-id` or trigger panel reload after payment.
- [x] Add unit tests for checkout_session hydration logic (mock invoice with non-null `checkout_session`).
- [ ] If Gateway adds `invoice_id` aggregate: switch invoice-only mode to `fetchCheckoutContext` variant. *Deferred.*

## Breaking change?

| Breaking? | Mitigation |
|-----------|------------|
| No | SPA catches up to existing Monolith API; no contract change |

## Test plan (downstream sync session)

| Repo | Command |
|------|---------|
| Monolith | `composer test` |
| Gateway | `php artisan test` (if aggregate extended) |
| Frontend | `npm run test:unit -- --run`; `npm run build` |

**Manual checks (local Laragon + Vite):**

1. Open `/invoices/{pending-invoice}` with active seeded payment channels → channel picker visible (`checkout_mode: channel`).
2. Start channel checkout → instructions render; polling updates status when invoice paid (webhook or manual sync).
3. Reload invoice page mid-checkout → instructions resume from `checkout_session` without new channel POST.
4. Compare behavior with Blade `invoices.show` for the same invoice.
5. Confirm `/orders/:id` and `/subscriptions/:id` checkout still work (regression).

## Status

| Field | Value |
|-------|-------|
| Status | `done` |
| Synced | 2026-07-12 |
