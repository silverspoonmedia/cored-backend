# List display modes & lookups

Live API contract for tabular index endpoints and paginated select lookups. This is the authoritative spec — it was extracted out of the archived Blade UI notes so the live contract does not live inside a historical document.

## `display_mode`

List indexes speak query mode only. Validation lives in `App\Http\Requests\Concerns\InteractsWithListDisplayMode` (`listDisplayModeRules()`). `display_mode` is optional and, when present, must be `query`. `display_mode=datatable` is **422**. A stray `draw` parameter is ignored.

| Signal | Response |
|--------|----------|
| default / `display_mode=query` with no paging params | `{ status, message, data }` where `data` is a flat `T[]` |
| `display_mode=query` **and** `page` / `per_page` / `search` | `{ status, message, data: { items, pagination } }`. `pagination` is `{ current_page, last_page, per_page, total, has_more }`. Default `per_page` 10, max 100. Most lists still use in-memory search + slice (`App\Support\ListQueryPaginator`). **Users** without `search` paginate in SQL (`UserRepository::paginateVisibleToActorLevel`); `search` still uses the PHP recursive matcher so hits stay unchanged. |

The former Yajra DataTables presentation (`display_mode=datatable`) was removed with the Blade admin. The SPA Vue `DataTable` component is unrelated — it always sends `display_mode=query`.

Query-paging lists: users, invoices, payment-gateways, subscriptions, services, inference providers, inference model-cards, vtual orphans, activity logs, error logs. The navigation **tree** and menus list are excluded — they stay a full dump and are sliced client-side. Helper: `RespondsWithListDisplayMode::respondToQueryList`.

## Query-mode `actions[]` (live SPA contract)

**Source of truth for which row buttons exist.** Query and flat list rows carry `actions[]`. The SPA maps and executes; views must not invent keys.

DTO: `App\Support\ListActions\QueryRowAction`. Builders live under `App\Support\ListActions\Builders\`. Attach with `QueryRowActionAttacher` on resolved Resource arrays (zip with models).

Each item: `key`, `label` (`__()` `en`/`id`/`ja` via `lang/*/list_actions.php` plus existing `ui.*` / `web/*` confirms), `icon` (Bootstrap Icons, no `bi-` prefix), `variant`, plus **exactly one** of:

| Kind | Fields | SPA mapping |
|------|--------|-------------|
| Nav | `route` (Vue parent name from `config/spa-routes.php`, no `.view`) + `params` + optional `query` | `{ name: route + '.view', params, query }` |
| External | `href` + `target` | `<a href>` (`rel="noopener noreferrer"` when `_blank`) |
| Mutate | `method` + `path` (API path the SPA client already calls, not a full `route()` URL) + optional `confirm` `{ title, text }` | existing composable + `confirmDialog` |

Visibility copies **current SPA rules** (example: users always `edit`; `delete` only with `delete user` and not self). `@row-click` uses the same nav action already on the row. Lists that used labeled chrome (currencies, notification audiences, vTual platform “Manage”, hash blocklist) stay labeled — still driven by `row.actions`.

Out of scope: Card header toolbars, Archivd browse `.ar-action-group`, new keys.

## Lookup API (`/api/v1/lookups/*`)

Searchable selects use paginated lookups, never bulk dumps.

| Route | Permission | Params |
|-------|------------|--------|
| `GET …/users` | User `viewAny` (H-RBAC) | `q`, `page`, `per_page`, `ids[]` |
| `GET …/menus` | `view menu` | + `layout`, `exclude` |
| `GET …/roles` | `view menu` | `q`, `page`, `per_page`, `ids[]` |

Response `data` for `users` / `roles`: flat `{ results: [{ id, text }], pagination: { more } }`. Passing `ids[]` without `q` hydrates existing edit-form labels. An empty `q` returns the first page only (`config/lookup.php`).

`menus` search/list response is a **nested tree per root group**: one node per top-level root menu, paginated per root (`pagination.more` = more roots follow), each root carrying its entire subtree as recursive `children` with a `level` per node (0 = root) — `{ results: [{ id, text, level, children: [...] }], pagination: { more } }`. Search (`q`) keeps matching nodes together with their ancestors (a branch shows when it or any descendant matches); `exclude` drops only that root itself. The hydrate path (`ids[]` without `q`) stays flat with `group`/`parent_text`/`level` metadata. Every level is pre-sorted by sibling rank (type h<p<c, then `order` — the Dynamic Menu sidebar comparator).

Implementation: Identity `LookupService` / `LookupController`. There are no web proxies — the SPA calls these directly.

Consumer: the SPA `FormSelect2` component wraps jQuery Select2 and unwraps this envelope in `src/utils/select2-lookup.ts` — see [vue-workers-spa.md](../integration/vue-workers-spa.md#formselect2) and [ui-patterns.md — Nested selects](../../../monolith-vue-workers/docs/ui-patterns.md) for how the nested `menus` tree is flattened into one-level optgroups.

## Related

- [crud.md](crud.md) — controller/response conventions and API Resource presentation
- [integration/vue-workers-spa.md](../integration/vue-workers-spa.md) — SPA mapper (`mapQueryRowActions`); views must not invent keys
- [domains/identity/README.md](../domains/identity/README.md) — lookup permissions; users query-mode `actions[]`
- [domains/navigation/README.md](../domains/navigation/README.md) — why the menu tree opts out of query paging; menus list `actions[]`
- [web/ui-patterns.md](../web/ui-patterns.md) — archived Blade UI notes these sections were extracted from
