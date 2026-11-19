<?php

namespace Tests\Domain\Core;

use Tests\TestCase;

/**
 * Keeps `phpunit.xml` and the package tree in agreement.
 *
 * A package's tests only run because the root `phpunit.xml` names its directory.
 * Nothing else notices when the two drift: a new package with tests that is not
 * wired silently contributes zero assertions, and a wired directory that was
 * renamed makes the whole run fail at startup. Both are caught here instead.
 *
 * Each package has its own `Package:{slug}` suite, so its tests can be run alone
 * (`php artisan test --testsuite=Package:billing`). PHPUnit runs every suite in
 * the file by default, so the full run still covers all five trees — but a
 * directory may appear in exactly one suite. Listing it twice makes PHPUnit
 * refuse it for the second suite, warn once per file, and exit non-zero on an
 * otherwise green run.
 */
class PackageTestSuiteTest extends TestCase
{
    public function test_every_package_with_tests_is_wired_into_phpunit(): void
    {
        $suites = $this->suites($this->phpunitConfig());
        $missing = [];

        foreach ($this->packages() as $slug => $package) {
            $directory = 'packages/'.$slug.'/tests';

            if (! is_dir(base_path($directory))) {
                continue;
            }

            if (! in_array($directory, $suites['Package:'.$slug] ?? [], true)) {
                $missing[] = $package.' has tests but `Package:'.$slug.'` does not list '.$directory;
            }
        }

        $this->assertSame([], $missing, implode("\n", $missing));
    }

    public function test_no_directory_is_wired_into_two_suites(): void
    {
        $seen = [];
        $duplicates = [];

        foreach ($this->suites($this->phpunitConfig()) as $name => $directories) {
            foreach ($directories as $directory) {
                if (isset($seen[$directory])) {
                    $duplicates[] = $directory.' is in both '.$seen[$directory].' and '.$name;
                }

                $seen[$directory] = $name;
            }
        }

        $this->assertSame([], $duplicates, implode("\n", $duplicates));
    }

    public function test_every_wired_directory_exists(): void
    {
        $stale = [];

        foreach ($this->suites($this->phpunitConfig()) as $name => $directories) {
            foreach ($directories as $directory) {
                if (str_starts_with($directory, 'packages/') && ! is_dir(base_path($directory))) {
                    $stale[] = $name.' lists '.$directory.' but it does not exist';
                }
            }
        }

        $this->assertSame([], $stale, implode("\n", $stale));
    }

    public function test_every_package_has_a_suite(): void
    {
        $suites = $this->suites($this->phpunitConfig());

        foreach (array_keys($this->packages()) as $slug) {
            $this->assertArrayHasKey(
                'Package:'.$slug,
                $suites,
                'Package '.$slug.' has no `Package:'.$slug.'` suite in phpunit.xml.',
            );
        }
    }

    /**
     * @return array<string, list<string>>
     */
    private function suites(string $xml): array
    {
        $previous = libxml_use_internal_errors(true);
        $document = simplexml_load_string($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($document === false) {
            $this->fail('phpunit.xml is not well-formed XML.');
        }

        $suites = [];

        foreach ($document->testsuites->testsuite as $suite) {
            $name = (string) $suite['name'];
            $suites[$name] = [];

            foreach ($suite->directory as $directory) {
                $suites[$name][] = trim((string) $directory);
            }
        }

        return $suites;
    }

    private function phpunitConfig(): string
    {
        return (string) file_get_contents(base_path('phpunit.xml'));
    }

    /**
     * Packages declared in `config/domains.php`, keyed by the directory name
     * under `packages/` — the same key the suite name and path are built from.
     *
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
