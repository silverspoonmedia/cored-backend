<?php

namespace App\Domain\PaymentGateway\Tests;

use App\Domain\PaymentGateway\BillingServiceProvider;
use App\Support\Testing\PackageTestCase;

/**
 * PaymentGateway's standalone harness: the kernel plus the billing package's
 * provider (one provider carries all three billing domains) and its migrations.
 * Tests that exercise a host HTTP route live in
 * `tests/Domain/Core/{PaymentGateway,Service,Currency}/Feature/`, not here.
 */
abstract class TestCase extends PackageTestCase
{
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), BillingServiceProvider::class];
    }

    protected function runExtraDatabaseMigrations(): void
    {
        $this->runPackageMigrations(dirname(__DIR__, 2).'/database/migrations');
    }
}
