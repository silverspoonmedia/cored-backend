# Playground

The **playground** is a small, intentionally non-production surface that
demonstrates the application's layered architecture end to end — routing,
validation, services, repositories, API resources, localization, server-side
DataTables, and the Blade web client — without touching real product domains.

Everything here lives on the **global** API prefix `/api/playground/*` (not the
versioned `/api/v1/*`). Real product features must **not** be built here; the
playground is a reference you copy *from*, not a place product logic lands. See
[API Routing](./api-routing.md) for the global vs versioned split.

## Environment requirement (local/development only)

**Playground routes are disabled when `APP_ENV=production`.** The guard in `routes/api/playground.php` wraps all playground routes in `if (! app()->environment('production'))` to prevent accidental exposure in production environments.

**Global ping is always available:** `GET /api/ping` is registered outside the playground group and remains a liveness probe in every environment.

**Why disabled in production:**
- Playground routes have **no authentication or authorization** (intentional for quick testing)
- Exposing unauthenticated CRUD endpoints in production is a critical security risk
- See `SECURITY_AUDIT.md` FINDING-001 for details

**To use playground routes:**

Set `APP_ENV=local` or `APP_ENV=development` in your `.env` file, then clear config cache:

```bash
# .env
APP_ENV=local

# Clear config cache
php artisan config:clear
```

Verify playground routes are active:

```bash
php artisan route:list --path=api/playground
```

You should see routes for `/api/playground/users` and `/api/playground/orders`.

**Web frontend impact:** The Blade admin screens at `/orders/*` consume the playground orders API. If playground routes are disabled (production environment), these web routes will fail because the backend API endpoints they forward to do not exist.

---

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
├── Support/Orders/
│   ├── DirectAttachmentUploadService.php   # presigned direct-to-S3 upload orchestration
│   └── OrderAttachmentStorage.php          # object existence checks + deleteMany on s3Custom
├── Models/AttachmentUpload.php             # pending/completed browser-direct upload sessions
├── Http/
│   ├── Requests/Playground/{Store,Update,Index}OrderRequest.php
│   ├── Requests/Playground/{Init,Complete,Sign}AttachmentUploadRequest.php
│   ├── Resources/Playground/PlaygroundOrder{,Detail}Resource.php
│   ├── Controllers/API/PlaygroundOrderController.php
│   └── Controllers/API/PlaygroundAttachmentUploadController.php
├── Exceptions/Playground/
│   ├── OrderRequiresDetailException.php       # HTTP 422 — ≥1 line required
│   └── OrderNotEditableException.php           # HTTP 409 — completed orders are frozen
└── Support/DataTable/PlaygroundOrderRowActionResolver.php
```

`OrderRepositoryInterface` is bound to `OrderRepository` in
`App\Providers\Domain\PlaygroundServiceProvider`. The controller depends only on `OrderService`; the service
depends only on the repository contract.

### Endpoints

| Method | URI | Action | Route name |
|--------|-----|--------|------------|
| `GET` | `/api/playground/orders` | list (query + DataTables) | `playground.orders.index` |
| `POST` | `/api/playground/orders` | create master + details | `playground.orders.store` |
| `GET` | `/api/playground/orders/{order}` | show master + lines | `playground.orders.show` |
| `PUT`/`PATCH` | `/api/playground/orders/{order}` | update (full line replace) | `playground.orders.update` |
| `DELETE` | `/api/playground/orders/{order}` | delete master + lines | `playground.orders.destroy` |
| `DELETE` | `/api/playground/orders/bulk` | **mass-delete** the `ids[]` set | `playground.orders.bulk-destroy` |
| `POST` | `/api/playground/attachments/uploads` | init direct upload session | `playground.attachments.uploads.store` |
| `GET` | `/api/playground/attachments/uploads/{upload}` | upload status / resume | `playground.attachments.uploads.show` |
| `POST` | `/api/playground/attachments/uploads/{upload}/parts` | presign part numbers | `playground.attachments.uploads.sign-parts` |
| `POST` | `/api/playground/attachments/uploads/{upload}/complete` | finalize upload | `playground.attachments.uploads.complete` |
| `DELETE` | `/api/playground/attachments/uploads/{upload}` | abort pending upload | `playground.attachments.uploads.destroy` |

Registered with `Route::apiResource(...)->except(['create','edit'])->names('playground.orders')`,
plus an explicit `DELETE orders/bulk` line **declared before** the resource so
the literal `bulk` segment is not captured by the `{order}` wildcard.

### Mass-delete (bulk)

`DELETE /api/playground/orders/bulk` removes many orders in one atomic batch.
The id set rides in the request body:

```json
{ "ids": ["019e…", "019f…"] }
```

- `BulkDestroyOrderRequest` validates the shape only: `ids` is
  `required|array|min:1`; each entry is `uuid|distinct|exists:order_master,id`.
- `OrderService::deleteMany()` enforces the **same frozen-order rule** as the
  single delete: if **any** order in the set is `completed` it throws
  `OrderNotEditableException` (409) and **nothing** is deleted (all-or-nothing).
- `OrderRepository::deleteManyWithDetails()` drops every master + its details +
  attachment rows inside **one** `DB::transaction()`; files are purged only
  after the commit (same ordering as the single-row delete — a stored file with
  no DB row is a harmless leak, the reverse is data loss).
- Success returns `{ data: { deleted: <count> } }` with the
  `api/playground-orders.bulk_deleted` message.

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
- Files are attached via **completed** direct-upload session ids in
  `staged_attachments[]` (JSON). Upload bytes go browser → S3-compatible storage
  through presigned URLs first; see
  [Attachments (direct S3 upload)](#attachments-direct-s3-upload) below.

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

### Attachments (direct S3 upload)

Attachment metadata lives in the shared **`attachments`** table (polymorphic via
`uuidMorphs`); an order owns many rows through a `morphMany` relation. The
**files** live on the `s3Custom` disk (see `config/filesystems.php` and
`config/attachments.php`). Each `attachments` row stores `{disk, path, name, size,
mime}`, plus the owning `user_id` and a signed-URL cache (`cached_url`,
`cached_url_expires_at`).

**Ingress is browser-direct.** The application never receives file bytes on
create/update. Instead:

1. The client calls the upload API to **init** a session and receives presigned
   URLs (single `PutObject` for small files, S3 **multipart** + per-part URLs for
   larger files).
2. The browser uploads chunks **straight to object storage** (no app-server
   bandwidth).
3. The client calls **complete**; the server finalizes multipart uploads (or
   verifies the object exists) and marks the `attachment_uploads` row `completed`.
4. Order **store/update** sends `staged_attachments: ["<upload-uuid>", …]`; the
   service resolves those sessions into metadata rows inside the master-detail
   transaction.

| Piece | Role |
|-------|------|
| `App\Domain\Playground\Models\Attachment` | Polymorphic metadata row; owns disk-aware **download** URL resolution (`Attachment::url()`). |
| `App\Domain\Playground\Models\AttachmentUpload` | Server-side upload session (`pending` → `completed` / `aborted`); tracks multipart parts for resume. Stored in **`attachment_uploads`** — file bytes are **not** in the database. |

**`attachment_uploads` session table** (upload orchestration only):

| Column | Role |
|--------|------|
| `id` | Client-facing upload UUID (`staged_attachments[]` references this) |
| `path` | Pre-issued object key on the storage disk |
| `s3_upload_id` | S3/R2 multipart `UploadId` for files ≥ `multipart_threshold_bytes`; `null` for single-PUT uploads. Stored as **`text`** (not `varchar`) because provider IDs can exceed 255 characters — this is an opaque handle, not file content |
| `parts` | JSON array of uploaded part numbers + ETags (resume / complete) |
| `size`, `mime`, `original_name` | Declared file metadata from the client at init time |

| Piece | Role |
|-------|------|
| `DirectAttachmentUploadService` | Presign + S3 multipart orchestration (`initiate`, `status`, `signParts`, `complete`, `abort`, `resolveStaged`). |
| `OrderAttachmentStorage` | `pathExists` + `deleteMany` (compensation / purge); no server-side `store()`. |
| `OrderService` | Resolves `staged_attachments` into metadata; reconciles `keep_attachments` on update; compensates on DB failure. |
| `OrderRepository` | Persists `attachments` rows inside the master-detail `DB::transaction()`. |
| `StoreOrderRequest` / `UpdateOrderRequest` | `staged_attachments` (`nullable|array|max:N`), each id must reference a **completed** upload (`CompletedAttachmentUpload` rule). |
| `PlaygroundAttachmentUploadController` | Upload orchestration endpoints under `/api/playground/attachments/uploads`. |
| `PlaygroundOrderResource` | Emits `{path, name, size, mime, url}` per attachment. |

**Configuration** (`config/attachments.php`, overridable via `.env`):

| Key | Default | Notes |
|-----|---------|-------|
| `disk` | `s3Custom` | Target filesystem disk |
| `chunk_size_bytes` | 8 MiB | Multipart part size (S3 min 5 MiB except last part) |
| `multipart_threshold_bytes` | 8 MiB | Files below this get a single presigned PUT |
| `max_file_size_bytes` | 5 GiB | POC ceiling per file |
| `max_files_per_order` | 10 | Matches staged attachment count limit |
| `max_concurrent_uploads` | 2 | Max simultaneous browser uploads (`ATTACHMENT_UPLOAD_MAX_CONCURRENT`) |
| `presign_ttl_minutes` | 15 | Presigned URL lifetime |
| `session_ttl_hours` | 24 | Pending session / resume window |

**Bucket CORS (operator prerequisite):** configure the S3-compatible bucket to
allow browser `PUT` from your app origin and **expose the `ETag` response
header** (required for multipart completion). This cannot be automated from the
Laravel app.

**Upload API flow**

```
POST /api/playground/attachments/uploads
  { "name": "invoice.pdf", "mime": "application/pdf", "size": 10485760 }
→ { upload_id, strategy, chunk_size, part_count, parts: [{ part_number, url, headers }] }

PUT <presigned-url>   (browser → storage, per part)

POST /api/playground/attachments/uploads/{upload_id}/complete
  { "parts": [{ "part_number": 1, "etag": "…" }, …] }   // multipart only
→ { metadata: { disk, path, name, size, mime } }

GET /api/playground/attachments/uploads/{upload_id}   // resume: recorded_parts + fresh part URLs
DELETE /api/playground/attachments/uploads/{upload_id}   // abort
```

**Order attach (store/update)** — JSON body (no file bytes):

```json
{
  "customer_name": "Jane Doe",
  "customer_email": "jane@example.com",
  "details": [{ "product_name": "Widget", "quantity": 1, "unit_price": 100 }],
  "staged_attachments": ["019f…upload-uuid"]
}
```

**Reconciliation on update** — `keep_attachments[]` carries disk **paths** of
existing files to retain. The new set is `kept ∪ staged_attachments`:

| `keep_attachments` | `attachments_managed` | Behavior |
|--------------------|-----------------------|----------|
| *omitted* | *omitted / falsy* | keep **all** current files (metadata-only edit) |
| `[]` | — | drop **all** current files |
| *omitted* | `true` | drop **all** current files (form marker when every checkbox is unticked) |
| `["orders/attachments/…/a.pdf", …]` | — | keep only listed paths; rest deleted from disk |

**URL resolution (download)** — `Attachment::url()` returns a plain URL for
public disks or a cached **signed GET URL** for S3-compatible disks (TTL 60 min,
refresh threshold 5 min). See the table in the previous revision of this doc;
behavior is unchanged.

#### Transaction safety (no orphaned files)

Objects are written on object storage **before** the order transaction. The
service orders work so neither side leaks:

- **Create / update:** resolve completed staged uploads → DB transaction (master
  + details + `attachments` rows) → **on DB failure, delete the just-staged
  objects** (compensation). Dropped files (update) are deleted **only after**
  commit.
- **Delete:** remove DB rows first, then delete files from disk.

#### Web upload widget (manual queue)

The Blade form (`resources/views/adminlte4/orders/_form.blade.php`) mounts
`[data-direct-upload]` and loads `resources/js/orders/direct-upload.js` via Vite.
Upload orchestration still goes through proxied web routes
(`/orders/attachments/uploads/*` → playground API); completed sessions populate
`staged_attachments[]` hidden inputs submitted with the order JSON payload.

**Why manual start:** file selection no longer triggers immediate storage I/O.
The user reviews the queue, then clicks **Start upload**. This avoids surprise
bandwidth use and gives a clear checkpoint before presigned URLs are minted.

**Concurrency cap:** `max_concurrent_uploads` in `config/attachments.php`
(env `ATTACHMENT_UPLOAD_MAX_CONCURRENT`, default **2**) limits how many files
upload to object storage at once. The Blade template exposes this as
`data-max-concurrent` on `[data-direct-upload]`; a translated hint under the
file input explains the limit.

| Client state | When | Storage I/O |
|--------------|------|-------------|
| **Pending** | File picked, batch not started | None — row can be removed (×) |
| **Waiting…** | Batch started, all concurrency slots busy | None yet |
| **Uploading…** | Worker slot acquired | Init → PUT part(s) → complete |
| **Ready** | `complete` succeeded | Staged hidden input added |
| **Upload failed** | Any step threw | Row marked error; not staged |

Scheduler: `resources/js/orders/upload-queue.js` exports `runWithConcurrency()`.
`direct-upload.js` passes each queued item to the existing per-file upload worker.
Unit tests: `npm run test:js` (`resources/js/orders/upload-queue.test.js`).

**Form submit guard:** blocked while a batch is draining or any file is actively
**Uploading…**. Unstarted **Pending** files are *not* staged and do *not* block
save (same as never having picked them).

See also [web frontend — order direct-upload widget](./web-frontend.md#order-direct-upload-widget).

### Web (Blade) screens

The admin UI consumes the playground API exactly like the rest of the
[web frontend](./web-frontend.md) — it holds no business logic. Because
playground orders sit on the **global** prefix, `Web\OrderController` points the
shared client there with `ApiClient::withPrefix('api/playground')`.

| Method | URI | Name | Forwards to |
|--------|-----|------|-------------|
| GET | `/orders` | `orders.index` | Blade DataTable shell |
| GET | `/orders/datatable` | `orders.datatable` | `GET /playground/orders?display_mode=datatable` (proxied) |
| GET/POST | `/orders/create`, `/orders` | `orders.create` / `orders.store` | `POST /playground/orders` (JSON + `staged_attachments`) |
| POST | `/orders/attachments/uploads` | `orders.attachments.uploads.store` | `POST /playground/attachments/uploads` |
| GET | `/orders/attachments/uploads/{upload}` | `orders.attachments.uploads.show` | `GET /playground/attachments/uploads/{id}` |
| POST | `/orders/attachments/uploads/{upload}/complete` | `orders.attachments.uploads.complete` | `POST /playground/attachments/uploads/{id}/complete` |
| DELETE | `/orders/attachments/uploads/{upload}` | `orders.attachments.uploads.destroy` | `DELETE /playground/attachments/uploads/{id}` |
| GET | `/orders/{order}` | `orders.show` | `GET /playground/orders/{id}` |
| GET/PUT | `/orders/{order}/edit`, `/orders/{order}` | `orders.edit` / `orders.update` | `GET`/`PUT /playground/orders/{id}` |
| DELETE | `/orders/{order}` | `orders.destroy` | `DELETE /playground/orders/{id}` |
| DELETE | `/orders/bulk` | `orders.bulk-destroy` | `DELETE /playground/orders/bulk` (proxied; `ids[]` in body) |

- `resources/views/adminlte4/orders/_form.blade.php` renders **dynamic detail rows**
  (add/remove line item) with a monotonic row index so removing then adding
  never collides on a `details[i][...]` field name. It always keeps ≥1 row. On
  edit it shows a checkbox list of current files (`keep_attachments[]`) plus the
  **direct-upload widget** (`direct-upload.js` + `upload-queue.js`): user picks
  files (pending queue), clicks **Start upload**, then files upload to storage in
  chunks with at-most-`max_concurrent_uploads` parallelism before save; completed sessions populate `staged_attachments[]` hidden
  inputs. A hidden `attachments_managed=1` marker ensures unticking every
  keep-box drops all existing files (see
  [reconciliation](#attachments-direct-s3-upload)).
- `index.blade.php` is a server-side DataTable; the `status` column renders a
  colored badge, `grand_total` is locale-formatted, and completed orders drop
  their edit/delete buttons (`PlaygroundOrderRowActionResolver`). The first
  column is a **select checkbox** (header "select all" toggles the current
  page); completed rows are not selectable (frozen). A **"Delete selected"**
  toolbar button appears once ≥1 row is checked and posts the chosen `ids[]` to
  `orders.bulk-destroy` (AJAX); the selected set survives paging/search in JS,
  and the table reloads in place on success.
- `show.blade.php` renders the master summary, the read-only line table, and a
  download list of attachments (each links to its disk `url`).
- A sidebar entry (`orders.index`) is seeded by `MenuSeeder`.
- Order mutations forward **JSON only** (`ApiClient::post` / `put`); file bytes
  never pass through the web layer. Direct uploads use
  `Web\OrderAttachmentUploadController` as a thin proxy to the playground upload
  API.

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
| `lang/{locale}/api/playground-attachment-uploads.php` | Direct-upload API messages + validation errors |
| `lang/{locale}/web/orders.php` | Order web screens (titles, datatable columns, form + direct-upload labels, statuses) |
| `lang/{locale}/ui.php` | Shared datatable action labels + `confirm.delete_playground_order` |

---

## Tests

| Test | Focus |
|------|-------|
| `tests/Unit/Services/Playground/UserServiceTest.php` | User business rules (repository mocked) |
| `tests/Unit/Http/Controllers/API/PlaygroundControllerTest.php` | User controller delegates to service |
| `tests/Domain/Playground/Feature/Http/Controllers/API/PlaygroundUserCrudTest.php` | User full HTTP stack |
| `tests/Domain/Playground/Feature/Http/Controllers/API/PlaygroundUserDatatableTest.php` | User dual-mode list |
| `tests/Unit/Services/Playground/OrderServiceTest.php` | Order rules + total derivation; **upload-rollback compensation** (repository mocked, disk faked) |
| `tests/Feature/Repositories/OrderRepositoryTest.php` | **Transaction rollback** — no orphaned master/details on a failed line insert; ID-preserving line sync |
| `tests/Domain/Playground/Feature/Http/Controllers/API/PlaygroundOrderCrudTest.php` | Order full HTTP stack: atomic create, ID-preserving update, frozen completed orders, **atomic mass-delete (frozen-order batch abort, id-set validation)**, datatable mode |
| `tests/Domain/Playground/Feature/Http/Controllers/API/PlaygroundOrderAttachmentTest.php` | Staged direct uploads, keep/drop reconciliation, delete purges files (disk faked) |
| `tests/Domain/Playground/Feature/Http/Controllers/API/PlaygroundAttachmentUploadTest.php` | Upload init/complete/status/abort API lifecycle (fake S3 service) |
| `tests/Unit/Support/Orders/DirectAttachmentUploadServiceTest.php` | Upload strategy selection + `resolveStaged` rules |
| `tests/Feature/Http/Controllers/Web/OrderAttachmentWebTest.php` | Web layer forwards JSON order mutations + upload init proxy |
| `tests/Feature/Http/Controllers/Web/OrderBulkDeleteWebTest.php` | Web layer forwards the selected `ids[]` to the API bulk endpoint and relays its envelope |
| `tests/Unit/Models/AttachmentUploadTest.php` | Multipart `s3_upload_id` longer than 255 chars persists (`text` column) |
| `tests/Feature/Models/AttachmentTest.php` | Disk-aware `Attachment::url()`: plain URL for public disks; signed-URL mint + cache, fresh-cache reuse, near-expiry refresh for S3; owning-user relation |
| `resources/js/orders/upload-queue.test.js` | Browser upload queue scheduler (`runWithConcurrency`): empty list, concurrency ceiling, completion, error propagation |

```bash
php artisan test --filter=Playground
php artisan test --filter=Order
npm run test:js    # Vitest — upload-queue scheduler
```

---

## Related documentation

- [CRUD Architecture & Repository Pattern](./crud-architecture.md) — layer responsibilities; simple (generic repo) vs master-detail (dedicated repo + transaction)
- [API Routing](./api-routing.md) — global vs versioned; why the playground is global-only
- [List display modes](./list-display-modes.md) — query + DataTables dual mode and row-action resolvers
- [Web frontend (Blade + AdminLTE 4)](./web-frontend.md) — the API-client architecture the order screens use
- [Localization](./localization.md) — translation requirement for all user-facing copy
- [User primary keys (UUID v7)](./user-uuid-v7.md) — the UUID convention the order tables follow
