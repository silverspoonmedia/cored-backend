<?php

namespace Tests\Domain\Core;

use App\Domain\Navigation\Repositories\Contracts\MenuRepositoryInterface;
use App\Domain\Navigation\Repositories\Eloquent\MenuRepository;
use App\Support\Domain\DomainManager;
use App\Support\Navigation\PresentedTreeEpoch;
use Tests\TestCase;

/**
 * Locks the two invariants that make a core domain package usable from a host.
 *
 * 1. The package is actually installed and resolves — a path package that only
 *    exists on disk but is missing from the autoloader fails silently at runtime.
 * 2. The host still wins the PSR-4 race. Extraction is only useful for a
 *    downstream project if `app/Domain/{Domain}/...` shadows the packaged class,
 *    and that precedence lives in one line of the root composer.json: drop it and
 *    every host override becomes dead code with no error to notice.
 */
class PackageOverrideTest extends TestCase
{
    public function test_packaged_core_domain_resolves_from_the_package_directory(): void
    {
        $manager = app(DomainManager::class);

        $this->assertTrue($manager->isPackaged('Navigation'));
        $this->assertTrue($manager->isPackaged('Notification'));
        $this->assertFalse($manager->isPackaged('Identity'));

        $this->assertStringContainsString(
            'packages/navigation',
            str_replace('\\', '/', $manager->domainPath('Navigation')),
        );

        $reflection = new \ReflectionClass(MenuRepository::class);
        $this->assertStringContainsString(
            'packages/navigation',
            str_replace('\\', '/', (string) $reflection->getFileName()),
        );
    }

    public function test_kernel_contract_is_bound_to_the_package_implementation(): void
    {
        $this->assertTrue(app()->bound(PresentedTreeEpoch::class));
        $this->assertInstanceOf(MenuRepositoryInterface::class, app(PresentedTreeEpoch::class));
    }

    public function test_host_file_shadows_the_package_directory(): void
    {
        // Dynamic proof of the PSR-4 race, with a class the package does not
        // define: writing it under app/Domain/Navigation must make the loader
        // resolve that path, not the package's src directory.
        $hostDir = base_path('app/Domain/Navigation/Support');
        $hostFile = $hostDir.'/PackageOverrideProbe.php';
        $createdDir = ! is_dir($hostDir);

        $this->assertFileDoesNotExist($hostFile);

        $loader = require base_path('vendor/autoload.php');
        $probe = 'App\\Domain\\Navigation\\Support\\PackageOverrideProbe';

        // Composer caches a failed lookup for the process lifetime, so the
        // "does it exist yet" check above would poison the real assertion.
        $forgetMissing = function () use ($loader): void {
            $cache = new \ReflectionProperty($loader, 'missingClasses');
            $cache->setValue($loader, []);
        };

        try {
            $this->assertFalse(
                (bool) $loader->findFile($probe),
                'The probe class must not exist anywhere before the host writes it.'
            );

            if ($createdDir) {
                mkdir($hostDir, 0777, true);
            }

            file_put_contents($hostFile, "<?php\n\nnamespace App\\Domain\\Navigation\\Support;\n\nfinal class PackageOverrideProbe\n{\n    public const SOURCE = 'host';\n}\n");

            clearstatcache();
            $forgetMissing();

            $this->assertSame(
                str_replace('\\', '/', (string) realpath($hostFile)),
                str_replace('\\', '/', (string) realpath((string) $loader->findFile($probe))),
                'A host class in the package namespace must resolve from app/, not from packages/.'
            );
        } finally {
            if (is_file($hostFile)) {
                @unlink($hostFile);
            }

            foreach ([dirname($hostFile), base_path('app/Domain/Navigation')] as $dir) {
                if (is_dir($dir) && count(scandir($dir) ?: []) <= 2) {
                    @rmdir($dir);
                }
            }

            $forgetMissing();
            clearstatcache();
        }
    }
}
