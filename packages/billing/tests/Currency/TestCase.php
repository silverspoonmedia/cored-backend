<?php

namespace App\Domain\Currency\Tests;

use App\Domain\PaymentGateway\BillingServiceProvider;
use App\Support\Testing\PackageTestCase;

/**
 * Currency's standalone harness: the kernel plus the billing package's provider
 * (one provider carries all three billing domains) and its migrations.
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
