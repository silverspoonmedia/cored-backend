# Navigation — dynamic menu tree

Self-referential, orderable menus with RBAC, domain-access, product-activation and per-user visibility — the single source of the SPA sidebar and navbar. `MenuSeeder` is the authoritative tree; admin CRUD edits it at runtime. Code: `packages/navigation/src/`. Invariants: [.cursor/rules/navigation-menus.mdc](../../../.cursor/rules/navigation-menus.mdc).

| Need | Path |
|------|------|
| Code | `packages/navigation/src/` — `MenuRepository` (build + cache), `MenuService` (field map, orchestration) |
| Provider | `packages/navigation/src/NavigationServiceProvider.php` |
| Seeder | [`packages/navigation/database/seeders/MenuSeeder.php`](../../../packages/navigation/database/seeders/MenuSeeder.php) (sole canonical tree; force-rebuilds in one transaction) |
| Config | [`packages/navigation/config/navigation.php`](../../../packages/navigation/config/navigation.php) · [`config/spa-routes.php`](../../../config/spa-routes.php) · [`config/products.php`](../../../config/products.php) `menu_routes` |
| Table | `menus` + `menu_roles`, `menu_user_includes`, `menu_user_excludes` |
| API | [`packages/navigation/routes/api.php`](../../../packages/navigation/routes/api.php) — `/api/v1/navigation/menus` |
| SPA | Vue Router `menus.index` / `menus.trashed`; shell sidebar via `useNavigation` |
| Cache | `menus.navigation.snapshot` + `tree_epoch` + `presented.*` · `php artisan menu:flush-cache` |
| Realtime | `navigation.menus` private channel (permission `view menu`) — `.menu.tree.changed` |
| Tests | `packages/navigation/tests/` (`php artisan test packages/navigation/tests`) |
| Pakem | [.cursor/rules/navigation-menus.mdc](../../../.cursor/rules/navigation-menus.mdc) |
| Deep dive | [reference.md](reference.md) — schema, visibility rules, request fields, API, canonical trees, cache invalidation, SPA consumer |
| Related | [domain-access](../domain-access/README.md) (gate) · [product-activations](../product-activations/README.md) (gate) · [platform/cache-policy.md](../../platform/cache-policy.md) (two-tier cache) |
