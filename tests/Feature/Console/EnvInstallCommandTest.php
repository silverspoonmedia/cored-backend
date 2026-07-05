<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Covers `env:install` — scaffolds `.env` from committed profile templates.
 * Backs up and restores the real `.env` so developer machines are not mutated.
 */
class EnvInstallCommandTest extends TestCase
{
    private ?string $envBackup = null;

    private bool $hadEnv = false;

    protected function tearDown(): void
    {
        $envPath = base_path('.env');

        if ($this->hadEnv && $this->envBackup !== null) {
            File::put($envPath, $this->envBackup);
        } elseif (File::exists($envPath)) {
            File::delete($envPath);
        }

        parent::tearDown();
    }

    public function test_it_installs_local_profile_and_generates_secrets(): void
    {
        $this->prepareEnvFile();

        $this->artisan('env:install', ['profile' => 'local'])
            ->assertSuccessful();

        $env = File::get(base_path('.env'));

        $this->assertStringContainsString('APP_ENV="local"', $env);
        $this->assertStringContainsString('APP_DEBUG=true', $env);
        $this->assertMatchesRegularExpression('/^APP_KEY="base64:.+"/m', $env);
        $this->assertMatchesRegularExpression('/^JWT_SECRET=.+$/m', $env);
        $this->assertDoesNotMatchRegularExpression('/^JWT_SECRET=""$/m', $env);
    }

    public function test_it_refuses_to_overwrite_existing_env_without_force(): void
    {
        $this->prepareEnvFile();
        File::put(base_path('.env'), "APP_NAME=\"Existing\"\n");

        $this->artisan('env:install', ['profile' => 'local'])
            ->assertFailed();

        $this->assertSame("APP_NAME=\"Existing\"\n", File::get(base_path('.env')));
    }

    public function test_it_overwrites_existing_env_with_force(): void
    {
        $this->prepareEnvFile();
        File::put(base_path('.env'), "APP_NAME=\"Existing\"\n");

        $this->artisan('env:install', [
            'profile' => 'staging',
            '--force' => true,
        ])->assertSuccessful();

        $env = File::get(base_path('.env'));
        $this->assertStringContainsString('APP_ENV="staging"', $env);
        $this->assertStringNotContainsString('APP_NAME="Existing"', $env);
    }

    public function test_it_rejects_an_invalid_profile(): void
    {
        $this->prepareEnvFile();

        $this->artisan('env:install', ['profile' => 'qa'])
            ->assertFailed();
    }

    private function prepareEnvFile(): void
    {
        $envPath = base_path('.env');
        $this->hadEnv = File::exists($envPath);

        if ($this->hadEnv) {
            $this->envBackup = File::get($envPath);
            File::delete($envPath);
        }
    }
}
