# Form selects (Select2 + lookups)

Searchable **Select2** fields with the **Bootstrap 5** theme replace native `<select>` on Blade admin forms and the Vue SPA. Large option sets load through paginated lookup APIs instead of bulk list endpoints.

## Lookup API (`/api/v1/lookups/*`)

| Route | Permission | Params | Purpose |
|-------|------------|--------|---------|
| `GET /api/v1/lookups/users` | `viewAny` User (HRBAC-filtered) | `q`, `page`, `per_page` (default 10, max 50), `ids[]` | User multiselect |
| `GET /api/v1/lookups/menus` | `view menu` | `layout`, `q`, `page`, `per_page` (default 10), `exclude`, `ids[]` | Parent / reference |
| `GET /api/v1/lookups/roles` | `view menu` | `q`, `page`, `per_page` (default 10), `ids[]` | Role multiselect |

**Response** (inside standard API envelope):

```json
{
  "status": "success",
  "data": {
    "results": [{ "id": "uuid", "text": "Label" }],
    "pagination": { "more": true }
  }
}
```

- `ids[]` without `q`: hydrate labels for edit forms (no pagination).
- Empty `q`: first page only (10 items by default via `config/lookup.php`) — never dumps the full table.

Implementation: `App\Domain\Identity\Services\Lookup\LookupService`, `App\Domain\Identity\Http\Controllers\API\V1\LookupController`.

## Blade

- Components: `resources/views/adminlte4/components/form/select.blade.php`, `multiselect.blade.php` — props `ajaxUrl`, `ajaxParams`, `select2`, `autosubmit`.
- Assets: `components/partials/select2-assets.blade.php`, `select2-scripts.blade.php`, `resources/js/select2-init.js` (Vite).
- Web proxies (session auth): `GET /lookups/{users,menus,roles}` → `App\Domain\Identity\Http\Controllers\Web\LookupController`.
- AJAX transport uses a per-element generation counter; stale responses are ignored.
- Blade client sends `per_page=10` explicitly in `select2-init.js` (parity with SPA `LOOKUP_PER_PAGE` and `config/lookup.php` default).
- Form components (`select`, `multiselect`) `@push` `select2-scripts` via `@once` — pages that only use those components do **not** need a separate `<x-adminlte4::partials.select2-scripts />` slot. Keep the slot on pages with raw `data-monolith-select2="1"` markup (e.g. approval modals).

Menu create/edit no longer loads all users/menus in `MenuController::renderForm()` — selected values are hydrated via lookup `ids[]` on edit.

## Vue SPA

See [monolith-frontend/docs/form-selects.md](../../monolith-frontend/docs/form-selects.md).

Gateway aggregate `GET /api/v1/navigation/menus/form-options` returns **menu record only** on edit (empty on create). Select options use lookup endpoints proxied through Gateway.

## Tests

- `tests/Domain/Identity/Feature/Http/Controllers/API/V1/LookupTest.php`
- `tests/Feature/Http/Controllers/Web/Select2ScriptsTest.php`

## Troubleshooting

**Blade: native `<select>` still visible**

1. Run `npm run build` (or keep `npm run dev` running) so `Vite::asset('resources/js/select2-init.js')` resolves via `public/build/manifest.json`.
2. Form components (`select`, `multiselect`) auto-push Select2 assets via `@stack('scripts')`; pages with raw `data-monolith-select2="1"` selects still need `<x-adminlte4::partials.select2-scripts />` in the layout `scripts` slot. **Do not use `data-select2`** — it collides with jQuery `.data('select2')` and breaks destroy/init.
3. Init is a **classic** script (not `type="module"`) loaded after jQuery + Select2 CDN; it retries until `jQuery.fn.select2` is available.

**Vue SPA: `select2 is not a function`**

Select2 must be registered on the same jQuery instance Vue imports (`select2Factory(window, jQuery)` in `useSelect2.ts`). Rebuild the SPA after changing composable code.
