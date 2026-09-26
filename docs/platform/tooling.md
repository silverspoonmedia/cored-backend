# Tooling

Developer tooling that is not a test: formatting, and the two-job GitHub Actions workflow that gates a pull request. Both are deliberately narrow — Pint never runs on the runner, and each CI job needs exactly one repo, so the workflow carries no credentials.

| Need | Open |
|------|------|
| Pint usage | below |
| CI job steps, why each one exists, expected skip/risky counts | [reference-tooling.md](reference-tooling.md) |
| Which defect class each `locale` job step covers | [reference-localization.md — CI coverage vs manual runs](reference-localization.md#ci-coverage-vs-manual-runs) |
| PR & merge process, and the gap left for SPA-only pull requests | [reference-localization.md — PR & merge process](reference-localization.md#pr--merge-process) |
| Localization gates the `locale` job runs | [localization.md](localization.md) |

## Laravel Pint

[Pint](https://laravel.com/docs/pint) is a dev dependency. Use the Artisan wrapper (forwards all args to the binary):

```bash
php artisan pint
php artisan pint --test          # report only, no writes
php artisan pint --dirty         # uncommitted files only
php artisan pint --test --bail
php artisan pint app/Domain/Identity/Models/User.php
```

Implementation: `app/Console/Commands/PintCommand.php` — Symfony Process, `ignoreValidationErrors()` so Pint flags pass through.

**Limits:** `-h`/`--help`/`-q`/`-v` are Artisan-owned; for full Pint help: `php vendor/bin/pint --help`. No project `pint.json` yet (Pint defaults).

**Workflow:** `pint --dirty` before commit; `pint --test` before opening the pull request. Pint is a **local** step — neither CI job invokes it, so a formatting difference is not caught on the runner and lands in the next commit. Do not describe `pint --test` as a CI check until a job actually runs it.

## Gates

```bash
php artisan pint --test          # formatting (local only)
php artisan test                 # whole backend suite — what the `backend` CI job runs
php artisan test --filter=Documentation
php artisan test --filter=Locale
php scripts/docs-guard.php
```

Reproduce the `backend` job locally before pushing — same command, same in-memory database:

```bash
php artisan test                 # whole suite; ~9 min on the dev machine, longer on a runner
php artisan test --filter=Vtual  # the subset to iterate on while working
```

A green run is `failed: 0`, **not** `passed == tests`: the suite ends with one skipped OCI KMS integration test and one risky stdout report, both by design. The exact counts and why they must stay are in [reference-tooling.md](reference-tooling.md#a-green-run-is-failed-0).
