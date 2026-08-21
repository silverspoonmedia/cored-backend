<?php

namespace Tests\Feature\Http\Middleware;

use Tests\TestCase;

class TrustProxiesRedirectTest extends TestCase
{
    public function test_root_redirect_uses_forwarded_host_and_proto(): void
    {
        config(['services.spa.url' => '']);

        $response = $this->withHeaders([
            'X-Forwarded-Host' => 'tunnel.example.com',
            'X-Forwarded-Proto' => 'https',
        ])->get('/');

        $response->assertRedirect('https://tunnel.example.com');
    }
}
