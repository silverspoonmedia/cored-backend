# List display modes & lookups

Live API contract for tabular index endpoints and paginated select lookups. This is the authoritative spec — it was extracted out of the archived Blade UI notes so the live contract does not live inside a historical document.

## `display_mode`

One list endpoint, two presentations. Mode selection lives in `App\Http\Requests\Concerns\InteractsWithListDisplayMode::wantsDatatable()`.

| Signal | Response |
|--------|----------|
| default / `display_mode=query` with no paging params | `{ status, message, data }` where `data` is a flat `T[]` |
| `display_mode=query` **and** `page` / `per_page` / `search` | `{ status, message, data: { items, pagination } }`. `pagination` is `{ current_page, last_page, per_page, total, has_more }`. Default `per_page` 10, max 100. In-memory search + slice (`App\Support\ListQueryPaginator`); per-domain SQL `LIMIT` is a later wave. |
| `display_mode=datatable`, or `draw` present with no explicit `display_mode` | Yajra DataTables JSON plus a per-row `actions[]` array |

`display_mode=query` wins over a stray `draw` parameter. Validation: `display_mode` must be `query` or `datatable` (`listDisplayModeRules()`).

Query-paging lists: users, invoices, payment-gateways, subscriptions, services, inference providers, inference model-cards, vtual orphans, activity logs, error logs. The navigation **tree** and menus list are excluded — they stay a full dump and are sliced client-side. Helper: `RespondsWithListDisplayMode::respondToQueryList`.

## `datatable` mode has no consumer

**Read this before extending anything below.** The SPA sends **only** `display_mode=query`. `src/composables/useServerDataTable.ts` sends `page` / `per_page` / `search`, and every SPA call site passes `display_mode: 'query'` explicitly. Nothing in `monolith-vue-workers` ever requests `datatable`.

That makes the following a **retained compatibility surface with no current consumer**, kept deliberately rather than deleted:

| Piece | Location |
|-------|----------|
| Response builder | `App\Support\DataTable\DataTableResponseFactory` |
| Row-action contract | `App\Support\DataTable\Contracts\RowActionResolverInterface`, `App\Support\DataTable\ActionButton` |
| Row-action resolvers | `UserRowActionResolver`, `OrphanCreatorRowActionResolver`, `MenuRowActionResolver`, `MenuTrashedRowActionResolver`, `InvoiceRowActionResolver`, `SubscriptionRowActionResolver`, `PaymentGatewayRowActionResolver`, `AiModelCardRowActionResolver`, `InferenceProviderRowActionResolver` |
| Composer dependencies | `yajra/laravel-datatables-oracle`, `yajra/laravel-datatables-fractal` |
| Translations | `lang/{en,id,ja}/web/vtual-orphans.php` exists only because `OrphanCreatorRowActionResolver` reads `datatable.confirm_delete` |

Rules:

- **Do not extend it.** New list screens use query mode only. Do not add a row-action resolver for a new domain.
- **Do not treat it as dead-and-safe-to-strip on a whim** either — removing it is a deliberate task that touches every controller listed above, the resolvers, the Composer deps, the orphan lang file, and three feature tests (`UserManagementDatatableTest`, `NavigationMenuDatatableTest`, `OrphanCreatorApiTest`).
- If you are auditing "why does Monolith still depend on Yajra", this section is the answer.

### Actions contract (datatable mode only)

Each action carries `key`, `label`, `method`, `url`, `style`, `icon`, and an optional `confirm`. RBAC decides which keys appear. URLs point at `/api/v1/*` — the Blade-era rewriting of action URLs onto web routes is gone along with the web layer.

## Lookup API (`/api/v1/lookups/*`)

Searchable selects use paginated lookups, never bulk dumps.

| Route | Permission | Params |
|-------|------------|--------|
| `GET …/users` | User `viewAny` (H-RBAC) | `q`, `page`, `per_page`, `ids[]` |
| `GET …/menus` | `view menu` | + `layout`, `exclude` |
| `GET …/roles` | `view menu` | `q`, `page`, `per_page`, `ids[]` |

Response `data`: `{ results: [{ id, text }], pagination: { more } }`. Passing `ids[]` without `q` hydrates existing edit-form labels. An empty `q` returns the first page only (`config/lookup.php`).

Implementation: Identity `LookupService` / `LookupController`. There are no web proxies — the SPA calls these directly.

Consumer: the SPA `FormSelect2` component wraps jQuery Select2 and unwraps this envelope in `src/utils/select2-lookup.ts` — see [vue-workers-spa.md](../integration/vue-workers-spa.md#formselect2).

## Related

- [crud.md](crud.md) — controller/response conventions and API Resource presentation
- [domains/identity/README.md](../domains/identity/README.md) — lookup permissions
- [domains/navigation/README.md](../domains/navigation/README.md) — why the menu tree opts out of query paging
- [web/ui-patterns.md](../web/ui-patterns.md) — archived Blade UI notes these sections were extracted from
