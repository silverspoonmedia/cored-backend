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
| `attachments` | `id` (uuid PK), `attachable_type` + `attachable_id` (`uuidMorphs`, polymorphic owner), `user_id` (`foreignUuid` → `users`, `nullOnDelete`), `disk`, `path`, `name`, `size`, `mime`, `cached_url` + `cached_url_expires_at` (signed-URL cache, S3 only), timestamps |

> Attachment metadata lives in its own **`attachments`** table (polymorphic via
> `uuidMorphs`), **not** a JSON column on `order_master`. One shared table can
> own files for any model, and file metadata is queryable/manageable like any
> other row. An order owns its files through a `morphMany` relation. Each row
> records its `disk` and the owning `user_id`. For S3-compatible disks the
> signed URL is cached on the row (`cached_url` + `cached_url_expires_at`) and
> regenerated only when it nears expiry — see [Attachments](#attachments-multiple-file-upload).

> The migrations use `uuid` / `foreignUuid` (project rule), not the `ulid` from
> the original example file. `product_name` is added so each line references a
> product. The FK `cascadeOnDelete` is a DB-level safety net; the repository
> still deletes children explicitly inside the transaction so intent is clear
> and the behavior survives an FK policy change.

### Layers

```
app/
├── Models/
│   ├── Order.php          # HasMany details, MorphMany attachments, HasUuids, decimal casts, factory
│   ├── OrderDetail.php    # BelongsTo order (order_master_id), HasUuids, factory
│   └── Attachment.php     # MorphTo attachable + BelongsTo user, HasUuids, factory; disk-aware url() with signed-URL cache
├── Repositories/
│   ├── Contracts/OrderRepositoryInterface.php
│   └── Eloquent/OrderRepository.php          # all writes wrapped in DB::transaction()
├── Services/Playground/OrderService.php      # business rules + totals + attachment reconciliation
├── Support/Orders/OrderAttachmentStorage.php # file I/O on the s3Custom disk (store/delete/url)
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
- Files are uploaded as `attachments[]` over `multipart/form-data`; existing
  files are retained via `keep_attachments[]`. See [Attachments](#attachments-multiple-file-upload) below.

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

### Attachments (multiple file upload)

Attachment metadata lives in the shared **`attachments`** table (polymorphic via
`uuidMorphs`); an order owns many rows through a `morphMany` relation — one order
can carry many files. The **files** live on the `s3Custom` disk (see
`config/filesystems.php`); each `attachments` row stores lightweight metadata per
file: `{disk, path, name, size, mime}`, plus the owning `user_id` and a
signed-URL cache (`cached_url`, `cached_url_expires_at`).

| Piece | Role |
|-------|------|
| `App\Models\Attachment` | Polymorphic (`attachable`) metadata row; `Order morphMany attachments`, `Attachment belongsTo user`. Owns **disk-aware URL resolution** (see below). The shared table is not order-specific. |
| `App\Support\Orders\OrderAttachmentStorage` | Pure file I/O: `store`/`storeMany`/`deleteMany` on the `s3Custom` disk; `store` returns the `disk` in each metadata row. No DB access, no URL logic. |
| `OrderService` | Decides when to store/keep/forget files; stamps the authenticated `user_id` on freshly stored rows; reconciles the desired metadata set (by `path`) and hands it to the repository, which writes the rows inside the master-detail transaction. Compensates a failed DB write by deleting the just-uploaded files |
| `OrderRepository` | Persists/reconciles `attachments` rows **inside** the same `DB::transaction()` as the master + details (sync by `path`: drop removed, insert new, keep surviving UUIDs) |
| `StoreOrderRequest` | `attachments` (`nullable|array|max:10`), `attachments.*` (`file|max:10240|mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx`) |
| `UpdateOrderRequest` | Same `attachments` rules **plus** `keep_attachments` (array of disk paths to retain) and `attachments_managed` (boolean marker — see reconciliation below) |
| `PlaygroundOrderResource` | Emits each file as `{path, name, size, mime, url}` (from the `attachments` relation; the `url` comes from `Attachment::url()`) |

**URL resolution (disk-aware + signed-URL cache)** — `Attachment::url()` returns
the right URL for the file's `disk`:

| Disk kind | Behaviour |
|-----------|-----------|
| Public / local (`providesTemporaryUrls() === false`) | plain, non-expiring `Storage::url()`; nothing cached |
| S3-compatible (`providesTemporaryUrls() === true`) | a **signed** (temporary) URL is minted, cached on the row (`cached_url` + `cached_url_expires_at`, TTL `60` min), and reused on subsequent calls until it is within `5` min of expiring — then a fresh one is minted and re-cached |

> Signing is expensive and the signed URL itself expires, so caching it avoids
> re-minting on every read while the refresh threshold guarantees a caller never
> gets an about-to-die URL. The cache write is best-effort: the URL is always
> returned even if the row cannot be saved.

**Upload (store/update)** — send `multipart/form-data` with `attachments[]` file
parts alongside the JSON-ish fields:

```
POST /api/playground/orders        (multipart/form-data)
  customer_name = Jane Doe
  customer_email = jane@example.com
  details[0][product_name] = Widget
  details[0][quantity] = 1
  details[0][unit_price] = 100
  attachments[] = <file: invoice.pdf>
  attachments[] = <file: photo.png>
```

**Reconciliation on update** — `keep_attachments[]` carries the disk **paths**
of existing files to retain. The new set is `kept ∪ newly uploaded`:

| `keep_attachments` | `attachments_managed` | Behavior |
|--------------------|-----------------------|----------|
| *omitted* | *omitted / falsy* | keep **all** current files (metadata-only edit) |
| `[]` (empty array, JSON only) | — | drop **all** current files |
| *omitted* | `1` (truthy) | drop **all** current files (multipart-safe "drop all") |
| `["orders/attachments/a.pdf", …]` | — | keep only the listed paths; the rest are deleted from disk |

> **Why `attachments_managed`?** An empty `keep_attachments` array encodes fine
> in JSON, but **not** over `multipart/form-data` — an empty array produces zero
> form fields, so the key arrives *absent*, which otherwise means "keep all".
> The Blade form (multipart) therefore sends a scalar `attachments_managed=1`
> marker; when it is truthy and no keep-list arrived, the service treats it as
> "keep none". An explicit `keep_attachments` always wins over the marker. This
> is resolved in `OrderService::resolveKeepPaths()`, so both JSON API callers
> and the multipart web form get correct semantics.

#### Transaction safety (no orphaned files)

Files are stored on a disk, which is **not** part of the DB transaction (the
`attachments` rows are). The service orders the work so neither side leaks:

- **Create / update:** upload files → run the DB transaction (writes the
  master, details **and** `attachments` rows together) → **on DB failure,
  delete the just-uploaded files** (compensating action) and rethrow. A dropped
  file (update) is deleted **only after** the DB commit succeeds, so a rollback
  never loses a file that is still referenced.
- **Delete:** remove the DB rows first (master + details + attachments in one
  transaction), then delete its files. A stored file with no DB row is a
  harmless leak; a missing file with a live row is data loss — so the DB is the
  source of truth and files are purged last.

> The web layer forwards uploads as multipart too: `ApiClient::postMultipart()` /
> `putMultipart()` attach the files and flatten nested fields (e.g.
> `details[0][product_name]`) into multipart-safe keys. `putMultipart` uses
> `_method=PUT` spoofing because PHP does not parse multipart bodies on real PUT.

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
  never collides on a `details[i][...]` field name. It always keeps ≥1 row. It
  also has a multi-file `attachments[]` input and, on edit, a checkbox list of
  current files (`keep_attachments[]`) — unticking one removes it on save. The
  form also carries a hidden `attachments_managed=1` so unticking **every** box
  drops all files (an empty keep-list cannot survive multipart — see
  [reconciliation](#attachments-multiple-file-upload)).
- `index.blade.php` is a server-side DataTable; the `status` column renders a
  colored badge, `grand_total` is locale-formatted, and completed orders drop
  their edit/delete buttons (`PlaygroundOrderRowActionResolver`).
- `show.blade.php` renders the master summary, the read-only line table, and a
  download list of attachments (each links to its disk `url`).
- A sidebar entry (`orders.index`) is seeded by `MenuSeeder`.
- File uploads are forwarded as multipart by the web layer
  (`ApiClient::postMultipart`/`putMultipart`); the standard AJAX form already
  submits via `FormData`, so the file inputs ride along automatically.

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
| `lang/{locale}/web/orders.php` | Order web screens (titles, datatable columns, form + attachment labels, statuses) |
| `lang/{locale}/ui.php` | Shared datatable action labels + `confirm.delete_playground_order` |

---

## Tests

| Test | Focus |
|------|-------|
| `tests/Unit/Services/Playground/UserServiceTest.php` | User business rules (repository mocked) |
| `tests/Unit/Http/Controllers/API/PlaygroundControllerTest.php` | User controller delegates to service |
| `tests/Feature/Http/Controllers/API/PlaygroundUserCrudTest.php` | User full HTTP stack |
| `tests/Feature/Http/Controllers/API/PlaygroundUserDatatableTest.php` | User dual-mode list |
| `tests/Unit/Services/Playground/OrderServiceTest.php` | Order rules + total derivation; **upload-rollback compensation** (repository mocked, disk faked) |
| `tests/Feature/Repositories/OrderRepositoryTest.php` | **Transaction rollback** — no orphaned master/details on a failed line insert; ID-preserving line sync |
| `tests/Feature/Http/Controllers/API/PlaygroundOrderCrudTest.php` | Order full HTTP stack: atomic create, ID-preserving update, frozen completed orders, datatable mode |
| `tests/Feature/Http/Controllers/API/PlaygroundOrderAttachmentTest.php` | Multi-file upload, type validation, keep/drop reconciliation (incl. `attachments_managed` marker), delete purges files (disk faked) |
| `tests/Feature/Http/Controllers/Web/OrderAttachmentWebTest.php` | Web layer forwards uploads + attachment fields as multipart to the playground API |
| `tests/Feature/Models/AttachmentTest.php` | Disk-aware `Attachment::url()`: plain URL for public disks; signed-URL mint + cache, fresh-cache reuse, near-expiry refresh for S3; owning-user relation |

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
