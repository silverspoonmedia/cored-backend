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
     * Two shapes, one invariant: `phpunit.xml` in the monolith and the same
     * `tests/` directories in the package's own `phpunit.xml.dist`.
     *
     * The dist file is what a consumer runs after `composer install` inside a
     * cloned mirror; the root file is what CI runs here. They need not list the
     * suite under the same name (`Package:billing` vs `billing`), but every test
     * directory the package manifest declares must be discovered by both, or a
     * test exists that one of the two runs never sees.
     */
    public function test_every_package_dist_lists_its_declared_test_directories(): void
    {
        $violations = [];

        foreach ($this->packages() as $slug => $package) {
            $dist = base_path('packages/'.$slug.'/phpunit.xml.dist');

            if (! is_file($dist)) {
                $violations[] = $package.' has no phpunit.xml.dist — `composer test` inside a cloned tag would run nothing';

                continue;
            }

            $listed = array_merge(...array_values($this->suites((string) file_get_contents($dist)) ?: [[]]));
            foreach ($this->declaredTestDirectories($slug) as $directory) {
                if (! in_array(rtrim($directory, '/'), $listed, true)) {
                    $violations[] = $package.' phpunit.xml.dist does not list '.$directory;
                }
            }
        }

        $this->assertSame([], $violations, implode("\n", $violations));
    }

    /**
     * `tests/` directories the package manifest maps (`autoload.psr-4` paths
     * that start with `tests/`).
     *
     * @return list<string>
     */
    private function declaredTestDirectories(string $slug): array
    {
        $manifest = json_decode((string) file_get_contents(base_path('packages/'.$slug.'/composer.json')), true);
        $directories = [];

        foreach ((array) ($manifest['autoload']['psr-4'] ?? []) as $path) {
            if (is_string($path) && str_starts_with($path, 'tests/')) {
                $directories[] = $path;
            }
        }

        return array_values(array_unique($directories));
    }

    /**
     * A package test file must live under the PSR-4 prefix its own manifest
     * declares for `tests/`.
     *
     * PHPUnit discovers a file by path, so a wrong namespace still runs — and
     * silently stops reaching the package's namespace-based helpers, while a
     * consumer's `App\Domain\{Domain}\Tests\*` reference resolves nowhere. The
     * AI package shipped exactly that: `tests/Ai/...` declaring
     * `Tests\Domain\Ai\...` while `composer.json` mapped
     * `App\Domain\Ai\Tests\` to `tests/Ai/`.
     */
    public function test_every_test_file_matches_its_package_psr4_prefix(): void
    {
        $violations = [];

        foreach ($this->packages() as $slug => $package) {
            $prefixes = $this->psr4TestPrefixes($slug);

            foreach ($this->declaredTestNamespaces($slug) as $relative => $namespace) {
                $expected = $this->expectedNamespace($relative, $prefixes);

                if ($expected === null) {
                    $violations[] = $package.' test '.$relative.' is not covered by any `tests/` PSR-4 prefix in its composer.json (declared '.$namespace.')';

                    continue;
                }

                if ($namespace !== $expected) {
                    $violations[] = $package.' test '.$relative.' declares '.$namespace.' but its PSR-4 prefix expects '.$expected;
                }
            }
        }

        $this->assertSame([], $violations, implode("\n", $violations));
    }

    /**
     * Every package test boots the standalone harness, never a host base.
     *
     * A package test that extends `Tests\TestCase` only runs inside this
     * checkout: `Tests\*` is autoload-dev of the monolith and never ships, so a
     * consumer who clones a mirror tag gets a fatal on the first assertion. The
     * base is `App\Support\Testing\PackageTestCase`. A test that needs a host
     * route, a host fixture or a product table does not belong here — it moves
     * to `tests/Domain/Core/{Domain}/` and keeps the host base.
     *
     * Exempt: the package's own abstract `TestCase` scaffolds (they extend the
     * kernel base and are named in the list) and fixture helpers that are not
     * tests at all (`Concerns/`, `Factories/`, `Support/`).
     */
    public function test_every_package_test_runs_under_the_standalone_base(): void
    {
        $base = 'App\\Support\\Testing\\PackageTestCase';
        $violations = [];

        foreach ($this->packages() as $slug => $package) {
            $root = base_path('packages/'.$slug.'/tests');

            if (! is_dir($root)) {
                continue;
            }

            foreach ($this->phpFiles($root) as $relative => $source) {
                if ($this->isTestFixture($relative)) {
                    continue;
                }

                $isAbstract = (bool) preg_match('/\babstract\s+class\b/', $source);
                $parent = $this->parentClass($source);

                if ($parent === null) {
                    $violations[] = $package.' test '.$relative.' declares no class or extends nothing';

                    continue;
                }

                if ($isAbstract) {
                    if ($parent !== $base && $parent !== 'PackageTestCase' && ! $this->isPackageOwned($parent, $package)) {
                        $violations[] = $package.' abstract test base '.$relative.' extends '.$parent.' instead of '.$base;
                    }

                    continue;
                }

                // Reached through the package's own abstract base, whose own
                // parent is checked above — one hop is the whole chain here.
                if ($this->isPackageOwned($parent, $package)) {
                    continue;
                }

                if ($parent !== 'PHPUnit\\Framework\\TestCase' && $parent !== $base) {
                    $violations[] = $package.' test '.$relative.' extends '.$parent.' — package tests must extend '.$base.' (or a package TestCase built on it)';
                }
            }
        }

        $this->assertSame([], $violations, implode("\n", $violations));
    }

    /**
     * PHP files under a package `tests/` tree, keyed by path relative to it.
     *
     * @return array<string, string>
     */
    private function phpFiles(string $root): array
    {
        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            $files[$relative] = (string) file_get_contents($file->getPathname());
        }

        return $files;
    }

    /**
     * Whether a relative path is a fixture directory rather than a test class.
     */
    private function isTestFixture(string $relative): bool
    {
        foreach (['Concerns/', 'Factories/', 'Support/', 'Fixtures/'] as $directory) {
            if (str_starts_with($relative, $directory)) {
                return true;
            }
        }

        // A per-domain helper directory (`Currency/Concerns/`, `Service/Support/`)
        // holds traits and fixtures, not test classes.
        return (bool) preg_match('~/(Concerns|Factories|Support|Fixtures)/~', $relative);
    }

    /**
     * Fully-qualified name of the parent class declared in a test file.
     *
     * A file usually writes `extends TestCase` and imports the class, so reading
     * the `extends` token alone proves nothing: an unqualified name must be
     * resolved through the file's own `use` statements first, or a host base
     * would look identical to a package one. Returns null when the file declares
     * no class.
     */
    private function parentClass(string $source): ?string
    {
        if (! preg_match('/\bclass\s+[A-Za-z0-9_]+\s+extends\s+(\\\\?[A-Za-z0-9_\\\\]+)/', $source, $match)) {
            return null;
        }

        $parent = $match[1];

        if (str_starts_with($parent, '\\')) {
            return ltrim($parent, '\\');
        }

        $head = str_contains($parent, '\\') ? substr($parent, 0, (int) strpos($parent, '\\')) : $parent;

        preg_match_all('/^use\s+([A-Za-z0-9_\\\\]+)(?:\s+as\s+([A-Za-z0-9_]+))?\s*;/m', $source, $imports, PREG_SET_ORDER);

        foreach ($imports as $import) {
            $imported = $import[1];
            $alias = $import[2] ?? substr($imported, (int) strrpos('\\'.$imported, '\\'));

            if ($alias !== $head) {
                continue;
            }

            $rest = substr($parent, strlen($head));

            return $imported.$rest;
        }

        return $parent;
    }

    /**
     * Whether a parent class belongs to the package's own test namespace —
     * the package's abstract `TestCase` or `FeatureTestCase`.
     *
     * Resolved against the manifest's `tests/` PSR-4 prefixes rather than a
     * guessed `App\Domain\{Domain}\*`: a bundled package owns one test namespace
     * per domain (`PaymentGateway`, `Currency`, `Service`), and guessing one of
     * them would fail every test that extends another.
     */
    private function isPackageOwned(string $parent, string $package): bool
    {
        $slug = substr($package, strpos($package, '/') + 1);

        foreach (array_keys($this->psr4TestPrefixes($slug)) as $prefix) {
            if (str_starts_with($parent, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * `tests/` PSR-4 prefixes from the package manifest, keyed by namespace.
     *
     * @return array<string, string>
     */
    private function psr4TestPrefixes(string $slug): array
    {
        $manifest = json_decode((string) file_get_contents(base_path('packages/'.$slug.'/composer.json')), true);
        $prefixes = [];

        foreach ((array) ($manifest['autoload']['psr-4'] ?? []) as $namespace => $path) {
            if (is_string($path) && str_starts_with($path, 'tests/')) {
                $prefixes[$namespace] = substr($path, strlen('tests/'));
            }
        }

        return $prefixes;
    }

    /**
     * Declared namespace of every test file, keyed by its path relative to
     * `packages/{slug}/tests`.
     *
     * @return array<string, string>
     */
    private function declaredTestNamespaces(string $slug): array
    {
        $root = base_path('packages/'.$slug.'/tests');
        $namespaces = [];

        if (! is_dir($root)) {
            return [];
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            $source = (string) file_get_contents($file->getPathname());

            if (preg_match('/^namespace ([A-Za-z0-9_\\\\]+);/m', $source, $match)) {
                $namespaces[$relative] = $match[1];
            }
        }

        return $namespaces;
    }

    /**
     * Namespace the file should declare, given the prefix whose directory is the
     * longest match for its path; null when no prefix covers it.
     *
     * @param  array<string, string>  $prefixes
     */
    private function expectedNamespace(string $relative, array $prefixes): ?string
    {
        $best = null;

        foreach ($prefixes as $namespace => $directory) {
            $directory = trim($directory, '/');

            if ($directory !== '' && ! str_starts_with($relative, $directory.'/')) {
                continue;
            }

            if ($best === null || strlen($directory) > strlen($best[1])) {
                $best = [$namespace, $directory];
            }
        }

        if ($best === null) {
            return null;
        }

        [$namespace, $directory] = $best;
        $remainder = $directory === '' ? $relative : substr($relative, strlen($directory) + 1);

        // The class name is the file name, so it is not part of the namespace.
        $dirname = dirname($remainder);
        $remainder = $dirname === '.' ? '' : $dirname;
        $remainder = str_replace('/', '\\', $remainder);

        return $remainder === '' ? rtrim($namespace, '\\') : $namespace.$remainder;
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
