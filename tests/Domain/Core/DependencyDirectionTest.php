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
 * The rule has three halves, checked one method at a time:
 *
 * 1. A core package never imports a product domain.
 * 2. A core package never imports a sibling core package — the seam to a
 *    sibling is a kernel contract the owning package implements.
 * 3. The kernel (`app/Support/**`) reaches a package only through a
 *    kernel-owned contract, so a host without that package still boots.
 *
 * `App\Domain\Identity\*` is the documented exception: Identity is the host's
 * authentication hub, not a package, and every package may read `User`.
 */
class DependencyDirectionTest extends TestCase
{
    /**
     * Kernel files that still import a packaged core domain, with the reason.
     *
     * This is a burn-down list, not a permission: it may only shrink. Each entry
     * is a documented violation of the third half of the rule, kept because the
     * fix is a refactor (a kernel-owned interval contract) rather than a rename.
     *
     * @var array<string, string>
     */
    private const KERNEL_KNOWN_EXCEPTIONS = [
        'app/Support/Billing/BillingClock.php' => 'reads BillingInterval to compute a period end; wants a kernel-owned interval contract',
        'app/Support/Billing/FulfillmentContext.php' => 'snapshots ServiceEntitlement/ServicePurchase into a data-only context; wants a kernel-owned entitlement snapshot',
    ];

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

    public function test_kernel_reaches_a_package_only_through_a_kernel_contract(): void
    {
        $violations = [];

        foreach ($this->filesUnder('app/Support') as $path => $source) {
            foreach ($this->importsOf($source) as $domain) {
                if ($domain === 'Identity' || $this->domainKind($domain) !== 'core') {
                    continue;
                }

                if (! array_key_exists($path, self::KERNEL_KNOWN_EXCEPTIONS)) {
                    $violations[] = $path.' imports packaged domain '.$domain;
                }
            }
        }

        $this->assertSame([], $violations, implode("\n", $violations));
    }

    public function test_the_kernel_exception_list_records_a_real_violation(): void
    {
        foreach (self::KERNEL_KNOWN_EXCEPTIONS as $path => $reason) {
            $source = $this->readIfFile($path);

            $this->assertNotNull($source, $path.' is listed as a kernel exception but no longer exists — delete the entry.');

            $this->assertNotSame(
                [],
                $this->importsOf($source),
                $path.' is listed as a kernel exception but imports no packaged domain — delete the entry.',
            );
        }
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

    private function readIfFile(string $relative): ?string
    {
        $path = base_path($relative);

        return is_file($path) ? (string) file_get_contents($path) : null;
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
