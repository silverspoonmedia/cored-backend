<?php

namespace Tests\Feature\Broadcasting;

use App\Domain\Identity\Models\User;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Contracts\Broadcasting\Factory as BroadcastFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

/**
 * The `navigation.menus` private channel gates on the `view menu` permission
 * (the seeded navigation permission). It previously checked a non-existent
 * `view navigation menu` permission, so every subscriber — including Admin —
 * was rejected with 403.
 */
class NavigationMenusChannelTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        $this->enablePusherBroadcast();
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

    public function test_user_with_view_menu_can_subscribe(): void
    {
        $admin = $this->userWithRole('Admin');

        $this->actingAs($admin, 'api')
            ->postJson('/api/v1/broadcasting/auth', [
                'socket_id' => '1.1',
                'channel_name' => 'private-navigation.menus',
            ])
            ->assertOk();
    }

    public function test_user_without_view_menu_is_forbidden(): void
    {
        $user = $this->userWithRole('User');

        $this->actingAs($user, 'api')
            ->postJson('/api/v1/broadcasting/auth', [
                'socket_id' => '2.2',
                'channel_name' => 'private-navigation.menus',
            ])
            ->assertForbidden();
    }

    public function test_guest_cannot_subscribe(): void
    {
        $this->postJson('/api/v1/broadcasting/auth', [
            'socket_id' => '1.1',
            'channel_name' => 'private-navigation.menus',
        ])->assertUnauthorized();
    }

    private function userWithRole(string $role): User
    {
        $user = User::query()->create([
            'name' => $role.' NavChannel',
            'email' => strtolower($role).'-navchannel-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole($role);

        return $user;
    }
}
