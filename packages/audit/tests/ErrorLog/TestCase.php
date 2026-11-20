<?php

namespace App\Domain\ErrorLog\Tests;

use App\Domain\ActivityLog\AuditServiceProvider;
use App\Support\Testing\PackageTestCase;

/**
 * ErrorLog's standalone harness: the kernel plus the audit package's provider
 * (one provider carries both audit domains) and its migrations.
 */
abstract class TestCase extends PackageTestCase
{
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
