# List display modes (query & DataTables)

Every **tabular list/index** endpoint in this application supports two response shapes: the existing **query** JSON envelope and a **DataTables** payload powered by [Yajra Laravel DataTables](https://github.com/yajra/laravel-datatables). Datatable rows include an **`actions`** array: RBAC-filtered button metadata for Bootstrap 4 / jQuery 3 frontends.

Navigation **tree** `GET /api/v1/navigation/menus` is excluded — it returns a hierarchical tree, not a flat table.

---

## Endpoints

| Method | URI | Query mode (default) | Datatable mode |
|--------|-----|----------------------|----------------|
| `GET` | `/api/playground/users` | Playground demo list | Same data + actions |
| `GET` | `/api/v1/users` | User management list (H-RBAC filtered) | Same data + actions |
| `GET` | `/api/v1/navigation/menus/list` | Admin flat menu list | Same data + actions |
| `GET` | `/api/v1/navigation/menus/trashed` | Soft-deleted menus | Same data + actions (+ restore) |

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
      "roles": [{ "name": "Moderator", "level": 3 }]
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
      "roles": [{ "name": "Moderator", "level": 3 }],
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
| `label` | string | Button text |
| `method` | string | HTTP verb (`GET`, `PATCH`, `DELETE`, `POST`, …) |
| `url` | string | Fully qualified API URL for the action |
| `style` | string | Bootstrap 4 variant without `btn-` prefix (`info`, `primary`, `danger`, `success`, …) |
| `icon` | string? | Optional Font Awesome class(es), e.g. `fas fa-eye` |
| `confirm` | string? | When set, the client should show a confirmation dialog before sending the request |

The API returns **metadata only** — no pre-rendered HTML. The client builds buttons and attaches click handlers.

---

## RBAC resolution

Actions are computed **per row** for the authenticated actor:

| Resource | Resolver | Rules |
|----------|----------|-------|
| `/api/v1/users` | `UserRowActionResolver` | Laravel `UserPolicy` + H-RBAC via `RoleService`; no self-delete |
| `/api/playground/users` | `PlaygroundUserRowActionResolver` | All actions on every row (demo, no auth) |
| `/api/v1/navigation/menus/list` | `MenuRowActionResolver` | Spatie permissions: `view menu`, `manage menu` |
| `/api/v1/navigation/menus/trashed` | `MenuTrashedRowActionResolver` | `view menu`, `restore menu` |

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

List and show payloads are shaped by **Eloquent API Resources** (`JsonResource`). Query mode and datatable rows both resolve the same resource so the JSON contract stays in one place. See [CRUD Architecture — API Resources](./crud-architecture.md#api-resources-list-show).

### Adding datatable mode to a new list endpoint

1. Add `InteractsWithListDisplayMode` to the list Form Request (merge `listDisplayModeRules()`).
2. Use `RespondsWithListDisplayMode` in the controller: pass query and datatable closures.
3. Implement a `RowActionResolverInterface` for RBAC-aware actions.
4. Add an Eloquent Resource for the list row shape (as-is with the current JSON fields).
5. Call `DataTableResponseFactory::respond()` with the same iterable as query mode, resolving rows via the resource (`resolveResource(new YourResource($model))`), and the action resolver.

Keep business logic in Services; resolvers only evaluate authorization and build URLs.

---

## Related documentation

- [CRUD Architecture & Repository Pattern](./crud-architecture.md) — list endpoint layering
- [User Management & H-RBAC](./user-management-hrbac.md) — user list filtering and policies
- [Dynamic navigation menu](./navigation-menu.md) — menu list/trashed endpoints
