<?php

namespace Tests;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'gateway.enforce' => false,
            'gateway.secret' => 'testing-gateway-secret',
        ]);

        $this->withoutMiddleware(PreventRequestForgery::class);

        if (config('database.default') !== 'sqlite') {
            $this->fail(
                'Tests must use the sqlite connection (see phpunit.xml). '
                .'Run `composer test` or `php artisan config:clear` before `php artisan test` '
                .'so cached MySQL config does not target the dev database.'
            );
        }
    }
}
