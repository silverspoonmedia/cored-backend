<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * The registry is the whole mounting contract: it names which package carries
 * each core domain, and `DomainManager` resolves every path from it. A path that
 * does not exist on disk is a boot that fails at the first route load, so this
 * asserts the registry against the actual `vendor/` tree instead of trusting the
 * keys to be right.
 */
class DomainRegistryTest extends TestCase
{
    /**
     * Packages a consumer install may carry: core only, never a product domain.
     *
     * @var list<string>
     */
    private const CORE_PACKAGES = [
        'silverspoonmedia/platform',
        'silverspoonmedia/navigation',
        'silverspoonmedia/notification',
        'silverspoonmedia/audit',
        'silverspoonmedia/billing',
        'silverspoonmedia/ai',
    ];

    /**
     * @var list<string>
     */
    private const CORE_DOMAINS = [
        'Identity',
        'Navigation',
        'Notification',
        'ActivityLog',
        'ErrorLog',
        'PaymentGateway',
        'Currency',
        'Service',
        'Inference',
        'Ai',
    ];

    /**
     * The registry as `DomainManager::all()` reads it.
     *
     * `config/domains.php` also carries registry **metadata** under `siblings`
     * (the sibling checkout names `domain:remove` scans). It shares the file but
     * is not a domain, and `DomainManager` skips a key with no `kind` — this
     * helper applies the same rule so the assertions below measure what boots,
     * not what the array contains.
     *
     * @return array<string, array<string, mixed>>
     */
    private static function domains(): array
    {
        /** @var array<string, array<string, mixed>> $raw */
        $raw = (array) config('domains', []);

        return array_filter($raw, static fn (mixed $entry, mixed $name): bool => is_string($name) && is_array($entry) && array_key_exists('kind', $entry), ARRAY_FILTER_USE_BOTH);
    }

    public function test_it_registers_the_ten_core_domains_and_no_product_domain(): void
    {
        /** @var array<string, array<string, mixed>> $domains */
        $domains = self::domains();

        $this->assertSame(
            self::CORE_DOMAINS,
            array_keys($domains),
            'The registry order is route load order: keep the monolith core order, never alphabetize.',
        );

        foreach (['Archivd', 'Microstock'] as $product) {
            $this->assertArrayNotHasKey($product, $domains, "[{$product}] is this repository's own product surface; an entry would make domain:list report a domain the install cannot boot.");
        }
    }

    public function test_every_entry_declares_core_axes_and_a_core_package(): void
    {
        /** @var array<string, array<string, mixed>> $domains */
        $domains = self::domains();

        foreach ($domains as $name => $entry) {
            $this->assertSame('core', $entry['kind'] ?? null, "[{$name}] must be kind core: core domains are never removable.");
            $this->assertArrayNotHasKey('locked', $entry, "[{$name}] is core — the locked axis belongs to a product domain.");
            $this->assertArrayNotHasKey('parent', $entry, "[{$name}] is core — the parent axis belongs to a product subdomain.");

            $this->assertContains(
                $entry['package'] ?? null,
                self::CORE_PACKAGES,
                "[{$name}] names a package that is not part of a core install.",
            );
        }
    }

    public function test_every_declared_path_exists_on_disk(): void
    {
        /** @var array<string, array<string, mixed>> $domains */
        $domains = self::domains();

        foreach ($domains as $name => $entry) {
            foreach (['path', 'provider'] as $key) {
                $declared = (string) ($entry[$key] ?? '');

                $this->assertNotSame('', $declared, "[{$name}] declares no [{$key}] — DomainManager resolves every install path from this entry.");

                if ($key === 'path') {
                    $this->assertDirectoryExists(base_path($declared), "[{$name}] registry path does not exist: {$declared}");
                } else {
                    $this->assertFileExists(base_path($declared), "[{$name}] provider does not exist: {$declared}");
                }
            }

            $routes = $entry['routes'] ?? null;

            if (is_string($routes) && $routes !== '') {
                $this->assertFileExists(base_path($routes), "[{$name}] route file does not exist: {$routes}");
            }

            $web = $entry['web'] ?? null;

            if (is_string($web) && $web !== '') {
                $this->assertFileExists(base_path($web), "[{$name}] web route file does not exist: {$web}");
            }
        }
    }

    public function test_every_entry_resolves_to_its_package_install_directory(): void
    {
        /** @var array<string, array<string, mixed>> $domains */
        $domains = self::domains();

        foreach ($domains as $name => $entry) {
            $package = (string) ($entry['package'] ?? '');
            $path = (string) ($entry['path'] ?? '');

            $this->assertStringStartsWith(
                'vendor/'.$package.'/',
                $path,
                "[{$name}] must point at the Composer install ([vendor/{$package}/…]), not at this repository's packages/ tree.",
            );
        }
    }
}
