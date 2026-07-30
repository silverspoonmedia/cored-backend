<?php

namespace Tests\Unit\Support;

use App\Support\EnvironmentBackupManager;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class EnvironmentBackupManagerTest extends TestCase
{
    private string $sandbox;

    private EnvironmentBackupManager $backups;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sandbox = storage_path('framework/testing-env-backups-'.uniqid());
        File::makeDirectory($this->sandbox, recursive: true);
        $this->backups = new EnvironmentBackupManager($this->sandbox, $this->sandbox);
    }

    protected function tearDown(): void
    {
        if (File::isDirectory($this->sandbox)) {
            File::deleteDirectory($this->sandbox);
        }

        parent::tearDown();
    }

    public function test_it_creates_commits_and_lists_pending_backups(): void
    {
        $envPath = $this->sandbox.DIRECTORY_SEPARATOR.'.env';
        File::put($envPath, "APP_ENV=\"local\"\n");

        $pending = $this->backups->createPending('test', [$envPath => '.env']);

        $this->assertTrue($this->backups->hasPending());
        $this->assertCount(1, $this->backups->listPending());
        $this->assertFileExists($pending->directory.DIRECTORY_SEPARATOR.'files'.DIRECTORY_SEPARATOR.'.env');

        $this->backups->commit($pending);

        $this->assertFalse($this->backups->hasPending());
    }

    public function test_it_restores_files_on_rollback(): void
    {
        $envPath = $this->sandbox.DIRECTORY_SEPARATOR.'.env';
        File::put($envPath, "APP_ENV=\"local\"\n");

        $pending = $this->backups->createPending('test', [$envPath => '.env']);
        File::put($envPath, "APP_ENV=\"staging\"\n");

        $this->backups->rollback($pending);

        $this->assertStringContainsString('APP_ENV="local"', File::get($envPath));
    }
}
