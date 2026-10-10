# Database safety

MySQL 8.x for local development, PostgreSQL 16 for deployment parity; **SQLite in-memory** for PHPUnit. Destructive ops on the wrong connection wipe dev data.

`composer test` is the canonical test command (it clears config first); bare `php artisan test` is guarded by `tests/bootstrap.php`. Never run `migrate:fresh`, `migrate:refresh`, or `db:wipe` on a non-sqlite connection without `ALLOW_DESTRUCTIVE_DB=true`. The classic failure is a cached config overriding `phpunit.xml`: `config:cache` bakes `DB_CONNECTION=mysql`, and `RefreshDatabase` then wipes MySQL instead of the in-memory SQLite the suite expects. The migration portability contract (explicit `uuid` FK columns, no driver-specific enums, explicit JSON↔text casts, NULL-rejecting rollbacks) and the destructive-command guard chain are owned by [database-safety.mdc](../../.cursor/rules/database-safety.mdc) — read it before touching `database/**`.

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

## Squashed baseline (pre-production)

While the project is unreleased, `database/migrations` is a **baseline**, not a log: one create migration per owning group (framework, permission, navigation, each product), no `alter` and no data migration. A column or index that arrives later is added to the create that owns the table, and the DB is rebuilt with `migrate:fresh --seed`; nothing backfills, because seeders and `config/**` own the final values. `app/Domain/{Product}/uninstall.json` lists the migrations that survive under `data.migrations` — the drift guard fails on a stale entry.

The policy, the FK-order contract, and the DDL-diff verification recipe are owned by [migration-baseline.mdc](../../.cursor/rules/migration-baseline.mdc). It is temporary: deleted when the project reaches production, after which migrations become append-only history.

## Defense layers

0. `composer test` → `config:clear`
1. `tests/bootstrap.php` — delete config cache, force sqlite env
2. `tests/TestCase.php` — fail if `database.default !== sqlite`
3. `DestructiveDatabaseCommandGuard`
4. `ConsoleSafetyServiceProvider`
5. `AppServiceProvider` wires console safety

## Recovery

```bash
php artisan db:seed
# Full reset only when intentional:
# ALLOW_DESTRUCTIVE_DB=true php artisan migrate:fresh --seed
```

On **local**, `--seed` runs `SettingsSeeder` then `LocalSettingsSeeder` (Tier B dev pack from `config/settings-local.php`). Staging/production: `SettingsSeeder` only. Tier B values are not restored from `.env`.

Default sign-in: `admin@example.com` / `123456789`. Invoices are **not** recreated by seed.

| | |
|---|---|
| Code | `app/Support/Database/DestructiveDatabaseCommandGuard.php`, `app/Console/ConsoleSafetyServiceProvider.php`, `tests/bootstrap.php`, `tests/TestCase.php` |
| Config | `config/database.php`, `phpunit.xml`, `ALLOW_DESTRUCTIVE_DB` |
| Commands | `composer test`, `db:migration-diagnose`, `db:seed`, `migrate:rollback` |
| Tests | `tests/Unit/Database/MigrationPortabilityTest.php` |
| Pakem | [database-safety.mdc](../../.cursor/rules/database-safety.mdc) · [migration-baseline.mdc](../../.cursor/rules/migration-baseline.mdc) |
