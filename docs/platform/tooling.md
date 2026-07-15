# Tooling

## Laravel Pint

[Pint](https://laravel.com/docs/pint) is a dev dependency. Use the Artisan wrapper (forwards all args to the binary):

```bash
php artisan pint
php artisan pint --test          # CI check, no writes
php artisan pint --dirty         # uncommitted files only
php artisan pint --test --bail
php artisan pint app/Domain/Identity/Models/User.php
```

Implementation: `app/Console/Commands/PintCommand.php` — Symfony Process, `ignoreValidationErrors()` so Pint flags pass through.

**Limits:** `-h`/`--help`/`-q`/`-v` are Artisan-owned; for full Pint help: `php vendor/bin/pint --help`. No project `pint.json` yet (Pint defaults).

**Workflow:** local `pint --dirty` before commit; CI `pint --test`.
