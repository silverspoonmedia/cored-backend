<?php

namespace Tests\Domain\Core\PaymentGateway\Feature;

use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class PaymentRoutingChannelAuthTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
    }

    public function test_routing_channel_allows_user_with_initiate_payment(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('initiate payment');

        $this->assertTrue($this->authorizeRoutingChannel($user));
    }

    public function test_routing_channel_allows_user_with_view_invoice(): void
    {
        $user = $this->userWithRole('Admin');

        $this->assertTrue($this->authorizeRoutingChannel($user));
    }

    public function test_routing_channel_denies_user_without_permissions(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($this->authorizeRoutingChannel($user));
    }

    /**
     * Mirrors routes/channels.php payments.routing callback.
     */
    private function authorizeRoutingChannel(User $user): bool
    {
        return $user->can('initiate payment') || $user->can('view invoice');
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
