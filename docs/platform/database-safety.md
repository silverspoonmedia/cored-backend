# Database safety

MySQL for local development; **SQLite in-memory** for PHPUnit. Destructive ops on the wrong connection wipe dev data.

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
# ALLOW_DESTRUCTIVE_DB=true then migrate:fresh --seed
```

Default sign-in: `admin@example.com` / `123456789`. Orders/invoices are **not** recreated by seed.

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
- See [AGENTS.md](../../AGENTS.md), [agents/guide.md](../agents/guide.md)
