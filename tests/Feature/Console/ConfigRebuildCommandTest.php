<?php

namespace Tests\Feature\Console;

use App\Support\Config\ConfigSecretCheck;
use App\Support\Config\ConfigSecretVerificationResult;
use App\Support\Config\ConfigSecretVerifier;
use Illuminate\Support\Facades\File;
use Mockery\MockInterface;
use Tests\TestCase;

class ConfigRebuildCommandTest extends TestCase
{
    private ?string $configCacheBackup = null;

    protected function tearDown(): void
    {
        $cachePath = base_path('bootstrap/cache/config.php');

        if ($this->configCacheBackup !== null) {
            File::put($cachePath, $this->configCacheBackup);
        } elseif (File::exists($cachePath)) {
            File::delete($cachePath);
        }

        parent::tearDown();
    }

    public function test_rebuild_runs_clear_verify_cache_and_post_verify(): void
    {
        $cachePath = base_path('bootstrap/cache/config.php');
        $this->configCacheBackup = File::exists($cachePath) ? File::get($cachePath) : null;

        $pass = new ConfigSecretVerificationResult([
            new ConfigSecretCheck('app.key', 'APP_KEY', 'ok'),
        ], fromFreshBootstrap: true);

        $this->mock(ConfigSecretVerifier::class, function (MockInterface $mock) use ($pass): void {
            $mock->shouldReceive('verifyFresh')
                ->twice()
                ->andReturn($pass);
        });

        $this->artisan('config:rebuild')
            ->assertSuccessful();

        $this->assertFileExists($cachePath);
    }

    public function test_rebuild_stops_when_pre_cache_verification_fails(): void
    {
        $cachePath = base_path('bootstrap/cache/config.php');
        $this->configCacheBackup = File::exists($cachePath) ? File::get($cachePath) : null;

        if (File::exists($cachePath)) {
            File::delete($cachePath);
        }

        $fail = new ConfigSecretVerificationResult([
            new ConfigSecretCheck('payment.gateways.ipaymu.api_key', 'IPAYMU_API_KEY', 'missing'),
        ], fromFreshBootstrap: true);

        $this->mock(ConfigSecretVerifier::class, function (MockInterface $mock) use ($fail): void {
            $mock->shouldReceive('verifyFresh')
                ->once()
                ->andReturn($fail);
        });

        $this->artisan('config:rebuild')
            ->assertFailed();

        $this->assertFileDoesNotExist($cachePath);
    }
}
