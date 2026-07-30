<?php

namespace Tests\Unit\Support;

use App\Support\EnvironmentBackupManager;
use App\Support\EnvironmentKeyWriter;
use App\Support\EnvironmentMutationGuard;
use App\Support\EnvironmentProfileManager;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class EnvironmentProfileManagerTest extends TestCase
{
    private EnvironmentProfileManager $manager;

    private string $sandbox;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sandbox = storage_path('framework/testing-env-manager-'.uniqid());
        File::makeDirectory($this->sandbox, recursive: true);

        $storage = $this->sandbox.DIRECTORY_SEPARATOR.'storage';
        File::makeDirectory($storage, recursive: true);

        $this->manager = new EnvironmentProfileManager(
            new EnvironmentKeyWriter,
            new EnvironmentMutationGuard(new EnvironmentBackupManager($storage, $this->sandbox)),
            $this->sandbox,
        );

        File::copy(
            base_path('.env.local.example'),
            $this->manager->templatePath('local'),
        );

        File::copy(
            base_path('.env.staging.example'),
            $this->manager->templatePath('staging'),
        );
    }

    protected function tearDown(): void
    {
        if (File::isDirectory($this->sandbox)) {
            File::deleteDirectory($this->sandbox);
        }

        parent::tearDown();
    }

    public function test_it_saves_and_restores_profile_snapshots(): void
    {
        File::put($this->manager->envPath(), "APP_ENV=\"local\"\nDB_DATABASE=\"monolith_local\"\n");
        $this->manager->saveSnapshot('local');

        File::put($this->manager->envPath(), "APP_ENV=\"staging\"\nDB_DATABASE=\"monolith_staging\"\n");
        $this->manager->saveSnapshot('staging');

        File::copy($this->manager->profilePath('local'), $this->manager->envPath());

        $this->assertStringContainsString('monolith_local', File::get($this->manager->envPath()));
        $this->assertStringContainsString('monolith_staging', File::get($this->manager->profilePath('staging')));
    }

    public function test_it_tracks_active_profile_in_marker_file(): void
    {
        $this->manager->setActiveProfile('staging');

        $this->assertSame('staging', trim(File::get($this->manager->markerPath())));
        $this->assertSame('staging', $this->manager->activeProfile());
    }

    public function test_it_bootstraps_missing_profile_from_template(): void
    {
        $this->manager->bootstrapProfile('local');

        $snapshot = File::get($this->manager->profilePath('local'));

        $this->assertStringContainsString('APP_ENV="local"', $snapshot);
        $this->assertMatchesRegularExpression('/^APP_KEY="base64:.+"$/m', $snapshot);
        $this->assertMatchesRegularExpression('/^JWT_SECRET=.+$/m', $snapshot);
        $this->assertMatchesRegularExpression(
            '/^ENCRYPTION_BLIND_INDEX_PEPPER="[a-f0-9]{64}"$/m',
            $snapshot,
        );
    }

    public function test_activate_profile_switches_without_losing_saved_values(): void
    {
        File::put($this->manager->envPath(), implode("\n", [
            'APP_ENV="local"',
            'APP_KEY="base64:'.base64_encode(random_bytes(32)).'"',
            'APP_PREVIOUS_KEYS=""',
            'DB_DATABASE="monolith_local"',
        ]));
        $this->manager->setActiveProfile('local');
        $this->manager->saveSnapshot('local');

        $result = $this->manager->activateProfile('staging');

        $this->assertFalse($result->skipped);
        $this->assertTrue($result->bootstrapped);
        $this->assertStringContainsString('APP_ENV="staging"', File::get($this->manager->envPath()));
        $this->assertStringContainsString('monolith_local', File::get($this->manager->profilePath('local')));

        $this->manager->activateProfile('local');

        $this->assertStringContainsString('monolith_local', File::get($this->manager->envPath()));
        $this->assertSame('local', $this->manager->activeProfile());
    }

    public function test_activate_profile_skips_when_already_on_same_profile(): void
    {
        File::put($this->manager->envPath(), "APP_ENV=\"local\"\nDB_DATABASE=\"monolith\"\n");
        $this->manager->saveSnapshot('local');
        $this->manager->setActiveProfile('local');

        $result = $this->manager->activateProfile('local');

        $this->assertTrue($result->skipped);
    }

    public function test_activate_profile_fills_empty_blind_index_pepper_in_existing_snapshot(): void
    {
        File::put($this->manager->profilePath('local'), implode("\n", [
            'APP_ENV="local"',
            'APP_KEY="base64:'.base64_encode(random_bytes(32)).'"',
            'APP_PREVIOUS_KEYS=""',
            'JWT_SECRET="existing-jwt-secret"',
            'ENCRYPTION_BLIND_INDEX_PEPPER=""',
        ]));
        File::put($this->manager->markerPath(), "staging\n");
        File::put($this->manager->envPath(), "APP_ENV=\"staging\"\n");

        $this->manager->activateProfile('local');

        $snapshot = File::get($this->manager->profilePath('local'));
        $this->assertMatchesRegularExpression(
            '/^ENCRYPTION_BLIND_INDEX_PEPPER="[a-f0-9]{64}"$/m',
            $snapshot,
        );
    }
}
