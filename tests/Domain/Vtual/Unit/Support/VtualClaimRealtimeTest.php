<?php

namespace Tests\Domain\Vtual\Unit\Support;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\ChannelStatus;
use App\Domain\Vtual\Events\CreatorClaimProgressChanged;
use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Models\CreatorClaim;
use App\Domain\Vtual\Support\VtualClaimRealtime;
use App\Domain\Vtual\Services\CreatorService;
use Database\Seeders\VtualRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class VtualClaimRealtimeTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        $this->seed(VtualRoleSeeder::class);
    }

    private function enableBroadcast(): void
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
    }

    private function disableBroadcast(): void
    {
        config(['broadcasting.default' => 'null']);
    }

    public function test_enabled_follows_broadcast_credentials(): void
    {
        $rt = app(VtualClaimRealtime::class);

        $this->disableBroadcast();
        $this->assertFalse($rt->enabled());

        $this->enableBroadcast();
        $this->assertTrue($rt->enabled());
    }

    public function test_progress_dispatches_claim_channel_event(): void
    {
        Event::fake([CreatorClaimProgressChanged::class]);
        $this->enableBroadcast();

        [$orphan, $channel] = $this->seedOrphanWithChannel();
        $user = $this->userWithRole('User');
        $claim = $this->makePendingClaim($user, $orphan, $channel);

        app(VtualClaimRealtime::class)->progress($claim, 'verify_queued');

        Event::assertDispatched(CreatorClaimProgressChanged::class, function (CreatorClaimProgressChanged $event) use ($claim): bool {
            return $event->claimId === (string) $claim->id
                && $event->phase === 'verify_queued'
                && $event->broadcastOn()[0]->name === 'private-vtual.claim.'.$claim->id
                && isset($event->claim['attempts_remaining']);
        });
    }

    public function test_progress_no_op_when_broadcast_disabled(): void
    {
        Event::fake([CreatorClaimProgressChanged::class]);
        $this->disableBroadcast();

        [$orphan, $channel] = $this->seedOrphanWithChannel();
        $user = $this->userWithRole('User');
        $claim = $this->makePendingClaim($user, $orphan, $channel);

        app(VtualClaimRealtime::class)->progress($claim, 'verify_queued');

        Event::assertNotDispatched(CreatorClaimProgressChanged::class);
    }

    /**
     * @return array{0: \App\Domain\Vtual\Models\Creator, 1: Channel}
     */
    private function seedOrphanWithChannel(): array
    {
        $admin = $this->userWithRole('Admin');
        $creators = app(CreatorService::class);
        $orphan = $creators->createOrphan($admin, ['display_name' => 'Realtime Orphan']);
        $youtube = \App\Domain\Vtual\Models\Platform::query()->where('key', 'youtube')->firstOrFail();
        $channel = Channel::query()->create([
            'id' => (string) Str::uuid7(),
            'creator_id' => $orphan->id,
            'platform_id' => $youtube->id,
            'external_id' => 'UCrealtimeclaimchannel123456789',
            'handle' => 'realtimeorphan',
            'display_name' => 'Realtime Orphan',
            'status' => ChannelStatus::Active,
        ]);

        return [$orphan, $channel];
    }

    private function makePendingClaim(User $user, $orphan, Channel $channel): CreatorClaim
    {
        $claim = new CreatorClaim([
            'user_id' => $user->id,
            'creator_id' => $orphan->id,
            'channel_id' => $channel->id,
            'token' => 'vtual-claim-testtoken',
            'status' => 'pending',
            'attempt_count' => 1,
            'expires_at' => now()->addDay(),
        ]);
        $claim->id = (string) Str::uuid7();
        $claim->save();

        return $claim->load(['creator', 'channel.platform', 'user']);
    }

    private function userWithRole(string $role): User
    {
        $user = User::query()->create([
            'name' => $role.' Realtime',
            'email' => strtolower($role).'-realtime-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole($role);

        return $user;
    }
}
