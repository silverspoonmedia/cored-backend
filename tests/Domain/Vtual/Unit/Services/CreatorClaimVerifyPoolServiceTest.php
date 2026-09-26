<?php

namespace Tests\Domain\Vtual\Unit\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\ChannelStatus;
use App\Domain\Vtual\Enums\CreatorClaimVerifyPoolStatus;
use App\Domain\Vtual\Enums\PlatformCredentialStatus;
use App\Domain\Vtual\Enums\PlatformCredentialType;
use App\Domain\Vtual\Events\CreatorClaimProgressChanged;
use App\Domain\Vtual\Events\CreatorClaimStaffChanged;
use App\Domain\Vtual\Events\PlatformCredentialChanged;
use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Models\CreatorClaimVerifyPoolItem;
use App\Domain\Vtual\Models\Platform;
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

class CreatorClaimVerifyPoolServiceTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private const YT_ID = 'UCEMMrXP3_WeKiOunPYixMfA';

    private CreatorService $creators;

    private CreatorClaimService $claims;

    private CreatorClaimVerifyPoolService $pool;

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
        $this->pool = app(CreatorClaimVerifyPoolService::class);
        $this->credentials = app(PlatformCredentialService::class);
        $this->youtube = Platform::query()->where('key', 'youtube')->firstOrFail();
        $this->admin = $this->userWithRole('Admin');
        config([
            'vtual.claim.verify_cooldown_seconds' => 0,
            'vtual.claim.max_attempts' => 3,
            'vtual.claim_verify_pool.threshold' => 50,
            'vtual.claim_verify_pool.max_wait_minutes' => 15,
        ]);

        // Activation hooks fire before per-test Http::fake() registration —
        // fake the WebSub hub up front so no test ever reaches the real hub.
        Http::fake(['pubsubhubbub.appspot.com/*' => Http::response('', 202)]);
    }

    #[Test]
    public function enqueue_rejects_duplicate_open_queue_row(): void
    {
        [$orphan, $channel] = $this->seedOrphanWithChannel();
        $user = $this->userWithRole('User');
        $claim = $this->claims->start($user, $orphan->id, $channel->id);

        $this->claims->queueVerify($user, $claim->id);

        try {
            $this->claims->queueVerify($user, $claim->id);
            $this->fail('Expected duplicate queue rejection');
        } catch (\App\Domain\Vtual\Exceptions\VtualException $e) {
            $this->assertSame(422, $e->statusCode());
        }
    }

    #[Test]
    public function flush_skips_below_threshold_until_force(): void
    {
        $this->activateYoutubeCredential();
        [$orphan, $channel] = $this->seedOrphanWithChannel();
        $user = $this->userWithRole('User');
        $claim = $this->claims->start($user, $orphan->id, $channel->id);
        $this->claims->queueVerify($user, $claim->id);

        $skipped = $this->pool->flush(force: false);
        $this->assertSame('skipped', $skipped['status']);

        $this->assertSame(1, CreatorClaimVerifyPoolItem::query()
            ->where('claim_id', $claim->id)
            ->where('status', CreatorClaimVerifyPoolStatus::Pending)
            ->count());

        $claim->refresh();
        $this->fakeYoutubeChannel(self::YT_ID, 'poolorphan', 'Pool Orphan', 'bio '.$claim->token);
        $flushed = $this->pool->flush(force: true);
        $this->assertSame('flushed', $flushed['status']);
        $this->assertSame(1, $flushed['verified']);
    }

    #[Test]
    public function flush_http_failure_broadcasts_verify_failed_with_cleared_queue(): void
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
        // PlatformCredentialChanged fires from activateYoutubeCredential()
        // below (broadcast-gated only) — fake it so Pusher is never hit.
        Event::fake([CreatorClaimProgressChanged::class, PlatformCredentialChanged::class, CreatorClaimStaffChanged::class]);

        $this->activateYoutubeCredential();
        [$orphan, $channel] = $this->seedOrphanWithChannel();
        $user = $this->userWithRole('User');
        $claim = $this->claims->start($user, $orphan->id, $channel->id);
        $this->claims->queueVerify($user, $claim->id);

        Http::fake([
            'www.googleapis.com/youtube/v3/channels*' => Http::response(['error' => 'upstream'], 502),
        ]);
        // 502 is retried upstream; keep the pool test fast.
        config([
            'vtual.http.retry.times' => 2,
            'vtual.http.retry.base_ms' => 1,
            'vtual.http.retry.max_ms' => 1,
            'vtual.http.retry.jitter' => false,
        ]);

        $flushed = $this->pool->flush(force: true);
        $this->assertSame('flushed', $flushed['status']);
        $this->assertGreaterThan(0, $flushed['failed']);

        Event::assertDispatched(CreatorClaimProgressChanged::class, function (CreatorClaimProgressChanged $event) use ($claim): bool {
            return $event->claimId === (string) $claim->id
                && $event->phase === 'verify_failed'
                && ($event->claim['verify_queue_status'] ?? null) === null;
        });
    }

    /**
     * @return array{0: \App\Domain\Vtual\Models\Creator, 1: Channel}
     */
    private function seedOrphanWithChannel(): array
    {
        $orphan = $this->creators->createOrphan($this->admin, ['display_name' => 'Pool Orphan']);
        $channel = Channel::query()->create([
            'id' => (string) Str::uuid7(),
            'creator_id' => $orphan->id,
            'platform_id' => $this->youtube->id,
            'external_id' => self::YT_ID,
            'handle' => 'poolorphan',
            'display_name' => 'Pool Orphan',
            'status' => ChannelStatus::Active,
        ]);

        return [$orphan, $channel];
    }

    private function activateYoutubeCredential(): void
    {
        $cred = $this->credentials->create($this->admin, $this->youtube, [
            'label' => 'pool-yt-'.uniqid(),
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
                        ],
                    ],
                ],
            ], 200),
        ]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::query()->create([
            'name' => $role.' Pool',
            'email' => strtolower($role).'-pool-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole($role);

        return $user;
    }
}
