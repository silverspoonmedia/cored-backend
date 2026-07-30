<?php

namespace Tests\Unit\Support;

use App\Support\EnvironmentCurator;
use App\Support\EnvironmentKeyWriter;
use App\Support\EnvironmentMutationGuard;
use App\Support\EnvironmentProfileManager;
use App\Support\EnvironmentBackupManager;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class EnvironmentCuratorTest extends TestCase
{
    private string $sandbox;

    private EnvironmentCurator $curator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sandbox = storage_path('framework/testing-env-curator-'.uniqid());
        File::makeDirectory($this->sandbox, recursive: true);

        $storage = $this->sandbox.DIRECTORY_SEPARATOR.'storage';
        File::makeDirectory($storage, recursive: true);

        $backups = new EnvironmentBackupManager($storage, $this->sandbox);
        $guard = new EnvironmentMutationGuard($backups);
        $profiles = new EnvironmentProfileManager(new EnvironmentKeyWriter(), $guard, $this->sandbox);
        $this->curator = new EnvironmentCurator($profiles, $guard);

        File::copy(base_path('.env.local.example'), $profiles->templatePath('local'));
        File::put($profiles->markerPath(), "local\n");
    }

    protected function tearDown(): void
    {
        if (File::isDirectory($this->sandbox)) {
            File::deleteDirectory($this->sandbox);
        }

        parent::tearDown();
    }

    public function test_it_preserves_values_and_adds_missing_template_keys(): void
    {
        $profiles = new EnvironmentProfileManager(
            new EnvironmentKeyWriter(),
            new EnvironmentMutationGuard(new EnvironmentBackupManager(
                $this->sandbox.DIRECTORY_SEPARATOR.'storage',
                $this->sandbox,
            )),
            $this->sandbox,
        );

        File::copy(base_path('.env.local.example'), $profiles->templatePath('local'));
        File::put($profiles->markerPath(), "local\n");
        File::put($profiles->envPath(), implode("\n", [
            'APP_ENV="local"',
            'APP_KEY="base64:'.base64_encode(random_bytes(32)).'"',
            'APP_PREVIOUS_KEYS=""',
            'DB_DATABASE="custom_db"',
            'ORPHAN_KEY="keep-me"',
        ])."\n");

        $curator = new EnvironmentCurator(
            $profiles,
            new EnvironmentMutationGuard(new EnvironmentBackupManager(
                $this->sandbox.DIRECTORY_SEPARATOR.'storage',
                $this->sandbox,
            )),
        );

        $result = $curator->curate();

        $env = File::get($profiles->envPath());

        $this->assertStringContainsString('DB_DATABASE="custom_db"', $env);
        $this->assertStringContainsString('ORPHAN_KEY="keep-me"', $env);
        $this->assertStringContainsString('# --- Application', $env);
        $this->assertNotEmpty($result['added_keys']);
    }

    public function test_dry_run_does_not_write_files(): void
    {
        $profiles = new EnvironmentProfileManager(
            new EnvironmentKeyWriter(),
            new EnvironmentMutationGuard(new EnvironmentBackupManager(
                $this->sandbox.DIRECTORY_SEPARATOR.'storage',
                $this->sandbox,
            )),
            $this->sandbox,
        );

        File::copy(base_path('.env.local.example'), $profiles->templatePath('local'));
        File::put($profiles->markerPath(), "local\n");
        $original = "APP_ENV=\"local\"\nDB_DATABASE=\"unchanged\"\n";
        File::put($profiles->envPath(), $original);
        File::put($profiles->markerPath(), "local\n");

        $curator = new EnvironmentCurator(
            $profiles,
            new EnvironmentMutationGuard(new EnvironmentBackupManager(
                $this->sandbox.DIRECTORY_SEPARATOR.'storage',
                $this->sandbox,
            )),
        );

        $curator->curate(dryRun: true);

        $this->assertSame($original, File::get($profiles->envPath()));
    }
}
