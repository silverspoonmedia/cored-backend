<?php

namespace Tests\Domain\Vtual\Unit\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\ChannelStatus;
use App\Domain\Vtual\Enums\CreatorClaimStatus;
use App\Domain\Vtual\Enums\CreatorClaimVerifiedVia;
use App\Domain\Vtual\Enums\PlatformCredentialStatus;
use App\Domain\Vtual\Enums\PlatformCredentialType;
use App\Domain\Vtual\Exceptions\VtualException;
use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Models\Platform;
use App\Domain\Vtual\Services\ChannelClaimService;
use App\Domain\Vtual\Services\CreatorService;
use App\Domain\Vtual\Services\PlatformCredentialService;
use Database\Seeders\VtualRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class ChannelClaimServiceTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private const YT_ID = 'UCPkKpOHxEDcwmUAnRpIu-Ng';

    private ChannelClaimService $claims;

    private CreatorService $creators;

    private PlatformCredentialService $credentials;

    private Platform $youtube;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        $this->seed(VtualRoleSeeder::class);
        $this->claims = app(ChannelClaimService::class);
        $this->creators = app(CreatorService::class);
        $this->credentials = app(PlatformCredentialService::class);
        $this->youtube = Platform::query()->where('key', 'youtube')->firstOrFail();
        $this->admin = $this->userWithRole('Admin');
        config([
            'vtual.channel_claim.accept_anyway_enabled' => true,
            'vtual.channel_claim.verify_cooldown_seconds' => 0,
            'vtual.channel_claim.max_attempts' => 20,
            'vtual.channel_claim.ttl_hours' => 24,
            'vtual.channel_claim.token_prefix' => 'vtual-ch-',
        ]);
    }

    #[Test]
    public function start_creates_pending_claim_without_channel_row(): void
    {
        $this->activateYoutubeCredential();
        $this->fakeYoutube(self::YT_ID, 'stream', 'Stream', 'no token yet');

        $user = $this->userWithRole('User');
        $creator = $this->creators->create($user, ['display_name' => 'Owner']);

        $claim = $this->claims->start($user, $creator->id, [
            'platform_key' => 'youtube',
            'profile' => '@stream',
        ]);

        $this->assertSame(CreatorClaimStatus::Pending, $claim->status);
        $this->assertSame(self::YT_ID, $claim->external_id);
        $this->assertSame('stream', $claim->handle);
        $this->assertStringStartsWith('vtual-ch-', (string) $claim->token);
        $this->assertNull($claim->channel_id);
        $this->assertSame(0, Channel::query()->count());
    }

    #[Test]
    public function verify_without_token_fails_and_keeps_no_channel(): void
    {
        $this->activateYoutubeCredential();
        $this->fakeYoutube(self::YT_ID, 'stream', 'Stream', 'bio without proof');

        $user = $this->userWithRole('User');
        $creator = $this->creators->create($user, ['display_name' => 'Owner']);
        $claim = $this->claims->start($user, $creator->id, [
            'platform_key' => 'youtube',
            'profile' => self::YT_ID,
        ]);

        $this->expectException(VtualException::class);
        try {
            $this->claims->verify($user, $claim->id);
        } finally {
            $this->assertSame(0, Channel::query()->count());
            $this->assertSame(1, (int) $claim->fresh()?->attempt_count);
        }
    }

    #[Test]
    public function verify_with_token_in_bio_creates_active_channel(): void
    {
        $this->activateYoutubeCredential();

        $user = $this->userWithRole('User');
        $creator = $this->creators->create($user, ['display_name' => 'Owner']);

        // First response: start (no token). Second: verify (token injected via callback after claim exists).
        $tokenHolder = (object) ['token' => ''];
        Http::fake(function () use ($tokenHolder) {
            $description = $tokenHolder->token !== ''
                ? 'proof '.$tokenHolder->token.' end'
                : 'hello';

            return Http::response([
                'items' => [
                    [
                        'id' => self::YT_ID,
                        'snippet' => [
                            'title' => 'Stream',
                            'description' => $description,
                            'customUrl' => '@stream',
                            'publishedAt' => '2017-04-19T14:57:55Z',
                            'thumbnails' => [
                                'high' => ['url' => 'https://img.example/hi.jpg'],
                            ],
                        ],
                        'brandingSettings' => [
                            'image' => [
                                'bannerExternalUrl' => 'https://img.example/banner.jpg',
                            ],
                        ],
                    ],
                ],
            ], 200);
        });

        $claim = $this->claims->start($user, $creator->id, [
            'platform_key' => 'youtube',
            'profile' => self::YT_ID,
        ]);
        $tokenHolder->token = (string) $claim->token;

        $done = $this->claims->verify($user, $claim->id);

        $this->assertSame(CreatorClaimStatus::Verified, $done->status);
        $this->assertSame(CreatorClaimVerifiedVia::Bio, $done->verified_via);
        $this->assertNotNull($done->channel_id);

        $channel = Channel::query()->findOrFail($done->channel_id);
        $this->assertSame(ChannelStatus::Active, $channel->status);
        $this->assertSame(self::YT_ID, $channel->external_id);
        $this->assertSame((string) $creator->id, (string) $channel->creator_id);
        $this->assertStringContainsString($tokenHolder->token, (string) $channel->description);
    }

    #[Test]
    public function accept_anyway_staff_creates_channel_without_bio(): void
    {
        $this->activateYoutubeCredential();
        $this->fakeYoutube(self::YT_ID, 'stream', 'Stream', 'no token');

        $user = $this->userWithRole('User');
        $creator = $this->creators->create($user, ['display_name' => 'Owner']);
        $claim = $this->claims->start($user, $creator->id, [
            'platform_key' => 'youtube',
            'profile' => self::YT_ID,
        ]);

        $done = $this->claims->acceptAnyway($this->admin, $claim->id);

        $this->assertSame(CreatorClaimStatus::Verified, $done->status);
        $this->assertSame(CreatorClaimVerifiedVia::AcceptAnyway, $done->verified_via);
        $this->assertSame((string) $this->admin->id, (string) $done->verified_by_user_id);
        $this->assertSame(1, Channel::query()->count());
    }

    #[Test]
    public function start_rejects_when_external_id_already_bound(): void
    {
        $this->activateYoutubeCredential();
        $this->fakeYoutube(self::YT_ID, 'stream', 'Stream', 'bio');

        $user = $this->userWithRole('User');
        $creator = $this->creators->create($user, ['display_name' => 'Owner']);
        $claim = $this->claims->start($user, $creator->id, [
            'platform_key' => 'youtube',
            'profile' => self::YT_ID,
        ]);
        $this->claims->acceptAnyway($this->admin, $claim->id);

        $this->expectException(VtualException::class);
        $this->claims->start($user, $creator->id, [
            'platform_key' => 'youtube',
            'profile' => '@stream',
        ]);
    }

    #[Test]
    public function cancel_closes_pending_claim(): void
    {
        $this->activateYoutubeCredential();
        $this->fakeYoutube(self::YT_ID, 'stream', 'Stream', 'bio');

        $user = $this->userWithRole('User');
        $creator = $this->creators->create($user, ['display_name' => 'Owner']);
        $claim = $this->claims->start($user, $creator->id, [
            'platform_key' => 'youtube',
            'profile' => self::YT_ID,
        ]);

        $cancelled = $this->claims->cancel($user, $claim->id);

        $this->assertSame(CreatorClaimStatus::Cancelled, $cancelled->status);
        $this->assertSame(0, Channel::query()->count());
    }

    private function fakeYoutube(string $id, string $handle, string $title, string $description): void
    {
        Http::fake([
            'www.googleapis.com/youtube/v3/channels*' => Http::response([
                'items' => [
                    [
                        'id' => $id,
                        'snippet' => [
                            'title' => $title,
                            'description' => $description,
                            'customUrl' => '@'.$handle,
                            'publishedAt' => '2017-04-19T14:57:55Z',
                            'thumbnails' => [
                                'high' => ['url' => 'https://img.example/hi.jpg'],
                            ],
                        ],
                        'brandingSettings' => [
                            'image' => [
                                'bannerExternalUrl' => 'https://img.example/banner.jpg',
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);
    }

    private function activateYoutubeCredential(): void
    {
        $credential = $this->credentials->create($this->admin, $this->youtube, [
            'label' => 'yt-ch-claim-test',
            'type' => PlatformCredentialType::ApiKey->value,
            'secret' => ['api_key' => 'yt-api-test-key'],
        ]);
        $credential->forceFill([
            'status' => PlatformCredentialStatus::Active,
            'verified_at' => now(),
        ])->save();
    }

    private function userWithRole(string $role): User
    {
        $user = User::query()->create([
            'name' => $role.' User',
            'email' => strtolower($role).'-chc-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        $user->assignRole($role);

        return $user;
    }
}
