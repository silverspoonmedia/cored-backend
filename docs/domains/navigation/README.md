# Navigation — dynamic menu tree

Self-referential, orderable menus with RBAC, domain-access, product-activation, and per-user visibility. Dedicated repository (not generic CRUD). Code: `app/Domain/Navigation/`.

## Layers

| Piece | Role |
|-------|------|
| `Menu` model | `HasUuids`, Spatie translatable name/description, tree relations |
| `MenuRepository` | Tree build, visibility + `#` mask, two-tier forever cache (snapshot + presented) |
| `MenuService` | Field map, orchestration |
| `MenuController` API | Thin HTTP under `/api/v1/navigation/menus` |
| `jwt.optional` | Guest or authenticated tree on `index` |

Bound in `NavigationServiceProvider`.

## Schema (`menus`)

| Column | Notes |
|--------|-------|
| `id`, `parent_id` | UUID; self-FK cascade |
| `layout` | Shell region: `sidebar`, `navbar` (config `navigation.layouts`) |
| `name`, `description` | JSON translatable |
| `icon`, `route` | Full icon class; **SPA Vue Router symbolic name** (or remaining Laravel web route name), never a URL |
| `type` | `h` header, `p` parent, `c` child |
| `order` | Contiguous within `(layout, parent_id, type)`; NULL for soft-deleted |
| `is_authenticate`, `is_guest_only` | Audience flags |
| Soft deletes | Active and trashed rows share one tree identity space |

Pivots: `menu_roles`, `menu_user_includes`, `menu_user_excludes`. Unique index on `(layout, parent_id, type, order)`.

**Layouts:** independent trees; parent/child must share layout; layout fixed after create.

**Menu route validation:** `StoreMenuRequest` / `UpdateMenuRequest` validate `route` via `App\Support\Navigation\SpaRouteRegistry`. Allowed SPA Vue Router names (not `*.view`) live in `config/spa-routes.php` `menu`; `deny` rejects auth/public-utility names even if Laravel `Route::has()` would pass. Remaining Laravel web route names (share, iPaymu, Discord callback) still pass via `Route::has()` unless denied. Keep the allowlist in sync with `monolith-vue-workers/src/router/index.ts`.

## Visibility (navigation tree)

A node is visible only if **it and every ancestor** pass these rules in order:

1. Authenticated viewer is not in the node's exclude list.
2. If the include list is non-empty, the viewer is authenticated and included.
3. Guest-only node is hidden from authenticated viewers.
4. Authenticate-required node is hidden from guests.
5. **Domain Access gate** — if the node's route maps to a product through `config/products.php` `menu_routes`, `DomainAccessService::canAccess` must allow the viewer. This runs for guests too. Private products allow Root/Admin and holders of the matching `Beta tester {Domain}` role or `access {domain} product` permission. See [Domain Access — Menu visibility](../domain-access/README.md#menu-visibility).
6. **Product activation gate** — for the same mapped product, an authenticated viewer must have an `active` activation. Suspended, expired, pending, or missing activation hides the node. Guests skip this activation check. See [Product Activations — Menu visibility](../product-activations/README.md#menu-visibility-v2).
7. If roles are attached, the viewer must have any attached role; no roles means open to viewers who passed prior rules.

`MenuRepository` evaluates every candidate node and each ancestor in its chain. For each route-bearing node it resolves `ProductRegistry::productKeyByMenuRoute` using the **stored DB route** (never the presentational `#`); product parent routes therefore gate all descendants, including children whose own routes are not mapped. Empty headers and non-navigable parents are pruned; a parent with a route remains a valid leaf when children are all hidden. Validation rejects incompatible audience combinations such as guest-only plus authenticate-required or role restrictions.

**Presentation mask:** after visibility filtering, a `p` node with one or more visible children is emitted with `route = '#'` (expand-only in the sidebar). When every child is pruned, the stored DB route is emitted again so the parent acts as a navigable leaf. Admin flat list / CRUD always expose real DB routes.

## API (`/api/v1/navigation/menus`)

| Method | Path | Middleware | Purpose |
|--------|------|------------|---------|
| GET | `/` | `jwt.optional` | Visible tree (`?layout=`, `?q=`) |
| GET | `/list` | `auth:api`, `view menu` | Admin flat list / DataTable |
| GET | `/trashed` | `auth:api`, `view menu` | Soft-deleted rows |
| POST | `/` | `auth:api`, `manage menu` | Create menu |
| GET | `/{menu}` | `auth:api`, `view menu` | Show menu |
| PUT/PATCH | `/{menu}` | `auth:api`, `manage menu` | Update menu |
| DELETE | `/{menu}` | `auth:api`, `manage menu` | Soft-delete (`?cascade=1`) |
| POST | `/{menu}/restore` | `auth:api`, `restore menu` | Restore subtree |

Permissions come from `RoleSeeder`: `view menu`, `manage menu`, `restore menu`.

SPA menus admin consumes this API directly. Sidebar/navbar trees come from `GET /api/v1/navigation/menus`. Form option lookups use `/api/v1/lookups/*`.

### Request fields (store/update)

Service maps friendly names: `parent` → `parent_id`, `authenticate` → `is_authenticate`, etc. Placement accepts optional `position` + `reference_id` (`before`/`after`/`append`). Structure is `h` root → `p` under `h` → `c` under `p`. Create/update wrap row and pivot synchronization in one transaction.

Translatable `name`/`description` accepts a string or locale map; see [localization](../../platform/localization.md).

## Ordering

Reorder uses two phases: null current slots, then assign contiguous orders. Soft-delete nulls `order` before deletion so active siblings can be compacted safely.

## Caching

Two forever tiers — hot path must not query the `menus` table on a presented-tree hit:

| Key | Contents |
|-----|----------|
| `menus.navigation.snapshot` | All active menu primitives (translations, stored routes, layout/order, role names, include/exclude user IDs). Never hydrated Eloquent. |
| `menus.navigation.tree_epoch` | Integer generation bumped on structural invalidation. |
| `menus.navigation.presented.{epoch}.{layout}.{viewer}` | Final filtered + `#`-masked tree for `guest` or `u:{userId}`. |

On miss, `navigationTree` loads the snapshot (DB only if snapshot also misses), runs visibility + presentation mask, then stores the presented tree.

| Event | Action |
|-------|--------|
| Menu create/update/delete/restore/reorder/seeder; role update/delete; `menu:flush-cache` | Forget snapshot + bump epoch (all presented keys miss) |
| Domain Access visibility upsert | Bump epoch |
| Product activation state change for user U | Forget presented keys for U under the current epoch (both layouts) |
| User role sync that affects menu RBAC (`UserManagementService`, claim assignRole) | Forget presented keys for that user |

`php artisan menu:flush-cache` remains available for manual recovery.

## Authoritative seeding

`MenuSeeder` is the sole canonical navigation definition. Every run force-deletes all active and trashed menu rows; FK cascades remove their pivot rows. It rebuilds both layouts inside one DB transaction and intentionally removes admin-created/custom menus. A failure inside that transaction restores the prior DB tree. After commit, the seeder flushes the navigation snapshot and bumps the presented-tree epoch; no separate cache command is needed.

`MenuSeeder` assumes these role catalogs already exist, in order:

```bash
php artisan db:seed --class=RoleSeeder
php artisan db:seed --class=FileHostingRoleSeeder
php artisan db:seed --class=VtualRoleSeeder
php artisan db:seed --class=MenuSeeder
```

`DatabaseSeeder` already preserves this order. Running `MenuSeeder` alone before those role seeders can silently omit expected `menu_roles` pivots because unknown role names are filtered out.

### Canonical sidebar tree

- **Workspace**
  - Dashboard — `dashboard`
  - Notifications — `notifications.index`
  - Services — `services.index`
- **Products**
  - **File Hosting** — parent route `file-hosting.index`
    - My Files — `file-hosting.index`
    - Trash — `file-hosting.trash`
    - DMCA Blocklist — `file-hosting.blocklist.index`; Root, Admin, Moderator, Management, File Hosting Admin
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
  - **Monitoring** — Root, Admin
    - Activity log — `activity-logs.index`
    - Error log — `error-logs.index`
  - **vTual** — parent route `vtual.orphans.index`; Root, Admin, Moderator
    - Orphan vTubers — `vtual.orphans.index`
    - Platform credentials — `vtual.platform-credentials.index` (Root, Admin)
  - **Settings** — Root, Admin (single hub for all `settings.*` groups)
    - Platform — `settings.platform`
    - File Hosting — `settings.file-hosting`
    - Microstock — `settings.microstock`
    - vTual — `settings.vtual`
    - Notification audiences — `notification-audiences.index`
    - Currencies — `currencies.index`
  - **Navigation** — Root, Admin, Moderator, Management
    - Menu list — `menus.index`
    - Trashed menus — `menus.trashed`

Navbar contains one **Home** shortcut using `dashboard`. File Hosting and vTual parent routes stack Domain Access and activation gates across their branches. Microstock remains discoverable through Services and the File Hosting workspace switcher, with no dynamic-menu node.

No Debug fixture, Microstock node, or vTual Platform API Probe menu node is seeded. Platform API Probe is linked from Platform credentials index (`vtual.platform-credentials.probe`); probe is excluded from dynamic navigation.

Menus are a **UX tree** only — no `domain` / `domain_kind` column. Module registry stays in [`config/domains.php`](../../../config/domains.php); RBAC catalog ownership remains separate (`domain_kind` on roles/permissions). Do not add menu schema merely to mirror those axes.

## Vue-Workers SPA consumer

The sibling Vue-Workers SPA uses `useNavigationHydrate` in menu edit. It batches the menu record and selected parent, role, included-user, and excluded-user lookups through `/api/v1/batch`; failed entries fall back to the existing individual loaders. Shell sidebar and navbar seeds are documented in [vue-workers-spa.md — Invocation budget controls](../../integration/vue-workers-spa.md#invocation-budget-controls).

`NavTree` resolves `node.route + '.view'`, then `node.route`. Menu CRUD validates `route` via `SpaRouteRegistry` against `config/spa-routes.php` (plus any remaining Laravel `Route::has` names). Keep the config list in sync with `MenuSeeder` and `monolith-vue-workers/src/router/index.ts`. DMCA Blocklist is `file-hosting.blocklist.index`; the SPA registers `file-hosting.blocklist.index.view` so the sidebar click works.

## Tests

- `tests/Domain/Navigation/Unit/Repositories/Eloquent/MenuRepositoryTest.php` covers CRUD/ordering plus presentational `#` masking, presented-tree cache hits (no `menus` queries), epoch refresh after menu mutation, and per-user `forgetPresentedTreeFor`.
- `tests/Domain/Navigation/Feature/Database/Seeders/MenuSeederTest.php` locks the exact 44-row sidebar/navbar tree, `en`/`id`/`ja` translations, selected role pivots, absence of Debug/probe/Microstock product nodes, authoritative active-and-trashed rebuild, logical idempotence, and representative role visibility.
- `tests/Domain/Navigation/Feature/Http/Controllers/API/V1/NavigationMenuTest.php` covers menu CRUD including SPA symbolic route acceptance and garbage route rejection.
- `tests/Domain/Navigation/Feature/PrivateDomainMenuVisibilityTest.php` covers public/private product visibility, guests, privileged access, and empty-branch pruning.
- `tests/Domain/Product/Feature/MenuVisibilityTest.php` covers authenticated product-activation visibility; its guest case is a rendering smoke test, not a guest tree assertion.

## Related

- [platform/crud.md](../../platform/crud.md)
- [web/ui-patterns.md](../../web/ui-patterns.md)
- [web/blade-admin.md](../../web/blade-admin.md)
