# Tooling — reference

The parts of the toolchain that only matter when a run misbehaves: what each CI job step is for, what a green run actually looks like, and why the workflow is narrow. Usage and the gate list are in the brief.

## CI (GitHub Actions)

`.github/workflows/ci.yml` — two jobs, `locale` (`Localization guards (sibling-free)`) and `backend` (`Backend suite (sqlite in-memory)`), on `push` to `main` / `master` / `main-sandbox` / `main-staging` and on every `pull_request`.

Both jobs are deliberately narrow in the same way: they run only checks that need **one** repo, so the workflow carries no credentials. Which defect classes the `locale` job covers, and which four guards need the sibling checkout, is the table in [reference-localization.md — CI coverage vs manual runs](reference-localization.md#ci-coverage-vs-manual-runs). An earlier version of this workflow checked out `monolith-vue-workers` as a sibling so all six guards could run; it failed at the checkout step on every run, because both repos are private and `GITHUB_TOKEN` is scoped to the repo running the workflow. It was narrowed rather than patched with a personal access token.

### `locale` job

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

### `backend` job

Runs `php artisan test` — the whole suite (`tests/Unit`, `tests/Feature`, `tests/Domain`), not a `--testsuite` subset. It exists because `--filter=Locale` was the only thing the workflow ran: a change that broke `tests/Domain/**` merged green, and the vTual domain alone is 60+ feature test files. `--testsuite=Domain` was the alternative and was rejected: `tests/Unit` and `tests/Feature` boot the same app and cost little, and a subset invites "which suite was that in?" as a permanent question.

The job reuses the `locale` job's environment through a YAML alias (`env: &test-env` / `env: *test-env`), so the two cannot drift into different database settings. It repeats the PHP pin, the Composer cache, the install, and the empty `.env` step for the same reasons as `locale`.

Two properties make a full-suite run possible on a bare runner, and both are pinned in `phpunit.xml` rather than in the workflow:

| Setting | Why the suite needs it |
|---|---|
| `DB_CONNECTION=sqlite` / `DB_DATABASE=:memory:` | Every domain test migrates an in-memory database. The job `env:` sets the same values because `composer install` runs `package:discover` before PHPUnit reads `phpunit.xml`. |
| `BROADCAST_CONNECTION=null`, `QUEUE_CONNECTION=sync`, `MAIL_MAILER=array`, `CACHE_STORE=array` | No Redis, no queue worker, no mail service on the runner. Realtime assertions use `Event::fake()`, so a null broadcaster is enough. |

The job is the slow half of the workflow (the `locale` job is seconds). That is the price of not having `tests/Domain/**` unguarded, and it is why the suite must stay service-free: the moment a test needs MySQL, Redis, or the network, the job either grows a service container or starts failing intermittently, and a flaky gate gets ignored — which is worse than no gate.

## A green run is `failed: 0`

**A green run is `failed: 0`, not `passed == tests`.** The suite ends at `2233 tests, 2232 passed, 1 skipped, 1 risky` (measured 2026-09-24) and both non-passed counts are by design:

| Count | Source | Why it is expected |
|---|---|---|
| `skipped: 1` | `tests/Feature/Oci/OciKmsConnectionIntegrationTest.php` | Live OCI KMS connectivity check; it skips unless `OCI_KMS_INTEGRATION_TEST=true` is exported. Never enable it in CI — it needs real cloud credentials and the network. |
| `risky: 1` | `tests/Domain/Service/Unit/Services/ArchivdAdminJourneyScenarioTest.php` | The test prints its scenario report (a JSON journey dump) to stdout, which PHPUnit reports as risky output. Pre-existing since 2026-09-08 and unrelated to the vTual work; it pollutes the job log but does not fail the run. |

Do not "fix" either by adding `--fail-on-risky` or by deleting the skip: the first turns a cosmetic warning into a red build, the second removes the only coverage of the live-cloud path. If the Archivd report output ever needs silencing, capture it with `ob_start()` / `ob_get_clean()` inside that test rather than suppressing the whole suite's output.

`DocsStructureGuardTest` adds a third non-passed count on the runner: it **skips**, because the docs guard spans both repos and the sibling is private. The test asserts the guard fails loudly in that case rather than reporting a false green, so the skip is visible in the summary instead of hiding behind a pass.

## Job-level `env:`

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

**No branch protection.** Both repos are private on the GitHub **free** plan, so required status checks are unavailable: `gh api repos/silverspoonmedia/monolith/branches/main/protection` answers `403 Upgrade to GitHub Pro or make this repository public`. Nothing on GitHub blocks a merge with a red run. The day-to-day process, and the gap this leaves for SPA-only pull requests, is in [reference-localization.md — PR & merge process](reference-localization.md#pr--merge-process).
