<?php

namespace Tests\Feature\Broadcasting;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\ChannelStatus;
use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Models\CreatorClaim;
use App\Domain\Vtual\Services\CreatorService;
use Database\Seeders\VtualRoleSeeder;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Contracts\Broadcasting\Factory as BroadcastFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class CreatorClaimProgressChannelTest extends TestCase
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

    private function enablePusherBroadcast(): void
    {
        // phpunit sets BROADCAST_CONNECTION=null; channels.php registers on that driver at boot.
        // Switching default to pusher without re-registering leaves the pusher driver with no auth callbacks.
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

    public function test_owner_can_subscribe_to_claim_channel(): void
    {
        [$claim, $owner] = $this->makeClaimPair();

        $this->actingAs($owner, 'api')
            ->postJson('/broadcasting/auth', [
                'socket_id' => '1.1',
                'channel_name' => 'private-vtual.claim.'.$claim->id,
            ])
            ->assertOk();
    }

    public function test_other_user_cannot_subscribe_to_claim_channel(): void
    {
        [$claim] = $this->makeClaimPair();
        $other = $this->userWithRole('User');

        $this->actingAs($other, 'api')
            ->postJson('/broadcasting/auth', [
                'socket_id' => '2.2',
                'channel_name' => 'private-vtual.claim.'.$claim->id,
            ])
            ->assertForbidden();
    }

    /**
     * @return array{0: CreatorClaim, 1: User}
     */
    private function makeClaimPair(): array
    {
        $admin = $this->userWithRole('Admin');
        $orphan = app(CreatorService::class)->createOrphan($admin, ['display_name' => 'Channel Auth Orphan']);
        $youtube = \App\Domain\Vtual\Models\Platform::query()->where('key', 'youtube')->firstOrFail();
        $channel = Channel::query()->create([
            'id' => (string) Str::uuid7(),
            'creator_id' => $orphan->id,
            'platform_id' => $youtube->id,
            'external_id' => 'UCchannelauthclaim1234567890abcd',
            'handle' => 'channelauth',
            'display_name' => 'Channel Auth',
            'status' => ChannelStatus::Active,
        ]);
        $owner = $this->userWithRole('User');
        $claim = new CreatorClaim([
            'user_id' => $owner->id,
            'creator_id' => $orphan->id,
            'channel_id' => $channel->id,
            'token' => 'vtual-claim-channelauth',
            'status' => 'pending',
            'attempt_count' => 0,
            'expires_at' => now()->addDay(),
        ]);
        $claim->id = (string) Str::uuid7();
        $claim->save();

        return [$claim, $owner];
    }

    private function userWithRole(string $role): User
    {
        $user = User::query()->create([
            'name' => $role.' Channel',
            'email' => strtolower($role).'-channel-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole($role);

        return $user;
    }
}
