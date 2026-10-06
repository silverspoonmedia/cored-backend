<?php

namespace Tests\Feature\Console;

use App\Support\Domain\DomainManager;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Covers domain:list / domain:make / domain:remove including lock + platform core.
 *
 * Uses throwaway domain ZzDemo and restores registry/route/provider files in
 * tearDown so the real boilerplate is never left mutated.
 */
class DomainCommandsTest extends TestCase
{
    private const TEST_DOMAIN = 'ZzDemo';

    private const TEST_CHILD_DOMAIN = 'ZzChild';

    private string $domainsConfigPath;

    private string $domainsConfigBackup;

    private string $providersPath;

    private string $providersBackup;

    private string $apiV1Path;

    private string $apiV1Backup;

    private string $webPath;

    private string $webBackup;

    private string $bootstrapPath;

    private string $bootstrapBackup;

    private string $databaseSeederPath;

    private string $databaseSeederBackup;

    protected function setUp(): void
    {
        parent::setUp();

        // Purge leftovers BEFORE snapshotting, so a previous run that leaked
        // generated artifacts cannot be restored by tearDown.
        $this->purgeTestDomainArtifacts();

        $this->domainsConfigPath = config_path('domains.php');
        $this->domainsConfigBackup = File::get($this->domainsConfigPath);

        $this->providersPath = base_path('bootstrap/providers.php');
        $this->providersBackup = File::get($this->providersPath);

        $this->apiV1Path = base_path('routes/api/v1.php');
        $this->apiV1Backup = File::get($this->apiV1Path);

        $this->webPath = base_path('routes/web.php');
        $this->webBackup = File::get($this->webPath);

        $this->bootstrapPath = base_path('bootstrap/app.php');
        $this->bootstrapBackup = File::get($this->bootstrapPath);

        // domain:make / domain:remove inject and strip the `$this->call(...)`
        // line in DatabaseSeeder; without a backup the generated call leaks
        // into the working tree after the test run.
        $this->databaseSeederPath = database_path('seeders/DatabaseSeeder.php');
        $this->databaseSeederBackup = File::get($this->databaseSeederPath);
    }

    protected function tearDown(): void
    {
        $this->purgeTestDomainArtifacts();

        File::put($this->domainsConfigPath, $this->domainsConfigBackup);
        File::put($this->providersPath, $this->providersBackup);
        File::put($this->apiV1Path, $this->apiV1Backup);
        File::put($this->webPath, $this->webBackup);
        File::put($this->bootstrapPath, $this->bootstrapBackup);
        File::put($this->databaseSeederPath, $this->databaseSeederBackup);

        parent::tearDown();
    }

    public function test_domain_list_shows_kind_and_removal_axes(): void
    {
        $domains = app(DomainManager::class);

        $this->assertSame('core', $domains->kind('Identity'));
        $this->assertSame('core', $domains->kind('PaymentGateway'));
        $this->assertSame('product', $domains->kind('Archivd'));

        $this->assertSame('never', $domains->removalStatus('Identity'));
        $this->assertSame('locked', $domains->removalStatus('Archivd'));

        $this->artisan('domain:list')
            ->assertSuccessful()
            ->expectsOutputToContain('Identity')
            ->expectsOutputToContain('PaymentGateway')
            ->expectsOutputToContain('Archivd')
            ->expectsOutputToContain('foundation (never removable)')
            ->expectsOutputToContain('unlock via config/domains.php');
    }

    public function test_domain_make_defaults_to_locked_product(): void
    {
        $this->artisan('domain:make', [
            'name' => self::TEST_DOMAIN,
            '--no-interaction' => true,
        ])->assertSuccessful();

        $config = File::get($this->domainsConfigPath);
        $this->assertStringContainsString(
            "'".self::TEST_DOMAIN."' => ['kind' => 'product', 'locked' => true]",
            $config
        );

        $this->assertSame('product', app(DomainManager::class)->kind(self::TEST_DOMAIN));
        $this->assertSame('locked', app(DomainManager::class)->removalStatus(self::TEST_DOMAIN));

        $this->artisan('domain:remove', [
            'name' => self::TEST_DOMAIN,
            '--force' => true,
        ])->assertFailed();

        $this->assertDirectoryExists(app_path('Domain/'.self::TEST_DOMAIN));
    }

    public function test_domain_make_locked_flag_refuses_remove_until_config_unlock(): void
    {
        $this->artisan('domain:make', [
            'name' => self::TEST_DOMAIN,
            '--locked' => true,
        ])->assertSuccessful();

        $this->artisan('domain:remove', [
            'name' => self::TEST_DOMAIN,
            '--force' => true,
        ])->assertFailed();

        $this->assertDirectoryExists(app_path('Domain/'.self::TEST_DOMAIN));

        // Manual unlock — the only supported unlock path.
        $contents = File::get($this->domainsConfigPath);
        $contents = str_replace(
            "'".self::TEST_DOMAIN."' => ['kind' => 'product', 'locked' => true]",
            "'".self::TEST_DOMAIN."' => ['kind' => 'product', 'locked' => false]",
            $contents
        );
        File::put($this->domainsConfigPath, $contents);
        config([
            'domains.'.self::TEST_DOMAIN => ['kind' => 'product', 'locked' => false],
        ]);

        $this->assertSame('removable', app(DomainManager::class)->removalStatus(self::TEST_DOMAIN));

        $this->artisan('domain:remove', [
            'name' => self::TEST_DOMAIN,
            '--force' => true,
        ])->assertSuccessful();

        $this->assertDirectoryDoesNotExist(app_path('Domain/'.self::TEST_DOMAIN));
    }

    public function test_domain_make_removable_can_be_removed(): void
    {
        $this->artisan('domain:make', [
            'name' => self::TEST_DOMAIN,
            '--removable' => true,
        ])->assertSuccessful();

        $config = File::get($this->domainsConfigPath);
        $this->assertStringContainsString(
            "'".self::TEST_DOMAIN."' => ['kind' => 'product', 'locked' => false]",
            $config
        );

        $this->artisan('domain:remove', [
            'name' => self::TEST_DOMAIN,
            '--force' => true,
        ])->assertSuccessful();

        $this->assertDirectoryDoesNotExist(app_path('Domain/'.self::TEST_DOMAIN));
        $this->assertFileDoesNotExist(app_path('Providers/Domain/'.self::TEST_DOMAIN.'ServiceProvider.php'));
        $this->assertFileDoesNotExist(base_path('routes/api/v1/zz-demo.php'));
        $this->assertFileDoesNotExist(base_path('routes/web/zz-demo.php'));
        $this->assertDirectoryDoesNotExist(base_path('tests/Domain/'.self::TEST_DOMAIN));

        $this->assertFileDoesNotExist(database_path('seeders/'.self::TEST_DOMAIN.'RoleSeeder.php'));
        $this->assertStringNotContainsString(
            '$this->call('.self::TEST_DOMAIN.'RoleSeeder::class);',
            File::get($this->databaseSeederPath)
        );

        $this->assertStringNotContainsString("'".self::TEST_DOMAIN."'", File::get($this->domainsConfigPath));
        $this->assertStringNotContainsString(self::TEST_DOMAIN.'ServiceProvider', File::get($this->providersPath));
        $this->assertStringNotContainsString('zz-demo.php', File::get($this->apiV1Path));
        $this->assertStringNotContainsString('zz-demo.php', File::get($this->webPath));
    }

    public function test_domain_make_scaffolds_files_and_registrations(): void
    {
        $this->artisan('domain:make', [
            'name' => self::TEST_DOMAIN,
            '--removable' => true,
        ])->assertSuccessful();

        $this->assertDirectoryExists(app_path('Domain/'.self::TEST_DOMAIN));
        $this->assertFileExists(app_path('Providers/Domain/'.self::TEST_DOMAIN.'ServiceProvider.php'));
        $this->assertFileExists(base_path('routes/api/v1/zz-demo.php'));
        $this->assertFileExists(base_path('routes/web/zz-demo.php'));
        $this->assertDirectoryExists(base_path('tests/Domain/'.self::TEST_DOMAIN.'/Feature'));

        $this->assertFileExists(database_path('seeders/'.self::TEST_DOMAIN.'RoleSeeder.php'));
        $this->assertStringContainsString(
            '$this->call('.self::TEST_DOMAIN.'RoleSeeder::class);',
            File::get($this->databaseSeederPath)
        );

        $providers = File::get($this->providersPath);
        $this->assertStringContainsString(self::TEST_DOMAIN.'ServiceProvider::class', $providers);
        $this->assertStringContainsString('use App\\Providers\\Domain\\'.self::TEST_DOMAIN.'ServiceProvider;', $providers);

        $this->assertStringContainsString("require __DIR__.'/v1/zz-demo.php';", File::get($this->apiV1Path));
        $this->assertStringContainsString("require __DIR__.'/web/zz-demo.php';", File::get($this->webPath));
    }

    public function test_domain_make_rejects_existing_and_reserved_names(): void
    {
        $this->artisan('domain:make', [
            'name' => 'Identity',
            '--no-interaction' => true,
        ])->assertFailed();

        $this->artisan('domain:make', [
            'name' => 'Kernel',
            '--no-interaction' => true,
        ])->assertFailed();
    }

    public function test_domain_remove_refuses_platform_core_even_if_config_flipped(): void
    {
        // Prove config cannot unlock platform core.
        $contents = File::get($this->domainsConfigPath);
        $contents = str_replace(
            "'Identity' => ['kind' => 'core']",
            "'Identity' => ['kind' => 'product', 'locked' => false]",
            $contents
        );
        File::put($this->domainsConfigPath, $contents);
        config(['domains.Identity' => ['kind' => 'product', 'locked' => false]]);

        $this->assertTrue(app(DomainManager::class)->isPlatformCore('Identity'));
        $this->assertSame('core', app(DomainManager::class)->kind('Identity'));
        $this->assertSame('never', app(DomainManager::class)->removalStatus('Identity'));

        $this->artisan('domain:remove', [
            'name' => 'Identity',
            '--force' => true,
        ])->assertFailed();

        $this->assertDirectoryExists(app_path('Domain/Identity'));
    }

    public function test_domain_remove_refuses_all_platform_core_domains(): void
    {
        foreach (DomainManager::PLATFORM_CORE as $core) {
            $this->artisan('domain:remove', [
                'name' => $core,
                '--force' => true,
            ])->assertFailed();

            $this->assertDirectoryExists(app_path('Domain/'.$core));
        }
    }

    public function test_domain_remove_refuses_unknown_domain(): void
    {
        $this->artisan('domain:remove', [
            'name' => 'DoesNotExist',
            '--force' => true,
        ])->assertFailed();
    }

    public function test_loaders_list_core_requires_before_product(): void
    {
        $api = File::get($this->apiV1Path);
        $this->assertLessThan(
            strpos($api, "require __DIR__.'/v1/archivd.php';"),
            strpos($api, "require __DIR__.'/v1/identity.php';"),
            'API loader: core identity must appear before product archivd'
        );
        $this->assertLessThan(
            strpos($api, "require __DIR__.'/v1/archivd.php';"),
            strpos($api, "require __DIR__.'/v1/service.php';"),
            'API loader: core service must appear before product archivd'
        );

        $providers = File::get($this->providersPath);
        $this->assertLessThan(
            strpos($providers, 'ArchivdServiceProvider::class'),
            strpos($providers, 'IdentityServiceProvider::class'),
            'Providers: core Identity must appear before product Archivd'
        );
        $this->assertLessThan(
            strpos($providers, 'ArchivdServiceProvider::class'),
            strpos($providers, 'ServiceServiceProvider::class'),
            'Providers: core Service must appear before product Archivd'
        );
    }

    public function test_domain_remove_dry_run_prints_the_plan_and_changes_nothing(): void
    {
        $this->artisan('domain:make', [
            'name' => self::TEST_DOMAIN,
            '--removable' => true,
        ])->assertSuccessful();

        $manifestPath = app_path('Domain/'.self::TEST_DOMAIN.'/uninstall.json');
        $this->assertFileExists($manifestPath);

        $manifest = File::get($manifestPath);
        $config = File::get($this->domainsConfigPath);

        $this->artisan('domain:remove', [
            'name' => self::TEST_DOMAIN,
            '--dry-run' => true,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('Uninstall plan for')
            ->expectsOutputToContain('Dry run');

        $this->assertDirectoryExists(app_path('Domain/'.self::TEST_DOMAIN));
        $this->assertSame($manifest, File::get($manifestPath));
        $this->assertSame($config, File::get($this->domainsConfigPath));
    }

    public function test_domain_remove_draft_manifest_writes_a_candidate_and_refuses_to_overwrite(): void
    {
        $this->artisan('domain:make', [
            'name' => self::TEST_DOMAIN,
            '--removable' => true,
        ])->assertSuccessful();

        $manifestPath = app_path('Domain/'.self::TEST_DOMAIN.'/uninstall.json');
        File::delete($manifestPath);

        $this->artisan('domain:remove', [
            'name' => self::TEST_DOMAIN,
            '--draft-manifest' => true,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('Draft written to');

        $this->assertFileExists($manifestPath);
        $draft = json_decode(File::get($manifestPath), true);
        $this->assertSame('domain-uninstall/v1', $draft['schema']);
        $this->assertSame(self::TEST_DOMAIN, $draft['domain']);

        // Drafting is not removing.
        $this->assertDirectoryExists(app_path('Domain/'.self::TEST_DOMAIN));

        // A second draft must not clobber a reviewed manifest.
        $this->artisan('domain:remove', [
            'name' => self::TEST_DOMAIN,
            '--draft-manifest' => true,
        ])->assertFailed();
    }

    public function test_domain_remove_purge_data_is_refused_without_the_env_flag(): void
    {
        $this->artisan('domain:make', [
            'name' => self::TEST_DOMAIN,
            '--removable' => true,
        ])->assertSuccessful();

        config([
            'database.default' => 'mysql',
            'database.allow_destructive_db' => false,
        ]);

        $this->artisan('domain:remove', [
            'name' => self::TEST_DOMAIN,
            '--purge-data' => true,
            '--force' => true,
        ])->assertFailed();

        // The guard runs before any deletion, so nothing was touched.
        $this->assertDirectoryExists(app_path('Domain/'.self::TEST_DOMAIN));
        $this->assertFileExists(app_path('Domain/'.self::TEST_DOMAIN.'/uninstall.json'));
        $this->assertStringContainsString("'".self::TEST_DOMAIN."'", File::get($this->domainsConfigPath));
    }

    public function test_domain_list_shows_parent_axis_for_subproducts(): void
    {
        $domains = app(DomainManager::class);

        $this->assertSame('Archivd', $domains->parentOf('Microstock'));
        $this->assertNull($domains->parentOf('Archivd'));
        $this->assertNull($domains->parentOf('Identity'));
        $this->assertContains('Microstock', $domains->childrenOf('Archivd'));
        $this->assertSame([], $domains->childrenOf('Vtual'));

        $this->artisan('domain:list')
            ->assertSuccessful()
            ->expectsOutputToContain('Microstock')
            ->expectsOutputToContain('Archivd')
            ->expectsOutputToContain('subproduct');
    }

    public function test_domain_make_parent_scaffolds_a_subproduct(): void
    {
        $this->artisan('domain:make', [
            'name' => self::TEST_DOMAIN,
            '--removable' => true,
        ])->assertSuccessful();

        $this->artisan('domain:make', [
            'name' => self::TEST_CHILD_DOMAIN,
            '--removable' => true,
            '--parent' => self::TEST_DOMAIN,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('subproduct');

        $this->assertStringContainsString(
            "'".self::TEST_CHILD_DOMAIN."' => ['kind' => 'product', 'locked' => false, 'parent' => '".self::TEST_DOMAIN."']",
            File::get($this->domainsConfigPath)
        );

        $domains = app(DomainManager::class);
        $this->assertSame(self::TEST_DOMAIN, $domains->parentOf(self::TEST_CHILD_DOMAIN));
        $this->assertContains(self::TEST_CHILD_DOMAIN, $domains->childrenOf(self::TEST_DOMAIN));
    }

    public function test_domain_make_rejects_a_parent_that_is_not_a_registered_product(): void
    {
        $this->artisan('domain:make', [
            'name' => self::TEST_DOMAIN,
            '--no-interaction' => true,
            '--parent' => 'Identity',
        ])->assertFailed();

        $this->assertDirectoryDoesNotExist(app_path('Domain/'.self::TEST_DOMAIN));

        $this->artisan('domain:make', [
            'name' => self::TEST_DOMAIN,
            '--no-interaction' => true,
            '--parent' => 'DoesNotExist',
        ])->assertFailed();

        $this->assertDirectoryDoesNotExist(app_path('Domain/'.self::TEST_DOMAIN));
        $this->assertStringNotContainsString("'".self::TEST_DOMAIN."'", File::get($this->domainsConfigPath));
    }

    public function test_domain_remove_refuses_while_a_subproduct_is_registered(): void
    {
        $this->artisan('domain:make', [
            'name' => self::TEST_DOMAIN,
            '--removable' => true,
        ])->assertSuccessful();

        $this->artisan('domain:make', [
            'name' => self::TEST_CHILD_DOMAIN,
            '--removable' => true,
            '--parent' => self::TEST_DOMAIN,
        ])->assertSuccessful();

        // A subproduct cannot outlive its parent: the parent removal is refused.
        $this->artisan('domain:remove', [
            'name' => self::TEST_DOMAIN,
            '--force' => true,
        ])->assertFailed();

        $this->assertDirectoryExists(app_path('Domain/'.self::TEST_DOMAIN));

        // Dry run reports the same, still succeeds, and changes nothing.
        $this->artisan('domain:remove', [
            'name' => self::TEST_DOMAIN,
            '--dry-run' => true,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain(self::TEST_CHILD_DOMAIN);

        $this->assertDirectoryExists(app_path('Domain/'.self::TEST_DOMAIN));

        // Removing the child first unblocks the parent.
        $this->artisan('domain:remove', [
            'name' => self::TEST_CHILD_DOMAIN,
            '--force' => true,
        ])->assertSuccessful();

        $this->artisan('domain:remove', [
            'name' => self::TEST_DOMAIN,
            '--force' => true,
        ])->assertSuccessful();

        $this->assertDirectoryDoesNotExist(app_path('Domain/'.self::TEST_DOMAIN));
    }

    public function test_registry_parent_entries_point_at_a_registered_product(): void
    {
        $domains = app(DomainManager::class);
        $all = $domains->all();

        foreach ($all as $name => $meta) {
            if ($meta['parent'] === null) {
                continue;
            }

            $this->assertArrayHasKey($meta['parent'], $all, "[{$name}] declares an unregistered parent.");
            $this->assertSame('product', $all[$meta['parent']]['kind'], "[{$name}] declares a non-product parent.");
            $this->assertSame('product', $meta['kind'], "[{$name}] declares a parent but is not a product.");
        }

        // The live pair this axis was introduced for.
        $this->assertSame('Archivd', $domains->parentOf('Microstock'));
    }

    private function purgeTestDomainArtifacts(): void
    {
        foreach ([self::TEST_DOMAIN, self::TEST_CHILD_DOMAIN] as $name) {
            $this->purgeDomainArtifacts($name);
        }
    }

    private function purgeDomainArtifacts(string $name): void
    {
        $domainDir = app_path('Domain/'.$name);
        if (File::isDirectory($domainDir)) {
            File::deleteDirectory($domainDir);
        }

        $testsDir = base_path('tests/Domain/'.$name);
        if (File::isDirectory($testsDir)) {
            File::deleteDirectory($testsDir);
        }

        $provider = app_path('Providers/Domain/'.$name.'ServiceProvider.php');
        if (File::isFile($provider)) {
            File::delete($provider);
        }

        $slug = Str::kebab($name);

        foreach ([
            base_path('routes/api/v1/'.$slug.'.php'),
            base_path('routes/web/'.$slug.'.php'),
            database_path('seeders/'.$name.'RoleSeeder.php'),
        ] as $artifact) {
            if (File::isFile($artifact)) {
                File::delete($artifact);
            }
        }

        // A previous run that died before tearDown can leave the generated
        // `$this->call({Name}RoleSeeder::class);` line behind; strip it so the
        // backup/restore cycle never preserves the leak.
        $seederPath = database_path('seeders/DatabaseSeeder.php');
        $call = '$this->call('.$name.'RoleSeeder::class);';
        $contents = File::get($seederPath);
        if (str_contains($contents, $call)) {
            $kept = array_values(array_filter(
                preg_split('/\R/', $contents) ?: [],
                fn (string $line): bool => ! str_contains($line, $call),
            ));
            File::put($seederPath, implode(PHP_EOL, $kept).PHP_EOL);
        }
    }
}
