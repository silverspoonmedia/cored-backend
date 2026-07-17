# Navigation — dynamic menu tree

Self-referential, orderable menus with RBAC visibility. Dedicated repository (not generic CRUD). Code: `app/Domain/Navigation/`.

## Layers

| Piece | Role |
|-------|------|
| `Menu` model | `HasUuids`, Spatie translatable name/description, tree relations |
| `MenuRepository` | Tree build, visibility, transactional reorder, pivots |
| `MenuService` | Field map, orchestration |
| `MenuController` API/Web | Thin HTTP; web proxies API |
| `jwt.optional` | Guest or authed tree on `index` |

Bound in `NavigationServiceProvider`.

## Schema (`menus`)

| Column | Notes |
|--------|-------|
| `id`, `parent_id` | UUID; self-FK cascade |
| `layout` | Shell region: `sidebar`, `navbar` (config `navigation.layouts`) |
| `name`, `description` | JSON translatable |
| `icon`, `route` | Full icon class; **route name** not URL |
| `type` | `h` header, `p` parent, `c` child |
| `order` | Contiguous within `(layout, parent_id, type)`; NULL for soft-deleted |
| `is_authenticate`, `is_guest_only` | Audience flags |
| Soft deletes | |

Pivots: `menu_roles`, `menu_user_includes`, `menu_user_excludes`. Unique index on `(layout, parent_id, type, order)`.

**Layouts:** independent trees; parent/child must share layout; layout fixed after create.

## Visibility (navigation tree)

Node visible only if **it and every ancestor** pass:

1. Not in exclude list  
2. If includes non-empty → viewer in includes  
3. Guest-only hidden when authenticated  
4. Authenticate-required hidden for guests  
5. If roles attached → viewer has any; no roles → open to those who passed above  

Empty header/parent branches pruned (except leaf parent with a `route`). Invalid combos rejected at validation (`guest_only` + `authenticate` / roles).

## API (`/api/v1/navigation/menus`)

| Method | Path | Middleware | Purpose |
|--------|------|------------|---------|
| GET | `/` | `jwt.optional` | Tree (`?layout=`, `?q=`) |
| GET | `/list` | view menu | Admin flat list / DataTable |
| CRUD | `/{menu}` | view/manage menu | Store, show, update, destroy (`?cascade=1`) |
| GET | `/trashed` | view menu | Soft-deleted |
| POST | `/{menu}/restore` | restore menu | Restore subtree |

Permissions: `view menu`, `manage menu`, `restore menu` (RoleSeeder).

**Blade:** web MenuController forwards all mutations to API. **Exceptions:** sidebar/navbar composers call `MenuService::navigationTree` directly (no self-HTTP). Lookups for form options: `/api/v1/lookups/*`.

### Request fields (store/update)

Service maps friendly names: `parent` → `parent_id`, `authenticate` → `is_authenticate`, etc. Placement: optional `position` + `reference_id` (`before`/`after`/`append`). Structure: `h` root → `p` under `h` → `c` under `p`. Create/update wrap row + pivots in one transaction.

Translatable `name`/`description`: string or locale map — see [localization](../../platform/localization.md).

## Ordering

Two-phase reorder (null free slots, then reassign contiguous orders). Soft delete nulls `order` first.

## Caching

Active menus cached; per-request tree filters by layout + RBAC. Invalidate on menu mutations.

## Seeding

`MenuSeeder` — sample hierarchy; early-returns if any menu row exists (fresh seed only). Default sidebar headers: **Menu** (core UX: dashboard, users, notifications, services), **Products** (product surfaces, e.g. File Hosting “My Files”), **Administration** (activity log, payments, nav admin).

Menus are a **UX tree** only — no `domain` / `domain_kind` column. Module registry stays in [`config/domains.php`](../../../config/domains.php); RBAC catalog ownership is separate (`domain_kind` on roles/permissions). Do not add schema just to mirror those axes.

## Related

- [platform/crud.md](../../platform/crud.md)
- [web/ui-patterns.md](../../web/ui-patterns.md)
- [web/blade-admin.md](../../web/blade-admin.md)
