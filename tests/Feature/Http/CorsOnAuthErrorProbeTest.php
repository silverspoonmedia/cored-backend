<?php

namespace Tests\Feature\Http;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

/**
 * Probe: an unauthenticated cross-site GET to an auth-protected API route must
 * still carry CORS headers on its 401. Without them the browser surfaces a
 * generic "CORS error" and hides the real 401, which masks auth failures as
 * network errors (the post-login `navigation/menus` symptom).
 */
class CorsOnAuthErrorProbeTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
    }

    public function test_unauthenticated_get_returns_401_with_cors_headers(): void
    {
        $origin = (string) config('app.url');

        $response = $this->withHeaders([
            'Origin' => $origin,
            'Accept' => 'application/json',
        ])->getJson('/api/v1/auth/me');

        $response->assertStatus(401)
            ->assertHeader('Access-Control-Allow-Origin', $origin);
    }

    public function test_unauthenticated_get_includes_allow_credentials(): void
    {
        $origin = (string) config('app.url');

        $response = $this->withHeaders([
            'Origin' => $origin,
            'Accept' => 'application/json',
        ])->getJson('/api/v1/auth/me');

        $response->assertStatus(401)
            ->assertHeader('Access-Control-Allow-Credentials', 'true');
    }
}
