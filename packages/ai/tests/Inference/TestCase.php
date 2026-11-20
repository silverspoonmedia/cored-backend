<?php

namespace App\Domain\Inference\Tests;

use App\Domain\Ai\AiServiceProvider;
use App\Support\Testing\PackageTestCase;

/**
 * `Inference`'s standalone harness: the kernel plus the ai package's provider
 * (one provider carries both ai domains) and its migrations.
 */
abstract class TestCase extends PackageTestCase
{
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), AiServiceProvider::class];
    }

    protected function runExtraDatabaseMigrations(): void
    {
        $this->runPackageMigrations(dirname(__DIR__, 2).'/database/migrations');
    }
}
