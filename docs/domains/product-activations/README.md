# Product Activations — kernel product registry

Cross-domain **runtime access** registry for Product domains. Tracks per-user product activation state (pending / active / suspended / expired) so Monolith can gate access, track usage, suspend accounts, and run analytics — independent of the paid entitlement layer in [Service](../service/README.md).

| | |
|--|--|
| Code | `app/Support/Product/` (kernel, not a `app/Domain/*` module) |
| Config | [`config/products.php`](../../../config/products.php) |
| Table | `product_activations` |
| API (self) | `routes/api/v1/product-activations.php` |
| API (admin) | `routes/api/v1/product-activations.php` (`/admin/*`) |
| Web (admin) | `routes/web/product-activations.php` |
| Middleware alias | `product.active` |
| Tests | `tests/Domain/Product/` (PHPUnit suite `Domain`) |

## Why a separate registry

Monolith already had two registries; neither tracks runtime access:

| Registry | Scope | Tracks per-user access? |
|----------|-------|--------------------------|
| `config/domains.php` + `DomainManager` | Module taxonomy + lifecycle (`kind`, `locked`) | No — static config, not user-scoped |
| Spatie `domain_kind` on roles/permissions | RBAC catalog ownership (`core` \| `product`) | No — catalog tag, not activation state |
| **`product_activations` (this)** | **Runtime per-user product state** | **Yes** |

Paid entitlement was already tracked by `service_entitlements` (Active / Grace / Expired), but that only covers **paid** SKUs. A free File Hosting user had zero rows everywhere — "logged in = can use" was the emergent pattern via config fallback in `QuotaService::quotaBytesFor`. There was no single source of truth for "user X has product Y in state Z", no suspend, no analytics surface, no provisioning step for explicit products.

## Two activation types

| Type | Behavior | Example |
|------|----------|---------|
| **Implicit** | Auto-activated on register. No provisioning step. Lifecycle: `active` ↔ `suspended`. | File Hosting |
| **Explicit** | Requires provisioning / approval before `active`. Lifecycle: `pending` → `active` → `suspended` → `expired`. | Future products (e.g. KYC-gated billing) |

Declared in `config/products.php`:

```php
'FileHosting' => [
    'activation_type' => 'implicit',
    'fulfillment_target' => 'file_hosting',
    'menu_routes' => ['file-hosting.index'], // menu routes hidden when not Active
],
```

## Config

| Key | Role |
|-----|------|
| `{ProductKey}.activation_type` | `implicit` \| `explicit` |
| `{ProductKey}.fulfillment_target` | Links to Service catalog `fulfillment_target` for fulfillment hook. Null when no paid SKU backing. |
| `{ProductKey}.menu_routes` | List of named menu routes bound to this product. Hidden from the dynamic menu when the viewer's activation is not `active`. Empty/missing for products with no menu surface. |

## State machine

```
                       ┌──────────────────────────────────────┐
                       │                                      │
  [*] ──implicit──►  Active ──suspend───►  Suspended ──reactivate──► Active
                       │                    │
                       │                    └──expire──► Expired
                       │
  [*] ──explicit──►  Pending ──approve──►  Active
                       │
                       └──reject / expire──► Expired
```

- `markExpired` is idempotent on Expired.
- `suspend` only valid on Active; `reactivate` only on Suspended.
- `approve` only on Pending; `reject` only on Pending/Active/Suspended.
- Implicit activations cannot enter Pending.

## Backward compatibility (no backfill)

Existing users have no `product_activations` row. Two lazy-create paths keep them working:

1. **Register hook** — `AuthService::register` calls `ProductActivationService::activateImplicit` for every implicit product after role assignment. New users always have rows.
2. **Middleware lazy-create** — `product.active` middleware calls `ensureImplicitActive` when no row exists for an implicit product. Creates an Active row with `source = 'lazy_create'` on first product endpoint hit.

Explicit products have no lazy-create path — missing row = 403 `not_provisioned`.

## Access flow

```
Request → auth:api (JWT) → product.active:FileHosting → permission:<perm> → Controller
  → policy → service-layer checks (quota / workspace entitlement)
```

Middleware:

- 401 if not authenticated.
- 404 if `product_key` unknown (config miss).
- Lazy-create + pass if implicit and no row.
- Pass if state is `active`.
- 403 (`product.inactive`) if state is `suspended` / `expired`.
- 403 (`product.not_provisioned`) if explicit and no row or `pending`.

## Integration points

### Register hook (`AuthService::register`)

After `assignRole('User')`, loops `ProductRegistry::implicitProductKeys()` and calls `activateImplicit` for each. Wrapped in a DB transaction with the user creation. Failures are logged (`auth.register.product_activation_failed`) but do NOT abort registration — the middleware lazy-create is the safety net.

### Fulfillment hook (`ServiceFulfillmentService::fulfill`)

When a paid entitlement activates, `ensureProductActive` maps the Service catalog `fulfillment_target` (e.g. `file_hosting`) to a product key via `ProductRegistry::productKeyByFulfillmentTarget` and keeps the implicit activation Active. For explicit products, the admin approval gate stays the source of truth — fulfillment does not auto-activate.

### Service entitlement (unchanged)

`ServiceEntitlementService::hasActivePaidFileHosting` still gates paid features (uploads in grace, collaborative workspaces). The activation registry is orthogonal — it gates "can use this product at all"; the entitlement gates "can use paid features within the product".

## API

### Self-service — `/api/v1/product-activations` (`auth:api`)

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/` | List current user's activations |
| GET | `/{productKey}` | Show own activation (404 if missing) |

### Admin — `/api/v1/admin/product-activations` (`auth:api` + `permission:view product activations`)

| Method | Path | Permission | Purpose |
|--------|------|------------|---------|
| GET | `/products` | `view product activations` | List products with summary (v2) |
| GET | `/products/{productKey}` | `view product activations` | Per-product detail + paginated activations (v2) |
| GET | `/products/{productKey}/users/{userId}` | `view product activations` | Per-user drill-down (v2) |
| GET | `/` | `view product activations` | List/filter (`product_key`, `state`, `user_id`) |
| GET | `/stats` | `view product activations` | Count by state |
| GET | `/{productActivation}` | `view product activations` | Show (owner sees own) |
| POST | `/{productActivation}/approve` | `manage product activations` | Pending → Active |
| POST | `/{productActivation}/reject` | `manage product activations` | → Expired (reason required) |
| POST | `/{productActivation}/suspend` | `manage product activations` | Active → Suspended (reason optional) |
| POST | `/{productActivation}/reactivate` | `manage product activations` | Suspended → Active |

## Permissions (core `domain_kind`)

```
view product activations
manage product activations
```

| Role | Permissions |
|------|-------------|
| Root / Admin | view + manage |
| Moderator / Management | view only |
| User | (none — self-service reads don't need the perm) |

Self-service endpoints authorize via the `ProductActivationPolicy::view` method: a user can always view their own activation, and `view product activations` grants cross-user view.

## Per-product insights (v2)

`ProductInsightService` aggregates per-product metrics for the admin dashboard. Lives at `app/Support/Product/Services/ProductInsightService.php`.

Reads from the stored `product_analytics` table (kept in sync on domain mutations by `ProductAnalyticsService`) and caches the aggregate read for 5 minutes. Cache is invalidated on every write — no stale windows.

| Method | Returns |
|--------|---------|
| `productSummary(string $productKey): array` | `{ product_key, activation_type, fulfillment_target, counts: {active, suspended, pending, expired, total}, product_metrics }` |
| `productMetrics(string $productKey): array` | Product-specific aggregates. Dispatches by key. `FileHosting`: `total_storage_used_bytes`, `total_files`, `total_workspaces`, `paid_users_count`, `free_users_count`, `grace_users_count`, `by_tier {free,paid,grace,n/a}`. |
| `userDetailInProduct(string $userId, string $productKey): array` | Per-user drill-down (reads source tables directly). `FileHosting`: `quota {used_bytes, limit_bytes, state}`, `tier`, `file_count`, `workspace_count_owned`, `workspace_count_joined`, `last_activity_at`. |

Counts delegate to `ProductActivationService::countByProductState`. Quota delegates to `QuotaService::usage`. All other metrics read the denormalized `product_analytics` rows.

## Stored analytics (v3)

Denormalized per-`(product_key, tier, metric_key)` aggregate rows written on domain mutations, read by the insight dashboard. **Not per-user** — one row per bucket (e.g. FileHosting + paid + storage_used_bytes), so a product with 100k users still has ~12 rows (3 tiers × 4 metrics) instead of 400k. Per-user drill-down reads source tables directly: single-user indexed queries are cheap, and the data already lives in `hosted_files` / `workspaces`, so duplicating it into analytics rows would be wasted space.

| Layer | Location |
|-------|----------|
| Table | `product_analytics` — `id`, `product_key`, `tier`, `metric_key`, `metric_value` |
| Model | `App\Support\Product\Models\ProductAnalytic` |
| Write service | `App\Support\Product\Services\ProductAnalyticsService` |
| Read service | `App\Support\Product\Services\ProductInsightService` |
| Backfill | `php artisan product:recompute-analytics [--product=FileHosting]` |

`tier` ∈ `free` \| `paid` \| `grace` \| `n/a`. The unique key is `(product_key, tier, metric_key)` — one row per bucket.

### Metrics tracked (FileHosting)

| `metric_key` | Source mutation | Hook |
|--------------|-----------------|------|
| `storage_used_bytes` | file upload / delete / restore | `HostedFile::booted()` Eloquent events |
| `file_count` | file create / delete / restore | `HostedFile::booted()` |
| `workspace_count_owned` | workspace create | `WorkspaceService::create` (explicit call) |
| `workspace_count_joined` | (planned) workspace member add/remove | — |

Blind-spot for bulk restores (Eloquent `restored` event does not fire on mass `restore()`): `FolderRepository::restore` calls `ProductAnalyticsService::increment` explicitly per restored file.

### Tier resolution on writes

`increment` / `decrement` resolve the user's tier via `QuotaService::storageStateFor` (lazy-resolved to break the constructor cycle with `ServiceEntitlementService`). The delta lands in that tier's bucket. Hot-path writes are O(1) — one locked upsert.

### Tier transitions (moveUserTier)

`ServiceEntitlementService` captures the user's tier **before** the entitlement mutation, then calls `ProductAnalyticsService::moveUserTier(productKey, userId, oldTier, newTier)` after the save. `moveUserTier` computes the user's contribution via single-user indexed source queries (`SELECT SUM(size) FROM hosted_files WHERE user_id = X`) and transfers it between buckets. No-op when `oldTier === newTier`.

| Trigger | oldTier → newTier |
|---------|-------------------|
| `activateFromPurchase` (paid main/addon) | (free\|grace) → paid |
| `enterGrace` | (paid) → grace |
| `markExpired` (no other active paid entitlement) | (paid\|grace) → free |

Tier transitions are rare events (purchase/grace/expiry), so the single-user source query is acceptable cost.

### Write semantics

- Atomic `increment` / `decrement` on a `DB::transaction` with `lockForUpdate()` on the existing bucket row; `updateOrCreate` when absent. `decrement` floored at 0.
- Every write calls `Cache::forget("product_insights.{productKey}.metrics")` — no stale TTL windows.
- `recomputeFileHostingTier($tier)` and `recomputeProduct($productKey)` rebuild bucket rows from source tables for backfill and stale-bucket recovery; `recomputeProduct` deletes existing rows first so empty buckets don't linger. Idempotent.

### Constructor cycle avoidance

`ProductAnalyticsService` → `QuotaService` → `ServiceEntitlementService` → `ProductAnalyticsService` is a constructor cycle. Broken by resolving `QuotaService` lazily via `App::make(QuotaService::class)` inside `tierFor()` only (the single method that needs tier state). Hot-path `increment` / `decrement` / `moveUserTier` resolve tier through `tierFor()` on demand.

### Read side

`ProductInsightService::fileHostingMetrics` reads all `product_analytics` rows for FileHosting and assembles the `by_tier` breakdown + totals in one query. User counts per tier come from `QuotaService::userIdsForTier` (resolves paid/grace from entitlements, free = FileHosting activations minus paid minus grace). Per-user drill-down reads `hosted_files` / `workspaces` directly — no analytics table touch.

### Insight API endpoints

Added to admin section (`permission: view product activations`):

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/api/v1/admin/product-activations/products` | List all products with summary |
| GET | `/api/v1/admin/product-activations/products/{productKey}` | Summary + paginated activations (`?state=` filter) |
| GET | `/api/v1/admin/product-activations/products/{productKey}/users/{userId}` | Per-user drill-down |

## Menu visibility (v2)

Menu nodes bound to a product via `menu_routes` are hidden from the dynamic navigation when the viewer's activation is not `active`. The check runs in `MenuRepository::nodeVisibleLeafRules`:

```php
if ($user && $node['route'] !== null) {
    $productKey = ProductRegistry::productKeyByMenuRoute($node['route']);
    if ($productKey !== null && ! $this->productActivations->isActive($user->id, $productKey)) {
        return false;
    }
}
```

- Non-product routes return `null` from the map → skip the check.
- Guests skip the check (no auth gate; governed by `is_authenticate` / `is_guest_only`).
- Cache stays global snapshot; per-user product state checked live each request (same pattern as role checks).

Helpers on `ProductRegistry`:
- `menuRoutesFor(string $productKey): array` — routes bound to a product.
- `productKeyByMenuRoute(string $route): ?string` — reverse lookup.

## Web Blade

Two-level UI:

- **Index** (`resources/views/adminlte4/product-activations/index.blade.php`) — product cards grid. Each card: product key, type badge, state count chips, product metric summary, "View Insights" link. Consumes `GET /api/v1/admin/product-activations/products`.
- **Show** (`resources/views/adminlte4/product-activations/show.blade.php`) — per-product dashboard: insight cards (state distribution + product metrics), state filter, per-user activations table with action buttons (suspend/reactivate/approve/reject). Mutations redirect back to the show page.

Menu entry: `Product activations` under `Administration` sidebar header, gated to Root / Admin / Moderator / Management roles.

## Events

| Event | Fired on |
|-------|----------|
| `ProductActivated` | New implicit activation created, or explicit Pending approved |
| `ProductActivationStateChanged` | Any state transition (carries `previousState`) |

Wire listeners here for future analytics / notification hooks.

## Tests

```bash
composer test -- --filter=Product
```

| Suite | What it covers |
|-------|----------------|
| `ProductActivationServiceTest` (unit) | State transitions, idempotency, type enforcement, lazy-create, counts |
| `ProductAnalyticsServiceTest` (unit) | increment/decrement upsert + floor, tier resolution, moveUserTier transfer between buckets, cache flush on write, recomputeFileHostingTier matches live counts, recomputeProduct clears stale buckets |
| `ProductInsightServiceTest` (unit) | Summary shape, counts, product_metrics for FH (by_tier), userDetail shape + tier, missing product throws |
| `ProductRegistryTest` (unit) | Catalog reads, fulfillment_target mapping, implicit/explicit partition, `menuRoutesFor` / `productKeyByMenuRoute` |
| `ProductActiveMiddlewareTest` (feature) | 200 active, 403 suspended/expired, 401 unauth, lazy-create on miss |
| `ProductActivationControllerTest` (feature) | Self-service list/show, 404 missing |
| `AdminProductActivationControllerTest` (feature) | Admin list/filter/suspend/reactivate/stats, products/productShow/userDetail, RBAC (User blocked, Moderator view-only) |
| `HostedFileAnalyticsHookTest` (feature) | create/soft-delete/restore/force-delete update storage_used_bytes + file_count rows |
| `TierTransitionAnalyticsTest` (feature) | fulfill free→paid, enterGrace paid→grace, markExpired →free (bucket transfer assertions) |
| `MenuVisibilityTest` (feature) | FileHosting menu hidden when suspended/expired, visible when active, non-product menu unaffected, guest no auth gate |
| `RegisterProductActivationTest` (feature) | Register creates implicit rows; user can use FH after register |
| `FileHostingProductGateTest` (feature) | FH regression with new middleware: active pass, suspended 403, public routes ungated |

## Related

- [service/README.md](../service/README.md) — paid entitlement, `fulfillment_target` dispatch
- [identity/README.md](../identity/README.md) — register flow, H-RBAC
- [file-hosting/README.md](../file-hosting/README.md) — first implicit product, middleware wiring
- [platform/architecture.md](../../platform/architecture.md) — kernel `app/Support/*` layout
