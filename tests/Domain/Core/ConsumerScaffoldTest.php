<?php

namespace Tests\Domain\Core;

use Tests\TestCase;

/**
 * `client-scaffold/` is the official template for a project built on the core
 * packages. Nothing else proves it stays one: the scaffold is never booted by
 * this repo's test suite, so a registry entry pointing at a file the package does
 * not ship, a loader that stopped calling `packageApiRoutePaths()`, or a missing
 * provider would only surface the first time a real consumer ran
 * `php artisan route:list`.
 *
 * Every assertion here compares the scaffold against **this checkout**, which
 * installs the same packages under `vendor/silverspoonmedia/{slug}` through a
 * path repository. So the scaffold's `vendor/...` paths resolve here, and the
 * check is a real file check rather than a string comparison.
 */
class ConsumerScaffoldTest extends TestCase
{
    /**
     * The core domains a consumer install carries, in registry order.
     *
     * Pinned: the scaffold's order is route load order. Dropping an entry would
     * silently unmount a domain; reordering would move URIs.
     *
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
     * Root PSR-4 prefixes a consumer must declare itself.
     *
     * These are the override hooks: the scaffold's own `app/` directory is
     * declared **before** the package's copy, so a consumer that drops a file in
     * `app/Support/Foo.php` replaces the kernel class rather than losing to
     * `vendor/silverspoonmedia/platform/src/Support/Foo.php`. A prefix missing
     * from the scaffold's manifest is an override the consumer cannot make.
     *
     * @var list<string>
     */
    private const KERNEL_PREFIXES = [
        'App\\',
        'App\\Support\\',
        'App\\Console\\',
        'App\\Http\\',
        'App\\Repositories\\',
        'App\\Traits\\',
        'App\\Rules\\',
        'App\\Exceptions\\',
        'Database\\Factories\\',
        'Database\\Seeders\\',
    ];

    private string $scaffold;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scaffold = base_path('client-scaffold');
    }

    public function test_the_registry_names_only_core_domains_in_monolith_order(): void
    {
        $scaffold = $this->scaffoldConfig('domains');

        $this->assertSame(self::CORE_DOMAINS, array_keys($scaffold), 'The scaffold registry must carry the ten core domains, in this order — it is route load order.');

        foreach ($scaffold as $name => $meta) {
            $this->assertSame('core', $meta['kind'] ?? null, "Scaffold domain [{$name}] must be kind=core; a product domain is this repo's own surface and ships no code to a consumer.");
        }

        // A consumer's registry never names a product domain: its code is absent
        // from the install, so the entry would only make `domain:list` lie.
        foreach (['Archivd', 'Microstock'] as $product) {
            $this->assertArrayNotHasKey($product, $scaffold, "The scaffold registry must not name the product domain [{$product}].");
        }

        $host = (array) require base_path('config/domains.php');

        foreach (self::CORE_DOMAINS as $name) {
            $this->assertArrayHasKey($name, $host, "Host config/domains.php no longer defines [{$name}].");
            $this->assertSame($host[$name]['package'] ?? null, $scaffold[$name]['package'] ?? null, "Scaffold domain [{$name}] names a different package than the host registry.");
            $this->assertSame($host[$name]['routes'] ?? null, $this->stripPackagesPath($scaffold[$name]['routes'] ?? ''), "Scaffold domain [{$name}] names a different route file than the host registry.");
            $this->assertSame($host[$name]['web'] ?? null, $this->stripPackagesPath($scaffold[$name]['web'] ?? '') ?: null, "Scaffold domain [{$name}] names a different web route file than the host registry.");
            $this->assertSame($host[$name]['provider'] ?? null, $this->stripPackagesPath($scaffold[$name]['provider'] ?? ''), "Scaffold domain [{$name}] names a different provider than the host registry.");
        }
    }

    public function test_every_registry_path_exists_in_an_installed_package(): void
    {
        foreach ($this->scaffoldConfig('domains') as $name => $meta) {
            foreach (['path', 'routes', 'web', 'provider'] as $key) {
                $value = $meta[$key] ?? null;

                if (! is_string($value) || $value === '') {
                    continue;
                }

                // Host packages install as a junction to `packages/{slug}`, a
                // consumer install copies them into `vendor/`, so the literal
                // scaffold path resolves in both.
                $this->assertFileExists(
                    $this->scaffoldPath($value),
                    "Scaffold domain [{$name}] names [{$key}] = {$value}, which does not exist in the installed package.",
                );
            }
        }
    }

    public function test_route_loaders_mount_every_core_package(): void
    {
        $api = (string) file_get_contents($this->scaffold.'/routes/api/v1.php');
        $web = (string) file_get_contents($this->scaffold.'/routes/web.php');

        $this->assertStringContainsString('packageApiRoutePaths()', $api, 'The scaffold api loader must mount the packages through DomainManager::packageApiRoutePaths().');
        $this->assertStringContainsString('packageWebRoutePaths()', $web, 'The scaffold web loader must mount the packages through DomainManager::packageWebRoutePaths().');

        // The loaders must not hardcode a package route file: the registry is the
        // contract, and a `require` line here would go stale on the next split.
        // Comments are stripped first — the docblock explains the layout by
        // naming `vendor/silverspoonmedia/platform/routes/`, which is prose, not
        // a mount.
        foreach ([$api, $web] as $loader) {
            $code = $this->stripComments($loader);

            foreach (['packages/', 'vendor/silverspoonmedia/'] as $literal) {
                $this->assertStringNotContainsString($literal, $code, 'A scaffold route loader must not name a package path directly — config/domains.php is the contract.');
            }
        }
    }

    public function test_kernel_provider_is_registered_explicitly(): void
    {
        $providers = (string) file_get_contents($this->scaffold.'/bootstrap/providers.php');

        $this->assertStringContainsString(
            'KernelServiceProvider::class',
            $providers,
            'A consumer must list App\Providers\KernelServiceProvider in bootstrap/providers.php: the class is deliberately absent from extra.laravel.providers, so auto-discovery will never boot it.',
        );
    }

    public function test_composer_manifest_declares_the_kernel_override_prefixes(): void
    {
        $manifest = $this->scaffoldManifest();
        $psr4 = (array) ($manifest['autoload']['psr-4'] ?? []);

        foreach (self::KERNEL_PREFIXES as $prefix) {
            $this->assertArrayHasKey($prefix, $psr4, "The scaffold composer.json must declare the [{$prefix}] prefix, or a consumer override of that namespace can never win the PSR-4 race.");
        }

        $root = json_decode((string) file_get_contents(base_path('composer.json')), true);
        $onlyPrefixes = array_diff(self::KERNEL_PREFIXES, ['App\\']);

        foreach ($onlyPrefixes as $prefix) {
            $this->assertArrayHasKey($prefix, (array) ($root['autoload']['psr-4'] ?? []), "The root composer.json must still declare [{$prefix}] — the scaffold copies it.");
        }
    }

    public function test_composer_manifest_requires_every_core_package(): void
    {
        $manifest = $this->scaffoldManifest();
        $require = (array) ($manifest['require'] ?? []);

        foreach (['platform', 'navigation', 'notification', 'audit', 'billing', 'ai'] as $slug) {
            $this->assertArrayHasKey(
                'silverspoonmedia/'.$slug,
                $require,
                "The scaffold must require silverspoonmedia/{$slug}: the registry names a domain in it, so the install has to carry it.",
            );
        }

        // VCS repositories: a consumer has no access to this repo's `packages/`
        // directory, so the mirrors are the only install source.
        $repositories = (array) ($manifest['repositories'] ?? []);
        $urls = array_map(static fn (array $repo): string => (string) ($repo['url'] ?? ''), $repositories);

        foreach (['platform', 'navigation', 'notification', 'audit', 'billing', 'ai'] as $slug) {
            $this->assertContains(
                'https://github.com/silverspoonmedia/'.$slug.'.git',
                $urls,
                "The scaffold must register a vcs repository for silverspoonmedia/{$slug}.",
            );
        }
    }

    public function test_the_scaffold_carries_no_subtree_artifacts(): void
    {
        $paths = [
            'update-core.sh',
            'push-core.sh',
            'core',
        ];

        foreach ($paths as $path) {
            $this->assertFileDoesNotExist($this->scaffold.'/'.$path, "The scaffold still carries [{$path}] from the retired git subtree model.");
        }

        // `bootstrap/app.php` used to mount `core/routes/*` beside the client's
        // own files. A leftover reference is a fatal boot with a real install.
        foreach (['bootstrap/app.php', '.gitignore'] as $file) {
            $content = (string) file_get_contents($this->scaffold.'/'.$file);

            $this->assertStringNotContainsString('core/routes/', $content, "[{$file}] must not mount core/routes/*: packages own their routes now.");
        }

        $this->assertStringNotContainsString('/core', (string) file_get_contents($this->scaffold.'/.gitignore'), '.gitignore must not ignore /core — there is no core subtree to ignore.');
    }

    public function test_the_scaffold_test_case_boots_the_real_application(): void
    {
        $testCase = (string) file_get_contents($this->scaffold.'/tests/TestCase.php');

        $this->assertStringContainsString('createApplication', $testCase, 'The scaffold test case must implement createApplication(), or a consumer cannot run a single test.');
        $this->assertStringContainsString('bootstrap/app.php', $testCase, 'The scaffold must boot the project\'s own bootstrap/app.php, not a test-only arrangement of providers.');
    }

    /**
     * `domain:list` ships in the kernel, so the consumer project needs the same
     * override hook for `App\Console\` that it has for `App\Support\`.
     *
     * `KernelServiceProvider::boot()` registers the command class that resolves
     * out of `App\Console\Commands\Domain\`. Without the prefix the project's own
     * `app/Console/` is dead code, and with no project copy the packaged class
     * is the only answer — the same shape as every other kernel namespace.
     */
    public function test_the_scaffold_carries_the_kernel_console_override_directory(): void
    {
        $this->assertDirectoryExists(
            $this->scaffold.'/app/Console',
            'client-scaffold/app/Console/ must exist: the scaffold declares the App\Console\ prefix, and an empty host directory is what makes that hook alive.',
        );
    }

    /**
     * A template with an empty suite asserts nothing and proves nothing: the
     * smoke check would still pass while `createApplication()` rotted.
     */
    public function test_the_scaffold_ships_its_own_suite(): void
    {
        foreach (['Unit', 'Feature'] as $suite) {
            $this->assertNotEmpty(
                glob($this->scaffold.'/tests/'.$suite.'/*Test.php') ?: [],
                "client-scaffold/tests/{$suite} carries no test — the scaffold must prove its own wiring, not only be read by the monolith's guards.",
            );
        }

        $phpunit = (string) file_get_contents($this->scaffold.'/phpunit.xml');

        foreach (['<directory>tests/Unit</directory>', '<directory>tests/Feature</directory>'] as $registered) {
            $this->assertStringContainsString($registered, $phpunit, "client-scaffold/phpunit.xml must register {$registered}, or the scaffold's own tests never run.");
        }

        // The monolith suite cannot run the scaffold's suite: those tests boot
        // the copy's `vendor/`. The smoke check is the only place they execute.
        $runner = (string) file_get_contents(base_path('scripts/run-consumer-scaffold-check.php'));

        $this->assertStringContainsString(
            "'scaffold suite'",
            $runner,
            'run-consumer-scaffold-check.php must run the scaffold\'s own suite inside the copy — otherwise the tests exist and nothing executes them.',
        );

        $this->assertStringContainsString(
            'phpunit',
            $runner,
            'The scaffold runner must invoke PHPUnit from the copy\'s own vendor/bin.',
        );
    }

    public function test_the_scaffold_publishes_every_host_owned_config(): void
    {
        // The kernel ships `platform`, `spa-routes`, `localization`, `services`,
        // `hcaptcha`, `settings` through mergeConfigFrom. These it cannot ship:
        // they name the host's own key, connection or mail transport, so a
        // kernel-only install that forgot one resolves null at runtime.
        foreach (['app', 'auth', 'broadcasting', 'cors', 'database', 'encryption', 'filesystems', 'jwt', 'lookup', 'mail', 'oci', 'permission', 'session'] as $group) {
            $this->assertFileExists(
                $this->scaffold.'/config/'.$group.'.php',
                "The scaffold must publish config/{$group}.php: the kernel reads it and cannot ship a host-owned value.",
            );
        }

        foreach (['platform', 'spa-routes', 'localization', 'services', 'hcaptcha', 'settings'] as $group) {
            $this->assertFileDoesNotExist(
                $this->scaffold.'/config/'.$group.'.php',
                "The scaffold must not copy config/{$group}.php: the kernel ships it through a provider's mergeConfigFrom, and a copy would shadow the kernel's defaults on the next release.",
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function scaffoldManifest(): array
    {
        return (array) json_decode((string) file_get_contents($this->scaffold.'/composer.json'), true);
    }

    /**
     * @return array<string, mixed>
     */
    private function scaffoldConfig(string $name): array
    {
        return (array) require $this->scaffold.'/config/'.$name.'.php';
    }

    /**
     * The scaffold path, resolved in this checkout.
     *
     * `vendor/silverspoonmedia/{slug}` is a junction to `packages/{slug}` here,
     * so the literal path works — and a consumer install, where it is a real
     * directory, takes the same path.
     */
    private function scaffoldPath(string $relative): string
    {
        return base_path($relative);
    }

    /**
     * The scaffold names its packages the way a consumer install sees them     * (`vendor/silverspoonmedia/{slug}/...`); the host registry names them in
     * this checkout (`packages/{slug}/...`). Compare the tail, not the prefix.
     */
    private function stripPackagesPath(string $path): string
    {
        if ($path === '') {
            return '';
        }

        if (preg_match('#vendor/silverspoonmedia/[^/]+/#', $path) === 1) {
            return (string) preg_replace('#vendor/silverspoonmedia/([^/]+)/#', 'packages/$1/', $path);
        }

        return $path;
    }

    /**
     * Remove block and line comments, so a docblock that explains the install
     * layout is not read as a mount.
     */
    private function stripComments(string $code): string
    {
        $stripped = (string) preg_replace('#/\*.*?\*/#s', '', $code);

        return (string) preg_replace('#^\s*//.*$#m', '', $stripped);
    }
}
