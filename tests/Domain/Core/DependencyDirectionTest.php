<?php

namespace Tests\Domain\Core;

use Tests\TestCase;

/**
 * Locks the dependency direction stated in `.cursor/rules/architecture.mdc`.
 *
 * Extraction is only useful if the extracted packages can leave this repo. A
 * single `use App\Domain\{Product}\...` inside `packages/billing/src` compiles
 * here and breaks the moment a downstream host installs the package without
 * that product — and nothing else in the suite would notice, because the
 * product is always present in this checkout.
 *
 * The rule has four halves, checked one method at a time:
 *
 * 1. A core package never imports a product domain.
 * 2. A core package never imports a sibling core package — the seam to a
 *    sibling is a kernel contract the owning package implements.
 * 3. The kernel (`packages/platform/src/Support/**`) reaches a package only
 *    through a kernel-owned contract, so a host without that package still boots.
 * 4. Every non-kernel package requires `silverspoonmedia/platform`, because the
 *    seams it consumes live there — a manifest without it is a runtime fatal
 *    downstream, not here.
 *
 * `App\Domain\Identity\*` ships inside the kernel package
 * (`silverspoonmedia/platform`), so every package may read `User` the same way
 * it reads a kernel contract.
 */
class DependencyDirectionTest extends TestCase
{
    /**
     * Root of kernel source inside its package.
     */
    private const KERNEL_SUPPORT = 'packages/platform/src/Support';

    /**
     * The kernel package itself: it carries the seams, so it does not require
     * a platform seam from itself.
     */
    private const KERNEL_PACKAGE = 'platform';

    public function test_no_core_package_imports_a_product_domain(): void
    {
        $violations = [];

        foreach ($this->packageFiles() as $path => $source) {
            foreach ($this->importsOf($source) as $domain) {
                if ($this->domainKind($domain) === 'product') {
                    $violations[] = $path.' imports product domain '.$domain;
                }
            }
        }

        $this->assertSame([], $violations, implode("\n", $violations));
    }

    public function test_no_core_package_imports_a_sibling_core_package(): void
    {
        $violations = [];

        foreach ($this->packageFiles() as $path => $source) {
            $owner = $this->packageOf($path);

            foreach ($this->importsOf($source) as $domain) {
                if ($domain === 'Identity') {
                    continue;
                }

                $sibling = $this->packageForDomain($domain);

                if ($sibling !== null && $sibling !== $owner) {
                    $violations[] = $path.' imports '.$domain.' (owned by '.$sibling.')';
                }
            }
        }

        $this->assertSame([], $violations, implode("\n", $violations));
    }

    /**
     * The burn-down list is empty and stays empty: the kernel reaches a packaged
     * core domain through a kernel-owned contract (`SubscriptionInterval`,
     * `AuthActivityRecorder`, `NavigationTreeFlusher`,
     * `NotificationDispatcher`), never through a package class.
     */
    public function test_kernel_reaches_a_package_only_through_a_kernel_contract(): void
    {
        $violations = [];

        foreach ($this->filesUnder(self::KERNEL_SUPPORT) as $path => $source) {
            foreach ($this->importsOf($source) as $domain) {
                if ($domain === 'Identity' || $this->domainKind($domain) !== 'core') {
                    continue;
                }

                $violations[] = $path.' imports packaged domain '.$domain;
            }
        }

        $this->assertSame([], $violations, implode("\n", $violations));
    }

    public function test_every_non_kernel_package_requires_the_platform_package(): void
    {
        $violations = [];

        foreach (array_keys($this->packages()) as $slug) {
            if ($slug === self::KERNEL_PACKAGE) {
                continue;
            }

            $manifest = json_decode((string) file_get_contents(base_path('packages/'.$slug.'/composer.json')), true);
            $this->assertIsArray($manifest, 'packages/'.$slug.'/composer.json is not valid JSON.');

            $require = array_keys((array) ($manifest['require'] ?? []));

            if (! in_array('silverspoonmedia/platform', $require, true)) {
                $violations[] = 'packages/'.$slug.'/composer.json does not require silverspoonmedia/platform';
            }
        }

        $this->assertSame([], $violations, implode("\n", $violations));
    }

    /**
     * A package test may import only what ships with the package or its
     * dependencies.
     *
     * `Tests\Concerns\*` and `Database\Seeders\*` are the monolith's
     * autoload-dev and its host seeders: nothing a consumer's tag resolves. A
     * package test that reaches for either runs here and fatals downstream, so
     * the fixture it wants moves beside the test (a `Concerns/` trait in the
     * package's own `tests/`) or the test moves to `tests/Domain/Core/`.
     *
     * `App\Domain\{Product}\*` is the mirror of rule 1 for the test tree: a
     * product row or factory a core package cannot ship, so the test that needs
     * one is a host test by definition.
     *
     * @var list<string>
     */
    private const HOST_ONLY_TEST_ROOTS = [
        'Tests\\',
        'Database\\Seeders\\',
        'Database\\Factories\\',
    ];

    public function test_no_package_test_imports_a_host_only_fixture(): void
    {
        $violations = [];

        foreach (array_keys($this->packages()) as $slug) {
            foreach ($this->filesUnder('packages/'.$slug.'/tests') as $path => $source) {
                $code = (string) preg_replace('~/\*.*?\*/~s', '', $source);
                $code = (string) preg_replace('~^\s*(?:\*|//).*$~m', '', $code);

                foreach (self::HOST_ONLY_TEST_ROOTS as $root) {
                    if (preg_match('~['." \n\t".']'.preg_quote($root, '~').'~', $code)) {
                        $violations[] = $path.' imports host-only fixture '.$root.'*';
                    }
                }

                foreach ($this->importsOf($code) as $domain) {
                    if ($this->domainKind($domain) === 'product') {
                        $violations[] = $path.' imports product domain '.$domain.' — a test that needs a product row belongs in tests/Domain/Core/';
                    }
                }
            }
        }

        $this->assertSame([], $violations, implode("\n", $violations));
    }

    /**
     * Every PHP file in a listed package's `src`, keyed by repo-relative path.
     *
     * @return array<string, string>
     */
    private function packageFiles(): array
    {
        $files = [];

        foreach (array_keys($this->packages()) as $slug) {
            $files += $this->filesUnder('packages/'.$slug.'/src');
        }

        return $files;
    }

    /**
     * @return array<string, string>
     */
    private function filesUnder(string $directory): array
    {
        $root = base_path($directory);
        $files = [];

        if (! is_dir($root)) {
            return $files;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen(base_path()) + 1));
            $files[$relative] = (string) file_get_contents($file->getPathname());
        }

        return $files;
    }

    /**
     * Core domain names imported by an `use` statement or a fully qualified
     * reference, with comments stripped first — a docblock that names a class is
     * prose, not a dependency.
     *
     * @return list<string>
     */
    private function importsOf(string $source): array
    {
        $code = preg_replace('~/\*.*?\*/~s', '', $source);
        $code = preg_replace('~^\s*(?:\*|//).*$~m', '', (string) $code);

        if (! preg_match_all('~App\\\\Domain\\\\([A-Za-z]+)\\\\~', (string) $code, $matches)) {
            return [];
        }

        $domains = array_values(array_unique($matches[1]));

        return array_values(array_filter(
            $domains,
            fn (string $domain): bool => array_key_exists($domain, $this->domains()),
        ));
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function domains(): array
    {
        /** @var array<string, array<string, mixed>> $domains */
        $domains = config('domains', []);

        return $domains;
    }

    /**
     * @return array<string, string>
     */
    private function packages(): array
    {
        $packages = [];

        foreach ($this->domains() as $entry) {
            $package = $entry['package'] ?? null;

            if (is_string($package)) {
                $packages[substr($package, strpos($package, '/') + 1)] = $package;
            }
        }

        return $packages;
    }

    private function domainKind(string $domain): ?string
    {
        $entry = $this->domains()[$domain] ?? null;

        return is_array($entry) ? ($entry['kind'] ?? null) : null;
    }

    private function packageForDomain(string $domain): ?string
    {
        $package = $this->domains()[$domain]['package'] ?? null;

        return is_string($package) ? substr($package, strpos($package, '/') + 1) : null;
    }

    private function packageOf(string $path): ?string
    {
        if (! preg_match('~^packages/([a-z]+)/~', $path, $matches)) {
            return null;
        }

        return $matches[1];
    }
}
