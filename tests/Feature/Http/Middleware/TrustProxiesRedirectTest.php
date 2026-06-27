<?php

namespace Tests\Feature\Http\Middleware;

use Tests\TestCase;

class TrustProxiesRedirectTest extends TestCase
{
    public function test_root_redirect_uses_forwarded_host_and_proto(): void
    {
        $response = $this->withHeaders([
            'X-Forwarded-Host' => 'tunnel.example.com',
            'X-Forwarded-Proto' => 'https',
        ])->get('/');

        $response->assertRedirect('https://tunnel.example.com/login');
    }

    public function test_route_helper_uses_forwarded_host_in_blade(): void
    {
        $response = $this->withHeaders([
            'X-Forwarded-Host' => 'tunnel.example.com',
            'X-Forwarded-Proto' => 'https',
        ])->get('/login');

        $response->assertOk();
        $response->assertSee('action="https://tunnel.example.com/login"', false);
    }
}
