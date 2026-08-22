# Database safety

MySQL 8.x for local development, PostgreSQL 16 for deployment parity; **SQLite in-memory** for PHPUnit. Destructive ops on the wrong connection wipe dev data.

## Migration portability contract

Migrations target MySQL 8.x and PostgreSQL 16 through Laravel's portable schema builder:

- UUID keys and foreign keys use matching `uuid` columns. Self-references in `menus` and `file_hosting_folders` declare the UUID column, named foreign key, and lookup index explicitly.
- Subscription state values use bounded strings. PHP backed enums and request validation own allowed values; migrations do not depend on driver-specific database enums.
- PostgreSQL does not have MySQL unsigned integer types. Unsigned declarations are acceptable only where application validation and domain logic enforce non-negative values. Do not use ignored unsigned modifiers as the invariant.
- PostgreSQL JSON/text conversions require explicit casts. The envelope-encryption migration uses `USING` casts and validates rollback values before converting text back to JSON.
- Nullability rollbacks must reject existing NULL data before changing a column to NOT NULL.
- Do not assume SQLite migration success proves either production driver. SQLite remains unit/feature-test isolation only.

The reported `menus.parent_id` error means PostgreSQL could not find a unique or primary key constraint for `menus.id` at constraint-creation time. Current normalized DDL creates the table, then adds the self-FK, while `menus.id` is an explicit primary key. If the error persists, inspect wrong database/schema, stale or partial table, migration history, and actual indexes before changing schema again.

Diagnostic command:

```bash
php artisan db:migration-diagnose menus
```

It reports active driver, database, schema/search context, table existence, migration rows, indexes, and foreign keys. Run against disposable MySQL/PostgreSQL databases, not production.

## Disposable database matrix

Run fresh migration and rollback verification against isolated MySQL 8.x and PostgreSQL 16 databases. Use separate credentials/databases or containers. Never point matrix variables at development or production data.

```bash
DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3306 DB_DATABASE=monolith_migration_mysql DB_USERNAME=... DB_PASSWORD=... php artisan migrate --force
DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=5432 DB_DATABASE=monolith_migration_pgsql DB_USERNAME=... DB_PASSWORD=... php artisan migrate --force
```

After each run, verify `php artisan db:migration-diagnose menus`, foreign-key metadata, UUID column types, nullable composite unique behavior, JSON round trips, and representative seed/backfill data. Roll back only disposable databases:

```bash
php artisan migrate:rollback --step=1
```

`tests/Unit/Database/MigrationPortabilityTest.php` covers generated MySQL/PostgreSQL DDL without opening a live database. `composer test` remains SQLite-only and must stay protected by the existing destructive-command guard.

## Root cause: cached config + `RefreshDatabase`

1. `config:cache` bakes `DB_CONNECTION=mysql` / `DB_DATABASE=monolith`.
2. `phpunit.xml` sets sqlite, but **cached config overrides** env when Laravel boots.
3. `RefreshDatabase` runs `migrate:fresh` → empty MySQL tables (e.g. `users=0`).

## Safe commands

| Command | Safe? | Notes |
|---------|-------|-------|
| `composer test` | Yes | **Canonical** — `config:clear` then `php artisan test` |
| `php artisan test` | Yes (guarded) | Clears config; `tests/bootstrap.php` |
| IDE PHPUnit | Yes | Uses `tests/bootstrap.php` |

## Blocked destructive commands (non-sqlite)

Blocked when default driver is MySQL/PostgreSQL/MariaDB/SQL Server and `ALLOW_DESTRUCTIVE_DB` is not `true`:

- `migrate:fresh`, `migrate:refresh`, `db:wipe`

Allowed: sqlite (tests) or `ALLOW_DESTRUCTIVE_DB=true` (intentional reset).

`ALLOW_DESTRUCTIVE_DB` is read via `env()` at command time. After changing it, `php artisan config:clear` if config is cached.

## Recovery

```bash
php artisan db:seed
# Full reset only when intentional:
# ALLOW_DESTRUCTIVE_DB=true php artisan migrate:fresh --seed
```

On **local**, `--seed` runs `SettingsSeeder` then `LocalSettingsSeeder` (Tier B dev pack from `config/settings-local.php`). Staging/production: `SettingsSeeder` only. Tier B values are not restored from `.env`.

Default sign-in: `admin@example.com` / `123456789`. Invoices are **not** recreated by seed.

## Defense layers

0. `composer test` → `config:clear`
1. `tests/bootstrap.php` — delete config cache, force sqlite env
2. `tests/TestCase.php` — fail if `database.default !== sqlite`
3. `DestructiveDatabaseCommandGuard`
4. `ConsoleSafetyServiceProvider`
5. `AppServiceProvider` wires console safety

## For AI agents

- Prefer `composer test` / `composer test -- --filter=…`
- Never `migrate:fresh` / `db:wipe` on non-sqlite without `ALLOW_DESTRUCTIVE_DB=true`
- See [AGENTS.md](../../AGENTS.md), [agents/core-rules.md](../agents/core-rules.md)
