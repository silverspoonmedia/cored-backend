<?php

namespace Tests\Domain\Archivd\Feature\Http\Controllers\API\V1;

use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

/**
 * Bug 1 second angle: a credentialed GET with an Authorization header is a
 * non-simple CORS request, so the browser sends an OPTIONS preflight first.
 * If that preflight is itself throttled (the global api limiter does not
 * exempt OPTIONS) the browser reports the real GET as a "CORS error".
 *
 * This probe asks whether a preflight to the quota route is counted by the
 * throttle:api limiter.
 */
class QuotaPreflightProbeTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private const ORIGIN = 'http://localhost:5173';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
    }

    public function test_options_preflight_is_not_throttled(): void
    {
        config(['services.spa.url' => self::ORIGIN]);

        $user = $this->userWithRole('User');
        $token = auth('api')->login($user);

        // Saturate the limiter with real requests so the next OPTIONS is the
        // 61st hit. If OPTIONS is throttled too, the preflight returns 429 and
        // the browser reports a CORS failure.
        for ($i = 0; $i < 61; $i++) {
            $this->withHeader('Authorization', 'Bearer '.$token)
                ->withHeader('Origin', self::ORIGIN)
                ->getJson('/api/v1/archivd/quota');
        }

        $preflight = $this->call(
            'OPTIONS',
            '/api/v1/archivd/quota',
            server: [
                'HTTP_ORIGIN' => self::ORIGIN,
                'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
                'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'authorization',
            ],
        );

        $this->assertNotSame(
            429,
            $preflight->getStatusCode(),
            'OPTIONS preflight is throttled; the browser would report this as a CORS error'
        );
    }

    private function userWithRole(string $role): User
    {
        $user = User::query()->create([
            'name' => $role.' PreflightProbe',
            'email' => strtolower($role).'-preflight-probe-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole($role);

        return $user;
    }
}
