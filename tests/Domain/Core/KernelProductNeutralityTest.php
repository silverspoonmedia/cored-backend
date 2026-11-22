<?php

namespace Tests\Domain\Core;

use Tests\TestCase;

/**
 * A forked project deletes `app/Domain/{Product}` and keeps the packages.
 *
 * `DependencyDirectionTest` already forbids a PHP **import** of a product domain
 * inside a package. That check cannot see a product name in a docblock, a config
 * key read through `config()`, a literal menu label, or an env key in a catalog —
 * and each of those breaks a fork at runtime while this checkout stays green,
 * because the product is always present here.
 *
 * This guard closes the textual half: no product name from `config/domains.php`
 * may appear anywhere in a package's source, config, routes or migrations. The
 * exceptions are deliberate and narrow — the kernel's *own* seam documentation,
 * which must be able to say "a product publishes this".
 */
class KernelProductNeutralityTest extends TestCase
{
    /**
     * Package paths scanned for product names.
     */
    private const PACKAGE_ROOTS = [
        'packages/ai',
        'packages/audit',
        'packages/billing',
        'packages/navigation',
        'packages/notification',
        'packages/platform',
    ];

    /**
     * Directories inside a package that ship, in order: source, config, routes,
     * migrations. `tests` and `docs` are out: a package test may name the product
     * it is proving the seam with.
     */
    private const SHIPPED_DIRECTORIES = ['src', 'config', 'routes', 'database'];

    /**
     * A brand may appear as a translatable option in a package test fixture, but
     * never in shipped code — so no allowlist is needed here. The one exception is
     * prose in the kernel's own seam docblocks: those are the contract, and they
     * name the shape of a product, not a product.
     */
    private const ALLOWED_SUBSTRINGS = [
        // `App\Support\Domain\Access\ProductNames` documents the StudlyCase
        // shape a product key has (`Archivd`) — that is the contract, not a coupling.
        'packages/platform/src/Support/DomainAccess/ProductNames.php' => ['Archivd'],
        'packages/platform/src/Support/DomainAccess/Http/Middleware/DomainAccessGate.php' => ['Archivd'],
        'packages/platform/src/Support/Product/Http/Middleware/ProductActive.php' => ['Archivd'],
        'packages/platform/src/Support/Product/Models/ProductAnalytic.php' => ['Archivd'],
        'packages/platform/src/Support/Billing/FulfillmentRegistry.php' => ['Archivd', 'Microstock'],
        'packages/platform/src/Support/Billing/Contracts/FulfillmentHandler.php' => ['Archivd', 'Microstock'],
        'packages/platform/src/Support/Ai/AiUsageConsolidatorRegistry.php' => ['Microstock'],
        'packages/platform/src/Support/Broadcasting/BroadcastConnection.php' => ['Archivd'],
        // The kernel-owned storage config explains the seam with the exact
        // publish call a product makes; the example is the contract.
        'packages/platform/config/storage.php' => ['archivd'],
    ];

    public function test_no_package_names_a_product_domain(): void
    {
        $products = $this->productDomainNames();

        $this->assertNotSame([], $products, 'config/domains.php declares no product domain — this guard would pass for the wrong reason.');

        $violations = [];

        foreach ($this->shippedFiles() as $path => $source) {
            foreach ($products as $product) {
                foreach ($this->occurrences($source, $product) as $needle) {
                    if (in_array($needle, self::ALLOWED_SUBSTRINGS[$path] ?? [], true)) {
                        continue;
                    }

                    $violations[] = $path.' names product '.$product.' ('.$needle.')';
                }
            }
        }

        $this->assertSame([], $violations, "A package names a product domain. A fork deleting app/Domain/{Product} breaks here:\n".implode("\n", $violations));
    }

    public function test_the_kernel_settings_catalog_holds_only_kernel_groups(): void
    {
        $groups = (new \App\Support\Settings\SettingCatalog)->groups();

        $this->assertContains('platform', $groups);

        foreach ($this->productDomainNames() as $product) {
            $slug = strtolower($product);

            $this->assertNotContains(
                $slug,
                $groups,
                "The kernel catalog ships a [{$slug}] group — product settings belong in that product's SettingContributor.",
            );
        }
    }

    /**
     * Product domain names from the module registry, or an empty list when the
     * registry is not present (a mirror clone).
     *
     * @return list<string>
     */
    private function productDomainNames(): array
    {
        $registry = base_path('config/domains.php');

        if (! is_file($registry)) {
            return [];
        }

        /** @var array<string, array<string, mixed>> $domains */
        $domains = (array) require $registry;
        $products = [];

        foreach ($domains as $name => $meta) {
            if (($meta['kind'] ?? null) === 'product') {
                $products[] = $name;
            }
        }

        return $products;
    }

    /**
     * @return array<string, string> repo-relative path → file source
     */
    private function shippedFiles(): array
    {
        $files = [];

        foreach (self::PACKAGE_ROOTS as $root) {
            foreach (self::SHIPPED_DIRECTORIES as $directory) {
                $path = base_path($root.'/'.$directory);

                if (! is_dir($path)) {
                    continue;
                }

                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
                );

                foreach ($iterator as $file) {
                    if (! in_array($file->getExtension(), ['php', 'json'], true)) {
                        continue;
                    }

                    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen(base_path()) + 1));
                    $files[$relative] = (string) file_get_contents($file->getPathname());
                }
            }
        }

        return $files;
    }

    /**
     * The spellings a product name appears in: the StudlyCase domain name, the
     * lower-case slug a config key and an env prefix use, and the upper-case
     * env prefix.
     *
     * @return list<string>
     */
    private function occurrences(string $source, string $product): array
    {
        $needles = [$product, strtolower($product), strtoupper($product)];
        $found = [];

        foreach ($needles as $needle) {
            if (str_contains($source, $needle)) {
                $found[] = $needle;
            }
        }

        return $found;
    }
}
