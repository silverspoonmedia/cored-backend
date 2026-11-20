<?php

namespace App\Domain\Ai\Tests;

use App\Domain\Ai\AiServiceProvider;
use App\Support\Testing\PackageTestCase;

/**
 * `Ai`'s standalone harness: the kernel plus this package's provider and
 * migrations. Tests that exercise a host HTTP route live in
 * `tests/Domain/Core/{Ai,Inference}/Feature/`, not here.
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
