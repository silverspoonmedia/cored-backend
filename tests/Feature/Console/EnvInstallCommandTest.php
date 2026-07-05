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
    /** @var array<string, string|null> */
    private array $backups = [];

    /** @var array<int, string> */
    private const MANAGED_FILES = [
        '.env',
        '.env.profile',
        '.env.local',
        '.env.staging',
        '.env.production',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (self::MANAGED_FILES as $file) {
            $path = base_path($file);
            $this->backups[$file] = File::exists($path) ? File::get($path) : null;
            if (File::exists($path)) {
                File::delete($path);
            }
        }
    }

    protected function tearDown(): void
    {
        foreach (self::MANAGED_FILES as $file) {
            $path = base_path($file);

            if (File::exists($path)) {
                File::delete($path);
            }

            if ($this->backups[$file] !== null) {
                File::put($path, $this->backups[$file]);
            }
        }

        parent::tearDown();
    }

    public function test_it_installs_local_profile_and_generates_secrets(): void
    {
        $this->artisan('env:install', ['profile' => 'local'])
            ->assertSuccessful();

        $env = File::get(base_path('.env'));

        $this->assertStringContainsString('APP_ENV="local"', $env);
        $this->assertStringContainsString('APP_DEBUG=true', $env);
        $this->assertMatchesRegularExpression('/^APP_KEY="base64:.+"/m', $env);
        $this->assertStringContainsString('APP_PREVIOUS_KEYS=""', $env);
        $this->assertMatchesRegularExpression('/^JWT_SECRET=.+$/m', $env);
        $this->assertDoesNotMatchRegularExpression('/^JWT_SECRET=""$/m', $env);
        $this->assertFileExists(base_path('.env.local'));
        $this->assertSame('local', trim(File::get(base_path('.env.profile'))));
    }

    public function test_it_refuses_to_overwrite_existing_env_without_force(): void
    {
        File::put(base_path('.env'), "APP_NAME=\"Existing\"\n");

        $this->artisan('env:install', ['profile' => 'local'])
            ->assertFailed();

        $this->assertSame("APP_NAME=\"Existing\"\n", File::get(base_path('.env')));
    }

    public function test_it_overwrites_existing_env_with_force(): void
    {
        File::put(base_path('.env'), "APP_NAME=\"Existing\"\n");

        $this->artisan('env:install', [
            'profile' => 'staging',
            '--force' => true,
        ])->assertSuccessful();

        $env = File::get(base_path('.env'));
        $this->assertStringContainsString('APP_ENV="staging"', $env);
        $this->assertStringNotContainsString('APP_NAME="Existing"', $env);
        $this->assertSame('staging', trim(File::get(base_path('.env.profile'))));
    }

    public function test_it_rejects_an_invalid_profile(): void
    {
        $this->artisan('env:install', ['profile' => 'qa'])
            ->assertFailed();
    }
}
