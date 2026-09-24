# Tooling

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

**Workflow:** `pint --dirty` before commit; `pint --test` before opening the pull request. Pint is a **local** step: `.github/workflows/ci.yml` runs one job, `locale`, and nothing in it invokes Pint, so a formatting difference is not caught on the runner — it lands in the next commit. Do not describe `pint --test` as a CI check until a job actually runs it.

## CI (GitHub Actions)

`.github/workflows/ci.yml` — one job, `Localization guards (sibling-free)`, on `push` to `main` / `master` / `main-sandbox` / `main-staging` and on every `pull_request`.

The job is deliberately narrow: it runs only the checks that need **one** repo, so it carries no credentials. Which defect classes that covers, and which four need the sibling checkout, is the table in [localization.md — CI coverage vs manual runs](localization.md#ci-coverage-vs-manual-runs). An earlier version of this workflow checked out `monolith-vue-workers` as a sibling so all six guards could run; it failed at the checkout step on every run, because both repos are private and `GITHUB_TOKEN` is scoped to the repo running the workflow. It was narrowed rather than patched with a personal access token.

Steps, and why each one is there:

| Step | Why |
|---|---|
| `actions/checkout@v4` | |
| `shivammathur/setup-php`, `php-version: '8.4'` | Not cosmetic. `composer.json` requires `^8.4`, and so do the locked `silverspoonmedia/pdf-tools` (dev-main) and `symfony/*` 8.1. On 8.3, `composer install` fails before any test with `Your lock file does not contain a compatible set of packages`. The runner matches the local toolchain for this reason. |
| `actions/cache@v4` on `vendor`, keyed by `composer.lock` | |
| `composer install --prefer-dist --no-interaction --no-progress` | `post-autoload-dump` runs `artisan package:discover`, so this step boots every service provider — it is not a passive download. |
| `php -r "file_put_contents('.env', '');"` | `.env` is gitignored and CI never created one, so `LoadEnvironmentVariables` asked phpdotenv for a missing file on every test that boots the app. `safeLoad()` swallows the failure, but PHPUnit still reports the suppressed read as a warning: 36 of them, one per test, which buries the four `skipped` cross-repo guards in noise. An empty file is enough — every value the suite needs comes from `phpunit.xml` or the job `env:` below. |
| `php artisan test --filter=Locale` | `LocaleParityTest`, `LocaleUsageTest`, and `LocaleGuardTest`, which shells out to every guard in `scripts/` and asserts exit 0. The four cross-repo guards report as **skipped** here, each naming the missing sibling. |
| `php scripts/locale-flat-pack-parity.php`, `php scripts/locale-dynamic-key-check.php` | Run the two sibling-free guards directly as well, so a finding names the offending key and locale in the job log instead of only inside an aggregated PHPUnit failure. |

Job-level `env:` is what lets the install and test steps run with no database service:

```yaml
env:
  APP_KEY: 'base64:…'
  DB_CONNECTION: sqlite
  DB_DATABASE: ':memory:'
  SESSION_DRIVER: array
  CACHE_STORE: array
  QUEUE_CONNECTION: sync
  MAIL_MAILER: array
  COLUMNS: '200'
```

`SettingsServiceProvider::boot()` calls `Schema::hasTable('system_settings')` ([SettingsServiceProvider.php](../../app/Providers/SettingsServiceProvider.php)). Without `DB_CONNECTION=sqlite`, `config/database.php` defaults to `mysql`, the runner has no MySQL, and the install step fails. `sqlite :memory:` answers `hasTable()` with `false` and needs no service. These are real environment variables and Laravel's dotenv repository is immutable, so a populated `.env` could not override them either.

`COLUMNS: '200'` widens Collision's output. The runner's default is 80 columns, which truncated findings to `file_get_contents(/ho…` — the opposite of a readable gate.

**No branch protection.** Both repos are private on the GitHub **free** plan, so required status checks are unavailable: `gh api repos/silverspoonmedia/monolith/branches/main/protection` answers `403 Upgrade to GitHub Pro or make this repository public`. Nothing on GitHub blocks a merge with a red run. The day-to-day process, and the gap this leaves for SPA-only pull requests, is in [localization.md — PR & merge process](localization.md#pr--merge-process).
