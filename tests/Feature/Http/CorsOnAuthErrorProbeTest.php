<?php

namespace Tests\Feature\Http;

use App\Domain\Identity\Models\User;
use App\Support\Auth\SpaAuthCookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

/**
 * Probe: cross-site error responses must still carry CORS headers. Without
 * them the browser surfaces a generic "CORS error" and hides the real status,
 * which masks auth failures (401) and CSRF mismatches (419) as network errors
 * (the post-login `POST /api/v1/batch` symptom).
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

    public function test_csrf_mismatch_returns_419_with_cors_headers(): void
    {
        $origin = rtrim((string) config('app.url'), '/');
        $user = User::factory()->create();
        auth('api')->factory()->setTTL(60);
        $token = auth('api')->login($user);
        $this->assertIsString($token);
        auth()->forgetGuards();

        // Cookie session without a matching X-CSRF-Token: VerifySpaCsrf returns
        // 419 as a normal response so HandleCors can stamp allow-origin.
        $this->withCredentials()
            ->withCookie(SpaAuthCookie::NAME, $token)
            ->withHeaders([
                'Origin' => $origin,
                'Accept' => 'application/json',
            ])
            ->postJson('/api/v1/auth/locale', ['locale' => 'en'])
            ->assertStatus(419)
            ->assertJsonPath('status', 'error')
            ->assertHeader('Access-Control-Allow-Origin', $origin)
            ->assertHeader('Access-Control-Allow-Credentials', 'true');
    }

    public function test_refresh_without_cookie_returns_401_with_cors_headers(): void
    {
        $origin = (string) config('app.url');

        $this->withHeaders([
            'Origin' => $origin,
            'Accept' => 'application/json',
        ])->postJson('/api/v1/auth/refresh')
            ->assertStatus(401)
            ->assertJsonPath('status', 'error')
            ->assertHeader('Access-Control-Allow-Origin', $origin)
            ->assertHeader('Access-Control-Allow-Credentials', 'true');
    }

    public function test_guest_navigation_tree_returns_200_with_cors_headers(): void
    {
        $origin = (string) config('app.url');

        $this->withHeaders([
            'Origin' => $origin,
            'Accept' => 'application/json',
        ])->getJson('/api/v1/navigation/menus?layout=sidebar')
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', $origin)
            ->assertHeader('Access-Control-Allow-Credentials', 'true');
    }
}
