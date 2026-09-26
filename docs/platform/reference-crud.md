# CRUD architecture & repository pattern — reference

Detail behind [crud.md](crud.md): when the shared repository applies, the layer table, FormRequest DRY, envelope wiring, dual-field presentation, and the new-CRUD checklist.

Production features live on `/api/v1/*`. See [reference-api-routing.md](reference-api-routing.md), [reference-architecture.md](reference-architecture.md).

## When to use `EloquentCrudRepository`

`App\Repositories\Eloquent\EloquentCrudRepository` — generic helper for simple list/show/create/update/delete.

| Use shared repo | Prefer dedicated repo |
|-----------------|----------------------|
| Straightforward CRUD | Complex queries, multi-table transactions |
| Business rules in **Service** | Domain-specific persistence methods |
| Same patterns across models | Upserts, bulk imports, aggregate roots |

Never put business logic in the generic repository. Standard five-action CRUD → `Route::apiResource` (exclude `create`/`edit` for API-only).

## Routing simple CRUD

| Method | URI | Action |
|--------|-----|--------|
| `GET` | `/resources` | `index` |
| `POST` | `/resources` | `store` |
| `GET` | `/resources/{resource}` | `show` |
| `PUT`/`PATCH` | `/resources/{resource}` | `update` |
| `DELETE` | `/resources/{resource}` | `destroy` |

```php
Route::apiResource('users', UserController::class)->except(['create', 'edit']);
```

**References:** v1 users (`routes/api/v1/identity.php`); Archivd folders/files under `routes/api/v1/archivd.php`.

**Not** `apiResource` when you need extra actions, query-mode paging, per-verb permissions, or optional-auth reads — see [Navigation](../domains/navigation/README.md), [list-display-modes](list-display-modes.md).

## Layer responsibilities

| Layer | Does | Must not |
|-------|------|----------|
| **Form Request** | Input validation, `authorize()` | Business rules, data access |
| **Controller** | Validated input → Service → JSON | Validation, Eloquent, business rules |
| **Service** | Business rules; orchestrate repos | HTTP shape validation, raw queries |
| **Repository** | Query/persist | Business logic |
| **Model** | Mapping, casts, relations | App workflow |

Business failures → `BusinessRuleException` subclasses (not `ValidationException`).

### FormRequest DRY (store vs update)

Do **not** copy-paste full rule arrays between Store/Update. Prefer domain `Http/Requests/Concerns/*` that share field definitions:

| Pattern | Example |
|---------|---------|
| Shared field map + modality | `App\Support\Http\Requests\Concerns\ModalFieldRules` (`storeRules` / `updateRules`) |
| Domain catalog | Service `ServiceCatalogRules`, PaymentGateway gateway/subscription concerns |
| Micro-rules | Archivd `ArchivdRequestRules` (rename/move/bulk/visibility via `Visibility::values()`, workspace, sha256 blocklist) |
| Identity users | `UserMutationRules` (assignable roles + password once) |
| Microstock | `MicrostockRequestRules` (collection lock, metadata, workspace, credentials) |
| Product activations | `ProductActivationRequestRules` (admin filters, suspend/approve/reject) |
| Vtual | `VtualRequestRules` (external links, creator/management store vs update, credential secrets, invite email) |
| Inference | `InferenceCatalogRules` (provider/model-card slug uniqueness, store vs update `display_name`) |
| Translatable locale maps | `App\Support\Localization\Concerns\ValidatesTranslatableInput` (`zh-CN` → `zh_CN` canonicalization, all-locale requirement, empty-map opt-out) — [reference-localization.md](reference-localization.md#translatable-payload-maps) |

Keep separate Store/Update classes for route binding; only the **rules body** is shared. Input normalization (e.g. service `prices[]` currency uppercasing, Archivd sha256 lowercasing) belongs in FormRequest `prepareForValidation`, not the web proxy. Translatable locale maps are the exception: `ValidatesTranslatableInput` rewrites the request payload in place (alias → canonical key) because `validated()` and persistence must never see `zh-CN`.

API mutations use FormRequests — do **not** `$request->validate()` in controllers.

### Envelope wiring

Controllers return the canonical `{ status, message, data }` envelope via `App\Traits\ApiResponse` (`successResponse` / `errorResponse`). Direct `ApiResponder` stays for exception renderers and special cases that mutate `meta` (e.g. `BatchController`).

Always `resolveResource()` before `successResponse()` so `data` is a plain array — never pass a raw `JsonResource` into the envelope.

Query-mode `{ items, pagination }` that already emits the five-key meta (`current_page`, `last_page`, `per_page`, `total`, `has_more`) should call `ListQueryPaginator::meta()`. Do **not** add `has_more` to endpoints that do not already emit it.

## Request flow

```
HTTP → Form Request → Controller → Service → Repository → Model
```

## Identity Users (simple CRUD)

Reference for shared-repo CRUD: Identity `UserController` → user services/repos under `app/Domain/Identity/`, routes in `routes/api/v1/identity.php`.

API envelope: `ApiResponse` (`status`, `message`, optional `data`). List/show use Eloquent API Resources under `App\Domain\{Domain}\Http\Resources\…`. Tabular indexes that support `display_mode` may return a flat `T[]` or, when `page` / `per_page` / `search` are present on `display_mode=query`, `{ items, pagination }` — [list-display-modes.md](list-display-modes.md). Query and flat list rows carry `actions[]` (`QueryRowAction`); that is the live SPA row-button source.

## API Resource presentation (dual fields)

Human-readable display is produced **in Eloquent Resources** (or presenters that emit API arrays), never in Blade/services/models.

| Kind | Raw field (machine) | Display companions |
|------|---------------------|--------------------|
| DateTime | `created_at` ISO-8601 | `created_at_formatted` (absolute), `created_at_human` (relative) |
| Money | `amount` / `subtotal` numeric | `{field}_formatted` (`CURRENCY` + locale number, 2 dp) |
| Bytes | `size` / `storage_bytes` int | `{field}_human` (`1.5 MB`) |

**Helpers**

| Piece | Role |
|-------|------|
| `App\Support\Presentation\{Date,Money,Byte}Presenter` | Pure formatters; locale from `app()->getLocale()` |
| `App\Http\Resources\Concerns\FormatsPresentation` | `dateFields` / `moneyFields` / `byteFields` merge helpers for `toArray()` |

```php
return [
    ...$this->dateFields('created_at', $this->created_at),
    ...$this->moneyFields('subtotal', $this->subtotal, $this->currency),
    ...$this->byteFields('size', $this->size),
];
```

Rules:

- Null raw → null companions (not empty string).
- Keep raw types for math / gateway / interop; companions are display-only additive (non-breaking).
- Money always pairs with the currency field on the same payload (`currency`, `display_currency`, `settlement_currency`). Staff-only money keys (purchase settlement/FX) still use the same companions **when emitted**; they are omitted for buyers — [reference-security.md — response data minimization](reference-security.md#response-data-minimization-mandatory).
- Non-Resource array presenters (`UserPresenter`, upload session serializers, quota usage) call the same `*Presenter` classes.
- **Do not** `number_format` / `diffForHumans` / ad-hoc byte math in Blade for API data.

Tests: `tests/Unit/Support/Presentation/*`, `tests/Unit/Http/Resources/PresentationResourceTest.php`.

## Master-detail / aggregate writes

Multi-table atomic writes → **dedicated repository** + `DB::transaction()`.

Domain-specific examples: Service catalog prices, Archivd folder trees + upload sessions. Prefer domain READMEs over inventing a global demo surface.

## Evolving to a dedicated repository

1. Interface with domain language methods under `App\Domain\{Domain}\Repositories\Contracts\`
2. Eloquent implementation
3. Bind in domain ServiceProvider
4. Inject into Service; keep business rules in Service

Do not extend `EloquentCrudRepository` with domain methods.

## Checklist for new CRUD

1. Form Request(s) + `authorize()`
2. Service + `BusinessRuleException` subclasses
3. Shared or dedicated repository
4. API Resource(s) for list/show — dual-field presentation for any date/money/bytes (see above)
5. Thin controller
6. Tests under `tests/Domain/{Domain}/` (assert companions when display fields matter)
7. Routes under `routes/api/v1/{slug}.php`
8. SPA/API consumers use `*_formatted` / `*_human` only — no view-layer reformatting

## Related

- [crud.md](crud.md) — brief
- [api-routing.md](api-routing.md)
- [architecture.md](architecture.md)
- [list-display-modes.md](list-display-modes.md)
