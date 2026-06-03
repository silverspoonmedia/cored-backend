# Laravel Pint (Artisan wrapper)

This project ships [Laravel Pint](https://laravel.com/docs/pint) as a dev dependency. Pint enforces consistent PHP coding style using PHP-CS-Fixer under the hood.

Instead of calling the binary directly (`./vendor/bin/pint`), the app exposes an Artisan command so formatting fits the same workflow as migrations, tests, and other CLI tasks.

---

## Summary of changes

| Location | Change |
|----------|--------|
| `composer.json` | `laravel/pint` in `require-dev` (already present) |
| `app/Console/Commands/PintCommand.php` | Artisan command `pint` that forwards all arguments to the Pint binary |

The command is auto-discovered from `app/Console/Commands/` — no manual registration in `bootstrap/app.php` or a service provider is required.

---

## Why an Artisan wrapper

| Approach | Trade-off |
|----------|-----------|
| `./vendor/bin/pint` | Works, but feels disconnected from Laravel; path differs on Windows vs Unix; easy to forget in team docs. |
| `php artisan pint` | Same entry point as the rest of the project; no path juggling; reads naturally in README and CI scripts. |

The wrapper is intentionally thin: it does **not** reimplement Pint options. Every flag and path argument is passed through unchanged so new Pint releases stay compatible without updating the command signature.

---

## Usage

Run from the project root:

```bash
php artisan pint
```

Common examples:

```bash
# Check style without modifying files (CI-friendly)
php artisan pint --test

# Only files with uncommitted changes
php artisan pint --dirty

# Stop on first violation
php artisan pint --test --bail

# Target a specific path
php artisan pint app/Models/User.php

# Parallel execution (experimental)
php artisan pint --parallel --max-processes=4

# Custom config or preset
php artisan pint --config=pint.json --preset=laravel
```

Any option supported by your installed Pint version works the same way as with the binary. See upstream help:

```bash
php vendor/bin/pint --help
```

---

## How it works

`PintCommand` builds a Symfony `Process` that runs:

```
{PHP_BINARY} {base_path}/vendor/bin/pint {…arguments after "pint"}
```

Arguments are taken from `$_SERVER['argv']` — everything after the `pint` token — so flags like `--test`, `--dirty`, and `--parallel` are forwarded without being declared on the Artisan command.

```php
#[Signature('pint')]
#[Description('Fix the coding style of the given path')]
class PintCommand extends Command
{
    protected function configure(): void
    {
        parent::configure();

        $this->ignoreValidationErrors();
    }
    // …
}
```

`ignoreValidationErrors()` tells Artisan not to reject unknown options. Without it, flags such as `--test` would fail validation before Pint ever runs.

Output is streamed back to the terminal. When a TTY is available, the process runs in TTY mode so interactive output behaves like the native binary.

The command returns Pint’s exit code unchanged (`0` on success, non-zero when `--test` or `--bail` finds violations).

---

## Limitations

### Artisan-owned global flags

Symfony Console reserves some flags for Artisan itself:

| Flag | Behavior |
|------|----------|
| `-h`, `--help` | Shows **Artisan** help for `pint`, not Pint’s own option list |
| `-q`, `--quiet`, `-v`, `--verbose` | Handled by Artisan; not forwarded to Pint |

To view Pint’s full option list, use the binary directly:

```bash
php vendor/bin/pint --help
```

### Configuration file

There is no project-level `pint.json` yet. Pint uses its defaults until you add one at the repository root. See the [Pint documentation](https://laravel.com/docs/pint#configuration) for presets and rule overrides.

---

## CI and local workflow

**Before commit (local):**

```bash
php artisan pint --dirty
```

**CI check (no writes):**

```bash
php artisan pint --test
```

Add `--bail` in CI if you want the job to fail fast on the first file with violations.

---

## Related links

- [Laravel Pint documentation](https://laravel.com/docs/pint)
- Command source: `app/Console/Commands/PintCommand.php`
