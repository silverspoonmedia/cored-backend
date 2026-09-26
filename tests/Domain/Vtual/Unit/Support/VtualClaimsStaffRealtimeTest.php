<?php

namespace Tests\Domain\Vtual\Unit\Support;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\ChannelStatus;
use App\Domain\Vtual\Enums\CreatorClaimStatus;
use App\Domain\Vtual\Events\CreatorClaimStaffChanged;
use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Models\Creator;
use App\Domain\Vtual\Models\CreatorClaim;
use App\Domain\Vtual\Models\Platform;
use App\Domain\Vtual\Services\CreatorService;
use App\Domain\Vtual\Support\VtualClaimsStaffRealtime;
use Database\Seeders\VtualRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

/**
 * Staff queue ping: `vtual.claims` carries ids + action + status only.
 *
 * The owner-facing `vtual.claim.{claimId}` channel keeps the full snapshot
 * (including the bio token) and is authorized per-claimant; this staff ping
 * must never grow a snapshot — a leak here reaches every staff subscriber.
 */
class VtualClaimsStaffRealtimeTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        $this->seed(VtualRoleSeeder::class);
    }

    #[Test]
    public function enabled_follows_broadcast_credentials(): void
    {
        $realtime = app(VtualClaimsStaffRealtime::class);

        $this->disableBroadcast();
        $this->assertFalse($realtime->enabled());

        $this->enableBroadcast();
        $this->assertTrue($realtime->enabled());
    }

    #[Test]
    public function from_claim_broadcasts_ids_action_and_status_only(): void
    {
        Event::fake([CreatorClaimStaffChanged::class]);
        $this->enableBroadcast();

        [$orphan, $channel] = $this->seedOrphanWithChannel();
        $owner = $this->userWithRole('User');
        $claim = $this->makePendingClaim($owner, $orphan->id, $channel->id);

        app(VtualClaimsStaffRealtime::class)->fromClaim($claim, 'verify_queued');

        Event::assertDispatched(CreatorClaimStaffChanged::class, function (CreatorClaimStaffChanged $event) use ($claim): bool {
            $this->assertSame((string) $claim->id, $event->claimId);
            $this->assertSame((string) $claim->creator_id, $event->creatorId);
            $this->assertSame('verify_queued', $event->action);
            $this->assertSame(CreatorClaimStatus::Pending->value, $event->status);

            $this->assertSame('claim.changed', $event->broadcastAs());
            $this->assertSame('private-vtual.claims', $event->broadcastOn()[0]->name);

            $payload = $event->broadcastWith();
            $this->assertSame(['claim_id', 'creator_id', 'action', 'status'], array_keys($payload));

            // The bio token is the whole reason this channel is not the owner
            // snapshot channel. Guard the payload shape, not just the key list.
            $encoded = json_encode($payload);
            $this->assertStringNotContainsString('vtual-claim-testtoken', (string) $encoded);
            $this->assertStringNotContainsString('token', (string) $encoded);
            $this->assertArrayNotHasKey('claim', $payload);
            $this->assertArrayNotHasKey('user', $payload);

            return true;
        });
    }

    #[Test]
    public function no_event_when_broadcast_disabled(): void
    {
        Event::fake([CreatorClaimStaffChanged::class]);
        $this->disableBroadcast();

        [$orphan, $channel] = $this->seedOrphanWithChannel();
        $owner = $this->userWithRole('User');
        $claim = $this->makePendingClaim($owner, $orphan->id, $channel->id);

        app(VtualClaimsStaffRealtime::class)->fromClaim($claim, 'started');

        Event::assertNotDispatched(CreatorClaimStaffChanged::class);
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

    /**
     * @return array{0: Creator, 1: Channel}
     */
    private function seedOrphanWithChannel(): array
    {
        $admin = $this->userWithRole('Admin');
        $orphan = app(CreatorService::class)->createOrphan($admin, ['display_name' => 'Staff Ping Orphan']);
        $channel = Channel::query()->create([
            'id' => (string) Str::uuid7(),
            'creator_id' => $orphan->id,
            'platform_id' => Platform::query()->where('key', 'youtube')->firstOrFail()->id,
            'external_id' => 'UC'.substr(md5('staff-ping-'.uniqid()), 0, 22),
            'handle' => 'staff-ping-orphan',
            'display_name' => 'Staff Ping Orphan',
            'status' => ChannelStatus::Pending,
        ]);

        return [$orphan, $channel];
    }

    private function makePendingClaim(User $user, string $creatorId, string $channelId): CreatorClaim
    {
        $claim = new CreatorClaim([
            'user_id' => $user->id,
            'creator_id' => $creatorId,
            'channel_id' => $channelId,
            'token' => 'vtual-claim-testtoken',
            'status' => CreatorClaimStatus::Pending,
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
            'name' => $role.' StaffPing',
            'email' => strtolower($role).'-staffping-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole($role);

        return $user;
    }
}
