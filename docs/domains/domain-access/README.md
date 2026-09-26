# Domain Access — Public/Private product-domain gate

Kernel feature that gates each **product** domain by a global `public|private` flag, configurable from the SPA (**Administration → Access control → Product domain access**) by Root/Admin. Invariants: [.cursor/rules/domain-access.mdc](../../../.cursor/rules/domain-access.mdc).

It is a fourth axis, distinct from module taxonomy (`config/domains.php`), the RBAC catalog (`domain_kind`), and per-user product activation (`product_activations`). Default is `public`, so enabling it changes nothing until a domain is flipped; a `private` domain then admits Root, Admin, and holders of `Beta tester {Domain}` (or `access {domain} product`). Route middleware blocks requests only, so a second enforcement point in the navigation menu hides the link for viewers without access. Code lives in `app/Support/DomainAccess/`.

| Need | Path |
|------|------|
| Code | `app/Support/DomainAccess/` — `DomainAccessService`, `ProductNames`, `DomainAccessGate` |
| Config | [`config/domains.php`](../../../config/domains.php) · [`config/products.php`](../../../config/products.php) `menu_routes` |
| Table | `product_domain_visibilities` (`product_key` unique, `visibility`) |
| API | [`routes/api/v1/domain-access.php`](../../../routes/api/v1/domain-access.php) — `GET /api/v1/admin/domain-access`, `PUT …/{key}` |
| SPA | Vue Router `domain-access.index` → `/apps/domain-access` (no server `web` route) |
| Seeding | `DomainAccessSeeder` + `Database\Seeders\Concerns\SeedsBetaTesterRole` |
| Realtime | `domain-access` private channel — `.domain.visibility.changed` |
| Translations | `lang/*/domain-access.php` |
| Tests | `tests/Domain/DomainAccess/` · `tests/Domain/Navigation/Feature/PrivateDomainMenuVisibilityTest.php` |
| Pakem | [.cursor/rules/domain-access.mdc](../../../.cursor/rules/domain-access.mdc) |
| Related | [navigation](../navigation/README.md) (menu visibility) · [product-activations](../product-activations/README.md) (activation axis) · [platform/cache-policy.md](../../platform/cache-policy.md) (presented-tree epoch) |
