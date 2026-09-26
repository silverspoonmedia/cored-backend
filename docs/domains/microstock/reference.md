# Microstock — product domain

> **UI:** Microstock admin surfaces live in **Monolith-Vue-Workers** (Archivd workspace switcher + settings). Blade references below are historical — see [web/blade-admin.md](../../web/blade-admin.md).

> **Deep dives:** [reference-pipeline.md](reference-pipeline.md) - collections, AI metadata pipeline, prompts, variants · [reference-api.md](reference-api.md) - API, SPA surfaces, CSV export, credentials, ops.

## Agent entry

Open the rest of this README only when changing Microstock **behavior**. For a one-file fix, read the file under edit plus its test.

| Need | Path |
|------|------|
| Workspaces | Archivd `kind = 'microstock'` system workspaces; `POST /api/v1/microstock/workspaces` |
| Billing | `BillingLedgerService`, prepaid AI credits — not Archivd team slots |
| Do not | `Read` this whole guide or all locale JSON files. Do not invent client-side credit math — API `assertCanAnalyze` is the gate. |

Batch hydrate pointer: [vue-workers-spa.md — Invocation budget](../../integration/reference-vue-workers-spa.md#invocation-budget-controls).

Product domain (`kind => product`, `locked => true`) for microstock asset management on top of the Archivd base storage layer. Assets live in built-in **system workspaces** (`ARCHIVD_workspaces.kind = 'microstock'`, standalone slots) and reuse the entire Archivd upload/browse/quota/download pipeline. Microstock adds AI metadata (title/description/category/keywords), CSV metadata export, and envelope-encrypted marketplace credentials.

| | |
|--|--|
| Code | `app/Domain/Microstock/` |
| Config | [`config/microstock.php`](../../../config/microstock.php) |
| API | `routes/api/v1/microstock.php` |
| SPA | Vue Router names `archivd.microstock-credentials` / `archivd.microstock-usage` in [`config/spa-routes.php`](../../../config/spa-routes.php), plus the Archivd workspace switcher. No server `web` route and no standalone `/apps/microstock` screen |
| Seeder | `MicrostockRoleSeeder` (Beta tester role + Public visibility) |
| Product registry | `activation_type = implicit`; registration creates an active registry row |
| Workspace gate | Active `microstock_workspace_slots` entitlement; `product.active:Microstock` remains middleware defense |
| Menu | **No dynamic sidebar menu** (`menu_routes = []`) — reached via `/apps/services` and the Archivd workspace switcher |
| Storage class | **Product storage** — consumes the free-tier base (see [Archivd — Storage classification](../archivd/reference.md#storage-classification-core-vs-product)) |
| Quota pool | Shared with Archivd (one `UserQuota` byte pool) |
| Fulfillment target | **`microstock`** (standalone — NOT `archivd`; see activation below) |

## Registry activation vs workspace entitlement

Microstock's Product Activation registry entry is **implicit**: registration creates an active `product_activations` row, matching `config/products.php`. That row alone does not provision or unlock a workspace. Usable Microstock access remains **opt-in** through a `microstock_workspace_slots` entitlement purchased from `/apps/services`; workspace creation remains on demand. `menu_routes = []`, so registry activation never creates or reveals a dedicated dynamic-sidebar node.

Two SKUs are seeded by `ServiceCatalogSeeder` (codes in `App\Domain\Service\Support\MicrostockCatalog`):

| code | name | interval | plan_kind | entitlement | storage_bytes | price |
|-----|------|----------|-----------|-------------|---------------|-------|
| `microstock.plan.free` | Microstock Workspace (Free) | `once` | `main` | `microstock_workspace_slots` | 0 | $0 |
| `microstock.addon.workspace-slot.monthly` | Microstock Workspace Slot | `monthly` | `addon` | `microstock_workspace_slots` | 0 | $1 |

- **Free workspace entitlement** (`microstock.plan.free`, $0 one-time): the workspace-access gate. Purchasable **once** (`ServiceCheckoutService` throws 422 `api/services.already_activated` on rebuy; unique `(user_id, service_id)` entitlement key is the backstop). Being $0 it is **zero-charge**: `isZeroCharge()` skips the payment gateway, the purchase is immediately `Paid` (`fx_provider = zero_charge`), and fulfillment runs inline. Grants `quantity = 1`, `ends_at = null` (permanent). With `fulfillment_target = 'microstock'`, fulfillment also ensures the already-implicit registry activation remains active; it does not auto-create a workspace and does not recompute Archivd quota.
- **Slot addon** (`microstock.addon.workspace-slot.monthly`, $1 monthly): repeatable and **compounding** — each paid unit increments `quantity` on the active entitlement via `activateAddon`. Buying 10 units grants 10 extra Microstock workspace slots. Shares the addon anniversary stack with Archivd addons (`MainPlanChangeCalculator` / `extendAllAddons` cover both `archivd` and `microstock` addon targets).
- **AI credits** (`microstock.addon.ai-credits.5`, $5 once): prepaid analysis balance (`entitlement = microstock_ai_credits`). Fulfillment calls `AiCreditService::creditFromPurchase` — debited only on successful AI analysis (see [Microstock — AI billing](reference.md#ai-billing-prepaid-credits)).
- **UX**: `/apps/services` shows a dedicated Microstock card with an **Activate free** button for the workspace entitlement (disabled + "Active" badge once entitled) and a **Buy slot** button (visible once entitled) posting to the normal `services.checkout` flow. Here “Active” describes workspace entitlement, not creation of the implicit registry row.

## System workspaces (base storage)

Microstock assets live in `ARCHIVD_workspaces.kind = 'microstock'` rows — real workspaces, so all Archivd services (browse, upload, folders, members, activity, realtime) work unchanged. Unlike team workspaces, Microstock slots are **standalone**: they do NOT overlap or interchange with the Archivd team workspace slots (`archivd.addon.workspace-slot.monthly`). Owners may have **multiple** Microstock workspaces when their slot limit allows.

| Rule | Behavior |
|----|----------|
| Slot limit | `WorkspaceAccessService::microstockSlotLimit($ownerId)` = `BASE_WORKSPACE_SLOTS (1)` when `ServiceEntitlementService::hasActiveMicrostockWorkspaceSlot` is true, `+ microstockWorkspaceSlotExtraQuantity` (sum of active/grace slot-addon quantities). **0** when never activated |
| Provisioning gate | `WorkspaceService::systemWorkspaceFor` and `createSystemWorkspace` call `assertCanProvisionMicrostockWorkspace` before creating (`kind->isSystem()` only; team path unchanged). `422 api/microstock.workspace_not_activated` when limit is 0, `422 api/microstock.slot_limit` when full. Resolving an **existing** workspace is never gated |
| Activation gate | `MicrostockService::workspaceFor`/`createWorkspace` additionally throw `MicrostockException` 403 `workspace_not_activated` without an active slot entitlement |
| Slot accounting | `countActiveOwned` counts only `kind = 'team'`; `countActiveOwnedMicrostock` counts `kind = 'microstock'`. Neither pool sees the other, so Microstock never raises `owned_workspace_used` |
| Multi-workspace | Spare slots allow extra workspaces via `POST /api/v1/microstock/workspaces` or the switcher |
| Free users | The Microstock slot is independent of the Archivd paid/free plan; inviting still requires a paid Archivd main plan |

### No auto-provision on Archivd

The Microstock system workspace is **not** auto-provisioned on Archivd first paint. It appears in the Archivd switcher only after workspace entitlement and on-demand provisioning via `/apps/services` or the switcher/API (`POST /api/v1/microstock/workspaces`). The SPA services card and Archivd workspace switcher gate entry on `WorkspaceAccessService::assertCanProvisionMicrostockWorkspace` (slot limit from Microstock entitlements). Until entitlement and provisioning, the workspace simply does not appear in the switcher.

## Usage & limits

| Rule | Behavior |
|----|----------|
| Access | Requires an active Microstock slot entitlement (free registration or slot addon) |
| Public share | **Blocked** — files/folders in a system workspace can never be `anyone_with_link`. `VisibilityService::assertSystemWorkspaceShareAllowed` rejects enable (422 `public_share_not_allowed`); guest `fileAccessible`/`folderAccessible` return false |
| Invite | Only a **paid Ar** owner may invite (`WorkspaceService::invite` → `assertOwnerEligibleForCollab`); invitees may be free or paid |
| Upload/browse/download | Reuse Archivd services as-is. **Content collaboration is NOT paid-gated:** a free Microstock owner can upload into their collections, and an invited member (free or paid) can upload into a shared workspace. Upload only requires mutate access (`assertCanMutate` — owner or active member). The paid gate (`assertOwnerEligibleForCollab`) applies to **sharing** (invite/accept) and **creating workspaces**, never to putting content into one |

## AI billing (prepaid credits)

Upload/storage stays on the shared Archivd quota. **AI analysis** (vision + thinking metadata) is prepaid and metered per HTTP call.

| Phase | Billable? | Mechanism |
|-------|-----------|-----------|
| Upload into collection | No | Archivd `UserQuota` bytes |
| `POST …/analyze` / `POST assets/{file}/analyze` | Yes | Prepaid `microstock_ai_balances` |

**Flow:** purchase an AI credit pack via `/apps/services` → `ServiceFulfillmentService` credits `balance_micro_usd` → `AiBillingService::planAnalyze` gates dispatch (daily-free slots first, then prepaid balance for remainder; checks **billing user** = actor who triggered analyze) → each OpenAI-compatible HTTP call records a `microstock_ai_usage_events` row scoped to `analysis_run_id` → `composeResultFromThinking` settles (debit + `ai_billing` snapshot) **before** promoting `status = generated`. Thinking failure after successful vision triggers **partial vision settle**. Full edge-case inventory: [billing-edge-cases.md](billing-edge-cases.md).

### Daily free trial (optional)

Settings-gated per-user daily analyses (`microstock.daily_free.enabled` in **Settings → Microstock**). No activation SKU — slots reset lazily by calendar day in `microstock_daily_free_usage`. Daily-first mixed billing: first N files in a collection use `billing_source = daily_free` (no prepaid debit; provider cost still on usage events); remainder use prepaid. Free-tier jobs route to `*-free` queues and may use separate vision/thinking models through Settings → Microstock (`microstock.ai.vision.free_model` / `microstock.ai.thinking.free_model`); an empty free override inherits the paid/default stage model. Workers should process paid queues first. Details: [daily-free-trial.md](daily-free-trial.md).

**Billing subject:** `microstock_asset_metadata.billing_user_id` is set to the user who called `POST …/analyze` or `POST assets/{file}/analyze` (not the file uploader). Gate, usage events, and debit all use this ID.

**Analysis run:** Each analyze/re-analyze assigns a UUID `analysis_run_id` on metadata and usage events so re-runs do not mix unbilled rows from prior attempts.

**Credit pack SKU** (seeded by `ServiceCatalogSeeder`):

| code | price | entitlement |
|------|-------|-------------|
| `microstock.addon.ai-credits.5` | $5 once | `microstock_ai_credits` |

**Rate card** — core Inference domain ([Inference README](../inference/README.md)); Microstock applies `markup_multiplier` on top.

| Model | Input / 1M tokens | Output / 1M tokens |
|-------|-------------------|---------------------|
| `grok-grok-4.5-high` | $3.00 | $15.00 |
| `gpt-5.4` | $2.50 | $15.00 |
| `kimi-k3` | $3.00 | $15.00 |
| `gpt-5.6` / `gpt-5.6-sol` | $5.00 | $30.00 |
| `gpt-5.6-terra` | $2.50 | $15.00 |
| `gpt-5.6-luna` | $1.00 | $6.00 |
| `claude-opus-5` / `anthropic/claude-opus-5` | $5.00 | $25.00 |
| `claude-sonnet-5` / `anthropic/claude-sonnet-5` | $2.00 | $10.00 |
| `default` (unknown slug, provisional) | $15.00 | $60.00 |

Provisional holds are reconciled after catalog curation; see [Inference — unknown models](../inference/README.md).

Sonnet 5 rates above are Anthropic **introductory** pricing through Aug 31, 2026; standard from Sep 1 is $3 / $15 per 1M.

- `markup_multiplier` — `microstock.ai_billing.markup_multiplier` (default **1.35**; Settings → Microstock)
- `estimated_billable_micro_usd_per_asset` — `microstock.ai_billing.estimated_billable_micro_usd_per_asset` (default **150000** = $0.15 buffer for analyze gate)
- `estimate_thinking_attempt_factor` — `microstock.ai_billing.estimate_thinking_attempt_factor` (default **1.5**; gate = per-asset estimate × factor)
- `http_retries` — `microstock.ai.http_retries` (default **0** for billed stages; each retry can duplicate provider charges)
- Per-HTTP-call rates resolved from provider model rates (`microstock.ai.inference_provider_slug` in Settings → Microstock / `platform.inference.default_provider_slug` in Settings → Platform); immutable provider-specific snapshot on `microstock_ai_usage_events.ai_model_rate_snapshot_id` plus `inference_provider_id` when route known (alias in `alias_model`, resolved slug in `resolved_model`)

Amounts are stored as **micro-USD** (1 USD = 1_000_000) end-to-end. `microstock_ai_balances.balance_micro_usd` is **signed** — post-settlement consolidation debits may drive balance negative; new paid analyze is blocked until balance ≥ 0 (top-up via Services). Collection UI and `GET billing` show negative balances (e.g. `-$0.12`).

### Provisional model consolidation

When ops curates an unknown model on a provider ([Inference — unknown model workflow](../inference/reference.md#unknown-model-workflow)), `UnknownModelBillingConsolidationService` (triggered from `InferenceCatalogService::syncProviderRates()`):

- Scope: settled prepaid usage events whose snapshot `rate_source = unknown_provisional` for that provider + model slug, `consolidated_at` null.
- Recalculates `billable_micro_usd` / `provider_cost_micro_usd` at curated rates; posts ledger `adjustment` credit (refund) or debit (extra charge; balance may go negative).
- Daily-free rows: usage amounts updated for reporting only — no ledger movement.
- Idempotency: `consolidated_at` prevents double-run.

Services: `AiBillingService`, `AiCreditService`, `UnknownModelBillingConsolidationService`, `BillingLedgerService`.

### Usage & balance UI

Transparent prepaid balance and AI usage history for the billing subject (the user who triggered analyze).

`GET /api/v1/microstock/billing` returns `balance_micro_usd` (signed integer source), `balance_usd` (display string, `$49.91` or `-$0.12` when negative), `estimated_per_analysis_micro_usd`, `estimated_per_analysis_usd`, and `daily_free` snapshot from `AiDailyFreeService::snapshot()`: `{ enabled: bool, limit: int, used: int, remaining: int, resets_at: string|null }`. The SPA prefers the formatted string when present, else formats from micro-USD.

| Surface | Route / endpoint |
|---------|------------------|
| **Usage page** (SPA) | Vue Router `archivd.microstock-usage` → `/apps/archivd/microstock/usage` — summary cards + tabs |
| Summary | `GET /api/v1/microstock/billing` |
| Ledger mutations | `GET /api/v1/microstock/billing/ledger` |
| Usage detail | `GET /api/v1/microstock/billing/usage-events` |

**Page layout**

1. **Summary** — prepaid `balance_usd` (formatted string from API), `estimated_per_analysis_usd`, daily free (`daily_free: { enabled, limit, used, remaining, resets_at }` when enabled; `resets_at` is ISO 8601 or null).
2. **Tab: Balance mutations** — bank-style ledger from `microstock_ai_credit_transactions` (`purchase`, `analysis_debit`, `adjustment`); signed amounts, running `balance_after`.
3. **Tab: AI usage detail** — per HTTP call from `microstock_ai_usage_events` (stage, model, tokens, cost, `billing_source` badge: Free / Prepaid). Daily-free rows appear here only; they do not create ledger debits.
4. **Tab: Platform overview** (Root / Admin / Moderator only) — platform-wide aggregates: provider cost, billable, margin, events, active users, daily-free subsidized cost; breakdown by billing source, stage, and model; cross-user usage event list.

**Admin API**

| Method | Route |
|--------|-------|
| `GET` | `/api/v1/admin/microstock/billing/overview` |
| `GET` | `/api/v1/admin/microstock/billing/usage-events` |

Consumed directly by the SPA (`ArchivdMicrostockUsageView.vue`, `useMicrostockBilling.ts`). Gated by `hasAnyRole(['Root', 'Admin', 'Moderator'])` — Management excluded.

**Navigation**

| Location | Link |
|----------|------|
| Archivd toolbar (Microstock workspace) | **AI Balance** → navigates to `archivd.microstock-usage` or opens inline billing summary from `GET /api/v1/microstock/billing` |
| Collection describe modal footer | Billing line + **View balance & history** → usage route |
| `/apps/services` Microstock card | **Usage & balance** next to balance line |

Implementation: `BillingLedgerService`, `AdminBillingAnalyticsService`, `AdminBillingController`, `BillingLedgerEntryResource`, `BillingUsageEventResource`, `AdminBillingUsageEventResource`. Batch hydrate: `useMicrostockHydrate` — see [vue-workers-spa.md — Invocation budget controls](../../integration/reference-vue-workers-spa.md#invocation-budget-controls). Daily-free behaviour: [daily-free-trial.md](daily-free-trial.md).

