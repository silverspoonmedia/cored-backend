<?php

namespace Tests\Unit\Support;

use App\Support\EnvironmentBackupManager;
use App\Support\EnvironmentMutationGuard;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\TestCase;

class EnvironmentMutationGuardTest extends TestCase
{
    private string $sandbox;

    private EnvironmentMutationGuard $guard;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sandbox = storage_path('framework/testing-env-guard-'.uniqid());
        File::makeDirectory($this->sandbox, recursive: true);
        $this->guard = new EnvironmentMutationGuard(
            new EnvironmentBackupManager($this->sandbox, $this->sandbox),
        );
    }

    protected function tearDown(): void
    {
        if (File::isDirectory($this->sandbox)) {
            File::deleteDirectory($this->sandbox);
        }

        parent::tearDown();
    }

    public function test_it_commits_backup_on_success(): void
    {
        $envPath = $this->sandbox.DIRECTORY_SEPARATOR.'.env';
        File::put($envPath, "APP_ENV=\"local\"\n");

        $this->guard->run(
            'test-success',
            function () use ($envPath): void {
                File::put($envPath, "APP_ENV=\"staging\"\n");
            },
            [$envPath => '.env'],
        );

        $this->assertStringContainsString('APP_ENV="staging"', File::get($envPath));
        $this->assertFalse((new EnvironmentBackupManager($this->sandbox, $this->sandbox))->hasPending());
    }

    public function test_it_rolls_back_and_keeps_backup_on_failure(): void
    {
        $envPath = $this->sandbox.DIRECTORY_SEPARATOR.'.env';
        File::put($envPath, "APP_ENV=\"local\"\n");

        try {
            $this->guard->run(
                'test-failure',
                function () use ($envPath): void {
                    File::put($envPath, "APP_ENV=\"broken\"\n");
                    throw new RuntimeException('boom');
                },
                [$envPath => '.env'],
            );
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('boom', $exception->getMessage());
        }

        $this->assertStringContainsString('APP_ENV="local"', File::get($envPath));
        $this->assertTrue((new EnvironmentBackupManager($this->sandbox, $this->sandbox))->hasPending());
    }

    public function test_it_blocks_when_pending_backup_exists(): void
    {
        $envPath = $this->sandbox.DIRECTORY_SEPARATOR.'.env';
        File::put($envPath, "APP_ENV=\"local\"\n");

        $backups = new EnvironmentBackupManager($this->sandbox, $this->sandbox);
        $backups->createPending('stale', [$envPath => '.env']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('pending env backup exists');

        $this->guard->run('blocked', fn () => null, [$envPath => '.env']);
    }
}
