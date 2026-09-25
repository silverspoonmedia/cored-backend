<?php

namespace Tests\Domain\DomainAccess\Feature;

use App\Support\Domain\DomainManager;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Verifies domain:make wires the Beta tester RBAC seeder into the pipeline:
 * a {Name}RoleSeeder file is generated and registered in DatabaseSeeder, and
 * domain:remove tears both back down. Uses a throwaway domain and always
 * cleans up in tearDown so the repo is left pristine even on failure.
 */
class MakeDomainRoleSeederPipelineTest extends TestCase
{
    private const PROBE = 'DomainAccessProbe';

    private DomainManager $domains;

    protected function setUp(): void
    {
        parent::setUp();
        $this->domains = app(DomainManager::class);
    }

    protected function tearDown(): void
    {
        // `directoryExists` matters as much as `isRegistered`: a scaffold that
        // fails after creating the directory tree but before registering the
        // domain leaves an untracked `app/Domain/{Name}` behind, and git cannot
        // see it (empty directories are not tracked). That leftover makes every
        // later run of this test fail on the `already exists` guard, so clean it
        // up on the same terms `DomainManager::remove()` accepts.
        if ($this->domains->isRegistered(self::PROBE)
            || $this->domains->directoryExists(self::PROBE)
            || File::isFile($this->domains->roleSeederPath(self::PROBE))) {
            try {
                $this->domains->remove(self::PROBE);
            } catch (\Throwable) {
                // Best-effort cleanup.
            }
        }

        parent::tearDown();
    }

    public function test_scaffold_generates_and_registers_role_seeder(): void
    {
        $this->domains->scaffold(self::PROBE, locked: false);

        $seederPath = $this->domains->roleSeederPath(self::PROBE);
        $this->assertTrue(File::isFile($seederPath));

        $seeder = File::get($seederPath);
        $this->assertStringContainsString('class DomainAccessProbeRoleSeeder extends Seeder', $seeder);
        $this->assertStringContainsString('use SeedsBetaTesterRole;', $seeder);
        $this->assertStringContainsString("seedBetaTesterRole('DomainAccessProbe')", $seeder);
        $this->assertStringContainsString('ProductDomainVisibility::query()->updateOrCreate', $seeder);

        $databaseSeeder = File::get(database_path('seeders/DatabaseSeeder.php'));
        $this->assertStringContainsString('$this->call(DomainAccessProbeRoleSeeder::class);', $databaseSeeder);
    }

    public function test_remove_unregisters_role_seeder(): void
    {
        $this->domains->scaffold(self::PROBE, locked: false);
        $this->domains->remove(self::PROBE);

        $this->assertFalse(File::isFile($this->domains->roleSeederPath(self::PROBE)));

        $databaseSeeder = File::get(database_path('seeders/DatabaseSeeder.php'));
        $this->assertStringNotContainsString('DomainAccessProbeRoleSeeder', $databaseSeeder);
    }
}
