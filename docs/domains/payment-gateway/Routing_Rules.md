# Routing Config Definition for AI Agent

## Runtime implementation (Monolith)

The YAML in [Routing_Price.yaml](Routing_Price.yaml) is the **source of truth**. Code loads it via `App\Domain\PaymentGateway\Support\Routing\RoutingPolicyLoader`, validates it with `RoutingPolicyValidator`, then writes it to the DB via `PaymentRouteMaterializer` (`php artisan payments:sync-routing-policy`). Checkout resolver: `PaymentChannelResolver` + `PaymentChannelFeeCalculator`.

## How this config is defined

This routing config is a **declarative contract** between your payment system and the AI Agent (or router service). The YAML defines the policy (channel, PG, settlement, fee); **runtime execution** in Monolith goes through materializer → DB → `PaymentChannelResolver` (see the "Runtime implementation" section above). An agent or operator edits the YAML, then `php artisan payments:sync-routing-policy` rewrites the `payment_channels` / `payment_channel_routes` rows. The checkout resolver never reads the YAML directly — it reads the synced DB, ordered by `route_role` then `priority`.

The Agent interprets it through a fixed flow. First, the Agent maps the payment intent (e.g. "pay with BCA VA" or "pay with GoPay") to the internal **channel enum**. Second, it looks at `type`: if `single`, only one `pg` is valid and there is no routing choice; if `dual`, the Agent must try `primary` first, then `fallback` if primary fails, is down, or the bank/channel is unavailable at that PG. Third, the `settlement` field binds a **payout-time tier** already chosen because fees are lower on longer settlements — the Agent must not upgrade to H+0 just for faster payout, unless the request carries an explicit override. Fourth, the `fee` field is the cost model that must be used when estimating cost, ranking PGs, or showing a breakdown to the merchant/user. Additional fields such as `single_overrides`, `avoid`, and `cheap_alt` are **special rules**: overrides force a specific PG for a sub-case (e.g. SeaBank only via Midtrans), `avoid` is a hard ban (e.g. iPaymu QRIS H+0), and `cheap_alt` is an optional cheaper path when UX allows it (e.g. GoPay direct 2% may be replaced by QRIS 0.7% when QR scanning is acceptable).

To keep the Agent consistent, the config must be treated as an **immutable policy per environment** (staging/production), versioned, and equipped with a validation schema (`fee` type string vs number, `pg` enum, `settlement` enum). The Agent does not "guess" fees outside the config; it only executes this policy, then calls the Midtrans or iPaymu API for the chosen `pg`. If a field is missing (e.g. channel not registered), the Agent must fail clearly or ask for clarification — never invent a route.

***

## Meaning of the `fee` field

The `fee` field answers one question: **how much the PG deducts from a successful transaction**, in which model. There are two main forms in this config, and they are **never mixed in a single value**.

### 1. Flat fee (absolute number)

Example: `fee: 3500` or `fee: 4000`.

Means a **fixed charge per successful transaction**, independent of the payment amount. The unit is **Rupiah (IDR)**.

- Transaction Rp 50,000 → fee Rp 3,500
- Transaction Rp 500,000 → fee still Rp 3,500
- Transaction Rp 5,000,000 → fee still Rp 3,500

Used for channels such as **Virtual Account**, where the PG/bank charges per VA payment, not an MDR percentage. In the config, `3500` = iPaymu VA on long settlement (H+4); `4000` = Midtrans VA.

**How the Agent computes:**

```text
service_fee = fee                      # already in IDR
merchant_net ≈ payment_total - service_fee (- VAT where applicable)
```

### 2. Percentage fee (MDR)

Example: `fee: "2%"`, `fee: "1.5%"`, `fee: "0.7%"`.

Means a charge **proportional to the successful transaction total**. A string ending in `%` must be parsed as a decimal (`2%` → `0.02`) then multiplied by the amount.

- `2%` on Rp 100,000 → fee Rp 2,000
- `2%` on Rp 500,000 → fee Rp 10,000
- `0.7%` on Rp 100,000 → fee Rp 700
- `1.5%` on Rp 200,000 → fee Rp 3,000

Used for **QRIS** and **direct e-wallets** (GoPay, OVO, DANA, ShopeePay), following the MDR scheme.

**How the Agent computes:**

```text
rate = parse_percent(fee)              # "2%" → 0.02
service_fee = payment_total × rate
merchant_net ≈ payment_total - service_fee (- VAT where applicable)
```

### 3. What is absent from this config (but the Agent should know)

Some PGs use a **hybrid** `percentage + flat` model (classic credit-card example: `2.9% + Rp 2,000`). Those channels have been curated out of this config, so the Agent does **not** need a hybrid parser for the current routing file. If added later, the convention should be explicit, e.g.:

```yaml
fee:
  percent: 2.9
  flat: 2000
```

not an ambiguous string like `"2.9%+2000"` without a schema.

### 4. Interpretation rules for the Agent (summary)

| YAML form | Type | Meaning | Formula |
|---|---|---|---|
| `3500` / `4000` (number) | **Flat** | Fixed IDR deduction per successful trx | `fee` |
| `"0.7%"` / `"1.5%"` / `"2%"` (string `%`) | **Percentage** | Deduction from the **payment total** | `total × rate` |
| (not used in this config) | Hybrid | % of total **plus** flat | `(total × rate) + flat` |

Important conventions so the Agent does not misread:

- **Number without `%`** → always flat IDR.
- **String with `%`** → always a fraction of the total.
- Fees are charged only on **successful transactions** (not pending/expired/cancelled), matching the Midtrans/iPaymu model.
- `fee` in the config is the **channel cost charged to the buyer** at checkout. The value is materialized into `payment_channel_routes.fee_*`, estimated in the UI/API (`?subtotal=`), and recorded on the invoice as `payment_channel_fee`. Midtrans uses the policy estimate at charge time; iPaymu uses the actual provider figure after the charge. When a checkout channel succeeds, the applicable policy is **copied immutably** into `payment_channel_route_snapshots` (the `payment_channel_routes` rows stay mutable after a YAML sync) — see [Route policy snapshot](README.md#route-policy-snapshot) in the payment-gateway README.
- **VAT** (e.g. 11% on the Midtrans fee for many channels) is **not** folded into the `fee` figure unless the channel is already VAT-inclusive in its list price (e.g. Midtrans QRIS 0.7%). An Agent needing net cost must have a separate `vat_inclusive: true/false` flag or a tax-calculation layer on top of this config.
- Settlement (`H+2`, `H+4`) is **not a fee**, but determines which **price tier** is valid; changing settlement can change `fee` (e.g. iPaymu QRIS H+0 is far more expensive than H+2). That is why `settlement` and `fee` are always paired under `primary` / `fallback`.

### 5. End-to-end Agent reading examples

**Case A — BCA VA Rp 250,000**

1. Channel = `va` → `type: dual`
2. Dual bank → `primary.pg = ipaymu`, `settlement = H+4`, `fee = 3500`
3. Flat fee → cost = **Rp 3,500** (not 1.4% of 250k)
4. If iPaymu fails → `fallback` Midtrans, flat fee **Rp 4,000**

**Case B — GoPay direct Rp 250,000**

1. Channel = `gopay` → `type: single`, `pg = midtrans`
2. `fee = "2%"` → cost = 250,000 × 0.02 = **Rp 5,000**
3. If user/policy allows QR UX → `cheap_alt: qris` with `fee = "0.7%"` → cost = **Rp 1,750**

**Case C — QRIS Rp 250,000**

1. Channel = `qris` → dual, primary Midtrans H+2, `fee = "0.7%"`
2. Cost = **Rp 1,750**
3. Never pick a path listed in `avoid` (iPaymu H+0), even though settlement is faster.

***

With this separation, the AI Agent has three guarantees: **(1)** it knows which channels are dual vs single, **(2)** it knows which PG + settlement pairs are allowed so the fee stays low, **(3)** it knows whether a fee figure is flat Rupiah or a percentage of the total — with no ambiguity when computing cost or dispatching a payment.
