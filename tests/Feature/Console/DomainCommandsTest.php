<?php

namespace Tests\Feature\Console;

use App\Support\Domain\DomainManager;
use Illuminate\Support\Facades\File;
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

    protected function setUp(): void
    {
        parent::setUp();

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

        $this->purgeTestDomainArtifacts();
    }

    protected function tearDown(): void
    {
        $this->purgeTestDomainArtifacts();

        File::put($this->domainsConfigPath, $this->domainsConfigBackup);
        File::put($this->providersPath, $this->providersBackup);
        File::put($this->apiV1Path, $this->apiV1Backup);
        File::put($this->webPath, $this->webBackup);
        File::put($this->bootstrapPath, $this->bootstrapBackup);

        parent::tearDown();
    }

    public function test_domain_list_shows_kind_and_removal_axes(): void
    {
        $domains = app(DomainManager::class);

        $this->assertSame('core', $domains->kind('Identity'));
        $this->assertSame('core', $domains->kind('PaymentGateway'));
        $this->assertSame('product', $domains->kind('FileHosting'));

        $this->assertSame('never', $domains->removalStatus('Identity'));
        $this->assertSame('locked', $domains->removalStatus('FileHosting'));

        $this->artisan('domain:list')
            ->assertSuccessful()
            ->expectsOutputToContain('Identity')
            ->expectsOutputToContain('PaymentGateway')
            ->expectsOutputToContain('FileHosting')
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
            strpos($api, "require __DIR__.'/v1/file-hosting.php';"),
            strpos($api, "require __DIR__.'/v1/identity.php';"),
            'API loader: core identity must appear before product file-hosting'
        );
        $this->assertLessThan(
            strpos($api, "require __DIR__.'/v1/file-hosting.php';"),
            strpos($api, "require __DIR__.'/v1/service.php';"),
            'API loader: core service must appear before product file-hosting'
        );

        $web = File::get($this->webPath);
        $this->assertLessThan(
            strpos($web, "require __DIR__.'/web/file-hosting.php';"),
            strpos($web, "require __DIR__.'/web/identity.php';"),
            'Web loader: core identity must appear before product file-hosting'
        );
        $this->assertLessThan(
            strpos($web, "require __DIR__.'/web/file-hosting.php';"),
            strpos($web, "require __DIR__.'/web/service.php';"),
            'Web loader: core service must appear before product file-hosting'
        );

        $providers = File::get($this->providersPath);
        $this->assertLessThan(
            strpos($providers, 'FileHostingServiceProvider::class'),
            strpos($providers, 'IdentityServiceProvider::class'),
            'Providers: core Identity must appear before product FileHosting'
        );
        $this->assertLessThan(
            strpos($providers, 'FileHostingServiceProvider::class'),
            strpos($providers, 'ServiceServiceProvider::class'),
            'Providers: core Service must appear before product FileHosting'
        );
    }

    private function purgeTestDomainArtifacts(): void
    {
        $domainDir = app_path('Domain/'.self::TEST_DOMAIN);
        if (File::isDirectory($domainDir)) {
            File::deleteDirectory($domainDir);
        }

        $testsDir = base_path('tests/Domain/'.self::TEST_DOMAIN);
        if (File::isDirectory($testsDir)) {
            File::deleteDirectory($testsDir);
        }

        $provider = app_path('Providers/Domain/'.self::TEST_DOMAIN.'ServiceProvider.php');
        if (File::isFile($provider)) {
            File::delete($provider);
        }

        foreach ([
            base_path('routes/api/v1/zz-demo.php'),
            base_path('routes/web/zz-demo.php'),
        ] as $route) {
            if (File::isFile($route)) {
                File::delete($route);
            }
        }
    }
}
