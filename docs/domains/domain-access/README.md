# Domain Access — Public/Private product-domain gate

Kernel feature (`App\Support\DomainAccess`, mirrors `App\Support\Product`) that gates each **product** domain by a global `public` | `private` flag, configurable from the Blade admin by Root/Admin only. It is a **fourth axis**, distinct from module taxonomy (`config/domains.php`), the RBAC catalog (`domain_kind`), and per-user product activation (`product_activations`).

## Concept

| Visibility | Who can reach the domain |
|------------|--------------------------|
| `public` (default) | Everyone (unchanged legacy behavior) |
| `private` | `Root`, `Admin`, and holders of `Beta tester {Domain}` (or the `access {domain} product` permission) |

Root/Admin always bypass. Default is `public`, so enabling the feature does not change existing access until a domain is flipped to `private`.

## Moving parts

| Piece | Location |
|-------|----------|
| Visibility store | `product_domain_visibilities` (`product_key` unique, `visibility`) |
| Enum | `App\Support\DomainAccess\Enums\DomainVisibility` (`Public`\|`Private`) |
| Model | `App\Support\DomainAccess\Models\ProductDomainVisibility` |
| Service | `App\Support\DomainAccess\DomainAccessService` — `visibilityFor`, `setVisibility`, `canAccess`, request-scoped cache |
| Naming | `App\Support\DomainAccess\ProductNames` — `label`, `betaRole`, `accessPermission` |
| Middleware | `domain.access:{Domain}` → `DomainAccessGate` (alias in `DomainAccessServiceProvider`) |
| Admin API | `GET admin/domain-access`, `PUT admin/domain-access/{key}` (`view`/`manage domain access` perms) |
| Admin web | `App\Support\DomainAccess\Http\Controllers\Web\DomainAccessController` + `adminlte4/domain-access/index.blade.php` + `resources/js/domain-access/toggle.js` |
| Beta role seeding | `Database\Seeders\Concerns\SeedsBetaTesterRole` + `DomainAccessSeeder` (built-ins) |

## Naming derivation

Product key is StudlyCase (e.g. `FileHosting`). `ProductNames` derives:

- label: `File Hosting`
- beta role: `Beta tester File Hosting`
- access permission: `access file hosting product`

## Beta tester role

`Beta tester {Domain}` is `domain_kind = product` with **`level = null`**. Null keeps it out of the H-RBAC ladder (`RoleService` ignores null levels) and sidesteps the `unique(domain_kind, level)` constraint entirely — so any number of product domains can seed a beta role without hand-picking level offsets. The `access {domain} product` permission is granted to the beta role and to core `Root`/`Admin`.

## Enforcement

`domain.access:{Domain}` is layered **before** `product.active:{Domain}` / `permission:` on both the web and API product route groups:

```php
// routes/api/v1/{slug}.php
Route::middleware(['auth:api', 'domain.access:FileHosting', 'product.active:FileHosting'])->group(...);

// routes/web/{slug}.php
Route::middleware('domain.access:FileHosting')->group(...);
```

Denials: API → 403 JSON via `ApiResponder::error`; web → `abort(403)`. Unauthenticated on a private domain → 401.

## Menu visibility

Route middleware only blocks requests — it does not hide the sidebar link. A second enforcement point in the navigation menu makes a private domain's links disappear for viewers without access.

`MenuRepository::nodeVisibleLeafRules` (`app/Domain/Navigation/Repositories/Eloquent/MenuRepository.php`) resolves a node's product via `ProductRegistry::productKeyByMenuRoute($node['route'])` (reverse lookup of `config/products.php` `menu_routes`), then calls `DomainAccessService::canAccess($user, $productKey)`. If it returns false the node is hidden; empty headers/parents are then pruned by `shouldPruneEmptyBranch`. This is **guest-aware** (runs even for guests, unlike the activation check) and stacks with the existing per-user activation gate:

```php
if ($productKey !== null) {
    if (! $this->domainAccess->canAccess($user, $productKey)) {
        return false; // Private + no access → hide link
    }
    if ($user && ! $this->productActivations->isActive($user->id, $productKey)) {
        return false; // existing activation gate (auth only)
    }
}
```

Domain Access, product activation, and RBAC run when `MenuRepository` **builds** the presented navigation tree (on cache miss). The `menus.navigation.snapshot` stays product/user-agnostic and stores real DB routes — gates never see presentational `#`. Changing Public/Private via `setVisibility` bumps the presented-tree epoch (`bumpPresentedTreeEpoch`) so every viewer rebuilds. See [Navigation — Caching](../navigation/README.md#caching) and [cache-policy](../../platform/cache-policy.md). New domains still need their routes listed in `config/products.php` `menu_routes` to be gated (same requirement as the activation gate).

## Admin config screen

- Sidebar node **Administration → Access control → Product domain access**, restricted to Root/Admin via `menu_roles`.
- Card grid of product domains with a Public/Private switch gated by `@can('manage domain access')`; the toggle PUTs to the web proxy which forwards to the API.
- i18n: `lang/{en,id,ja}/domain-access.php`.

## `domain:make` pipeline

`domain:make {Name}` generates `database/seeders/{Name}RoleSeeder.php` (Beta tester role + permission + Public visibility row via `SeedsBetaTesterRole`) and registers `$this->call({Name}RoleSeeder::class)` in `DatabaseSeeder`. `domain:remove` deletes the seeder and unregisters it. Run `php artisan db:seed --class={Name}RoleSeeder` after scaffolding.

## Tests

`tests/Domain/DomainAccess/`: service matrix, gate middleware (web + API), admin API authorization, seeder assertions, and the `domain:make` seeder-generation pipeline.

`tests/Domain/Navigation/Feature/PrivateDomainMenuVisibilityTest.php`: menu link hiding — Public shows for activated users; Private hides the link (and prunes the empty header) for plain users and guests, but keeps it for Root and `Beta tester {Domain}`.
