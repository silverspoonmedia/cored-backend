# Navigation — dynamic menu tree

Self-referential, orderable menus with RBAC, domain-access, product-activation, and per-user visibility. Dedicated repository (not generic CRUD). Code: `app/Domain/Navigation/`.

## Agent: constructing menus

Open this guide when creating or changing dynamic menus (including `MenuSeeder`). Glob rule: `.cursor/rules/navigation-menus.mdc`.

- Every menu `route` is a SPA Vue Router **symbolic name** in `config/spa-routes.php` (and the SPA router). Never a URL.
- Each product parent route appears in `config/products.php` `menu_routes`. Referenced roles must already be seeded.
- `MenuSeeder` is the sole authoritative tree: Workspace / Products / Administration; submenus not duplicate hubs; contiguous sibling `order`; translations; exact RBAC.
- Product-bound routes on product parents so Domain Access + activation gates protect children.
- Parents may store a real product route; `navigationTree` masks `route` to `#` while children are visible, and restores the stored route when children are all pruned.
- Two-tier forever cache (snapshot + presented). Rebuild active and trashed rows in one transaction; flush cache only after commit. Never seed debug/test/probe nodes.
- Update this guide and every affected domain guide in the same task. Tests lock the tree.

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
| `layout` | Shell region: `sidebar`, `navbar`, `vtual` (config `navigation.layouts`) |
| `name`, `description` | JSON translatable |
| `icon`, `route`, `hash` | Full icon class; **SPA Vue Router symbolic name** (or remaining Laravel web route name), never a URL; `hash` optional in-page anchor (e.g. `#vtubers`) appended client-side |
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

**Layouts:** independent trees; parent/child must share layout; layout fixed after create. `sidebar` is the only layout that enforces a header root (`h`); the flat template layouts (`navbar`, `vtual`) also accept parentless `p` roots.

**Menu route validation:** `StoreMenuRequest` / `UpdateMenuRequest` validate `route` via `App\Support\Navigation\SpaRouteRegistry`. Allowed SPA Vue Router names (not `*.view`) live in `config/spa-routes.php` `menu`; `deny` rejects auth/public-utility names even if Laravel `Route::has()` would pass. Remaining Laravel web route names (share, iPaymu, Discord callback) still pass via `Route::has()` unless denied. Keep the allowlist in sync with `monolith-vue-workers/src/router/index.ts`.

## Visibility (navigation tree)

A node is visible only if **it and every ancestor** pass these rules in order:

1. Authenticated viewer is not in the node's exclude list.
2. If the include list is non-empty, the viewer is authenticated and included.
3. **Audience** — `guest` hides the node from signed-in viewers; `auth` and `role` hide it from guests; `all` never hides.
4. **Domain Access gate** — if the node's route maps to a product through `config/products.php` `menu_routes`, `DomainAccessService::canAccess` must allow the viewer. This runs for guests too. Private products allow Root/Admin and holders of the matching `Beta tester {Domain}` role or `access {domain} product` permission. See [Domain Access — Menu visibility](../domain-access/README.md#menu-visibility).
5. **Product activation gate** — for the same mapped product, an authenticated viewer must have an `active` activation. Suspended, expired, pending, or missing activation hides the node. Guests skip this activation check. See [Product Activations — Menu visibility](../product-activations/README.md#menu-visibility-v2).
6. **vTual management membership gate** — for nodes whose stored route is in `MenuRepository::MANAGEMENT_MEMBER_ROUTES` (`vtual.profiles.index` — the Workspace vTual parent, `vtual.managements.index` — the Managements children, and `vtual.events.index` — the Events child), a viewer without the attached roles is still granted if they hold any `vtual_management_members` row. Guests are never granted. This gate applies **per node**: the Products "Managements" and "Events" children store **no roles at all** and are therefore visible *only* through membership (rule 7's no-roles-fallback is never reached because rule 6 returns first for non-members too — empty roles + rule 6 route = member-only node). The Administration "Managements" child is NOT effectively granted to members: rule 6's route check would grant the node itself, but the Administration parent (`vtual.orphans.index`) is not in the constant, so the ancestor chain fails and the branch stays hidden. Staff reach Managements only through Administration; members only through Products.
7. If roles are attached, the viewer must have any attached role; no roles means open to viewers who passed prior rules. Role pivots only exist for `audience = role`, so this rule is the `role` audience in practice.

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

SPA menus admin consumes this API directly. Sidebar/navbar trees come from `GET /api/v1/navigation/menus`; the Valkivid3 public template fetches its `vtual` tree from the same endpoint (`?layout=vtual`) and renders it via `useVkNavigation`. Admin layout select options are populated dynamically from `GET /api/v1/navigation/menus/layouts`. Form option lookups use `/api/v1/lookups/*` — the menu edit parent/reference selects consume `lookups/menus` as a nested tree per root group, pre-sorted like this sidebar (type rank h<p<c, then `order`).

### Request fields (store/update)

Service maps friendly names: `parent` → `parent_id`, and the rest 1:1 (`audience`, `roles`, `type`, `layout`, …). Placement accepts optional `position` + `reference_id` (`before`/`after`/`append`). Structure is `h` root → `p` under `h` → `c` under `p`; flat template layouts (`navbar`, `vtual`) additionally allow parentless `p` roots — no header wrapper. Create/update wrap row and pivot synchronization in one transaction.

`audience` is `sometimes|enum:MenuAudience` and defaults to `auth` when omitted — the same default as the DB column, the repository and the factory, so no layer invents its own. `roles` is only accepted together with `audience = role`: `role` without roles fails on `roles`, and roles under `all`/`auth`/`guest` fail on `audience`. On update, omitting `audience` keeps the stored value, and moving away from `role` clears the `menu_roles` pivots in the same transaction.

Translatable `name`/`description` accepts a string or a locale map; locale keys are canonicalized (`zh-CN` → `zh_CN`), every supported locale is required, and an empty map means "leave unset" — full rules in [localization — Translatable payload maps](../../platform/localization.md#translatable-payload-maps). The edit form mirrors the canonical keys: `name_translations`/`description_translations` arrive backend-keyed (`zh_CN`) and the SPA `FormTranslatableInput` resolves them per row through `translatableValue()`, so the `zh-CN` row shows the stored Chinese value instead of a blank input.

## Ordering

Reorder uses two phases: null current slots, then assign contiguous orders. Soft-delete nulls `order` before deletion so active siblings can be compacted safely.

## Caching

Two forever tiers — hot path must not query the `menus` table on a presented-tree hit:

| Key | Contents |
|-----|----------|
| `menus.navigation.snapshot` | All active menu primitives (translations, stored routes, layout/order, role names, include/exclude user IDs). Never hydrated Eloquent. |
| `menus.navigation.tree_epoch` | Integer generation bumped on structural invalidation. |
| `menus.navigation.presented.{epoch}.{locale}.{layout}.{viewer}` | Final filtered + `#`-masked tree for `guest` or `u:{userId}`, keyed per locale. |

On miss, `navigationTree` loads the snapshot (DB only if snapshot also misses), runs visibility + presentation mask, then stores the presented tree. Node labels resolve via `TranslationResolver::resolve()`, which reads `app()->getLocale()` set by the `SetLocale` middleware — so each locale gets its own presented tree under the same epoch. Resolution chain is `users.locale → X-SPA-Locale header → locale cookie → default`; see [docs/platform/localization.md](../../platform/localization.md).

| Event | Action |
|-------|--------|
| Menu create/update/delete/restore/reorder/seeder; role update/delete; `menu:flush-cache` | Forget snapshot + bump epoch (all presented keys miss) |
| Domain Access visibility upsert | Bump epoch |
| Product activation state change for user U | Forget presented keys for U under the current epoch (both layouts) |
| User role sync that affects menu RBAC (`UserManagementService`, claim assignRole) | Forget presented keys for that user |
| vTual management membership change for user U (invite accept / cancel-accepted / member remove) | `ManagementService` forgets presented keys for U (`forgetPresentedTreeForUserId`) and fires `NavigationRealtime::userTreeChanged(U, 'membership')` → targeted `.menu.tree.changed` broadcast carrying `target_user_id`; only that viewer's SPA refetches sidebar/navbar. The SPA-side accept flow also calls `refetchNavigation()` directly before redirecting. |
| Claim completion in SPA | SPA refreshes `auth/me`, then refetches sidebar/navbar before opening claimed profile; claim route rejects non-claimable profiles back to read-only profile |

`php artisan menu:flush-cache` remains available for manual recovery.

## Authoritative seeding

`MenuSeeder` is the sole canonical navigation definition. Every run force-deletes all active and trashed menu rows; FK cascades remove their pivot rows. It rebuilds the sidebar, navbar, and vtual layouts inside one DB transaction and intentionally removes admin-created/custom menus. A failure inside that transaction restores the prior DB tree. After commit, the seeder flushes the navigation snapshot and bumps the presented-tree epoch; no separate cache command is needed. Each node's `audience` is derived from its declared `roles` (`$roleNames === [] ? Auth : Role`) unless the node sets `audience` explicitly, so a seeded node can never carry role pivots under a non-role audience; the `vtual` tree's public nodes declare `audience => MenuAudience::All`, and Directory/Contents use the `hash` column (`#vtubers` / `#contents`) for in-page anchors.

`MenuSeeder` assumes these role catalogs already exist, in order:

```bash
php artisan db:seed --class=RoleSeeder
php artisan db:seed --class=ArchivdRoleSeeder
php artisan db:seed --class=VtualRoleSeeder
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
  - **vTual** — parent route `vtual.profiles.index`; Vtual vTuber, Vtual Editor, Vtual Admin (+ any vTual management member via rule 6)
    - My vTuber profiles — `vtual.profiles.index`
    - My claims — `vtual.claims.index`; Vtual vTuber, Vtual Editor, Vtual Admin
    - Managements — `vtual.managements.index`; **no stored roles** — member-only via rule 6 (safe only while the route stays in `MANAGEMENT_MEMBER_ROUTES`)
    - Events — `vtual.events.index`; **no stored roles** — same rule-7 member-only pattern (event submission is an org Owner/Admin power)
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
  - **vTual** — parent route `vtual.orphans.index`; Root, Admin, Moderator
    - Orphan vTubers — `vtual.orphans.index`
    - Claims queue — `vtual.claims.index`; Root, Admin, Moderator
    - Managements — `vtual.managements.index`; Root, Admin (staff manage orgs; membership never grants this branch because the parent route is not in `MANAGEMENT_MEMBER_ROUTES`)
    - Platform credentials — `vtual.platform-credentials.index` (Root, Admin)
  - **Settings** — Root, Admin (single hub for all `settings.*` groups)
    - Platform — `settings.platform`
    - Archivd — `settings.archivd`
    - Microstock — `settings.microstock`
    - vTual — `settings.vtual`
    - Notification audiences — `notification-audiences.index`
    - Currencies — `currencies.index`
  - **Navigation** — Root, Admin, Moderator, Management
    - Menu list — `menus.index`
    - Trashed menus — `menus.trashed`

Two nodes share the `vtual.claims.index` route: **My claims** (Products → vTual) is the owner side of the claims hub, **Claims queue** (Administration → vTual) the staff side. Both carry **stored roles**, so neither reaches the rule-6/rule-7 membership fallback: `vtual.claims.index` is not in `MANAGEMENT_MEMBER_ROUTES`, and an empty-role node it is not. A vTual role holder without `manage any vtual resource` therefore still reaches its own claims, while the staff *tab* inside the single view is what the permission gates — the view, the `claims/pending` endpoint and the `private-vtual.claims` channel all require the same permission. Do not collapse the two nodes into one: the audiences reach the screen from different sidebars.

Navbar contains one **Home** shortcut using `dashboard`. Archivd and vTual parent routes stack Domain Access and activation gates across their branches. Microstock remains discoverable through Services and the Archivd workspace switcher, with no dynamic-menu node.

### Canonical vtual tree (Valkivid3 public template)

Guest-visible nodes, `audience => MenuAudience::All`, no roles — visible to everyone including unauthenticated visitors:

- **Home** — `vtual.public`
- **Directory** — `vtual.public` + `hash: #vtubers`
- **Contents** — `vtual.public` + `hash: #contents`

`hash` is a nullable column appended client-side by the SPA router (`router-link` `:to` includes `hash`), and `router.scrollBehavior` handles the in-page anchor. `hash` also discriminates the active-state marker in the Valkivid3 nav (`isVkNavActive`): because Home/Directory/Contents share the `vtual.public` route, a node with a `hash` is marked active only when `route.hash` matches, and a node without one only when the route has no hash. This keeps the public nav fully dynamic (managed via Menu CRUD) without hardcoding links in the Valkivid3 layout.

No Debug fixture, Microstock node, or vTual Platform API Probe menu node is seeded. Platform API Probe is linked from Platform credentials index (`vtual.platform-credentials.probe`); probe is excluded from dynamic navigation.

Menus are a **UX tree** only — no `domain` / `domain_kind` column. Module registry stays in [`config/domains.php`](../../../config/domains.php); RBAC catalog ownership remains separate (`domain_kind` on roles/permissions). Do not add menu schema merely to mirror those axes.

## Vue-Workers SPA consumer

The sibling Vue-Workers SPA uses `useNavigationHydrate` in menu edit. It batches the menu record and selected parent, role, included-user, and excluded-user lookups through `/api/v1/batch`. Failure handling is three-way, not a silent empty. A batch-level/transport failure falls back to the individual loaders (`fetchOne` + `useLookups`). A sub-entry answering 401/403/429 raises `TerminalBatchError` and is deliberately **not** retried standalone — a 401 would trip the global force-logout — so it reaches the view's error path instead. Any other non-200 (422/500/504) is recorded per key in `failedLookups`: the select renders empty for **display only**, the view warns the admin (`web.menus.hydrate_partial_warning`) and skips pruning that field, and because `MenuService::update` only touches a relationship when its key is present the stored pivots survive. That is why the multi-value `ids[]` shape must be exact: pass an array (`{ 'ids[]': menu.role_ids }`) so the SPA sends repeated params (`ids[]=a&ids[]=b`); a comma-joined string fails `ids.* => uuid` (422) and now surfaces as that warning plus an untouched field, never as a silent wipe. For ajax-backed selects the pre-selected rows arrive **after** mount, so `FormSelect2` appends late `selectedOptions` in place and re-applies the value (no select2 re-init, which would drop the ajax adapter state), and `applyValue()` suppresses its own `change.select2` echo so a select whose `<option>` rows are not hydrated yet can never clear its parent — see [ui-patterns.md — Select2 values must survive a failed hydrate](../../../monolith-vue-workers/docs/ui-patterns.md#select2-values-must-survive-a-failed-hydrate). Shell sidebar and navbar seeds are documented in [vue-workers-spa.md — Invocation budget controls](../../integration/vue-workers-spa.md#invocation-budget-controls). Chrome must not `GET navigation/menus` (or start Echo) in parallel with that shell batch: `AppLayout` / `AppNavbar` join `hydrateShell()`, skip GET when `isTreeLoaded` or `isShellFallbackBlocked`, and start Echo only after the batch settles — a cookie-jar race on this guest-safe `jwt.optional` GET shows in DevTools as CORS and vanishes on hard reload.

`NavTree` resolves `node.route + '.view'`, then `node.route`. Leaf nodes render the resolved `href` via `router.resolve` so the destination URL is visible on hover; parent nodes stay `#` while they have visible children (expand-only). Menu CRUD validates `route` via `SpaRouteRegistry` against `config/spa-routes.php` (plus any remaining Laravel `Route::has` names). Keep the config list in sync with `MenuSeeder` and `monolith-vue-workers/src/router/index.ts`. DMCA Blocklist is `archivd.blocklist.index`; the SPA registers `archivd.blocklist.index.view` so the sidebar click works.

## Realtime

Menu mutations (create/update/delete/trash/restore) ping the `navigation.menus` private channel (permission `view menu`) as `.menu.tree.changed` via `NavigationRealtime`; targeted user-scoped invalidations go to the personal user channel with `target_user_id`. SPA: `useMenusRealtime.ts` + `useNavigation.ts` refetch the tree.

## Tests

- `tests/Domain/Navigation/Unit/Repositories/Eloquent/MenuRepositoryTest.php` covers CRUD/ordering plus presentational `#` masking, presented-tree cache hits (no `menus` queries), epoch refresh after menu mutation, and per-user `forgetPresentedTreeFor`.
- `tests/Domain/Navigation/Feature/Database/Seeders/MenuSeederTest.php` locks the exact 56-row sidebar/navbar/vtual tree, all-seven-locale (`en`/`id`/`ja`/`ko`/`zh_CN`/`ar`/`ru`) `name` translations, selected role pivots (both `vtual.managements.index` rows: Products member-only `[]`, Administration `['Admin','Root']`; `vtual.events.index` Products `[]`; both `vtual.claims.index` rows carry stored roles — Administration `['Admin','Moderator','Root']`, Products `['Vtual Admin','Vtual Editor','Vtual vTuber']`), vtual `hash` anchors, absence of Debug/probe/Microstock product nodes, authoritative active-and-trashed rebuild, logical idempotence, and representative role visibility.
- `tests/Domain/Navigation/Feature/Database/Seeders/MenuSeederConformanceTest.php` asserts every seeded row satisfies the same rules the Store/Update FormRequests enforce (type enum, layout enum, h→p→c hierarchy with flat-layout `p` roots, parent-layout match, route via `SpaRouteRegistry`, hash/icon/name max lengths, all-locale non-empty `name`, audience validity + role-pivot invariant, contiguous sibling order) so editing a seeded node never trips validation.
- `tests/Domain/Navigation/Unit/Enums/MenuAudienceTest.php` locks the four audience cases and their predicates (`allowsGuests`, `requiresUser`, `usesRoles`, `isGuestOnly`).
- `tests/Domain/Navigation/Feature/Http/Controllers/API/V1/NavigationMenuTest.php` covers menu CRUD including SPA symbolic route acceptance and garbage route rejection, the audience default/round-trip and both audience error paths (`role` without roles, roles outside `role`), pivot clearing when the audience leaves `role`, plus `zh-CN` alias acceptance on translatable maps (store/update, mixed-key merge, unknown-locale rejection).
- `tests/Domain/Navigation/Feature/Http/Controllers/API/V1/NavigationMenuLayoutTest.php` covers layout scoping (sidebar/navbar/vtual), parent-layout mismatch rejection, guest fetch of the vtual tree, and the `layouts` endpoint exposing the configured identifiers.
- `tests/Domain/Navigation/Unit/Support/MenuLayoutTest.php` asserts the three configured layouts (`sidebar`/`navbar`/`vtual`) and the default.
- `tests/Domain/Navigation/Feature/PrivateDomainMenuVisibilityTest.php` covers public/private product visibility, guests, privileged access, and empty-branch pruning.
- `tests/Domain/Product/Feature/MenuVisibilityTest.php` covers authenticated product-activation visibility; its guest case is a rendering smoke test, not a guest tree assertion.
- `tests/Domain/Navigation/Feature/ManagementMemberMenuVisibilityTest.php` covers the vTual management-membership visibility rule (rule 6) under the dual placement: members see the Products Managements node without staff roles, non-members and guests see neither, staff without membership see only the Administration node, staff with membership see both, and membership loss removes only the Products grant.
- `tests/Domain/Vtual/Feature/Http/Controllers/API/V1/ManagementMembershipNavigationTest.php` locks `ManagementService` membership mutations (accept invite, cancel accepted invite, remove member): presented-tree invalidation for the affected viewer plus targeted `MenuTreeChanged` broadcast with `target_user_id`.

## Related

- [platform/crud.md](../../platform/crud.md)
- [platform/list-display-modes.md](../../platform/list-display-modes.md) — why the menu tree opts out of query paging
- [web/blade-admin.md](../../web/blade-admin.md)
