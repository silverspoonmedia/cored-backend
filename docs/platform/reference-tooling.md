# Tooling — reference

The parts of the toolchain that only matter when a run misbehaves: what each CI job step is for, what a green run actually looks like, and why the workflow is narrow. Usage and the gate list are in the brief.

## CI (GitHub Actions)

`.github/workflows/ci.yml` — two jobs, `locale` (`Localization guards (sibling-free)`) and `backend` (`Backend suite (sqlite in-memory)`, which now starts with Pint and PHPStan), on `push` to `main` / `master` / `main-sandbox` / `main-staging` and on every `pull_request`.

Both jobs are deliberately narrow in the same way: they run only checks that need **one** repo, so the workflow carries no credentials. Which defect classes the `locale` job covers, and which four guards need the sibling checkout, is the table in [reference-localization.md — CI coverage vs manual runs](reference-localization.md#ci-coverage-vs-manual-runs). An earlier version of this workflow checked out `monolith-vue-workers` as a sibling so all the cross-repo guards could run; it failed at the checkout step on every run, because both repos are private and `GITHUB_TOKEN` is scoped to the repo running the workflow. It was narrowed rather than patched with a personal access token.

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
| `php scripts/locale-flat-pack-parity.php`, `php scripts/locale-dynamic-key-check.php`, `php scripts/locale-curation-flag-scan.php` | Run the three sibling-free guards directly as well, so a finding names the offending key and locale in the job log instead of only inside an aggregated PHPUnit failure. |

### `backend` job

Runs `php artisan test` — the whole suite (`tests/Unit`, `tests/Feature`, `tests/Domain`), not a `--testsuite` subset. It exists because `--filter=Locale` was the only thing the workflow ran: a change that broke `tests/Domain/**` merged green, and a single product domain can be 60+ feature test files. `--testsuite=Domain` was the alternative and was rejected: `tests/Unit` and `tests/Feature` boot the same app and cost little, and a subset invites "which suite was that in?" as a permanent question.

The job reuses the `locale` job's environment through a YAML alias (`env: &test-env` / `env: *test-env`), so the two cannot drift into different database settings. It repeats the PHP pin, the Composer cache, the install, and the empty `.env` step for the same reasons as `locale`.

Two steps before the suite are the quality gates, both added 2026-11-19:

| Step | Why it is a job step |
|---|---|
| `vendor/bin/pint --test` | The whole tree was formatted once, by hand, before this step existed. From then on `--test` fails only on drift a diff introduced — the check is cheap and the failure names the file. |
| `vendor/bin/phpstan analyse` | Level 5 over `app/`, the packages' `src/`, `database/`, `routes/`, `config/`; `phpstan-baseline.neon` names the debt adopted at the same time. `JWT_SECRET` is in the job `env:` because Larastan boots the app: without a secret the JWT guard extension is absent and `JWTAuth::parser()` analyses as an undefined static method — three findings that do not exist locally, so the committed baseline would stop matching on the runner. |

Two properties make a full-suite run possible on a bare runner, and both are pinned in `phpunit.xml` rather than in the workflow:

| Setting | Why the suite needs it |
|---|---|
| `DB_CONNECTION=sqlite` / `DB_DATABASE=:memory:` | Every domain test migrates an in-memory database. The job `env:` sets the same values because `composer install` runs `package:discover` before PHPUnit reads `phpunit.xml`. |
| `BROADCAST_CONNECTION=null`, `QUEUE_CONNECTION=sync`, `MAIL_MAILER=array`, `CACHE_STORE=array` | No Redis, no queue worker, no mail service on the runner. Realtime assertions use `Event::fake()`, so a null broadcaster is enough. |

The job is the slow half of the workflow (the `locale` job is seconds). That is the price of not having `tests/Domain/**` unguarded, and it is why the suite must stay service-free: the moment a test needs MySQL, Redis, or the network, the job either grows a service container or starts failing intermittently, and a flaky gate gets ignored — which is worse than no gate.

## A green run is `failed: 0`

**A green run is `failed: 0`, not `passed == tests`.** The suite is `1718` tests either way; the only thing that moves is the split between `passed` and `skipped`, because seven cross-repo cases skip when the sibling checkout is absent. The local row is quoted exactly as that environment prints it. The runner row is the same run with those seven cases moved from `passed` to `skipped`, so it is derived, not quoted:

| Source | Result |
|---|---|
| Local `php artisan test`, both siblings present (re-measured 2026-11-19) | JSON report: `"tests":1718, "passed":1717, "skipped":1, "risky":1, "assertions":18108` (~7 min) |
| `backend` CI job, sibling-free | console summary: `1 risky, 8 skipped, 1710 passed` — derived: the same `1718` total with the seven cross-repo cases skipped |

Both are green: `failed: 0` is the contract, and the total is `1718` in both. The seven extra skips on the runner are the cross-repo cases below; the single `risky` test is counted inside `passed` by the JSON report and as its own count by the console summary, so the two `passed` figures are not directly comparable.

The `1718` is the current corpus. It moved from `1698` when the five packaged core domains' suites (`tests/Packages`, 496 cases) were added to the runner, then to `1711` with the quality-gate and dependency-direction tests, and to `1718` with the subject-label seam tests — the pre-removal figure was `2454`, and the 2026-10-07 product-domain removal plus the migration squash are what took that difference. Re-measure this pair whenever a product domain is removed, a testsuite is added, or a `config/*.php` file appears or disappears — a stale total here reads as a corpus failure in the next session that touches it.

The `assertions` field is the one number here that moves while the corpus does not. `SettingRegistryEnvPolicyTest::test_operational_env_keys_are_not_used_in_config_files` asserts once per (config file × the `45` keys in `removedOperationalEnvKeys()`), so deleting `config/purifier.php` on 2026-10-12 took exactly `45` off the total — `18392` → `18347`, and the packaged suites brought it to `18095`, the quality gates to `18108` — with `tests` unchanged at `1718`. `tests`, `skipped` and `risky` are the fields that track the corpus; `assertions` tracks the corpus plus the config surface.

| Count | Source | Why it is expected |
|---|---|---|
| `skipped: 1` (any checkout) | `tests/Feature/Oci/OciKmsConnectionIntegrationTest.php` | Live OCI KMS connectivity check; it skips unless `OCI_KMS_INTEGRATION_TEST=true` is exported. Never enable it in CI — it needs real cloud credentials and the network. |
| `skipped: +7` (sibling-free only) | `LocaleGuardTest` ×4 — `locale-prose-scan.php`, `locale-rawkey-scan.php`, `locale-pack-parity.php`, `locale-glossary-scan.php` — and `DocsStructureGuardTest`, `DocsCrossRepoLinkTest`, `DocsBudgetGuardTest` | Each cross-repo case first asserts the guard **fails loudly** without the sibling (`exit 1`, `cannot see the SPA repo`) and only then marks itself skipped. A pass would claim coverage the run never had, so the skip is the honest summary — see [reference-localization.md — CI coverage vs manual runs](reference-localization.md#ci-coverage-vs-manual-runs). |
| `risky: 1` | `tests/Domain/Archivd/Unit/Billing/ArchivdAdminJourneyScenarioTest.php` | The test prints its scenario report (a JSON journey dump) to stdout, which PHPUnit reports as risky output. Pre-existing since 2026-09-08; it pollutes the job log but does not fail the run. |

Do not "fix" either by adding `--fail-on-risky` or by deleting the skip: the first turns a cosmetic warning into a red build, the second removes the only coverage of the live-cloud path. If the Archivd report output ever needs silencing, capture it with `ob_start()` / `ob_get_clean()` inside that test rather than suppressing the whole suite's output.

## One suite at a time

**Never run two PHPUnit processes against this checkout at once.** The suite is not parallel-safe, and the failure it produces is indistinguishable from a real regression: `bootstrap/cache` is per-repo, `Filesystem::replace()` writes the config cache through `tempnam(dirname($path), basename($path))` + `rename()`, and on Windows that `rename()` fails with `Access is denied` whenever a second process is holding the same path. The temporary domain fixtures that `DomainCommandsTest` and `DomainUninstallManifestTest` create are shared for the same reason.

The symptom is not a red gate you can attribute. It is six errors in whichever run lost — `bootstrap/cache/services.php` rename denied, missing `ZzDemo` / `zz-child.php` fixtures — in files the change under review never touched, next to a focused rerun of the same tests that passes. That is exactly the shape that gets a working diff "fixed" until it breaks.

`tests/bootstrap.php` holds an advisory `flock` on `bootstrap/cache/.phpunit.lock` and exits `1` with the lock path when another run holds it, so a second run stops before booting anything instead of corrupting the first. The OS releases the lock when the process exits, so a killed run cannot wedge the suite; delete the file only when no run is active. This is a harness guard, not a product behavior — if a future change makes the suite parallel-safe (no shared `bootstrap/cache` writes, per-process domain fixtures), the lock is what should be deleted, not worked around.

The lock only stops a second *PHPUnit*. Heavy I/O against the same checkout while a run is in flight breaks it just as well, and that cause is invisible in the summary: a `git status --untracked-files=all` that walks `vendor/` is enough to make the `.env` rewrite in `EnvInstallCommandTest` fail with `Permission denied` and to push a full-tree walk past `DocsStructureGuardTest`'s 180s timeout. Both are errors in files the change under review never touched, both disappear on a focused rerun. Keep the machine quiet for the duration — the suite owns the checkout.

One cross-repo guard deliberately does **not** skip. `DomainUninstallManifestTest` proves the sibling-absent path without a sibling by binding a scanner whose `root()` returns `null` for the SPA and docs repos (`test_the_target_guard_degrades_loudly_when_a_sibling_is_absent`), then asserting the plan reports `sibling checkout absent — section [...] skipped (unverifiable, not clean)`. It runs green on the runner, so the degradation is **covered** there rather than only counted as skipped. Copy that shape when a new cross-repo guard needs runner coverage: a skip proves the guard ran and could not see half its input; an injected absent sibling proves the guard is *correct* about it.

## The docs gates

`php artisan test` carries three documentation suites, and the docs guard is not a CI step of its own:

| Gate | Runner | Needs the sibling |
|---|---|---|
| `php scripts/docs-guard.php` | `backend` job, through `DocsStructureGuardTest` | No — it runs `--repo=backend` when the sibling is absent |
| `php artisan test --filter=Documentation` | `backend` job | No; `DocsCrossRepoLinkTest` and `DocsBudgetGuardTest` skip the sibling half |
| `php scripts/docs-cross-repo-links.php` | **nothing** — developer machine only | Yes |

The third row is the honest gap. Links that leave this repo (`../../../monolith-vue-workers/…`, `../../../../gateway/…`) cannot be resolved on the runner: both repos are private, so `GITHUB_TOKEN` cannot read the sibling, and an earlier attempt to check it out failed at the step before any test ran (see the `locale` job note above). The guard therefore treats an absent sibling as **unverifiable, not broken** — correct, but it means a green run proves nothing about those links.

Two things narrow the gap without a credential:

- `docs-cross-repo-links.php` is the fail-closed check for the present-sibling case. Run it locally, and after any change that moves a file the sibling links to.
- Both guards print the skip on their OK line (`… N cross-repo link(s) unverified — sibling checkout absent`), so a green run cannot be misread as "every link was checked".

The behaviour itself is covered on the runner: `DocsCrossRepoLinkTest` drives the same library against a throwaway two-repo fixture, asserting that an absent sibling is skipped, a missing target is reported, and a dead `#anchor` is reported. Closing the gap fully needs a fine-grained PAT scoped to read the sibling — deliberately not taken; the workflow carries no credentials by design.

The guard resolves each rule's `globs:` by walking the checkout, so the walk is part of the gate's cost. It prunes `vendor/`, `node_modules/`, `.git/`, `storage/` and `public/build/` **while descending**, and builds the path list once per repo. The skip list used to be applied *after* `RecursiveDirectoryIterator` had already entered those trees, so every glob of every rule re-walked them path by path. That made the guard's runtime a property of the machine instead of the repo: under load the walk blew `DocsStructureGuardTest`'s 180s timeout and reported a broken doc split when the real cause was a slow disk. Pruning only drops paths the post-filter discarded anyway, so the finding set is unchanged.

## The sibling's CI job

`monolith-vue-workers/.github/workflows/ci.yml` runs `type-check` → `check:i18n` → `check:docs` → **`check:env`** → `test` → `build-only`, and the deploy workflow repeats the same gates before a build so a deploy can never bypass them.

`npm run check:env` (`scripts/check-mode-env.mjs`) guards the **values** of the three tracked Vite mode files, which the branch→mode→env mapping never did — `.env.production` once shipped pointing at the sandbox API origin. It fails on a missing or empty required `VITE_*` key, a placeholder value, production or staging pointing at a host containing `sandbox`, two environments sharing an API origin, and a leftover `TODO`. A non-origin credential shared between environments (hCaptcha sitekey, Pusher app key) is a **warning**, not a failure: it is drift worth fixing, but it does not send traffic to the wrong backend. Adding an environment means adding it to `MODES` in that script; the operator-facing write-up is sibling [deployment.md](../../../monolith-vue-workers/docs/deployment.md), section *Mode file values*.

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

`SettingsServiceProvider::boot()` calls `Schema::hasTable('system_settings')` ([SettingsServiceProvider.php](../../packages/platform/src/Providers/SettingsServiceProvider.php)). Without `DB_CONNECTION=sqlite`, `config/database.php` defaults to `mysql`, the runner has no MySQL, and the install step fails. `sqlite :memory:` answers `hasTable()` with `false` and needs no service. These are real environment variables and Laravel's dotenv repository is immutable, so a populated `.env` could not override them either.

`COLUMNS: '200'` widens Collision's output. The runner's default is 80 columns, which truncated findings to `file_get_contents(/ho…` — the opposite of a readable gate.

**No branch protection.** Both repos are private on the GitHub **free** plan, so required status checks are unavailable: `gh api repos/silverspoonmedia/monolith/branches/main/protection` answers `403 Upgrade to GitHub Pro or make this repository public`. Nothing on GitHub blocks a merge with a red run. The day-to-day process, and the gap this leaves for SPA-only pull requests, is in [reference-localization.md — PR & merge process](reference-localization.md#pr--merge-process).
