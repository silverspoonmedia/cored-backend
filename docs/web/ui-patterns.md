# UI patterns — selects & list modes

## Form selects (Select2 + lookups)

Searchable Select2 (Bootstrap 5 theme). Large sets via paginated lookups, not bulk dumps.

### Lookup API (`/api/v1/lookups/*`)

| Route | Permission | Params |
|-------|------------|--------|
| `GET …/users` | User `viewAny` (H-RBAC) | `q`, `page`, `per_page`, `ids[]` |
| `GET …/menus` | `view menu` | + `layout`, `exclude` |
| `GET …/roles` | `view menu` | `q`, `page`, `per_page`, `ids[]` |

Response `data`: `{ results: [{id,text}], pagination: { more } }`.  
`ids[]` without `q` hydrates edit labels. Empty `q` → first page only (`config/lookup.php`).

Implementation: Identity `LookupService` / `LookupController`. Blade proxies: `GET /lookups/{users,menus,roles}`.

Components: `form.select` / `multiselect` (`ajaxUrl`, `select2`). Assets: `select2-init.js` (Vite). Attribute: `data-monolith-select2` (**not** `data-select2` — jQuery collision).

### Troubleshooting

- Native select still showing → `npm run build` / `dev`; ensure select2 scripts after jQuery  

## List display modes (query & DataTables)

Tabular index endpoints support:

| Signal | Response |
|--------|----------|
| default / `display_mode=query` | `{ status, message, data }` |
| `display_mode=datatable` or `draw` present | Yajra JSON + per-row `actions[]` |

Covered lists: v1 users, menus list/trashed, file-hosting lists where applicable. Navigation **tree** excluded.

### Actions contract

Each action: `key`, `label`, `method`, `url`, `style`, `icon`, optional `confirm`. RBAC filters which keys appear; Blade datatable proxies **rewrite URLs** to web routes only.

### Web datatable routes

e.g. `GET /users/datatable` → API `?display_mode=datatable` + Bearer from cookie. No list logic in web layer.

Implementation helpers under `App\Support\DataTable\*`; domain row action resolvers per list.

## Related

- [blade-admin.md](blade-admin.md)
- [domains/identity](../domains/identity/README.md)
- [domains/navigation](../domains/navigation/README.md)
