<?php

namespace Tests\Feature\Broadcasting;

use App\Domain\Identity\Models\User;
use Database\Seeders\VtualRoleSeeder;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Contracts\Broadcasting\Factory as BroadcastFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

/**
 * `vtual.incidents` is the staff incident-queue ping. Subscription is gated on
 * `view vtual incident` — the same permission the list endpoint needs, so a
 * subscriber can always refetch what the ping told it about. A viewer without
 * it would receive ids it cannot resolve, which is an existence oracle.
 */
class VtualIncidentsChannelTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        $this->seed(VtualRoleSeeder::class);
        $this->enablePusherBroadcast();
    }

    public function test_staff_with_the_view_permission_can_subscribe(): void
    {
        $admin = $this->userWithRole('Admin');

        $this->actingAs($admin, 'api')
            ->postJson('/api/v1/broadcasting/auth', [
                'socket_id' => '1.1',
                'channel_name' => 'private-vtual.incidents',
            ])
            ->assertOk();
    }

    public function test_a_user_without_the_permission_is_forbidden(): void
    {
        $user = $this->userWithRole('User');

        $this->actingAs($user, 'api')
            ->postJson('/api/v1/broadcasting/auth', [
                'socket_id' => '2.2',
                'channel_name' => 'private-vtual.incidents',
            ])
            ->assertForbidden();
    }

    public function test_guest_cannot_subscribe(): void
    {
        $this->postJson('/api/v1/broadcasting/auth', [
            'socket_id' => '3.3',
            'channel_name' => 'private-vtual.incidents',
        ])->assertUnauthorized();
    }

    private function enablePusherBroadcast(): void
    {
        config([
            'broadcasting.default' => 'pusher',
            'broadcasting.connections.pusher' => [
                'driver' => 'pusher',
                'key' => 'key',
                'secret' => 'secret',
                'app_id' => 'app',
                'options' => ['cluster' => 'ap1', 'port' => 443, 'scheme' => 'https'],
            ],
        ]);

        /** @var BroadcastManager $broadcast */
        $broadcast = app(BroadcastFactory::class);
        $broadcast->purge('null');
        $broadcast->purge('pusher');

        require base_path('routes/channels.php');
    }

    private function userWithRole(string $role): User
    {
        $user = User::query()->create([
            'name' => $role.' IncidentsChannel',
            'email' => strtolower($role).'-incidentschannel-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole($role);

        return $user;
    }
}
