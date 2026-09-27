# Navigation — dynamic menu tree

Self-referential, orderable menus with RBAC, domain-access, product-activation and per-user visibility — the single source of the SPA sidebar, navbar and the Valkivid3 public template nav. `MenuSeeder` is the authoritative tree; admin CRUD edits it at runtime. Code: `app/Domain/Navigation/`. Invariants: [.cursor/rules/navigation-menus.mdc](../../../.cursor/rules/navigation-menus.mdc).

| Need | Path |
|------|------|
| Code | `app/Domain/Navigation/` — `MenuRepository` (build + cache), `MenuService` (field map, orchestration) |
| Provider | `app/Providers/Domain/NavigationServiceProvider.php` |
| Seeder | [`database/seeders/MenuSeeder.php`](../../../database/seeders/MenuSeeder.php) (sole canonical tree; force-rebuilds in one transaction) |
| Config | [`config/navigation.php`](../../../config/navigation.php) · [`config/spa-routes.php`](../../../config/spa-routes.php) · [`config/products.php`](../../../config/products.php) `menu_routes` |
| Table | `menus` + `menu_roles`, `menu_user_includes`, `menu_user_excludes` |
| API | [`routes/api/v1/navigation.php`](../../../routes/api/v1/navigation.php) — `/api/v1/navigation/menus` |
| SPA | Vue Router `menus.index` / `menus.trashed`; shell sidebar via `useNavigation` |
| Cache | `menus.navigation.snapshot` + `tree_epoch` + `presented.*` · `php artisan menu:flush-cache` |
| Realtime | `navigation.menus` private channel (permission `view menu`) — `.menu.tree.changed` |
| Tests | `tests/Domain/Navigation/` (`php artisan test --filter=Navigation`) |
| Pakem | [.cursor/rules/navigation-menus.mdc](../../../.cursor/rules/navigation-menus.mdc) |
| Deep dive | [reference.md](reference.md) — schema, visibility rules, request fields, API, canonical trees, cache invalidation, SPA consumer |
| Related | [domain-access](../domain-access/README.md) (gate) · [product-activations](../product-activations/README.md) (gate) · [platform/cache-policy.md](../../platform/cache-policy.md) (two-tier cache) |
