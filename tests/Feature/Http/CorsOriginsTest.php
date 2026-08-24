<?php

namespace Tests\Feature\Http;

use Tests\TestCase;

class CorsOriginsTest extends TestCase
{
    public function test_app_url_origin_is_allowed(): void
    {
        $origin = rtrim((string) config('app.url'), '/');

        $this->withHeaders(['Origin' => $origin])
            ->getJson('/api/ping')
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', $origin);
    }

    public function test_foreign_origin_does_not_receive_allow_origin(): void
    {
        $this->withHeaders(['Origin' => 'https://evil.example'])
            ->getJson('/api/ping')
            ->assertOk()
            ->assertHeaderMissing('Access-Control-Allow-Origin');
    }

    public function test_spa_marker_is_allowed_by_preflight(): void
    {
        $origin = rtrim((string) config('app.url'), '/');

        $this->call(
            'OPTIONS',
            '/api/v1/csrf',
            server: [
                'HTTP_ORIGIN' => $origin,
                'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
                'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'x-spa-origin',
            ],
        )
            ->assertSuccessful()
            ->assertHeader('Access-Control-Allow-Origin', $origin)
            ->assertHeader('Access-Control-Allow-Credentials', 'true');
    }
}
