<?php

namespace App\Domain\ActivityLog\Tests;

use App\Domain\ActivityLog\AuditServiceProvider;
use App\Support\Testing\PackageTestCase;

/**
 * ActivityLog's standalone harness: the kernel plus this package's provider and
 * migrations. Tests that exercise a host HTTP route live in
 * `tests/Domain/Core/{ActivityLog,ErrorLog}/Feature/`, not here.
 */
abstract class TestCase extends PackageTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The kernel base disables the activity trail (a package that does not
        // own `activity_log` must not try to write to it). This package owns the
        // table, so it turns the trail back on after the migrations ran.
        config(['activitylog.enabled' => true]);
    }

    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), AuditServiceProvider::class];
    }

    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();

        $this->runPackageMigrations(__DIR__.'/../../database/migrations');
    }
}
