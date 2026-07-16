<?php

namespace Tests\Feature\Routing;

use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class ApiRoutingRegistrationTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_global_ping_route_is_registered(): void
    {
        $this->getJson('/api/ping')
            ->assertOk()
            ->assertJsonPath('status', 'success');
    }

    public function test_removed_approval_routes_are_not_registered(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Admin');
        $token = auth('api')->login($user);

        $this->withToken($token)
            ->getJson('/api/v1/approvals/my-pending')
            ->assertNotFound();
    }
}
