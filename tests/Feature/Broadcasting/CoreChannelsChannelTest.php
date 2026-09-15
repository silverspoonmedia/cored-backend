<?php

namespace Tests\Feature\Broadcasting;

use App\Domain\Identity\Models\User;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Contracts\Broadcasting\Factory as BroadcastFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

/**
 * Channel auth coverage for the channels added during the live-update
 * coverage rollout (RBAC split, activity log, payments admin, currencies).
 *
 * Authorization matrix is intentionally exercised at both ends of the
 * permission ladder:
 *  - Admin holds every core permission → 200 on every channel.
 *  - User holds only `view notification|view service|purchase service` → 403.
 *  - Guest → 401.
 *
 * `App.Models.User.{id}` is self-only and asserted both ways (owner + other).
 */
class CoreChannelsChannelTest extends TestCase
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

    public static function adminOnlyChannelProvider(): \Generator
    {
        yield 'rbac.roles' => ['private-rbac.roles'];
        yield 'rbac.users' => ['private-rbac.users'];
        yield 'legacy rbac' => ['private-rbac'];
        yield 'logs.activity' => ['private-logs.activity'];
        yield 'logs.errors' => ['private-logs.errors'];
        yield 'settings.currencies' => ['private-settings.currencies'];
        yield 'payments.admin' => ['private-payments.admin'];
        yield 'product-activations' => ['private-product-activations'];
        yield 'domain-access' => ['private-domain-access'];
    }

    #[DataProvider('adminOnlyChannelProvider')]
    public function test_admin_can_subscribe(string $channel): void
    {
        $admin = $this->userWithRole('Admin');

        $this->actingAs($admin, 'api')
            ->postJson('/api/v1/broadcasting/auth', [
                'socket_id' => '1.1',
                'channel_name' => $channel,
            ])
            ->assertOk();
    }

    #[DataProvider('adminOnlyChannelProvider')]
    public function test_plain_user_is_forbidden(string $channel): void
    {
        $user = $this->userWithRole('User');

        $this->actingAs($user, 'api')
            ->postJson('/api/v1/broadcasting/auth', [
                'socket_id' => '2.2',
                'channel_name' => $channel,
            ])
            ->assertForbidden();
    }

    #[DataProvider('adminOnlyChannelProvider')]
    public function test_guest_is_unauthorized(string $channel): void
    {
        $this->postJson('/api/v1/broadcasting/auth', [
            'socket_id' => '3.3',
            'channel_name' => $channel,
        ])->assertUnauthorized();
    }

    public function test_personal_channel_owner_can_subscribe(): void
    {
        $user = $this->userWithRole('User');

        $this->actingAs($user, 'api')
            ->postJson('/api/v1/broadcasting/auth', [
                'socket_id' => '4.4',
                'channel_name' => 'private-App.Models.User.'.$user->id,
            ])
            ->assertOk();
    }

    public function test_personal_channel_rejects_other_users(): void
    {
        $user = $this->userWithRole('User');
        $other = $this->userWithRole('User');

        $this->actingAs($other, 'api')
            ->postJson('/api/v1/broadcasting/auth', [
                'socket_id' => '5.5',
                'channel_name' => 'private-App.Models.User.'.$user->id,
            ])
            ->assertForbidden();
    }

    private function userWithRole(string $role): User
    {
        $user = User::query()->create([
            'name' => $role.' CoreChannel',
            'email' => strtolower($role).'-corechannel-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole($role);

        return $user;
    }
}
