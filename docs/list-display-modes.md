# List display modes (query & DataTables)

Every **tabular list/index** endpoint in this application supports two response shapes: the existing **query** JSON envelope and a **DataTables** payload powered by [Yajra Laravel DataTables](https://github.com/yajra/laravel-datatables). Datatable rows include an **`actions`** array: RBAC-filtered button metadata for Bootstrap 4 / jQuery 3 frontends.

Navigation **tree** `GET /api/v1/navigation/menus` is excluded — it returns a hierarchical tree, not a flat table.

---

## Endpoints

| Method | URI | Query mode (default) | Datatable mode |
|--------|-----|----------------------|----------------|
| `GET` | `/api/playground/users` | Playground demo list | Same data + actions |
| `GET` | `/api/playground/orders` | Playground master-detail list | Same data + actions |
| `GET` | `/api/v1/users` | User management list (H-RBAC filtered) | Same data + actions |
| `GET` | `/api/v1/navigation/menus/list` | Admin flat menu list | Same data + actions |
| `GET` | `/api/v1/navigation/menus/trashed` | Soft-deleted menus | Same data + actions (+ restore) |

### Web (Blade) datatable routes

The server-rendered admin UI exposes **dedicated web JSON routes** for DataTables AJAX. Each route is a **proxy**: the web controller forwards the DataTables query string to the matching API endpoint (`display_mode=datatable`), re-reads the JWT from the **HttpOnly `auth_token` cookie** as `Authorization: Bearer`, and returns the API's Yajra payload after rewriting per-row `actions` to web route URLs. The web layer holds no list/RBAC logic of its own (see [Web frontend — Server-side DataTables](./web-frontend.md#server-side-datatables-user-list)).

| Method | URI | Route name | Source | Notes |
|--------|-----|------------|--------|-------|
| `GET` | `/users/datatable` | `users.datatable` | **proxy of** `GET /api/v1/users?display_mode=datatable` | Forwards DataTables params + Bearer, then maps the API's allowed-action keys onto web routes (`users.edit` GET, `users.destroy` DELETE) with `bi bi-*` icons |

Web datatable routes always respond in Yajra format (the `draw` query parameter is sent automatically by DataTables). The web layer holds **no** datatable logic of its own: it proxies the API's datatable payload and rewrites only the per-row action URLs (the API still decides *which* actions are allowed — RBAC).

> **Deferred:** realtime DataTable reload on notification ping is **not** implemented.
> See [Activity notifications — Deferred: DataTable live reload](./notifications.md#deferred-datatable-live-reload-not-shipped).

---

## Selecting a display mode

Pass **`display_mode`** on the query string, or rely on DataTables auto-detection.

| Value / signal | Response |
|----------------|----------|
| *(omitted)* or `display_mode=query` | Standard `{ status, message, data }` envelope via `ApiResponse` |
| `display_mode=datatable` | Yajra DataTables JSON (`draw`, `recordsTotal`, `recordsFiltered`, `data`) |
| `draw` present (DataTables AJAX) | Datatable mode (unless `display_mode=query` forces query mode) |

### Query mode (unchanged)

```http
GET /api/v1/users
Authorization: Bearer <token>
```

```json
{
  "status": "success",
  "message": "Daftar pengguna berhasil diambil.",
  "data": [
    {
      "id": "019e8766-857b-7259-a580-a21e1ddfa27e",
      "name": "Jane Doe",
      "email": "jane@example.com",
      "roles": [{ "name": "Moderator", "level": 200 }]
    }
  ]
}
```

### Datatable mode

```http
GET /api/v1/users?display_mode=datatable&draw=1&start=0&length=10
Authorization: Bearer <token>
```

```json
{
  "draw": 1,
  "recordsTotal": 1,
  "recordsFiltered": 1,
  "data": [
    {
      "id": "019e8766-857b-7259-a580-a21e1ddfa27e",
      "name": "Jane Doe",
      "email": "jane@example.com",
      "roles": [{ "name": "Moderator", "level": 200 }],
      "actions": [
        {
          "key": "show",
          "label": "Detail",
          "method": "GET",
          "url": "http://monolith.test/api/v1/users/019e8766-857b-7259-a580-a21e1ddfa27e",
          "style": "info",
          "icon": "fas fa-eye"
        },
        {
          "key": "delete",
          "label": "Hapus",
          "method": "DELETE",
          "url": "http://monolith.test/api/v1/users/019e8766-857b-7259-a580-a21e1ddfa27e",
          "style": "danger",
          "icon": "fas fa-trash",
          "confirm": "Yakin ingin menghapus pengguna ini?"
        }
      ]
    }
  ]
}
```

Standard DataTables request parameters (`draw`, `start`, `length`, `search`, `order`, `columns`) are supported for server-side pagination, search, and sorting.

---

## Action button contract (frontend)

Each entry in `actions` is a plain object meant to be rendered as a Bootstrap 4 button (`btn btn-{style}`) and wired with jQuery 3:

| Field | Type | Description |
|-------|------|-------------|
| `key` | string | Stable identifier (`show`, `edit`, `delete`, `restore`, …) for JS handlers |
| `label` | string | Button text, resolved for the active locale via `__('ui.datatable.*')` |
| `method` | string | HTTP verb (`GET`, `PATCH`, `DELETE`, `POST`, …) |
| `url` | string | Fully qualified API URL for the action |
| `style` | string | Bootstrap 4 variant without `btn-` prefix (`info`, `primary`, `danger`, `success`, …) |
| `icon` | string? | Optional Font Awesome class(es), e.g. `fas fa-eye` |
| `confirm` | string? | When set, the client should show a confirmation dialog before sending the request; also localized via `__('ui.datatable.confirm.*')` |

The API returns **metadata only** — no pre-rendered HTML. The client builds buttons and attaches click handlers. Action `label` and `confirm` text come from `lang/{locale}/ui.php` (`ui.datatable.*`), so they follow the active request locale; the JSON examples above show Indonesian (`id`).

---

## RBAC resolution

Actions are computed **per row** for the authenticated actor:

| Resource | Resolver | Rules |
|----------|----------|-------|
| `/api/v1/users` | `UserRowActionResolver` | Laravel `UserPolicy` + H-RBAC via `RoleService`; no self-delete |
| `/api/playground/users` | `PlaygroundUserRowActionResolver` | All actions on every row (demo, no auth) |
| `/api/playground/orders` | `PlaygroundOrderRowActionResolver` | All actions; edit/delete dropped for `completed` orders (frozen) |
| `/api/v1/navigation/menus/list` | `MenuRowActionResolver` | Spatie permissions: `view menu`, `manage menu` |
| `/api/v1/navigation/menus/trashed` | `MenuTrashedRowActionResolver` | `view menu`, `restore menu` |
| `/users/datatable` (web) | *(proxy)* `UserRowActionResolver` via the API | The API resolves RBAC; the web proxy maps allowed keys to web URLs (`users.edit`, `users.destroy`), dropping `show` |

When the actor lacks permission for an action, that button is omitted from the row's `actions` array.

---

## Implementation layout

```
app/
├── Http/
│   ├── Controllers/Concerns/RespondsWithListDisplayMode.php
│   ├── Requests/Concerns/InteractsWithListDisplayMode.php
│   └── Resources/
│       ├── Concerns/ResolvesResourceArray.php
│       ├── User/UserManagementResource.php
│       ├── Playground/PlaygroundUserResource.php
│       └── Navigation/MenuListResource.php, MenuDetailResource.php, …
└── Support/DataTable/
    ├── ActionButton.php
    ├── DataTableResponseFactory.php
    ├── Contracts/RowActionResolverInterface.php
    └── Resolvers/
        ├── UserRowActionResolver.php
        ├── PlaygroundUserRowActionResolver.php
        ├── MenuRowActionResolver.php
        └── MenuTrashedRowActionResolver.php
```

> The web Blade datatable does **not** have its own resolver — `Web\UserController::datatable()` proxies the API's datatable response and rewrites the action URLs to web routes.

List and show payloads are shaped by **Eloquent API Resources** (`JsonResource`). Query mode and datatable rows both resolve the same resource so the JSON contract stays in one place. See [CRUD Architecture — API Resources](./crud-architecture.md#api-resources-list-show).

### Adding datatable mode to a new list endpoint (API)

1. Add `InteractsWithListDisplayMode` to the list Form Request (merge `listDisplayModeRules()`).
2. Use `RespondsWithListDisplayMode` in the controller: pass query and datatable closures.
3. Implement a `RowActionResolverInterface` for RBAC-aware actions.
4. Add an Eloquent Resource for the list row shape (as-is with the current JSON fields).
5. Call `DataTableResponseFactory::respond()` with the same iterable as query mode, resolving rows via the resource (`resolveResource(new YourResource($model))`), and the action resolver.

Keep business logic in Services; resolvers only evaluate authorization and build URLs.

### Adding a datatable list screen on the web (Blade)

Follow the users index as the reference (`resources/views/adminlte4/users/index.blade.php`). Steps:

1. Register `GET /{resource}/datatable` on `web.php` (before `{resource}/{id}` routes) under `web.auth` (no `permission:` gate — the API authorizes).
2. Add a `datatable()` method on the web controller (using `ConsumesApi`) that **proxies** `GET /api/v1/{resource}?display_mode=datatable` — forward `$request->query()` plus `display_mode=datatable`, then `array_map` over `data[]` to rewrite each row's `actions` keys to web route URLs (`bi bi-*` icons). Return an empty payload on API failure.
3. Change the index view to an empty `<table>` shell; include `<x-adminlte4::partials.datatable-assets />` and `<x-adminlte4::partials.datatable-row-actions />` in `<x-slot:scripts>`.
4. Initialise DataTables with `serverSide: true`, `ajax` pointing at the web datatable route, and define **column headers via `title`** on each `columns[]` entry (single source of truth — no duplicate `<thead>` markup).
5. Add a feature test under `tests/Feature/Http/Controllers/Web/` that `Http::fake()`s the API datatable response and asserts the proxy maps the API action keys onto web URLs.

Full web-layer conventions → [Web frontend — Server-side DataTables](./web-frontend.md#server-side-datatables-user-list).

---

## Related documentation

- [CRUD Architecture & Repository Pattern](./crud-architecture.md) — list endpoint layering
- [User Management & H-RBAC](./user-management-hrbac.md) — user list filtering and policies
- [Dynamic navigation menu](./navigation-menu.md) — menu list/trashed endpoints
- [Web frontend (Blade + AdminLTE 4)](./web-frontend.md) — API-client architecture, `ApiClient`/`ConsumesApi`, datatable proxy, shared partials
