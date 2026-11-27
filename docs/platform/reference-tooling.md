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

Runs `php artisan test` — the host suites this `phpunit.xml` declares (`Unit`, `Feature`, and the documentation/localization guards), not a `--testsuite` subset. Package suites run in the package mirrors (`composer test`); this fork has no `packages/` tree and no `tests/` in the install to wire a `Package:{slug}` suite to. It exists because `--filter=Locale` was the only thing the workflow ran: a change that broke a host feature test merged green. `--testsuite=Domain` was the alternative and was rejected: the other suites boot the same app and cost little, and a subset invites "which suite was that in?" as a permanent question.

The job reuses the `locale` job's environment through a YAML alias (`env: &test-env` / `env: *test-env`), so the two cannot drift into different database settings. It repeats the PHP pin, the Composer cache, the install, and the empty `.env` step for the same reasons as `locale`.

Two steps before the suite are the quality gates, both added 2026-11-19:

| Step | Why it is a job step |
|---|---|
| `vendor/bin/pint --test` | The whole tree was formatted once, by hand, before this step existed. From then on `--test` fails only on drift a diff introduced — the check is cheap and the failure names the file. |
| `vendor/bin/phpstan analyse` | Level 5 over `app/`, `database/`, `routes/`, `config/` (the packaged code arrives as `vendor/silverspoonmedia/*` and is analysed in the mirrors); there is no baseline, so the step fails on any finding. `JWT_SECRET` is in the job `env:` because Larastan boots the app: without a secret the JWT guard extension is absent and `JWTAuth::parser()` analyses as an undefined static method — three findings that do not exist locally. |

Two properties make a full-suite run possible on a bare runner, and both are pinned in `phpunit.xml` rather than in the workflow:

| Setting | Why the suite needs it |
|---|---|
| `DB_CONNECTION=sqlite` / `DB_DATABASE=:memory:` | Every domain test migrates an in-memory database. The job `env:` sets the same values because `composer install` runs `package:discover` before PHPUnit reads `phpunit.xml`. |
| `BROADCAST_CONNECTION=null`, `QUEUE_CONNECTION=sync`, `MAIL_MAILER=array`, `CACHE_STORE=array` | No Redis, no queue worker, no mail service on the runner. Realtime assertions use `Event::fake()`, so a null broadcaster is enough. |

The job is the slow half of the workflow (the `locale` job is seconds). That is why the suite must stay service-free: the moment a test needs MySQL, Redis, or the network, the job either grows a service container or starts failing intermittently, and a flaky gate gets ignored — which is worse than no gate.

## A green run is `failed: 0`

**A green run is `failed: 0`, not `passed == tests`.** This fork's suite is small — host suites only — and every count below is measured in *this* checkout, not copied from the living line:

| Source | Result |
|---|---|
| Local `php artisan test`, siblings present (re-measured 2026-11-28) | `"tests":77, "passed":77, "assertions":716, "skipped":0, "risky":0` (~1 min) |
| `backend` CI job, sibling-free | same total; the cross-repo cases in `LocaleGuardTest` ×4 and `Docs*Test` ×3 report as skipped instead of reading the sibling |

Both are green: `failed: 0` is the contract. There is no `skipped: 1` OCI row here — the live-cloud integration test lives in the kernel's own suite, which runs in the `silverspoonmedia/platform` mirror, not in this install.

**Why the fork total is `77` and not the living line's four-figure corpus.** The packaged core domains' suites (`packages/*/tests` in the source repo, one `Package:{slug}` suite each) are not wired here and cannot be: `tests/` is `export-ignore`d from every tag, so `vendor/silverspoonmedia/*/tests/` does not exist and this `phpunit.xml` lists only host suites. There is also no product domain, so `tests/Domain/**` is empty. What remains is real coverage of *this* checkout: `KernelBootTest` (the kernel and every package provider boot, registry resolves, routes mount), `DomainRegistryTest`, `RemoveLocaleCommandTest`, the six localization guards and the five documentation guards. A package's own suite runs from a clone of its mirror (`composer install && composer test`); the living line's `standalone-packages` job proves that path.

`assertions: 716` tracks the same thing relative to this corpus — a config file added or removed moves it without moving `tests`. Re-measure both numbers whenever a suite is added, a guard is added, or a `config/*.php` file appears or disappears; a stale total here reads as a corpus failure in the next session that touches it. The living line's own history (and the unexplained assertion drop it went through) is its archive's business, not this page's.

| Count | Source | Why it is expected |
|---|---|---|
| `skipped: +7` (sibling-free only) | `LocaleGuardTest` ×4 — `locale-prose-scan.php`, `locale-rawkey-scan.php`, `locale-pack-parity.php`, `locale-glossary-scan.php` — and `DocsStructureGuardTest`, `DocsCrossRepoLinkTest`, `DocsBudgetGuardTest` | Each cross-repo case first asserts the guard **fails loudly** without the sibling (`exit 1`, `cannot see the SPA repo`) and only then marks itself skipped. A pass would claim coverage the run never had, so the skip is the honest summary — see [reference-localization.md — CI coverage vs manual runs](reference-localization.md#ci-coverage-vs-manual-runs). |

Do not delete a skip to make the numbers tidy: it removes the only honest statement that the guard ran and could not see half its input.

## One suite at a time

**Never run two PHPUnit processes against this checkout at once.** The suite is not parallel-safe, and the failure it produces is indistinguishable from a real regression: `bootstrap/cache` is per-repo, `Filesystem::replace()` writes the config cache through `tempnam(dirname($path), basename($path))` + `rename()`, and on Windows that `rename()` fails with `Access is denied` whenever a second process is holding the same path. The temporary domain fixtures that `DomainCommandsTest` and `DomainUninstallManifestTest` create are shared for the same reason.

The symptom is not a red gate you can attribute. It is six errors in whichever run lost — `bootstrap/cache/services.php` rename denied, missing `ZzDemo` / `zz-child.php` fixtures — in files the change under review never touched, next to a focused rerun of the same tests that passes. That is exactly the shape that gets a working diff "fixed" until it breaks.

`tests/bootstrap.php` holds an advisory `flock` on `bootstrap/cache/.phpunit.lock` and exits `1` with the lock path when another run holds it, so a second run stops before booting anything instead of corrupting the first. The OS releases the lock when the process exits, so a killed run cannot wedge the suite; delete the file only when no run is active. This is a harness guard, not a product behavior — if a future change makes the suite parallel-safe (no shared `bootstrap/cache` writes, per-process domain fixtures), the lock is what should be deleted, not worked around.

The lock only stops a second *PHPUnit*. Heavy I/O against the same checkout while a run is in flight breaks it just as well, and that cause is invisible in the summary: a `git status --untracked-files=all` that walks `vendor/` is enough to make the `.env` rewrite in `EnvInstallCommandTest` fail with `Permission denied` and to push a full-tree walk past `DocsStructureGuardTest`'s 180s timeout. Both are errors in files the change under review never touched, both disappear on a focused rerun. Keep the machine quiet for the duration — the suite owns the checkout.

One cross-repo guard deliberately does **not** skip. `DomainUninstallManifestTest` proves the sibling-absent path without a sibling by binding a scanner whose `root()` returns `null` for the SPA and docs repos (`test_the_target_guard_degrades_loudly_when_a_sibling_is_absent`), then asserting the plan reports `sibling checkout absent — section [...] skipped (unverifiable, not clean)`. It runs green on the runner, so the degradation is **covered** there rather than only counted as skipped. Copy that shape when a new cross-repo guard needs runner coverage: a skip proves the guard ran and could not see half its input; an injected absent sibling proves the guard is *correct* about it.

Local scratch under `storage/logs/`, `.phpstan.cache/`, `.phpunit.result.cache`, `storage/framework/{cache,views,sessions}/` and `bootstrap/cache/*.php` is regenerated by the next run and is safe to empty at any time — it is gitignored, and nothing asserts on it. Do not let an editor or a script *track* anything new under those paths; the only committed files there are their `.gitignore` placeholders. What a local cleanup kept and why is recorded in [archive/local-residue-retention.md](../archive/local-residue-retention.md).

## The docs gates

`php artisan test` carries three documentation suites, and the docs guard is not a CI step of its own:

| Gate | Runner | Needs the sibling |
|---|---|---|
| `php scripts/docs-guard.php` | `backend` job, through `DocsStructureGuardTest` | No — it runs `--repo=backend` when the sibling is absent |
| `php artisan test --filter=Documentation` | `backend` job | No; `DocsCrossRepoLinkTest` and `DocsBudgetGuardTest` skip the sibling half |
| `php scripts/docs-cross-repo-links.php` | **nothing** — developer machine only | Yes |

The third row is the honest gap. Links that leave this repo (`../../../cored-frontend/…`, `../../../../gateway/…`) cannot be resolved on the runner: both repos are private, so `GITHUB_TOKEN` cannot read the sibling, and an earlier attempt to check it out failed at the step before any test ran (see the `locale` job note above). The guard therefore treats an absent sibling as **unverifiable, not broken** — correct, but it means a green run proves nothing about those links.

Two things narrow the gap without a credential:

- `docs-cross-repo-links.php` is the fail-closed check for the present-sibling case. Run it locally, and after any change that moves a file the sibling links to.
- Both guards print the skip on their OK line (`… N cross-repo link(s) unverified — sibling checkout absent`), so a green run cannot be misread as "every link was checked".

The behaviour itself is covered on the runner: `DocsCrossRepoLinkTest` drives the same library against a throwaway two-repo fixture, asserting that an absent sibling is skipped, a missing target is reported, and a dead `#anchor` is reported. Closing the gap fully needs a fine-grained PAT scoped to read the sibling — deliberately not taken; the workflow carries no credentials by design.

The guard resolves each rule's `globs:` by walking the checkout, so the walk is part of the gate's cost. It prunes `vendor/`, `node_modules/`, `.git/`, `storage/` and `public/build/` **while descending**, and builds the path list once per repo. The skip list used to be applied *after* `RecursiveDirectoryIterator` had already entered those trees, so every glob of every rule re-walked them path by path. That made the guard's runtime a property of the machine instead of the repo: under load the walk blew `DocsStructureGuardTest`'s 180s timeout and reported a broken doc split when the real cause was a slow disk. Pruning only drops paths the post-filter discarded anyway, so the finding set is unchanged.

## The sibling's CI job

`cored-frontend/.github/workflows/ci.yml` runs `type-check` → `check:i18n` → `check:docs` → **`check:env`** → `test` → `build-only`, and the deploy workflow repeats the same gates before a build so a deploy can never bypass them.

`npm run check:env` (`scripts/check-mode-env.mjs`) guards the **values** of the three tracked Vite mode files, which the branch→mode→env mapping never did — `.env.production` once shipped pointing at the sandbox API origin. It fails on a missing or empty required `VITE_*` key, a placeholder value, production or staging pointing at a host containing `sandbox`, two environments sharing an API origin, and a leftover `TODO`. A non-origin credential shared between environments (hCaptcha sitekey, Pusher app key) is a **warning**, not a failure: it is drift worth fixing, but it does not send traffic to the wrong backend. Adding an environment means adding it to `MODES` in that script; the operator-facing write-up is sibling [deployment.md](../../../cored-frontend/docs/deployment.md), section *Mode file values*.

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

`SettingsServiceProvider::boot()` calls `Schema::hasTable('system_settings')` ([SettingsServiceProvider.php](../../vendor/silverspoonmedia/platform/src/Providers/SettingsServiceProvider.php)). Without `DB_CONNECTION=sqlite`, `config/database.php` defaults to `mysql`, the runner has no MySQL, and the install step fails. `sqlite :memory:` answers `hasTable()` with `false` and needs no service. These are real environment variables and Laravel's dotenv repository is immutable, so a populated `.env` could not override them either.

`COLUMNS: '200'` widens Collision's output. The runner's default is 80 columns, which truncated findings to `file_get_contents(/ho…` — the opposite of a readable gate.

**No branch protection.** Both repos are private on the GitHub **free** plan, so required status checks are unavailable: `gh api repos/silverspoonmedia/cored-backend/branches/main/protection` answers `403 Upgrade to GitHub Pro or make this repository public`. Nothing on GitHub blocks a merge with a red run. The day-to-day process, and the gap this leaves for SPA-only pull requests, is in [reference-localization.md — PR & merge process](reference-localization.md#pr--merge-process).
