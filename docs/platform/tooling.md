# Tooling

Developer tooling that is not a test: formatting, static analysis, and the two-job GitHub Actions workflow that gates a pull request. Each CI job needs exactly one repo, so the workflow carries no credentials.

| Need | Open |
|------|------|
| Pint and PHPStan usage, `composer check` | below |
| CI job steps, why each one exists, expected skip/risky counts | [reference-tooling.md](reference-tooling.md) |
| Why a gate reports a finding that is not a defect, and what to do when it is | [reference-edge-cases.md](reference-edge-cases.md) |
| Which docs gates CI runs, and which need both checkouts | [reference-tooling.md — the docs gates](reference-tooling.md#the-docs-gates) |
| What each `locale` job step covers | [localization.md](localization.md) + [reference-localization.md — CI coverage](reference-localization.md#ci-coverage-vs-manual-runs) |
| PR & merge process, and the gap left for SPA-only pull requests | [reference-localization.md — PR & merge process](reference-localization.md#pr--merge-process) |

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

**Limits:** `-h`/`--help`/`-q`/`-v` are Artisan-owned; for full Pint help: `php vendor/bin/pint --help`. Preset `laravel` and the excludes live in `pint.json` (`lang/` is excluded: its `binary_operator_spaces` and `line_ending` fixes would rewrite every locale file and fight the localization guards).

**Workflow:** `pint --dirty` before commit; `composer check:style` before opening the pull request.

## PHPStan (Larastan)

Level 5 over `app/`, the six packages' `src/`, `database/`, `routes/`, and `config/` — `tests/` is out of scope on purpose (a suite is a scaffold, not shipped code). Config: `phpstan.neon`. There is **no baseline**: the tree is clean at level 5.

```bash
php artisan phpstan                 # wrapper, forwards args
composer check:types                # what the gate runs
vendor/bin/phpstan analyse --generate-baseline   # forbidden — fix the finding, not the report
```

Four settings are load-bearing, each with its reason in the config:

| Setting | Why |
|---|---|
| `databaseMigrationsPath` lists every migration dir, including the six under `packages/*/database/migrations` | Without a package's migrations Larastan cannot read its model columns and every `$model->column` in that package reports `property.notFound`. A new package with migrations must be added in the same task. |
| `treatPhpDocTypesAsCertain: false` | PHPDoc here documents payloads that arrive over HTTP, so treating it as proven turns every defensive `??` and `is_array()` into a false positive. |
| `ignoreErrors: property.notFound` under `*/Http/Resources/*` | `JsonResource::$this->field` is `__get` on the wrapped model. |
| `parseModelCastsMethod: true` | Every model here declares `casts()` as a method, not a `$casts` property. With Larastan's default (`false`) the returned array is not read, so an enum-cast column keeps the migration's `string` type and `$model->status->isX()` reports `method.nonObject` — 136 findings that were artifacts, not defects. Turning it on makes the analysed type the type Eloquent actually returns. |

`tmpDir` is `.phpstan.cache`, which is gitignored **and** in `UninstallReferenceScanner::SKIP_DIRS` — the scanner reads the working tree, so a cache of generated PHP would otherwise appear as product-domain references and fail `DomainUninstallManifestTest`.

**Zero baseline is the invariant.** `composer check:types` fails on any finding, so new code is held to level 5 with no allowlist to absorb it — the tree opened on 2026-11-19 at 299 findings and was burned down by typing the generics (`Builder<Model>`, `LengthAwarePaginator<int, Model>`), replacing `SoftDeletes` macros with `withoutGlobalScope(SoftDeletingScope::class)` on the shared repository, and aggregating through `getAttribute()` instead of undeclared aliases. Never reintroduce `phpstan-baseline.neon`; fix the finding it would have named. `selectRaw('COUNT(*) as event_count')` returns an alias that is not a migrated column, so `$row->event_count` is `property.notFound` — read it through `$row->getAttribute('event_count')`.

## `composer check`

`composer check` = `check:style` (Pint `--test`) → `check:types` (PHPStan) → `test` (the whole suite). Run it before opening a pull request; CI runs the same suite plus the localization and docs guards.

## Gates

```bash
php artisan pint --test          # formatting — also a `backend` CI job step
php artisan phpstan              # static analysis — also a `backend` CI job step
php artisan test                 # whole backend suite — what the `backend` CI job runs
php artisan test --testsuite=Package:billing   # one core package's tree alone
php artisan test --filter=Documentation
php artisan test --filter=Locale
php scripts/docs-guard.php
php scripts/docs-cross-repo-links.php   # needs both checkouts; skipped links are reported, not failed
```

The SPA gates are `npm run check:docs`, `npm run check:i18n` and `npm run check:env` (the mode-file value guard — see [reference-tooling.md — the sibling's CI job](reference-tooling.md#the-siblings-ci-job)). The last four commands above are the **docs** gates; their normative statement is owned by [.cursor/rules/docs-pakem.mdc](../../.cursor/rules/docs-pakem.mdc) — run them from there when a task touches documentation, this list is the operational copy. Which of them CI actually runs, and the cross-repo half it cannot, is in [reference-tooling.md — the docs gates](reference-tooling.md#the-docs-gates).

Reproduce the `backend` job locally before pushing — same command, same in-memory database:

```bash
php artisan test                 # whole suite; ~7 min on the dev machine, longer on a runner
```

A green run is `failed: 0`, **not** `passed == tests`: the suite ends with one skipped OCI KMS integration test, seven more cross-repo skips when the sibling checkout is absent, and one risky stdout report — all by design. The exact counts and why they must stay are in [reference-tooling.md](reference-tooling.md#a-green-run-is-failed-0).

Run **one** suite at a time. `tests/bootstrap.php` takes an advisory lock on `bootstrap/cache/.phpunit.lock` and refuses to start a second concurrent run, because `bootstrap/cache` and the temporary domain fixtures are shared and a concurrent run reports phantom errors in files the change never touched. The full reasoning is in [reference-tooling.md — one suite at a time](reference-tooling.md#one-suite-at-a-time).

## Per-package suites

`phpunit.xml` gives each package its own `Package:{slug}` suite, so one package runs alone (`php artisan test --testsuite=Package:billing`); PHPUnit runs every suite in the file by default, so the full run still covers all six trees. A directory may appear in exactly one suite — listing it twice makes PHPUnit reject it for the second suite, warn once per file, and exit non-zero on an otherwise green run; `PackageTestSuiteTest` fails on both a missing wiring and a duplicate.

Those tests run **standalone**, not against the host: each extends `App\Support\Testing\PackageTestCase` (Orchestra Testbench), which boots the kernel providers plus the package's own provider, builds the schema from `App\Support\Testing\KernelSchema` plus the package's migrations, and mounts the package's registry-declared routes under `/api/v1`. Nothing is read from `bootstrap/app.php`, `database/migrations` or `tests/**`, so a consumer who clones the mirror tag runs the same suite; `PackageTestSuiteTest` fails a package test that extends anything else, and `DependencyDirectionTest` fails one that imports `Tests\Concerns\*`, `Database\Seeders\*` or a product domain. A test that needs a host-assembled route, a host fixture or a product row lives in `tests/Domain/Core/{Domain}/` and keeps the host base.

Each package carries its own `phpunit.xml.dist` and a `composer test` script (`@php vendor/bin/phpunit`), so `composer install && composer test` inside `packages/{slug}` works with no monolith. `composer test:standalone` (`scripts/run-standalone-package-suite.php`, the `standalone-packages` CI job) is the proof: it copies each package to a temporary directory, resolves the siblings from source, installs and runs the suite; `PackageStandaloneRunTest` asserts the pieces that runner needs.
