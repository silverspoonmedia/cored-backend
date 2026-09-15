<?php

namespace Tests\Feature\Http;

use App\Support\Broadcasting\BroadcastAuthEndpoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Guard: every route that answers broadcast channel authorization must be
 * CORS-readable by the SPA.
 *
 * The failure mode this locks down is invisible server-side: Laravel's
 * `withRouting(channels: ...)` registers a **web-group twin** of
 * `/broadcasting/auth`, which is not matched by `config/cors.php`'s `api/*`
 * path and answers 403/419 with no `Access-Control-*` header. The browser then
 * reports a generic "CORS error" instead of the real status, so a client that
 * resolves the endpoint without the `/api/v1` prefix (Echo `authEndpoint`,
 * `VITE_API_BASE_URL` missing the prefix) looks like a broken CORS allowlist.
 *
 * `CorsOnAuthErrorProbeTest` covers the API twin for auth failures; this file
 * covers the endpoint itself plus the route/config invariant.
 */
class CorsBroadcastAuthTest extends TestCase
{
    use RefreshDatabase;

    private function spaOrigin(): string
    {
        $origin = rtrim((string) config('services.spa.url'), '/');

        return $origin !== '' ? $origin : rtrim((string) config('app.url'), '/');
    }

    public function test_api_preflight_advertises_credentials_and_csrf_header(): void
    {
        $origin = $this->spaOrigin();

        $response = $this->call('OPTIONS', '/'.BroadcastAuthEndpoint::PATH, server: [
            'HTTP_ORIGIN' => $origin,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type, x-csrf-token, x-spa-origin',
        ]);

        $response->assertSuccessful()
            ->assertHeader('Access-Control-Allow-Origin', $origin)
            ->assertHeader('Access-Control-Allow-Credentials', 'true');

        $allowedHeaders = strtolower((string) $response->headers->get('Access-Control-Allow-Headers'));

        $this->assertStringContainsString('x-csrf-token', $allowedHeaders);
        $this->assertStringContainsString('x-spa-origin', $allowedHeaders);
    }

    public function test_unauthenticated_api_post_returns_401_with_cors_headers(): void
    {
        $origin = $this->spaOrigin();

        $response = $this->withHeaders([
            'Origin' => $origin,
            'Accept' => 'application/json',
        ])->postJson('/'.BroadcastAuthEndpoint::PATH, [
            'socket_id' => '1.1',
            'channel_name' => 'private-logs.activity',
        ]);

        $response->assertStatus(401)
            ->assertHeader('Access-Control-Allow-Origin', $origin)
            ->assertHeader('Access-Control-Allow-Credentials', 'true');
    }

    public function test_web_twin_of_broadcast_auth_is_cors_readable(): void
    {
        // The framework's web-group route. It stays registered (see
        // WebSurfaceRouteListTest) but must never be able to produce an opaque
        // browser CORS error again.
        $origin = $this->spaOrigin();

        $response = $this->withHeaders([
            'Origin' => $origin,
            'Accept' => 'application/json',
        ])->postJson('/broadcasting/auth', [
            'socket_id' => '1.1',
            'channel_name' => 'private-logs.activity',
        ]);

        $response->assertHeader('Access-Control-Allow-Origin', $origin)
            ->assertHeader('Access-Control-Allow-Credentials', 'true');
    }

    public function test_every_broadcasting_auth_route_is_covered_by_cors_paths(): void
    {
        $corsPaths = array_map(
            static fn (string $path): string => trim($path, '/'),
            array_filter((array) config('cors.paths'), 'is_string'),
        );

        $routes = collect(Route::getRoutes()->getRoutes())
            ->map(static fn ($route): string => $route->uri())
            ->filter(static fn (string $uri): bool => str_ends_with($uri, 'broadcasting/auth'))
            ->unique()
            ->values();

        $this->assertNotEmpty($routes, 'No broadcasting/auth route is registered.');

        foreach ($routes as $uri) {
            $covered = false;

            foreach ($corsPaths as $path) {
                if ($uri === $path || Str::is($path, $uri)) {
                    $covered = true;
                    break;
                }
            }

            $this->assertTrue(
                $covered,
                "Route [{$uri}] is not matched by config('cors.paths'), so its error responses reach the browser as an opaque CORS error.",
            );
        }
    }

    public function test_server_issued_auth_endpoint_uses_the_versioned_api_path(): void
    {
        $url = BroadcastAuthEndpoint::url();

        $this->assertStringEndsWith('/'.BroadcastAuthEndpoint::PATH, $url);
        $this->assertStringContainsString('/api/v1/broadcasting/auth', $url);
        $this->assertSame('broadcasting/auth', BroadcastAuthEndpoint::SUBPATH);
    }
}
