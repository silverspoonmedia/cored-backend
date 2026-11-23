<?php

namespace Tests\Domain\Core;

use Tests\TestCase;

/**
 * What an integration CI job asserts: every package directory has a complete
 * standalone toolchain.
 *
 * Every other package guard reads a file. This one describes the run that
 * proves the files are enough — the workflow copies `packages/{slug}` to a
 * temporary directory, points Composer at the sibling sources, runs
 * `composer install` and `composer test` inside the copy, and asserts the exit
 * code. It is the only check that notices a package whose `phpunit.xml.dist`
 * misses a directory, whose manifest lacks `orchestra/testbench`, or whose test
 * still reaches for `Tests\Concerns\*` — a published tag that cannot run its own
 * suite.
 *
 * The run itself is `scripts/run-standalone-package-suite.php`, driven by the
 * `standalone-packages` CI job: a Composer install per package is minutes of
 * network, which does not belong in the in-memory suite. Here the cheap half is
 * asserted — that each package carries the two files that run defines
 * (`phpunit.xml.dist` and a `test` script). The directory list below is what
 * makes the job fire: a package that moves under `tests/` is listed in the
 * workflow's `paths:` filter, so a change there is never silently untested.
 */
class PackageStandaloneRunTest extends TestCase
{
    /**
     * Directories whose change must schedule the standalone job.
     *
     * @var list<string>
     */
    public const DIRECTORIES = [
        'packages',
        'client-scaffold',
        'tests/Domain/Core/PackageStandaloneRunTest.php',
        'tests/Domain/Core/ConsumerScaffoldTest.php',
        'scripts/run-standalone-package-suite.php',
        'scripts/run-consumer-scaffold-check.php',
        'scripts/run-vcs-consumer-check.php',
        '.github/workflows/ci.yml',
    ];

    /**
     * The VCS consumer check is the half the scaffold run cannot provide.
     *
     * `run-consumer-scaffold-check.php` rewrites the repositories to `path`, so it
     * resolves whatever this checkout pins and never notices a mirror missing the
     * released tag. This script installs from the private mirrors and is a release
     * gate rather than part of the in-memory suite (network plus an org token).
     */
    public function test_the_vcs_consumer_runner_exists(): void
    {
        $runner = base_path('scripts/run-vcs-consumer-check.php');

        $this->assertFileExists($runner, 'The VCS consumer runner is missing — nothing proves a consumer resolves the published tags.');

        $contents = (string) file_get_contents($runner);

        $this->assertStringContainsString('composer.lock', $contents, 'The VCS consumer check must assert what the install locked, or a path fallback would pass it.');
        $this->assertStringContainsString('silverspoonmedia/platform', $contents, 'The VCS consumer check must name the release line it holds the mirrors to.');

        $documentation = (string) file_get_contents(base_path('docs/integration/client-project.md'));

        $this->assertStringContainsString('run-vcs-consumer-check.php', $documentation, 'The consumer-side recipe must name the VCS check, or nobody runs it before a release.');
    }

    public function test_every_package_can_run_its_own_suite(): void
    {
        $violations = [];

        foreach ($this->packages() as $slug => $package) {
            if (! is_dir(base_path('packages/'.$slug.'/tests'))) {
                continue;
            }

            if (! is_file(base_path('packages/'.$slug.'/phpunit.xml.dist'))) {
                $violations[] = $package.' has no phpunit.xml.dist — `composer test` inside a cloned tag would run nothing';
            }

            $manifest = json_decode((string) file_get_contents(base_path('packages/'.$slug.'/composer.json')), true);

            if (! isset($manifest['scripts']['test'])) {
                $violations[] = $package.' composer.json has no `test` script';
            }

            if (! isset($manifest['require-dev']['orchestra/testbench'])) {
                $violations[] = $package.' does not require orchestra/testbench in require-dev';
            }

            // A mirror tag carries no `vendor/`: `vendor/bin/phpunit` only exists
            // after the consumer's own install, and `composer test` runs that
            // install, so the script must address it through `@php`.
            $script = (string) ($manifest['scripts']['test'][0] ?? '');

            if (! str_starts_with($script, '@php ')) {
                $violations[] = $package.' `test` script runs "'.$script.'" — use `@php vendor/bin/phpunit` so it resolves on a fresh install and on Windows';
            }
        }

        $this->assertSame([], $violations, implode("\n", $violations));
    }

    public function test_the_standalone_runner_exists(): void
    {
        $this->assertFileExists(
            base_path('scripts/run-standalone-package-suite.php'),
            'The standalone package runner is missing — the `standalone-packages` CI job would call nothing.',
        );
    }

    /**
     * The consumer template is the other half of the same job.
     *
     * A package suite proves one package in isolation; the scaffold run proves the
     * six assemble into a bootable project. Without the script the job would still
     * pass while `client-scaffold` rotted.
     */
    public function test_the_consumer_scaffold_runner_exists(): void
    {
        $this->assertFileExists(
            base_path('scripts/run-consumer-scaffold-check.php'),
            'The consumer scaffold runner is missing — the `Consumer scaffold` CI step would call nothing.',
        );

        $workflow = (string) file_get_contents(base_path('.github/workflows/ci.yml'));

        $this->assertStringContainsString(
            'scripts/run-consumer-scaffold-check.php',
            $workflow,
            'The `standalone-packages` job must run the consumer scaffold check; otherwise only the packages are proven and the template is not.',
        );

        $this->assertDirectoryExists(
            base_path('client-scaffold/config'),
            'client-scaffold must be a real template — the scaffold check copies it.',
        );
    }

    /**
     * @return array<string, string>
     */
    private function packages(): array
    {
        $packages = [];

        /** @var array<string, array<string, mixed>> $domains */
        $domains = config('domains', []);

        foreach ($domains as $entry) {
            $package = $entry['package'] ?? null;

            if (is_string($package)) {
                $packages[substr($package, strpos($package, '/') + 1)] = $package;
            }
        }

        return $packages;
    }
}
