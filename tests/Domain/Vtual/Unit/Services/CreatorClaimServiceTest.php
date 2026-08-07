<?php

namespace Tests\Domain\Vtual\Unit\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\ChannelStatus;
use App\Domain\Vtual\Enums\CreatorClaimClosedReason;
use App\Domain\Vtual\Enums\CreatorClaimStatus;
use App\Domain\Vtual\Enums\CreatorClaimVerifiedVia;
use App\Domain\Vtual\Enums\CreatorMemberRole;
use App\Domain\Vtual\Events\CreatorClaimProgressChanged;
use App\Domain\Vtual\Models\CreatorClaim;
use App\Domain\Vtual\Enums\PlatformCredentialStatus;
use App\Domain\Vtual\Enums\PlatformCredentialType;
use App\Domain\Vtual\Exceptions\VtualException;
use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Models\CreatorMember;
use App\Domain\Vtual\Models\Platform;
use App\Domain\Vtual\Models\CreatorClaimBlock;
use App\Domain\Vtual\Services\CreatorClaimService;
use App\Domain\Vtual\Services\CreatorClaimVerifyPoolService;
use App\Domain\Vtual\Services\CreatorService;
use App\Domain\Vtual\Services\PlatformCredentialService;
use Database\Seeders\VtualRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

/**
 * Phase 1: orphan seed + bio token claim / Accept Anyway.
 */
class CreatorClaimServiceTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private const YT_ID = 'UCEMMrXP3_WeKiOunPYixMfA';

    private CreatorService $creators;

    private CreatorClaimService $claims;

    private PlatformCredentialService $credentials;

    private Platform $youtube;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        $this->seed(VtualRoleSeeder::class);
        $this->creators = app(CreatorService::class);
        $this->claims = app(CreatorClaimService::class);
        $this->credentials = app(PlatformCredentialService::class);
        $this->youtube = Platform::query()->where('key', 'youtube')->firstOrFail();
        $this->admin = $this->userWithRole('Admin');
        config([
            'vtual.claim.accept_anyway_enabled' => true,
            'vtual.claim.verify_cooldown_seconds' => 60,
            'vtual.claim.max_attempts' => 3,
            'vtual.claim.block_hours_after_max_attempts' => 24,
            'vtual.claim.ttl_hours' => 24,
            'vtual.claim.max_pending_per_user' => 5,
        ]);
    }

    #[Test]
    public function create_orphan_has_no_owner_member(): void
    {
        $orphan = $this->creators->createOrphan($this->admin, ['display_name' => 'Orphan Neuron']);

        $this->assertTrue($this->creators->isClaimable($orphan));
        $this->assertSame(0, CreatorMember::query()->where('creator_id', $orphan->id)->count());
        $this->assertCount(1, $this->creators->listClaimable());
    }

    #[Test]
    public function create_orphan_requires_manage_any(): void
    {
        $user = $this->userWithRole('User');

        $this->expectException(VtualException::class);
        $this->creators->createOrphan($user, ['display_name' => 'Nope']);
    }

    #[Test]
    public function claimable_excludes_owned_creators(): void
    {
        $user = $this->userWithRole('User');
        $this->creators->create($user, ['display_name' => 'Owned']);
        $this->creators->createOrphan($this->admin, ['display_name' => 'Free']);

        $list = $this->creators->listClaimable();
        $this->assertCount(1, $list);
        $this->assertSame('Free', $list->first()?->display_name);
    }

    #[Test]
    public function start_claim_issues_token(): void
    {
        [$orphan, $channel] = $this->seedOrphanWithChannel();
        $user = $this->userWithRole('User');

        $claim = $this->claims->start($user, $orphan->id, $channel->id);

        $this->assertSame(CreatorClaimStatus::Pending, $claim->status);
        $this->assertStringStartsWith('vtual-claim-', $claim->token);
        $this->assertSame((string) $user->id, (string) $claim->user_id);
    }

    #[Test]
    public function channel_must_belong_to_creator(): void
    {
        [$orphan, $channel] = $this->seedOrphanWithChannel();
        $other = $this->creators->createOrphan($this->admin, ['display_name' => 'Other']);
        $user = $this->userWithRole('User');

        try {
            $this->claims->start($user, $other->id, $channel->id);
            $this->fail('Expected VtualException');
        } catch (VtualException $e) {
            $this->assertStringContainsString('channel', strtolower($e->getMessage()));
        }
    }

    #[Test]
    public function queue_verify_dispatches_progress_when_broadcast_enabled(): void
    {
        config([
            'broadcasting.default' => 'pusher',
            'broadcasting.connections.pusher' => [
                'driver' => 'pusher',
                'key' => 'key',
                'secret' => 'secret',
                'app_id' => 'app',
                'options' => ['cluster' => 'ap1'],
            ],
        ]);
        Event::fake([CreatorClaimProgressChanged::class]);

        [$orphan, $channel] = $this->seedOrphanWithChannel();
        $user = $this->userWithRole('User');
        $claim = $this->claims->start($user, $orphan->id, $channel->id);

        $this->claims->queueVerify($user, $claim->id);

        Event::assertDispatched(CreatorClaimProgressChanged::class, function (CreatorClaimProgressChanged $event) use ($claim): bool {
            return $event->claimId === (string) $claim->id
                && $event->phase === 'verify_queued';
        });
    }

    #[Test]
    public function verify_success_when_description_contains_token(): void
    {
        $this->activateYoutubeCredential();
        [$orphan, $channel] = $this->seedOrphanWithChannel();
        $user = $this->userWithRole('User');
        $claim = $this->claims->start($user, $orphan->id, $channel->id);

        $this->fakeYoutubeChannel(self::YT_ID, 'neuron', 'Neuron', 'Hello '.$claim->token.' world');

        $this->claims->queueVerify($user, $claim->id);
        app(CreatorClaimVerifyPoolService::class)->flush(force: true);

        $verified = CreatorClaim::query()->findOrFail($claim->id);

        $this->assertSame(CreatorClaimStatus::Verified, $verified->status);
        $this->assertSame(CreatorClaimVerifiedVia::Bio, $verified->verified_via);

        $member = CreatorMember::query()
            ->where('creator_id', $orphan->id)
            ->where('user_id', $user->id)
            ->first();
        $this->assertNotNull($member);
        $this->assertSame(CreatorMemberRole::Owner, $member->role);
        $this->assertTrue($user->fresh()->hasRole('Vtual vTuber'));
        $this->assertFalse($this->creators->isClaimable($orphan->fresh()));
    }

    #[Test]
    public function verify_fails_without_token_increments_attempt(): void
    {
        $this->activateYoutubeCredential();
        [$orphan, $channel] = $this->seedOrphanWithChannel();
        $user = $this->userWithRole('User');
        $claim = $this->claims->start($user, $orphan->id, $channel->id);

        $this->fakeYoutubeChannel(self::YT_ID, 'neuron', 'Neuron', 'no token here');

        $this->claims->queueVerify($user, $claim->id);
        app(CreatorClaimVerifyPoolService::class)->flush(force: true);

        $claim->refresh();
        $this->assertSame(1, (int) $claim->attempt_count);
        $this->assertSame(CreatorClaimStatus::Pending, $claim->status);
    }

    #[Test]
    public function verify_failed_broadcast_snapshot_clears_queue_status_when_broadcast_enabled(): void
    {
        config([
            'broadcasting.default' => 'pusher',
            'broadcasting.connections.pusher' => [
                'driver' => 'pusher',
                'key' => 'key',
                'secret' => 'secret',
                'app_id' => 'app',
                'options' => ['cluster' => 'ap1'],
            ],
            'vtual.claim.verify_cooldown_seconds' => 0,
        ]);
        Event::fake([CreatorClaimProgressChanged::class]);

        $this->activateYoutubeCredential();
        [$orphan, $channel] = $this->seedOrphanWithChannel();
        $user = $this->userWithRole('User');
        $claim = $this->claims->start($user, $orphan->id, $channel->id);

        $this->fakeYoutubeChannel(self::YT_ID, 'neuron', 'Neuron', 'no token here');

        $this->claims->queueVerify($user, $claim->id);
        app(CreatorClaimVerifyPoolService::class)->flush(force: true);

        Event::assertDispatched(CreatorClaimProgressChanged::class, function (CreatorClaimProgressChanged $event) use ($claim): bool {
            return $event->claimId === (string) $claim->id
                && $event->phase === 'verify_failed'
                && ($event->claim['verify_queue_status'] ?? null) === null;
        });

        Event::assertDispatched(CreatorClaimProgressChanged::class, function (CreatorClaimProgressChanged $event) use ($claim): bool {
            return $event->claimId === (string) $claim->id
                && $event->phase === 'verify_failed'
                && $event->claim['can_verify'] === true;
        });
    }

    #[Test]
    public function verify_rate_limited_by_cooldown(): void
    {
        $this->activateYoutubeCredential();
        config(['vtual.claim.verify_cooldown_seconds' => 60]);
        [$orphan, $channel] = $this->seedOrphanWithChannel();
        $user = $this->userWithRole('User');
        $claim = $this->claims->start($user, $orphan->id, $channel->id);

        $this->fakeYoutubeChannel(self::YT_ID, 'neuron', 'Neuron', 'nope');

        try {
            $this->claims->queueVerify($user, $claim->id);
        } catch (VtualException) {
            // first queue increments attempt
        }

        $this->fakeYoutubeChannel(self::YT_ID, 'neuron', 'Neuron', 'nope again');

        try {
            $this->claims->queueVerify($user, $claim->id);
            $this->fail('Expected cooldown VtualException');
        } catch (VtualException $e) {
            $this->assertStringContainsString('wait', strtolower($e->getMessage()));
        }
    }

    #[Test]
    public function verify_fails_if_owner_appeared(): void
    {
        $this->activateYoutubeCredential();
        [$orphan, $channel] = $this->seedOrphanWithChannel();
        $user = $this->userWithRole('User');
        $claim = $this->claims->start($user, $orphan->id, $channel->id);

        // Race: another owner appears before verify.
        CreatorMember::query()->create([
            'creator_id' => $orphan->id,
            'user_id' => $this->admin->id,
            'role' => CreatorMemberRole::Owner,
        ]);

        $this->fakeYoutubeChannel(self::YT_ID, 'neuron', 'Neuron', $claim->token);

        try {
            $this->claims->queueVerify($user, $claim->id);
            $this->fail('Expected VtualException');
        } catch (VtualException $e) {
            $this->assertTrue(
                str_contains(strtolower($e->getMessage()), 'owner')
                || str_contains(strtolower($e->getMessage()), 'claimable')
            );
        }

        $claim->refresh();
        $this->assertSame(CreatorClaimStatus::Cancelled, $claim->status);
        $this->assertSame(CreatorClaimClosedReason::Superseded, $claim->closed_reason);
        $this->assertSame(0, (int) $claim->attempt_count);
    }

    #[Test]
    public function winner_verify_cancels_other_pending(): void
    {
        $this->activateYoutubeCredential();
        [$orphan, $channel] = $this->seedOrphanWithChannel();
        $userA = $this->userWithRole('User');
        $userB = $this->userWithRole('User');

        $claimA = $this->claims->start($userA, $orphan->id, $channel->id);
        $claimB = $this->claims->start($userB, $orphan->id, $channel->id);

        $this->fakeYoutubeChannel(self::YT_ID, 'neuron', 'Neuron', 'bio '.$claimA->token);

        $this->claims->queueVerify($userA, $claimA->id);
        app(CreatorClaimVerifyPoolService::class)->flush(force: true);

        $verified = CreatorClaim::query()->findOrFail($claimA->id);
        $this->assertSame(CreatorClaimStatus::Verified, $verified->status);

        $claimB->refresh();
        $this->assertSame(CreatorClaimStatus::Cancelled, $claimB->status);
        $this->assertSame(CreatorClaimClosedReason::Superseded, $claimB->closed_reason);

        try {
            $this->claims->queueVerify($userB, $claimB->id);
            $this->fail('Expected not_pending after supersede');
        } catch (VtualException $e) {
            $this->assertStringContainsString('pending', strtolower($e->getMessage()));
        }
    }

    #[Test]
    public function max_verify_attempts_blocks_user_for_creator(): void
    {
        $this->activateYoutubeCredential();
        [$orphan, $channel] = $this->seedOrphanWithChannel();
        $user = $this->userWithRole('User');
        $claim = $this->claims->start($user, $orphan->id, $channel->id);

        config(['vtual.claim.verify_cooldown_seconds' => 0]);

        for ($i = 0; $i < 3; $i++) {
            $this->fakeYoutubeChannel(self::YT_ID, 'neuron', 'Neuron', 'no token');
            $this->claims->queueVerify($user, $claim->id);
            app(CreatorClaimVerifyPoolService::class)->flush(force: true);
            $claim->refresh();
        }

        $this->assertSame(CreatorClaimStatus::Cancelled, $claim->status);
        $this->assertSame(CreatorClaimClosedReason::MaxAttempts, $claim->closed_reason);
        $this->assertTrue($this->claims->isBlocked($user, $orphan->id));

        try {
            $this->claims->start($user, $orphan->id, $channel->id);
            $this->fail('Expected blocked');
        } catch (VtualException $e) {
            $this->assertStringContainsString('block', strtolower($e->getMessage()));
        }

        $this->assertNotNull(CreatorClaimBlock::query()
            ->where('user_id', $user->id)
            ->where('creator_id', $orphan->id)
            ->first());
    }

    #[Test]
    public function max_pending_per_user(): void
    {
        config(['vtual.claim.max_pending_per_user' => 2]);
        $user = $this->userWithRole('User');

        [$o1, $c1] = $this->seedOrphanWithChannelNamed('A');
        [$o2, $c2] = $this->seedOrphanWithChannelNamed('B');
        [$o3, $c3] = $this->seedOrphanWithChannelNamed('C');

        $this->claims->start($user, $o1->id, $c1->id);
        $this->claims->start($user, $o2->id, $c2->id);

        try {
            $this->claims->start($user, $o3->id, $c3->id);
            $this->fail('Expected max pending');
        } catch (VtualException $e) {
            $this->assertStringContainsString('pending', strtolower($e->getMessage()));
        }

        $this->assertSame(2, CreatorClaim::query()
            ->where('user_id', $user->id)
            ->where('status', CreatorClaimStatus::Pending)
            ->count());
    }

    #[Test]
    public function staff_list_pending(): void
    {
        [$orphan, $channel] = $this->seedOrphanWithChannel();
        $userA = $this->userWithRole('User');
        $userB = $this->userWithRole('User');
        $this->claims->start($userA, $orphan->id, $channel->id);
        $this->claims->start($userB, $orphan->id, $channel->id);

        $pending = $this->claims->listPendingForStaff($this->admin);
        $this->assertCount(2, $pending);

        try {
            $this->claims->listPendingForStaff($userA);
            $this->fail('Expected staff-only');
        } catch (VtualException $e) {
            $this->assertSame(403, $e->statusCode());
        }
    }

    #[Test]
    public function accept_anyway_staff_only(): void
    {
        [$orphan, $channel] = $this->seedOrphanWithChannel();
        $user = $this->userWithRole('User');
        $claim = $this->claims->start($user, $orphan->id, $channel->id);

        try {
            $this->claims->acceptAnyway($user, $claim->id);
            $this->fail('Expected VtualException');
        } catch (VtualException $e) {
            $this->assertSame(403, $e->statusCode());
        }

        $verified = $this->claims->acceptAnyway($this->admin, $claim->id);
        $this->assertSame(CreatorClaimStatus::Verified, $verified->status);
        $this->assertSame(CreatorClaimVerifiedVia::AcceptAnyway, $verified->verified_via);
        $this->assertSame((string) $this->admin->id, (string) $verified->verified_by_user_id);
        $this->assertTrue($user->fresh()->hasRole('Vtual vTuber'));
    }

    #[Test]
    public function accept_anyway_disabled_config(): void
    {
        config(['vtual.claim.accept_anyway_enabled' => false]);
        [$orphan, $channel] = $this->seedOrphanWithChannel();
        $user = $this->userWithRole('User');
        $claim = $this->claims->start($user, $orphan->id, $channel->id);

        $this->expectException(VtualException::class);
        $this->claims->acceptAnyway($this->admin, $claim->id);
    }

    /**
     * @return array{0: \App\Domain\Vtual\Models\Creator, 1: Channel}
     */
    private function seedOrphanWithChannel(): array
    {
        return $this->seedOrphanWithChannelNamed('Neuron Orphan', self::YT_ID);
    }

    /**
     * @return array{0: \App\Domain\Vtual\Models\Creator, 1: Channel}
     */
    private function seedOrphanWithChannelNamed(string $name, ?string $externalId = null): array
    {
        $orphan = $this->creators->createOrphan($this->admin, ['display_name' => $name]);
        $channel = Channel::query()->create([
            'id' => (string) Str::uuid7(),
            'creator_id' => $orphan->id,
            'platform_id' => $this->youtube->id,
            'external_id' => $externalId ?? ('UC'.substr(md5($name.uniqid()), 0, 22)),
            'handle' => Str::slug($name),
            'display_name' => $name,
            'status' => ChannelStatus::Pending,
        ]);

        return [$orphan, $channel];
    }

    private function activateYoutubeCredential(): void
    {
        $cred = $this->credentials->create($this->admin, $this->youtube, [
            'label' => 'claim-yt-'.uniqid(),
            'type' => PlatformCredentialType::ApiKey->value,
            'priority' => 0,
            'secret' => ['api_key' => 'test-key'],
        ]);
        $cred->forceFill([
            'status' => PlatformCredentialStatus::Active,
            'verified_at' => now(),
        ])->save();
    }

    private function fakeYoutubeChannel(
        string $id,
        string $handle,
        string $title,
        string $description = '',
    ): void {
        Http::fake([
            'www.googleapis.com/youtube/v3/channels*' => Http::response([
                'items' => [
                    [
                        'id' => $id,
                        'snippet' => [
                            'title' => $title,
                            'description' => $description,
                            'customUrl' => '@'.$handle,
                            'thumbnails' => ['default' => ['url' => 'https://img.example/c.png']],
                        ],
                    ],
                ],
            ], 200),
        ]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::query()->create([
            'name' => $role.' Claim',
            'email' => strtolower($role).'-claim-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole($role);

        return $user;
    }
}
