<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

/**
 * Base test case for a project built on the core packages.
 *
 * The application is booted from `bootstrap/app.php` exactly as a request would
 * boot it, so a test exercises the same providers, middleware aliases and route
 * loader the deployed project uses. That is the point of this scaffold: prove
 * the package wiring, not a test-only arrangement of it.
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * Creates the application.
     */
    public function createApplication(): Application
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }
}
