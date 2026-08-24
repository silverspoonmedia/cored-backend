<?php

namespace Tests;

use App\Http\Middleware\RequireSpaRequestHeader;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'encryption.kms_enabled' => false,
            'encryption.blind_index_pepper' => 'testing-blind-index-pepper',
            'encryption.encrypt_sessions' => false,
        ]);

        $this->withoutMiddleware(PreventRequestForgery::class);

        // API feature tests model the sanctioned SPA caller by default. Tests
        // covering the perimeter explicitly remove this marker.
        $this->withHeader(RequireSpaRequestHeader::HEADER, RequireSpaRequestHeader::VALUE);

        if (config('database.default') !== 'sqlite') {
            $this->fail(
                'Tests must use the sqlite connection (see phpunit.xml). '
                .'Run `composer test` or `php artisan config:clear` before `php artisan test` '
                .'so cached MySQL config does not target the dev database.'
            );
        }
    }
}
