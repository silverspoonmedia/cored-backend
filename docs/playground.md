# Playground

The **playground** is a small, intentionally non-production surface that
demonstrates the application's layered architecture end to end — routing,
validation, services, repositories, API resources, localization, server-side
DataTables, and the Blade web client — without touching real product domains.

Everything here lives on the **global** API prefix `/api/playground/*` (not the
versioned `/api/v1/*`). Real product features must **not** be built here; the
playground is a reference you copy *from*, not a place product logic lands. See
[API Routing](./api-routing.md) for the global vs versioned split.

| Surface | Demonstrates |
|---------|--------------|
| **Playground Users** (`/api/playground/users`) | Simple five-action CRUD on the **shared** `EloquentCrudRepository`, business rules in a Service, dual-mode list (query + DataTables) |
| **Playground Orders** (`/api/playground/orders`) | A **master-detail aggregate** (`Order` → `OrderDetail`) with a **dedicated repository** and **transactional** two-table writes |

> Why two? Users shows the *simple* path (generic CRUD repo); Orders shows the
> *rich* path (dedicated repo + `DB::transaction`). Pick the pattern that
> matches the data-access complexity — never put master-detail/transactional
> writes into the generic CRUD repository.

---

## Playground Orders (master-detail)

A canonical **one master → many details** aggregate: an order header
(`order_master`) owns many line items (`order_detail`). The write spans two
tables and is **atomic** — a failed line insert rolls the whole thing back, so a
master never persists without its details (**no orphaned data**).

### Schema

Both tables follow the project UUID convention (see [UUID v7](./user-uuid-v7.md)).

| Table | Key columns |
|-------|-------------|
| `order_master` | `id` (uuid PK), `order_number` (unique), `customer_*`, `subtotal`, `discount`, `tax`, `grand_total`, `status` (`pending`/`processing`/`completed`/`cancelled`), `notes`, timestamps; index on `status` |
| `order_detail` | `id` (uuid PK), `order_master_id` (`foreignUuid` → `order_master`, `cascadeOnDelete`), `product_name`, `quantity`, `unit_price`, `discount`, `subtotal`, `notes`, timestamps |

> The migrations use `uuid` / `foreignUuid` (project rule), not the `ulid` from
> the original example file. `product_name` is added so each line references a
> product. The FK `cascadeOnDelete` is a DB-level safety net; the repository
> still deletes children explicitly inside the transaction so intent is clear
> and the behavior survives an FK policy change.

### Layers

```
app/
├── Models/
│   ├── Order.php          # HasMany details, HasUuids, decimal casts, factory
│   └── OrderDetail.php    # BelongsTo order (order_master_id), HasUuids, factory
├── Repositories/
│   ├── Contracts/OrderRepositoryInterface.php
│   └── Eloquent/OrderRepository.php          # all writes wrapped in DB::transaction()
├── Services/Playground/OrderService.php      # business rules + derives totals from lines
├── Http/
│   ├── Requests/Playground/{Store,Update,Index}OrderRequest.php
│   ├── Resources/Playground/PlaygroundOrder{,Detail}Resource.php
│   └── Controllers/API/PlaygroundOrderController.php
├── Exceptions/Playground/
│   ├── OrderRequiresDetailException.php       # HTTP 422 — ≥1 line required
│   └── OrderNotEditableException.php           # HTTP 409 — completed orders are frozen
└── Support/DataTable/Resolvers/PlaygroundOrderRowActionResolver.php
```

`OrderRepositoryInterface` is bound to `OrderRepository` in
`AppServiceProvider`. The controller depends only on `OrderService`; the service
depends only on the repository contract.

### Endpoints

| Method | URI | Action | Route name |
|--------|-----|--------|------------|
| `GET` | `/api/playground/orders` | list (query + DataTables) | `playground.orders.index` |
| `POST` | `/api/playground/orders` | create master + details | `playground.orders.store` |
| `GET` | `/api/playground/orders/{order}` | show master + lines | `playground.orders.show` |
| `PUT`/`PATCH` | `/api/playground/orders/{order}` | update (full line replace) | `playground.orders.update` |
| `DELETE` | `/api/playground/orders/{order}` | delete master + lines | `playground.orders.destroy` |

Registered with `Route::apiResource(...)->except(['create','edit'])->names('playground.orders')`.

### Request shape (store/update)

```json
{
  "customer_name": "Jane Doe",
  "customer_email": "jane@example.com",
  "customer_phone": "0812345678",
  "discount": 10,
  "tax": 5,
  "status": "pending",
  "notes": "Rush order",
  "details": [
    { "product_name": "Widget", "quantity": 2, "unit_price": 100, "discount": 0 },
    { "product_name": "Gadget", "quantity": 1, "unit_price": 50, "discount": 5 }
  ]
}
```

On **update**, include each surviving line's `id` to edit it in place; omit `id`
for a new line; drop a line from the array to delete it:

```json
{
  "customer_name": "Jane Doe",
  "customer_email": "jane@example.com",
  "details": [
    { "id": "019e…existing-uuid", "product_name": "Widget", "quantity": 3, "unit_price": 100 },
    { "product_name": "Brand new line", "quantity": 1, "unit_price": 10 }
  ]
}
```

- `details` is `required | array | min:1`; each line validated via `details.*.*` rules.
- `subtotal` and `grand_total` are **never** taken from the client — the service
  computes each line's `subtotal = (quantity × unit_price) − discount`, sums them
  into the master `subtotal`, then `grand_total = subtotal − discount + tax`
  (floored at 0).
- `order_number` is generated on create (`ORD-YYYYMMDD-XXXXXX`) and **immutable** afterward.
- On **update**, send each existing line's `id` to keep it (and its UUID); omit
  `id` for new lines. See [Update semantics](#rules-and-responsibilities) below.

### Rules and responsibilities

| Concern | Layer | Notes |
|---------|-------|-------|
| Input shape (types, `details` array) | `Store/UpdateOrderRequest` | HTTP-shape validation only |
| Atomic two-table write | `OrderRepository` | `createWithDetails` / `updateWithDetails` / `deleteWithDetails` each in `DB::transaction()` |
| Totals derived from lines | `OrderService` | persisted money always matches items |
| `≥1 line` required | `OrderService` → `OrderRequiresDetailException` (422) | business rule, not request validation |
| Completed orders frozen | `OrderService` → `OrderNotEditableException` (409) | update/delete rejected for `status = completed` |
| Update semantics | `OrderRepository::updateWithDetails` | **ID-preserving sync**: a line sent with an existing `id` is updated in place (UUID kept); a line without `id` is inserted; a line absent from the payload is deleted. A foreign/unknown `id` is treated as a new line (no cross-order hijack). All in one transaction. |

> **Update preserves line ids.** Editing an order updates the matching
> `order_detail` rows in place, so their UUIDs — and any external reference to a
> line — survive the edit. This is an ID-preserving **sync**, not a delete-all +
> recreate (which would silently re-issue every line id on each save).

### Web (Blade) screens

The admin UI consumes the playground API exactly like the rest of the
[web frontend](./web-frontend.md) — it holds no business logic. Because
playground orders sit on the **global** prefix, `Web\OrderController` points the
shared client there with `ApiClient::withPrefix('api/playground')`.

| Method | URI | Name | Forwards to |
|--------|-----|------|-------------|
| GET | `/orders` | `orders.index` | Blade DataTable shell |
| GET | `/orders/datatable` | `orders.datatable` | `GET /playground/orders?display_mode=datatable` (proxied) |
| GET/POST | `/orders/create`, `/orders` | `orders.create` / `orders.store` | `POST /playground/orders` |
| GET | `/orders/{order}` | `orders.show` | `GET /playground/orders/{id}` |
| GET/PUT | `/orders/{order}/edit`, `/orders/{order}` | `orders.edit` / `orders.update` | `GET`/`PUT /playground/orders/{id}` |
| DELETE | `/orders/{order}` | `orders.destroy` | `DELETE /playground/orders/{id}` |

- `resources/views/orders/_form.blade.php` renders **dynamic detail rows**
  (add/remove line item) with a monotonic row index so removing then adding
  never collides on a `details[i][...]` field name. It always keeps ≥1 row.
- `index.blade.php` is a server-side DataTable; the `status` column renders a
  colored badge, `grand_total` is locale-formatted, and completed orders drop
  their edit/delete buttons (`PlaygroundOrderRowActionResolver`).
- `show.blade.php` renders the master summary plus the read-only line table.
- A sidebar entry (`orders.index`) is seeded by `MenuSeeder`.

### DataTables

Both playground lists support query + DataTables dual mode (see
[List display modes](./list-display-modes.md)). The order resolver exposes
`show`/`edit`/`delete`, dropping edit/delete for `completed` rows. Action
labels/confirm come from `lang/{locale}/ui.php` (`ui.datatable.*`).

---

## Localization

All playground copy is translated for every supported locale (`en`, `id`, `ja`)
per the [Localization task rule](./localization.md#task-rule-required):

| File | Contents |
|------|----------|
| `lang/{locale}/api/playground.php` | Playground **Users** API messages |
| `lang/{locale}/api/playground-orders.php` | Playground **Orders** API messages + business-rule errors |
| `lang/{locale}/web/orders.php` | Order web screens (titles, datatable columns, form labels, statuses) |
| `lang/{locale}/ui.php` | Shared datatable action labels + `confirm.delete_playground_order` |

---

## Tests

| Test | Focus |
|------|-------|
| `tests/Unit/Services/Playground/UserServiceTest.php` | User business rules (repository mocked) |
| `tests/Unit/Http/Controllers/API/PlaygroundControllerTest.php` | User controller delegates to service |
| `tests/Feature/Http/Controllers/API/PlaygroundUserCrudTest.php` | User full HTTP stack |
| `tests/Feature/Http/Controllers/API/PlaygroundUserDatatableTest.php` | User dual-mode list |
| `tests/Unit/Services/Playground/OrderServiceTest.php` | Order rules + total derivation (repository mocked) |
| `tests/Feature/Repositories/OrderRepositoryTest.php` | **Transaction rollback** — no orphaned master/details on a failed line insert |
| `tests/Feature/Http/Controllers/API/PlaygroundOrderCrudTest.php` | Order full HTTP stack: atomic create, full-line replace, frozen completed orders, datatable mode |

```bash
php artisan test --filter=Playground
php artisan test --filter=Order
```

---

## Related documentation

- [CRUD Architecture & Repository Pattern](./crud-architecture.md) — layer responsibilities; simple (generic repo) vs master-detail (dedicated repo + transaction)
- [API Routing](./api-routing.md) — global vs versioned; why the playground is global-only
- [List display modes](./list-display-modes.md) — query + DataTables dual mode and row-action resolvers
- [Web frontend (Blade + AdminLTE 4)](./web-frontend.md) — the API-client architecture the order screens use
- [Localization](./localization.md) — translation requirement for all user-facing copy
- [User primary keys (UUID v7)](./user-uuid-v7.md) — the UUID convention the order tables follow
