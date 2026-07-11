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
    }
}
