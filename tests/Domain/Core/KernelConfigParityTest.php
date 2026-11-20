<?php

namespace Tests\Domain\Core;

use Tests\TestCase;

/**
 * The kernel ships config defaults so a package install boots on its own; the
 * host config overrides them. That split only works while the two stay in
 * step — a kernel default that names a route the host never allows, or a
 * `localization` scalar that drifted, is invisible at runtime: `mergeConfigFrom`
 * fills a missing host key silently, and a package key nothing consumes simply
 * sits there.
 *
 * Shape of every assertion here:
 *
 * - **Identity** where the two files are the same document
 *   (`localization.php`): a scalar that moves in one and not the other is a
 *   defect, not a host preference.
 * - **Kernel ⊆ host** where the host legitimately owns more
 *   (`spa-routes.menu`/`deny` grow with product domains, `services` gains
 *   blocks): the kernel may not introduce an entry the host has not accepted,
 *   because a kernel-only install would then boot a payload the host rejects.
 */
class KernelConfigParityTest extends TestCase
{
    /**
     * `spa-routes` is the navigation allowlist. Product domains add entries; the
     * kernel must never add one the host does not carry, or a package test would
     * accept a menu target the monolith rejects.
     */
    public function test_kernel_spa_routes_are_a_subset_of_the_host_allowlist(): void
    {
        $kernel = $this->kernelConfig('platform', 'spa-routes');
        $host = $this->hostConfig('spa-routes');

        foreach (['menu', 'deny'] as $list) {
            $this->assertArrayHasKey($list, $kernel, "packages/platform/config/spa-routes.php must define [{$list}].");
            $this->assertArrayHasKey($list, $host, "config/spa-routes.php must define [{$list}].");

            $unknown = array_values(array_diff($kernel[$list], $host[$list]));

            $this->assertSame([], $unknown, "Kernel `spa-routes.{$list}` carries entries the host does not allow: ".implode(', ', $unknown));
        }

        // The host really is the wider document: product routes are the reason
        // the kernel list is a subset and not an identity.
        $this->assertNotSame([], array_values(array_diff($host['menu'], $kernel['menu'])), 'The host menu must carry at least the product routes the kernel cannot name.');
    }

    /**
     * `localization` is one document in two places: locales, cookie, user column
     * and the managed-file list. Any difference means one side is stale.
     */
    public function test_kernel_localization_matches_the_host_copy(): void
    {
        $this->assertSame(
            $this->hostConfig('localization'),
            $this->kernelConfig('platform', 'localization'),
            'packages/platform/config/localization.php drifted from config/localization.php.',
        );
    }

    /**
     * `services`: the kernel carries the blocks its own code reads (Discord
     * OAuth, the SPA origin, mail transports). The host may add blocks; it must
     * still define every key the kernel defaults, otherwise a kernel install
     * reads a null credential the host considers configured.
     */
    public function test_kernel_services_blocks_exist_in_the_host_copy(): void
    {
        $kernel = $this->kernelConfig('platform', 'services');
        $host = $this->hostConfig('services');

        $this->assertNotSame([], $kernel);

        foreach (array_keys($kernel) as $block) {
            $this->assertArrayHasKey($block, $host, "config/services.php no longer defines the kernel block [{$block}].");
        }

        // Nested blocks are compared key-for-key: `discord.redirect` is derived
        // from `app.url`, so only the shape is stable enough to lock.
        foreach ($kernel as $block => $values) {
            if (! is_array($values)) {
                continue;
            }

            foreach (array_keys($values) as $key) {
                $this->assertArrayHasKey($key, (array) $host[$block], "config/services.php is missing [{$block}.{$key}].");
            }
        }

        // The one literal both sides carry: a Tier B setting default that a
        // drift would silently change the Discord snapshot window.
        $this->assertSame(60, $kernel['discord']['connections_snapshot_max_age_minutes']);
        $this->assertSame(60, $host['discord']['connections_snapshot_max_age_minutes']);
    }

    /**
     * frankfurter belongs to `silverspoonmedia/billing` now. The package
     * provider merges it, so the host must resolve the block without carrying a
     * copy — and if a host keeps one, it is the override and must stay a
     * superset.
     */
    public function test_frankfurter_defaults_are_merged_from_the_billing_package(): void
    {
        $kernel = $this->kernelConfig('billing', 'services');

        $this->assertArrayHasKey('frankfurter', $kernel, 'packages/billing/config/services.php must define [frankfurter].');
        $this->assertSame(
            ['base_url', 'timeout', 'cache_ttl'],
            array_keys($kernel['frankfurter']),
            'The billing frankfurter block and the Settings registry (`platform.frankfurter.*`) must name the same keys.',
        );

        // Runtime resolution is the point of the move: a billing-only install
        // reads these through the merge, never through the host file.
        $this->assertSame($kernel['frankfurter']['timeout'], config('services.frankfurter.timeout'));
        $this->assertSame($kernel['frankfurter']['cache_ttl'], config('services.frankfurter.cache_ttl'));
        $this->assertSame((string) $kernel['frankfurter']['base_url'], (string) config('services.frankfurter.base_url'));

        $host = $this->hostConfig('services');

        if (array_key_exists('frankfurter', $host)) {
            $missing = array_values(array_diff(array_keys($kernel['frankfurter']), array_keys((array) $host['frankfurter'])));

            $this->assertSame([], $missing, 'A host frankfurter override must still carry every kernel key: '.implode(', ', $missing));
        }
    }

    /**
     * The file a kernel package ships, as a fresh require — no application
     * state, so the comparison is between documents and not boot order.
     *
     * @return array<string, mixed>
     */
    private function kernelConfig(string $package, string $name): array
    {
        $path = base_path('packages/'.$package.'/config/'.$name.'.php');

        $this->assertFileExists($path);

        return (array) require $path;
    }

    /**
     * @return array<string, mixed>
     */
    private function hostConfig(string $name): array
    {
        $path = base_path('config/'.$name.'.php');

        $this->assertFileExists($path);

        return (array) require $path;
    }
}
