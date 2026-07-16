# Product — stub (catalog retired)

Locked product **scaffold** kept for registry / `domain_kind` demos. Catalog **Items** CRUD is no longer the primary product example.

**Use instead:** [File Hosting](../file-hosting/README.md).

## What remains

| Piece | Status |
|-------|--------|
| `config/domains.php` → `Product` locked | Kept |
| `app/Domain/Product/` models/services | Code still on disk (historical); routes empty |
| `routes/api/v1/product.php` | Stub group only |
| `routes/web/product.php` | Empty |
| `ProductRoleSeeder` | Still seeds product item roles/permissions (harmless) |
| Menu “Product items” | Removed from `MenuSeeder` |

## Why stub, not delete

Plan choice: strip public surface after FileHosting is green, keep domain shell so agents can still study a second product entry without shipping catalog UI.

To fully remove later: unlock in `config/domains.php`, then `domain:remove Product --force` (migrations stay).

## Related

- [file-hosting/README.md](../file-hosting/README.md)
- [platform/architecture.md](../../platform/architecture.md)
