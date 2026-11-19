# Navigation — deep dive

Schema, visibility rules, request fields, canonical trees, caching, SPA consumer, tests. Invariants: [.cursor/rules/navigation-menus.mdc](../../../.cursor/rules/navigation-menus.mdc). Brief: [README.md](README.md).

## Layers

| Piece | Role |
|-------|------|
| `Menu` model | `HasUuids`, Spatie translatable name/description, tree relations |
| `MenuRepository` | Tree build, visibility + `#` mask, two-tier forever cache (snapshot + presented) |
| `MenuService` | Field map (`parent` → `parent_id`, …), locale-map canonicalization (`canonicalizeLocaleMap()`), orchestration |
| `MenuController` API | Thin HTTP under `/api/v1/navigation/menus` |
| `jwt.optional` | Guest or authenticated tree on `index` |

Bound in `NavigationServiceProvider`.

## Schema (`menus`)

| Column | Notes |
|--------|-------|
| `id`, `parent_id` | UUID; self-FK cascade |
| `layout` | Shell region: `sidebar`, `navbar` (config `navigation.layouts`) |
| `name`, `description` | JSON translatable |
| `icon`, `route`, `hash` | Full icon class; **SPA Vue Router symbolic name** (or remaining Laravel web route name), never a URL; `hash` optional in-page anchor (e.g. `#overview`) appended client-side |
| `type` | `h` header, `p` parent, `c` child |
| `order` | Contiguous within `(layout, parent_id, type)`; NULL for soft-deleted |
| `audience` | Who may see the node: `all`, `auth` (default), `role`, `guest` |
| Soft deletes | Active and trashed rows share one tree identity space |

Pivots: `menu_roles`, `menu_user_includes`, `menu_user_excludes`. Unique index on `(layout, parent_id, type, order)`.

**Audience** is the single visibility axis; `menu_roles` only exists for `role`:

| `audience` | Meaning | `menu_roles` |
|------------|---------|--------------|
| `all` | Guests and signed-in users | must be empty |
| `auth` | Any signed-in user (default) | must be empty |
| `role` | Signed-in user holding any attached role | **required**, at least one |
| `guest` | Guests only | must be empty |

Validation rejects every illegal combination (`role` without roles → error on `roles`; roles under any other audience → error on `audience`), and `MenuService` clears `menu_roles` as soon as the stored audience is not `role`, so a pivot can never contradict the audience.

**Layouts:** independent trees; parent/child must share layout; layout fixed after create. `sidebar` is the only layout that enforces a header root (`h`); the flat template layout (`navbar`) also accepts parentless `p` roots.

**Menu route validation:** `StoreMenuRequest` / `UpdateMenuRequest` validate `route` via `App\Support\Navigation\SpaRouteRegistry`. Allowed SPA Vue Router names (not `*.view`) live in `config/spa-routes.php` `menu`; `deny` rejects auth/public-utility names even if Laravel `Route::has()` would pass. Remaining Laravel web route names (share, iPaymu, Discord callback) still pass via `Route::has()` unless denied. Keep the allowlist in sync with `monolith-vue-workers/src/router/index.ts`.

## Visibility (navigation tree)

A node is visible only if **it and every ancestor** pass these rules in order:

1. Authenticated viewer is not in the node's exclude list.
2. If the include list is non-empty, the viewer is authenticated and included.
3. **Audience** — `guest` hides the node from signed-in viewers; `auth` and `role` hide it from guests; `all` never hides.
4. **Domain Access gate** — if the node's route maps to a product through `config/products.php` `menu_routes`, `DomainAccessService::canAccess` must allow the viewer. This runs for guests too. Private products allow Root/Admin and holders of the matching `Beta tester {Domain}` role or `access {domain} product` permission. See [Domain Access — menu visibility](../domain-access/README.md).
5. **Product activation gate** — for the same mapped product, an authenticated viewer must have an `active` activation. Suspended, expired, pending, or missing activation hides the node. Guests skip this activation check. See [Product Activations — menu visibility](../product-activations/reference.md).
6. If roles are attached, the viewer must have any attached role; no roles means open to viewers who passed prior rules. Role pivots only exist for `audience = role`, so this rule is the `role` audience in practice.

`MenuRepository` evaluates every candidate node and each ancestor in its chain. For each route-bearing node it resolves `ProductRegistry::productKeyByMenuRoute` using the **stored DB route** (never the presentational `#`); product parent routes therefore gate all descendants, including children whose own routes are not mapped. Empty headers and non-navigable parents are pruned; a parent with a route remains a valid leaf when children are all hidden. Validation rejects illegal audience/role combinations (see the audience table above).

**Presentation mask:** after visibility filtering, a `p` node with one or more visible children is emitted with `route = '#'` (expand-only in the sidebar). When every child is pruned, the stored DB route is emitted again so the parent acts as a navigable leaf. Admin flat list / CRUD always expose real DB routes.

## API (`/api/v1/navigation/menus`)

| Method | Path | Middleware | Purpose |
|--------|------|------------|---------|
| GET | `/` | `jwt.optional` | Visible tree (`?layout=`, `?q=`) |
| GET | `/layouts` | `jwt.optional` | Configured layout identifiers (config-driven) |
| GET | `/list` | `auth:api`, `view menu` | Admin flat list / plain table. Rows include query-mode `actions[]` (`edit` / `delete` with layout query; SPA `MenusView` maps them — does not invent keys) |
| GET | `/trashed` | `auth:api`, `view menu` | Soft-deleted rows. Same `actions[]` contract (`restore` / `force-delete`) for `MenusTrashedView` |
| POST | `/` | `auth:api`, `manage menu` | Create menu |
| GET | `/{menu}` | `auth:api`, `view menu` | Show menu |
| PUT/PATCH | `/{menu}` | `auth:api`, `manage menu` | Update menu |
| DELETE | `/{menu}` | `auth:api`, `manage menu` | Soft-delete (`?cascade=1`) |
| POST | `/{menu}/restore` | `auth:api`, `restore menu` | Restore subtree |

Permissions come from `RoleSeeder`: `view menu`, `manage menu`, `restore menu`.

SPA menus admin consumes this API directly. Sidebar/navbar trees come from `GET /api/v1/navigation/menus`. Admin layout select options are populated dynamically from `GET /api/v1/navigation/menus/layouts`. Form option lookups use `/api/v1/lookups/*` (identity) and `/api/v1/navigation/lookups/*` (menus) — the menu edit parent/reference selects consume `navigation/lookups/menus` as a nested tree per root group, pre-sorted like this sidebar (type rank h<p<c, then `order`).

### Request fields (store/update)

Service maps friendly names: `parent` → `parent_id`, and the rest 1:1 (`audience`, `roles`, `type`, `layout`, …). Placement accepts optional `position` + `reference_id` (`before`/`after`/`append`). Structure is `h` root → `p` under `h` → `c` under `p`; the flat template layout (`navbar`) additionally allows parentless `p` roots — no header wrapper. Create/update wrap row and pivot synchronization in one transaction.

`audience` is `sometimes|enum:MenuAudience` and defaults to `auth` when omitted — the same default as the DB column, the repository and the factory, so no layer invents its own. `roles` is only accepted together with `audience = role`: `role` without roles fails on `roles`, and roles under `all`/`auth`/`guest` fail on `audience`. On update, omitting `audience` keeps the stored value, and moving away from `role` clears the `menu_roles` pivots in the same transaction.

Translatable `name`/`description` accepts a string or a locale map; locale keys are canonicalized (`zh-CN` → `zh_CN`), every supported locale is required, and an empty map means "leave unset" — full rules in [localization — translatable payload maps](../../platform/localization.md). The edit form mirrors the canonical keys: `name_translations`/`description_translations` arrive backend-keyed (`zh_CN`) and the SPA `FormTranslatableInput` resolves them per row through `translatableValue()`, so the `zh-CN` row shows the stored Chinese value instead of a blank input.

## Ordering

Reorder uses two phases: null current slots, then assign contiguous orders. Soft-delete nulls `order` before deletion so active siblings can be compacted safely.

## Caching

Two forever tiers — hot path must not query the `menus` table on a presented-tree hit:

| Key | Contents |
|-----|----------|
| `menus.navigation.snapshot` | All active menu primitives (translations, stored routes, layout/order, role names, include/exclude user IDs). Never hydrated Eloquent. |
| `menus.navigation.tree_epoch` | Integer generation bumped on structural invalidation. |
| `menus.navigation.presented.{epoch}.{locale}.{layout}.{viewer}` | Final filtered + `#`-masked tree for `guest` or `u:{userId}`, keyed per locale. |

On miss, `navigationTree` loads the snapshot (DB only if snapshot also misses), runs visibility + presentation mask, then stores the presented tree. Node labels resolve via `TranslationResolver::resolve()`, which reads `app()->getLocale()` set by the `SetLocale` middleware — so each locale gets its own presented tree under the same epoch. Resolution chain is `users.locale → X-SPA-Locale header → locale cookie → default`; see [platform/localization.md](../../platform/localization.md).

| Event | Action |
|-------|--------|
| Menu create/update/delete/restore/reorder/seeder; role update/delete; `menu:flush-cache` | Forget snapshot + bump epoch (all presented keys miss) |
| Domain Access visibility upsert | Bump epoch |
| Product activation state change for user U | Forget presented keys for U under the current epoch (both layouts) |
| User role sync that affects menu RBAC (`UserManagementService`) | Forget presented keys for that user |

`php artisan menu:flush-cache` remains available for manual recovery.

## Authoritative seeding

`MenuSeeder` is the sole canonical navigation definition. Every run force-deletes all active and trashed menu rows; FK cascades remove their pivot rows. It rebuilds the sidebar and navbar layouts inside one DB transaction and intentionally removes admin-created/custom menus. A failure inside that transaction restores the prior DB tree. After commit, the seeder flushes the navigation snapshot and bumps the presented-tree epoch; no separate cache command is needed. Each node's `audience` is derived from its declared `roles` (`$roleNames === [] ? Auth : Role`) unless the node sets `audience` explicitly, so a seeded node can never carry role pivots under a non-role audience.

`MenuSeeder` assumes these role catalogs already exist, in order:

```bash
php artisan db:seed --class=RoleSeeder
php artisan db:seed --class=ArchivdRoleSeeder
php artisan db:seed --class=MenuSeeder
```

`DatabaseSeeder` already preserves this order. Running `MenuSeeder` alone before those role seeders now **fails fast**: `idsForRoles()` throws a `RuntimeException` naming the missing roles instead of silently dropping the node's RBAC.

### Canonical sidebar tree

- **Workspace**
  - Dashboard — `dashboard`
  - Notifications — `notifications.index`
  - Services — `services.index`
- **Products**
  - **Archivd** — parent route `archivd.index`
    - My Files — `archivd.index`
    - Trash — `archivd.trash`
    - DMCA Blocklist — `archivd.blocklist.index`; Root, Admin, Moderator, Management, Archivd Admin
- **Administration**
  - **Access control** — Root, Admin, Moderator, Management
    - User management — `users.index`
    - Product activations — `product-activations.index`
    - Product domain access — `domain-access.index`; Root, Admin
  - **Payments** — Root, Admin, Moderator, Management
    - Payment income — `payments.income.index`
    - Invoices — `invoices.index`
    - Subscriptions — `subscriptions.index`
    - Payment channels — `payment-channels.index`
    - Payment gateways — `payment-gateways.index`
  - **Inference** — Root, Admin, Moderator, Management
    - Model catalog — `inference.model-cards.index`
    - Providers — `inference.providers.index` (show = model rate grid)
    - Usage events — `inference.usage-events.index`
  - **AI connections** — `ai.connections.index`; Root, Admin, Moderator, Management (`view ai connections` / `manage ai connections`)
  - **Monitoring** — Root, Admin
    - Activity log — `activity-logs.index`
    - Error log — `error-logs.index`
  - **Settings** — Root, Admin (single hub for all `settings.*` groups)
    - Platform — `settings.platform`
    - Archivd — `settings.archivd`
    - Microstock — `settings.microstock`
    - Notification audiences — `notification-audiences.index`
    - Currencies — `currencies.index`
  - **Navigation** — Root, Admin, Moderator, Management
    - Menu list — `menus.index`
    - Trashed menus — `menus.trashed`

Navbar contains one **Home** shortcut using `dashboard`. Archivd stacks Domain Access and activation gates across its branches. Microstock remains discoverable through Services and the Archivd workspace switcher, with no dynamic-menu node.

No Debug fixture or Microstock product node is seeded. `hash` is a nullable column appended client-side by the SPA router (`router-link` `:to` includes `hash`), and `router.scrollBehavior` handles the in-page anchor.

Menus are a **UX tree** only — no `domain` / `domain_kind` column. Module registry stays in [`config/domains.php`](../../../config/domains.php); RBAC catalog ownership remains separate (`domain_kind` on roles/permissions). Do not add menu schema merely to mirror those axes.

## Vue-Workers SPA consumer

The sibling Vue-Workers SPA uses `useNavigationHydrate` in menu edit. It batches the menu record and selected parent, role, included-user, and excluded-user lookups through `/api/v1/batch`. Failure handling is three-way, not a silent empty. A batch-level/transport failure falls back to the individual loaders (`fetchOne` + `useLookups`). A sub-entry answering 401/403/429 raises `TerminalBatchError` and is deliberately **not** retried standalone — a 401 would trip the global force-logout — so it reaches the view's error path instead. Any other non-200 (422/500/504) is recorded per key in `failedLookups`: the select renders empty for **display only**, the view warns the admin (`web.menus.hydrate_partial_warning`) and skips pruning that field, and because `MenuService::update` only touches a relationship when its key is present the stored pivots survive. That is why the multi-value `ids[]` shape must be exact: pass an array (`{ 'ids[]': menu.role_ids }`) so the SPA sends repeated params (`ids[]=a&ids[]=b`); a comma-joined string fails `ids.* => uuid` (422) and now surfaces as that warning plus an untouched field, never as a silent wipe. For ajax-backed selects the pre-selected rows arrive **after** mount, so `FormSelect2` appends late `selectedOptions` in place and re-applies the value (no select2 re-init, which would drop the ajax adapter state), and `applyValue()` suppresses its own `change.select2` echo so a select whose `<option>` rows are not hydrated yet can never clear its parent — see the SPA rule `monolith-vue-workers/.cursor/rules/ui-patterns.mdc`. Shell sidebar and navbar seeds are documented in [vue-workers-spa.md — invocation budget controls](../../integration/vue-workers-spa.md). Chrome must not `GET navigation/menus` (or start Echo) in parallel with that shell batch: `AppLayout` / `AppNavbar` join `hydrateShell()`, skip GET when `isTreeLoaded` or `isShellFallbackBlocked`, and start Echo only after the batch settles — a cookie-jar race on this guest-safe `jwt.optional` GET shows in DevTools as CORS and vanishes on hard reload.

`NavTree` resolves `node.route + '.view'`, then `node.route`. Leaf nodes render the resolved `href` via `router.resolve` so the destination URL is visible on hover; parent nodes stay `#` while they have visible children (expand-only). Menu CRUD validates `route` via `SpaRouteRegistry` against `config/spa-routes.php` (plus any remaining Laravel `Route::has` names). Keep the config list in sync with `MenuSeeder` and `monolith-vue-workers/src/router/index.ts`. DMCA Blocklist is `archivd.blocklist.index`; the SPA registers `archivd.blocklist.index.view` so the sidebar click works.

## Realtime

Menu mutations (create/update/delete/trash/restore) ping the `navigation.menus` private channel (permission `view menu`) as `.menu.tree.changed` via `NavigationRealtime`; targeted user-scoped invalidations go to the personal user channel with `target_user_id`. SPA: `useMenusRealtime.ts` + `useNavigation.ts` refetch the tree.

## Tests

- `packages/navigation/tests/Unit/Repositories/Eloquent/MenuRepositoryTest.php` covers CRUD/ordering plus presentational `#` masking, presented-tree cache hits (no `menus` queries), epoch refresh after menu mutation, and per-user `forgetPresentedTreeFor`.
- `packages/navigation/tests/Feature/Database/Seeders/MenuSeederTest.php` locks the exact 38-row sidebar/navbar tree, all-seven-locale (`en`/`id`/`ja`/`ko`/`zh_CN`/`ar`/`ru`) `name` translations, selected role pivots, absence of Debug/probe/Microstock product nodes, authoritative active-and-trashed rebuild, logical idempotence, and representative role visibility.
- `packages/navigation/tests/Feature/Database/Seeders/MenuSeederConformanceTest.php` asserts every seeded row satisfies the same rules the Store/Update FormRequests enforce (type enum, layout enum, h→p→c hierarchy with flat-layout `p` roots, parent-layout match, route via `SpaRouteRegistry`, hash/icon/name max lengths, all-locale non-empty `name`, audience validity + role-pivot invariant, contiguous sibling order) so editing a seeded node never trips validation.
- `packages/navigation/tests/Unit/Enums/MenuAudienceTest.php` locks the four audience cases and their predicates (`allowsGuests`, `requiresUser`, `usesRoles`, `isGuestOnly`).
- `packages/navigation/tests/Feature/Http/Controllers/API/V1/NavigationMenuTest.php` covers menu CRUD including SPA symbolic route acceptance and garbage route rejection, the audience default/round-trip and both audience error paths (`role` without roles, roles outside `role`), pivot clearing when the audience leaves `role`, plus `zh-CN` alias acceptance on translatable maps (store/update, mixed-key merge, unknown-locale rejection).
- `packages/navigation/tests/Feature/Http/Controllers/API/V1/NavigationMenuLayoutTest.php` covers layout scoping (sidebar/navbar), parent-layout mismatch rejection, and the `layouts` endpoint exposing the configured identifiers.
- `packages/navigation/tests/Unit/Support/MenuLayoutTest.php` asserts the two configured layouts (`sidebar`/`navbar`) and the default.
- `packages/navigation/tests/Feature/PrivateDomainMenuVisibilityTest.php` covers public/private product visibility, guests, privileged access, and empty-branch pruning.
- `tests/Domain/Product/Feature/MenuVisibilityTest.php` covers authenticated product-activation visibility; its guest case is a rendering smoke test, not a guest tree assertion.
