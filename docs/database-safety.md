# Database safety

This project uses **MySQL** for local development (`monolith` database) and **SQLite in-memory** for automated tests (`phpunit.xml`). Destructive operations against the wrong connection can wipe dev data silently.

## Root cause: cached config + `RefreshDatabase`

1. Running `php artisan config:cache` (or any flow that writes `bootstrap/cache/config.php`) caches `DB_CONNECTION=mysql` and `DB_DATABASE=monolith`.
2. `phpunit.xml` sets `DB_CONNECTION=sqlite` and `DB_DATABASE=:memory:`, but **cached config overrides those env vars** when PHPUnit boots Laravel.
3. **72** test classes use `RefreshDatabase`, which runs `migrate:fresh` per suite.
4. Result: tables exist but `users=0` — classic wipe without seed.

**Orders are not seeded** by `DatabaseSeeder`; only `users=0` proves an unseeded wipe.

## Safe commands

| Command | Safe? | Notes |
|---------|-------|-------|
| `composer test` | Yes | **Canonical entry point** — runs `config:clear` then `php artisan test` |
| `php artisan test` | Yes (with guards) | Clears config cache on start; PHPUnit uses `tests/bootstrap.php` |
| IDE PHPUnit | Yes | Uses `tests/bootstrap.php` from `phpunit.xml` |
| `php artisan test` (legacy, no guards) | **No** | Could hit MySQL if config was cached |

Prefer `composer test` even when guards are active — it matches CI and [`composer.json`](../composer.json).

## Blocked destructive commands (non-sqlite)

These are blocked when the **default DB driver** is MySQL, PostgreSQL, MariaDB, or SQL Server and `ALLOW_DESTRUCTIVE_DB` is not `true` — in **all** environments, not only local:

- `migrate:fresh`
- `migrate:refresh`
- `db:wipe`

Allowed when:

- Default driver is `sqlite` (PHPUnit via `tests/bootstrap.php`)
- `ALLOW_DESTRUCTIVE_DB=true` in `.env` (intentional reset)

**Note:** Use `APP_ENV=local` for day-to-day development. `APP_ENV=testing` in `.env` disables Laravel's `CommandStarting` routing; [`ConsoleSafetyServiceProvider`](../app/Console/ConsoleSafetyServiceProvider.php) hooks `ArtisanStarting` and attaches a Symfony dispatcher when none exists.

`ALLOW_DESTRUCTIVE_DB` is read via `env()` at command time (not `config()`). After changing it, run `php artisan config:clear` if config is cached.

## Recovery after accidental wipe

```bash
php artisan db:seed
```

Default sign-in: `admin@example.com` / `123456789` (see `DatabaseSeeder`).

To fully reset schema **and** seed (only when you mean it):

```bash
# Set ALLOW_DESTRUCTIVE_DB=true in .env first
php artisan migrate:fresh --seed
```

Orders, invoices, and payment test data are **not** recreated by seed — recreate manually as needed.

## Defense layers (implementation)

0. **`composer test`** — `config:clear` before PHPUnit ([`composer.json`](../composer.json)).
1. **`tests/bootstrap.php`** — deletes config cache and forces sqlite env before autoload.
2. **`tests/TestCase.php`** — fails fast if `database.default !== sqlite`.
3. **`DestructiveDatabaseCommandGuard`** — blocks destructive Artisan commands on non-sqlite DBs.
4. **`ConsoleSafetyServiceProvider`** — registers guard + test config-clear on `CommandStarting` and `ArtisanStarting`.
5. **`AppServiceProvider`** — wires console safety hooks at boot.

## For AI agents

- **Prefer** `composer test` in Monolith (`composer test -- --filter=YourTest` for filtered runs).
- Bare `php artisan test` is guarded on current code, but `composer test` is the canonical entry point.
- **Never** run `migrate:fresh` or `db:wipe` on non-sqlite without `ALLOW_DESTRUCTIVE_DB=true` and `--seed` when you need users.
- See [AGENTS.md](../AGENTS.md) and [ai-agent-guide.md](ai-agent-guide.md).
