# Microstock — product domain

Product domain (`kind => product`, `locked => true`) for microstock asset management on top of the File Hosting base storage layer. Assets live in built-in **system workspaces** (`file_hosting_workspaces.kind = 'microstock'`, standalone slots) and reuse the entire FH upload/browse/quota/download pipeline. Microstock adds AI metadata (title/keywords), CSV metadata export, and envelope-encrypted marketplace credentials.

| | |
|--|--|
| Code | `app/Domain/Microstock/` |
| Config | [`config/microstock.php`](../../../config/microstock.php) |
| API | `routes/api/v1/microstock.php` |
| Web | `routes/web/microstock.php` |
| Seeder | `MicrostockRoleSeeder` (Beta tester role + Public visibility) |
| Product gate | `product.active:Microstock` middleware |
| Menu | **No dynamic sidebar menu** (removed) — reached via `/services` and the File Hosting workspace switcher |
| Storage class | **Product storage** — consumes the free-tier base (see [File Hosting — Storage classification](../file-hosting/README.md#storage-classification-core-vs-product)) |
| Quota pool | Shared with File Hosting (one `UserQuota` byte pool) |
| Fulfillment target | **`microstock`** (standalone — NOT `file_hosting`; see activation below) |

## Activation model (via `/services`)

Microstock is **opt-in**, activated by purchasing a service catalog SKU from `/services` — it is never auto-provisioned. Two SKUs (seeded by `ServiceCatalogSeeder`, codes in `App\Domain\Service\Support\MicrostockCatalog`):

| code | name | interval | plan_kind | entitlement | storage_bytes | price |
|-----|------|----------|-----------|-------------|---------------|-------|
| `microstock.plan.free` | Microstock Workspace (Free) | `once` | `main` | `microstock_workspace_slots` | 0 | $0 |
| `microstock.addon.workspace-slot.monthly` | Microstock Workspace Slot | `monthly` | `addon` | `microstock_workspace_slots` | 0 | $1 |

- **Free registration** (`microstock.plan.free`, $0 one-time): the activation gate. Purchasable **once** (`ServiceCheckoutService` throws 422 `api/services.already_activated` on rebuy; unique `(user_id, service_id)` entitlement key is the backstop). Being $0 it is **zero-charge**: `isZeroCharge()` skips the payment gateway, the purchase is immediately `Paid` (`fx_provider = zero_charge`), and fulfillment runs inline. Grants `quantity = 1`, `ends_at = null` (permanent). With `fulfillment_target = 'microstock'`, `ProductRegistry::productKeyByFulfillmentTarget` maps it to the `Microstock` product and `ServiceFulfillmentService::ensureProductActive` activates it **implicitly** (no quota recompute — that only happens for `file_hosting` targets).
- **Slot addon** (`microstock.addon.workspace-slot.monthly`, $1 monthly): repeatable and **compounding** — each paid unit increments `quantity` on the active entitlement via `activateAddon`. Buying 10 units grants 10 extra Microstock workspace slots. Shares the addon anniversary stack with FH addons (`MainPlanChangeCalculator` / `extendAllAddons` cover both `file_hosting` and `microstock` addon targets).
- **UX**: `/services` shows a dedicated Microstock card with an **Activate free** button (disabled + "Active" badge once active) and a **Buy slot** button (visible once active) posting to the normal `services.checkout` flow.

## System workspaces (base storage)

Microstock assets live in `file_hosting_workspaces.kind = 'microstock'` rows — real workspaces, so all FH services (browse, upload, folders, members, activity, realtime) work unchanged. Unlike team workspaces, Microstock slots are **standalone**: they do NOT overlap or interchange with the FH team workspace slots (`file-hosting.addon.workspace-slot.monthly`). Owners may have **multiple** Microstock workspaces when their slot limit allows.

| Rule | Behavior |
|----|----------|
| Slot limit | `WorkspaceAccessService::microstockSlotLimit($ownerId)` = `BASE_WORKSPACE_SLOTS (1)` when `ServiceEntitlementService::hasActiveMicrostockWorkspaceSlot` is true, `+ microstockWorkspaceSlotExtraQuantity` (sum of active/grace slot-addon quantities). **0** when never activated |
| Provisioning gate | `WorkspaceService::systemWorkspaceFor` and `createSystemWorkspace` call `assertCanProvisionMicrostockWorkspace` before creating (`kind->isSystem()` only; team path unchanged). `422 api/microstock.workspace_not_activated` when limit is 0, `422 api/microstock.slot_limit` when full. Resolving an **existing** workspace is never gated |
| Activation gate | `MicrostockService::workspaceFor`/`createWorkspace` additionally throw `MicrostockException` 403 `workspace_not_activated` without an active slot entitlement |
| Slot accounting | `countActiveOwned` counts only `kind = 'team'`; `countActiveOwnedMicrostock` counts `kind = 'microstock'`. Neither pool sees the other, so Microstock never raises `owned_workspace_used` |
| Multi-workspace | Spare slots allow extra workspaces via `POST /api/v1/microstock/workspaces` or the switcher |
| Free users | The Microstock slot is independent of the FH paid/free plan; inviting still requires a paid FH main plan |

### No auto-provision on File Hosting

`FileManagerController@index` does **not** create the Microstock workspace. It appears in the FH switcher only after activation (and provisioning) via `/services`. `GET /microstock` (`microstock.index`) redirects to `/services` with an error flash when not yet activated.

## Usage & limits

| Rule | Behavior |
|----|----------|
| Access | Requires an active Microstock slot entitlement (free registration or slot addon) |
| Public share | **Blocked** — files/folders in a system workspace can never be `anyone_with_link`. `VisibilityService::assertSystemWorkspaceShareAllowed` rejects enable (422 `public_share_not_allowed`); guest `fileAccessible`/`folderAccessible` return false |
| Invite | Only a **paid FH** owner may invite (`WorkspaceService::invite` → `assertOwnerEligibleForCollab`); invitees may be free or paid |
| Upload/browse/download | Reuse File Hosting services as-is |

## Metadata (AI: vision → thinking → manual)

Microstock's value is metadata. After uploading an asset the user triggers **Analyze**:

1. `POST assets/{file}/analyze` → `MicrostockService::requestAnalysis` creates/resets a `microstock_asset_metadata` row (`status = pending`) and dispatches `AnalyzeAssetMetadata` (queued, `tries = 3`, `backoff = 30`).
2. The job mints an inline signed URL (`SignedDownloadService::resolve($file, inline: true)`) and calls `AiMetadataService::generate($url)`.
3. **Vision** stage drafts title + keywords from the image; **thinking** stage curates them (dedupe repetitive keywords, relevance, SEO). Prompts are hardcoded in `App\Domain\Microstock\Support\Prompts` (English, SEO-focused).
4. Result stored with `status = generated`.
5. Final curation is **manual**: user edits title/keywords → `PATCH assets/{file}/metadata` → `status = finalized`.

`AiMetadataService` is an interface; the default binding `HttpAiMetadataService` posts to OpenAI-compatible chat-completions endpoints. Each stage is independently configurable in `config/microstock.php` (`ai.vision.*`, `ai.thinking.*`: endpoint/model/api_key/timeout, env-driven). Tests bind a stub.

| Column | Notes |
|----|-------|
| `microstock_asset_metadata` | `file_id` (unique FK to `file_hosting_files`), `user_id`, `title`, `keywords` json, `status` (`pending`/`generated`/`finalized`), `ai_raw` json |

## CSV export

`GET /microstock/export/csv` streams a CSV of the owner's **finalized** metadata within the Microstock workspace. Columns are config-driven (`microstock.csv.columns`, default `filename,title,keywords`; `microstock.csv.keywords_separator`). Per-marketplace field mapping is a later iteration.

## Marketplace credentials

`microstock_marketplace_credentials` stores per-user marketplace connection secrets (host/username/password/port/…) for the future FTP/API auto-upload. `secret_payload` uses the shared **envelope encryption** cast (`EnvelopeEncryptedArray`, resource type `microstock_marketplace_credentials`) — the same KEK/DEK infra as payment/vTual credentials; plaintext never reaches the DB. MVP only stores/lists/deletes credentials; no auto-upload yet.

## API (`/api/v1/microstock/*`, `auth:api`)

Middleware: `auth:api` → `domain.access:Microstock` → `product.active:Microstock`.

| Method | Path | Purpose |
|------|---------|---------|
| GET | `workspace` | Resolve (lazy-create, gated) the owner's system workspace |
| GET | `slots` | Slot status snapshot (`activated`, `used`, `limit`, `can_create`) |
| POST | `workspaces` | Create an additional Microstock workspace (slot-gated) |
| POST | `assets/{file}/analyze` | Queue AI metadata analysis (202) |
| GET | `assets/{file}/metadata` | Read current metadata row |
| PATCH | `assets/{file}/metadata` | Finalize manual curation (`title`, `keywords[]`) |
| GET | `credentials` | List marketplace credentials |
| POST | `credentials` | Store credential (`marketplace`, `label`, `secret{}`) |
| DELETE | `credentials/{id}` | Delete credential |

## Web

| Route name | Purpose |
|----------|---------|
| `microstock.index` | Redirects into the File Hosting SPA pinned to the Microstock system workspace (`/file-hosting?workspace_id=…`); redirects to `/services` when not activated |
| `microstock.export.csv` | Stream-download the finalized metadata CSV |
| `file-hosting.microstock.slots` | Thin web hop → `GET /api/v1/microstock/slots` (for the switcher UI) |
| `file-hosting.microstock.workspaces.store` | Thin web hop → `POST /api/v1/microstock/workspaces` (switcher "New Microstock workspace") |

### Switcher multi-workspace UI

The File Hosting workspace switcher fetches `file-hosting.microstock.slots` at runtime and renders one entry:

- not activated → "Activate Microstock" link to `/services`
- `can_create` → "New Microstock workspace" button → POSTs, then soft-navigates into the new workspace
- full → disabled "Microstock slots full" note

After a successful create (and after every switcher refetch) the SPA rebuilds the whole workspace item list from `lastWorkspaces` (`renderWorkspaceMenuItems`) and re-runs `refreshMicrostockSlotSection`, so the new workspace appears and the button state updates immediately — no hard reload.

## Tests

`composer test -- --filter=MicrostockTest` — provisioning gated by entitlement (403 without), slot-limit multi-workspace create (free=1, +addon compounding), public-share block, analyze → generate (stub AI), finalize curation, CSV export finalized-only, credential encrypt/decrypt roundtrip, invite paid-gate. Checkout coverage lives in `ServiceCheckoutZeroChargeTest` (free $0 auto-fulfill + activate, rebuy 422, addon quantity compounding).

## Explicitly out of scope (MVP)

- FTP/API auto-upload to marketplaces (CSV-only for now; phpseclib next iteration).
- Concrete AI provider lock-in — interface + HTTP client is enough; tests stub it.
- Per-marketplace metadata field mapping (generic CSV).

## Related

- [file-hosting/README.md](../file-hosting/README.md) — base storage layer, system workspaces, storage classification
- [payment-gateway/encryption.md](../payment-gateway/encryption.md) — envelope encryption infra
- [service/README.md](../service/README.md) — entitlements / quota fulfillment
