<?php

namespace Tests\Domain\Archivd\Feature\Http\Controllers\API\V1;

use App\Domain\Identity\Models\User;
use App\Domain\Service\Enums\EntitlementStatus;
use App\Domain\Service\Models\Service;
use App\Domain\Service\Models\ServiceEntitlement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

/**
 * Probe for Bug 1: cross-origin GET /archivd/quota reported by the
 * browser as a "CORS error" intermittently after upload bursts.
 *
 * Hypothesis: the global `throttle:api` limit (60/min per user) is tripped by
 * the burst of post-upload refetches, so the request is rejected with a 429.
 * The browser then surfaces the rejection as a CORS failure when the error
 * response lacks the Access-Control-Allow-Origin header. This test drives the
 * limit and inspects whether the 429 carries CORS headers.
 */
class QuotaThrottleProbeTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private const ORIGIN = 'http://localhost:5173';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
    }

    public function test_429_still_carries_cors_headers(): void
    {
        // Set the allowlist itself, not `services.spa.url`: `cors.allowed_origins`
        // is resolved at boot from `APP_URL` / `SPA_URL`, so a test that only moves
        // the SPA url leaves this origin outside the allowlist and then measures
        // the absence of the header instead of the throttle.
        config(['cors.allowed_origins' => [self::ORIGIN]]);

        $user = $this->userWithRole('User');
        $this->grantPaidMain($user);
        $token = auth('api')->login($user);

        $last = null;

        // Drive past the 60/min global API throttle, all from the SPA origin.
        for ($i = 0; $i < 65; $i++) {
            $last = $this
                ->withHeader('Authorization', 'Bearer '.$token)
                ->withHeader('Origin', self::ORIGIN)
                ->getJson('/api/v1/archivd/quota');
        }

        // Once the limiter trips we expect 429, and CORS must still be applied
        // so the browser can read the failure instead of masking it.
        $this->assertSame(429, $last->getStatusCode(), 'Expected the 61st+ request to be throttled');
        $this->assertSame(
            self::ORIGIN,
            $last->headers->get('Access-Control-Allow-Origin'),
            'Throttled response is missing the CORS allow-origin header'
        );
    }

    private function userWithRole(string $role): User
    {
        $user = User::query()->create([
            'name' => $role.' QuotaProbe',
            'email' => strtolower($role).'-quota-probe-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function grantPaidMain(User $user): void
    {
        $service = Service::query()->where('code', 'archivd.plan.1tb.monthly')->first()
            ?? Service::factory()->ArchivdMain1Tb()->create();

        ServiceEntitlement::query()->updateOrCreate(
            ['user_id' => $user->id, 'service_id' => $service->id],
            [
                'status' => EntitlementStatus::Active,
                'quantity' => 1,
                'starts_at' => now(),
                'ends_at' => now()->addDays(30),
            ],
        );
    }
}
