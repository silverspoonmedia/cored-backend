# Dynamic Navigation Menu (Hierarchy + RBAC)

A self-referential, orderable menu tree served two ways:

- **Navigation tree** (`index`) — auth-aware tree filtered for the current viewer (guest or authenticated). Used to render sidebars/menus.
- **Admin list** (`list`) — hierarchically sorted flat list with the raw RBAC assignments, for management screens.

All IDs are **UUID v7** end to end (see [User primary keys (UUID v7)](./user-uuid-v7.md)). The feature uses a **dedicated repository** because the queries are non-trivial (tree assembly, per-viewer visibility, transactional reordering). See [CRUD Architecture & Repository Pattern](./crud-architecture.md).

---

## Layers

| Layer | File | Responsibility |
|-------|------|----------------|
| Migration | `database/migrations/2026_06_09_000001_create_menu_navigation_tables.php` | `menus` tree + `menu_roles`, `menu_user_includes`, `menu_user_excludes` pivots |
| Model | `app/Models/Menu.php` | `HasUuids`, relations, scopes, `toNavigationArray()` |
| Contract | `app/Repositories/Contracts/MenuRepositoryInterface.php` | Repository contract |
| Repository | `app/Repositories/Eloquent/MenuRepository.php` | Tree build, viewer filtering, ordering transactions, pivot sync |
| Service | `app/Services/Navigation/MenuService.php` | Maps request fields → columns, orchestrates repository |
| Presenter | `app/Support/Navigation/MenuPresenter.php` | Admin/detail JSON shape |
| Controller | `app/Http/Controllers/API/V1/Navigation/MenuController.php` | Thin HTTP layer |
| Requests | `app/Http/Requests/Navigation/{Store,Update,IndexMenuList}MenuRequest.php` | Input validation |
| Middleware | `app/Http/Middleware/OptionalJwtAuthenticate.php` (alias `jwt.optional`) | Optional auth for `index` |
| Exception | `app/Exceptions/Navigation/InvalidMenuPositionException.php` | 422 for bad reordering input |
| Exception | `app/Exceptions/Navigation/MenuHasChildrenException.php` | 409 when deleting a non-empty branch without `cascade` |
| Exception | `app/Exceptions/Navigation/InvalidMenuParentException.php` | 422 for invalid parent (cycle / restoring under a trashed parent) |
| Seeder | `database/seeders/MenuSeeder.php` | Sample header → parent → children |

The repository is bound in `App\Providers\AppServiceProvider`:

```php
$this->app->bind(MenuRepositoryInterface::class, MenuRepository::class);
```

---

## Database schema

All keys are UUID. Because `users` and `roles` exist before this migration runs, every foreign key (including `user_id` on the pivots) is constrained directly in **one** migration — no deferred follow-up migration is needed.

### `menus`

| Column | Type | Notes |
|--------|------|-------|
| `id` | uuid PK | |
| `parent_id` | uuid, nullable | self-FK → `menus.id`, `cascadeOnDelete` |
| `name` | string | |
| `icon` | string, nullable | |
| `route` | string, nullable | a **registered Laravel route name**, not a URL |
| `type` | char(1) | `h` header, `p` parent, `c` child |
| `order` | unsigned int, **nullable** | sibling order **within a `(parent_id, type)` group**; active rows hold a contiguous `1..N`, soft-deleted/transient rows are parked at `NULL` |
| `is_authenticate` | bool, nullable | requires an authenticated viewer when `true` |
| `is_guest_only` | bool, nullable | hidden from authenticated viewers when `true` |
| `deleted_at` | timestamp, nullable | soft delete |
| `created_at` / `updated_at` | timestamps | |

**Unique index** on `(parent_id, type, order)` enforces the ordering invariant at the database level. `order` is **nullable on purpose**: SQL treats multiple `NULL`s as distinct, so soft-deleted rows (and rows mid-reorder) parked at `NULL` never collide with active siblings. This is why reordering runs in two phases (see [Ordering model](#ordering-model)) and why a soft delete first nulls the row's `order` to free its slot.

### Pivots

- `menu_roles` — (`menu_id`, `role_id`), composite PK, both FKs cascade.
- `menu_user_includes` / `menu_user_excludes` — (`menu_id`, `user_id`), composite PK, both FKs cascade.

---

## Visibility rules (navigation tree)

`index` resolves the viewer via the `jwt.optional` middleware (token if present, otherwise guest), then includes a node only if **the node and every ancestor** pass all of:

1. Viewer is **not** in the node's `excludedUsers` (blacklist wins).
2. If `includedUsers` is non-empty, the viewer must be in it (explicit allowlist).
3. `is_guest_only` ⇒ hidden from authenticated viewers.
4. `is_authenticate` ⇒ hidden from guests.
5. If the node has `api`-guard roles attached, the viewer must hold at least one (`hasAnyRole`). No roles attached ⇒ visible to all who passed the above.

Headers (`h`) and parents (`p`) that end up with no visible children are pruned, so empty branches never reach the client. Siblings are sorted by type rank (`h` → `p` → `c`) then `order`.

**Contradictory flags are rejected at validation time** (they would make a menu visible to nobody): `guest_only + authenticate` (mutually exclusive audiences) and `guest_only + roles` (roles require an authenticated viewer).

---

## API

Base prefix `/api/v1`, route name prefix `api.v1.navigation.menus.`.

| Method | URI | Name | Middleware | Action |
|--------|-----|------|------------|--------|
| GET | `/api/v1/navigation/menus` | `…menus.index` | `jwt.optional` | Filtered navigation tree |
| GET | `/api/v1/navigation/menus/list` | `…menus.list` | `auth:api`, `permission:view menu` | Admin flat list (`?load_type=h\|p\|c`, optional `?display_mode=datatable`) |
| POST | `/api/v1/navigation/menus` | `…menus.store` | `auth:api`, `permission:manage menu` | Create |
| GET | `/api/v1/navigation/menus/{menu}` | `…menus.show` | `auth:api`, `permission:view menu` | Read |
| PUT/PATCH | `/api/v1/navigation/menus/{menu}` | `…menus.update` | `auth:api`, `permission:manage menu` | Update (and reorder) |
| DELETE | `/api/v1/navigation/menus/{menu}` | `…menus.destroy` | `auth:api`, `permission:manage menu` | Delete (`?cascade=1` to remove the subtree) |
| GET | `/api/v1/navigation/menus/trashed` | `…menus.trashed` | `auth:api`, `permission:view menu` | List soft-deleted menus (`?load_type=h\|p\|c`, optional `?display_mode=datatable`) |
| POST | `/api/v1/navigation/menus/{menu}/restore` | `…menus.restore` | `auth:api`, `permission:restore menu` | Restore a menu + its trashed subtree |

Authorization is **permission-based** (consistent with the rest of the app), not a hardcoded role list. `RoleSeeder` grants `view menu` to Root/Admin/Moderator/Management, and `manage menu` + `restore menu` to Root/Admin/Moderator. The `{menu}` on the restore route is bound with `withTrashed()` so the soft-deleted record resolves.

`{menu}` is constrained with `whereUuid` and resolved by implicit model binding.

### Request fields (store/update)

The HTTP contract uses friendlier names than the columns; the **service** maps them:

| Request field | Column | Notes |
|---------------|--------|-------|
| `name`, `icon`, `route`, `type` | same | `type ∈ {h,p,c}`; `route` must pass `Route::has()` |
| `parent` | `parent_id` | must `exists:menus,id` **excluding soft-deleted**; on update cannot equal the menu itself **or any descendant** |
| `authenticate` | `is_authenticate` | boolean |
| `guest_only` | `is_guest_only` | boolean |
| `position` + `reference_id` | — | `append` (default) or `before`/`after` a sibling in the same `(parent_id, type)` group; `reference_id` must point at a **non-trashed** menu |
| `roles[]` | `menu_roles` | role IDs **or** names (`api` guard) |
| `user_includes[]` / `user_excludes[]` | pivots | user UUIDs (non-trashed) |

On **create**, all three relationship sets are written (omitting a key clears it). On **update**, a relationship is only touched when its key is present, so an empty array explicitly clears assignments while omitting the key leaves them untouched.

**Atomicity:** `MenuService::create`/`update` wrap the row write *and* all pivot syncs in a single `DB::transaction`, so a failed relationship sync rolls back the menu too — no half-wired records.

**Structural validation (`h → p → c`):** the form requests enforce strict nesting — a header (`h`) must be a root (no parent), a parent (`p`) must sit under a header, and a child (`c`) must sit under a parent. On update this is only checked when `type` or `parent` is actually being changed, so pure renames/reorders of existing data are never rejected. Re-parenting a menu under one of its own descendants is rejected (cycle guard). These are HTTP-layer concerns; the repository and seeders are trusted callers and bypass them.

Invalid `position`/`reference_id` combinations throw `InvalidMenuPositionException`; structural/cycle violations surface as standard `422` validation errors on the `parent`/`type` fields.

---

## Ordering model

`order` is meaningful only within a `(parent_id, type)` sibling group. Insert, move, and delete all run in a `DB::transaction` and call `persistOrderSequence()`, which rewrites the group to a clean `1..N` in **two phases**: first set every affected row's `order` to `NULL`, then assign the final positions. The `NULL` phase is what lets the rewrite coexist with the unique `(parent_id, type, order)` index — a straight in-place rewrite (e.g. `1 → 2` while another row still holds `2`) would violate the constraint mid-statement. Moving a menu to another parent/type compacts the old group and appends to the new one.

## Soft deletes

`Menu` uses `SoftDeletes`. `deleteMenu` nulls the row's `order` (freeing its slot under the unique index), soft-deletes it, then compacts the surviving siblings. Soft-deleted rows are excluded from the navigation tree, the admin list, and all sibling-ordering queries (default scope).

**Branch deletes do not orphan children.** A soft delete is an `UPDATE deleted_at`, so the `parent_id` FK `cascadeOnDelete` (which only fires on a real `DELETE`) never runs. Left unchecked, soft-deleting a header/parent would strand its still-active descendants — invisible in the tree/list (their parent is no longer reachable from a root) yet alive in the table. To prevent that, `deleteMenu($menu, $cascade)`:

- **rejects** deleting a menu that has active descendants unless `$cascade` is `true` (`MenuHasChildrenException`, HTTP **409**);
- with `$cascade = true`, nulls and soft-deletes the **entire subtree** in one transaction, then compacts the deleted menu's siblings.

The HTTP layer exposes this as `DELETE /…/{menu}?cascade=1`. Leaf (`c`) deletes need no flag.

Also note: because the `parent`/`reference_id` `exists` rules exclude trashed rows, you cannot create or move a menu under a soft-deleted parent (which would re-orphan it).

### Restore

`restoreMenu($menu)` is the inverse of a cascade delete and runs in one transaction:

1. **Parent guard.** If the menu has a `parent_id` whose row is not currently active, the restore is rejected (`InvalidMenuParentException`, **422**) — restoring under a still-trashed parent would just re-orphan the branch. Restore the parent first.
2. **Cascade up the subtree.** The menu **and all of its still-trashed descendants** are restored together (`Menu::onlyTrashed()` is walked depth-first to collect them).
3. **Re-sequence order.** Restored rows come back with `order = NULL`; each affected `(parent_id, type)` group is then re-sequenced so existing siblings keep their relative order and restored rows are **appended to the tail** (`siblingIdsForAppend`).

Listing is exposed via `GET …/menus/trashed` (`onlyTrashed`, newest deletion first, optional `?load_type`). There is still **no force-delete endpoint**: permanent removal (which would fire the real FK `cascadeOnDelete`) is intentionally left out, so the only API paths are soft-delete and restore.

## Caching

`navigationTree` reads a **serialization-safe scalar snapshot** (`Cache::rememberForever('menus.navigation.snapshot')`) — plain arrays of every active menu plus its `role_names` / include / exclude user IDs, **not** hydrated Eloquent models (those break a serializing store like the `database` driver). The tree is assembled and filtered from those arrays in memory; per-viewer filtering happens after the read, so the cache is viewer-agnostic.

Any menu mutation (`createMenu`, `updateMenu`, `updateMenuPosition`, `deleteMenu`, and the three `sync*` methods) busts the snapshot. Because the snapshot stores role **names**, renaming or deleting a `Role` would otherwise leave a stale gate — so `AppServiceProvider` flushes the snapshot on `Role::updated` / `Role::deleted` via `flushNavigationCache()`. (User role *assignments* need no bust: visibility is evaluated live per request against the current user.)

The admin list (`adminFlatList`) queries the database directly rather than the snapshot.

---

## Seeding

- **`MenuSeeder`** (wired into `DatabaseSeeder`) creates a sample tree: `Application` (header) → `Auth` (parent) → `Login` / `Register` (guest-only) / `Profile` (authenticated), using real route names (`api.v1.auth.*`).
- **`MenuRbacSeeder`** (debugging) populates all three pivots via a `Debug → RBAC Demo` branch: an Admin/Root-restricted item (`menu_roles`), an include-only item (`menu_user_includes`), and an exclude item (`menu_user_excludes`), plus two demo users.

Both are idempotent. A `MenuFactory` (with `header()` / `parentItem()` / `child()` / `authenticated()` / `guestOnly()` states) is available for tests; it defaults `order` to `NULL` so factory-built siblings never trip the unique index.

```bash
php artisan migrate:fresh --seed
```

---

## Related documentation

- [API Routing](./api-routing.md) — versioned route placement
- [List display modes (query & DataTables)](./list-display-modes.md) — `list` / `trashed` dual-mode responses and RBAC actions
- [CRUD Architecture & Repository Pattern](./crud-architecture.md) — when to use a dedicated repository
- [JWT Authentication & RBAC](./jwt-authentication.md) — guard, roles, middleware
- [User Management & H-RBAC](./user-management-hrbac.md) — role levels and hierarchy
- [User primary keys (UUID v7)](./user-uuid-v7.md) — UUID conventions
