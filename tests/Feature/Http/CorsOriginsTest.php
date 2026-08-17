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
}
