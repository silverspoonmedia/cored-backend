<?php

namespace Tests\Domain\Vtual\Feature\Http\Controllers\API\V1;

use App\Domain\Identity\Enums\OAuthProvider;
use App\Domain\Identity\Models\IdentityOAuthAccount;
use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\CreatorClaimStatus;
use App\Domain\Vtual\Models\Platform;
use App\Domain\Vtual\Services\ChannelClaimService;
use App\Domain\Vtual\Services\CreatorService;
use App\Support\Product\Services\ProductActivationService;
use Database\Seeders\VtualRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class ChannelClaimVerifyDiscordApiTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private const YT_ID = 'UCEMMrXP3_WeKiOunPYixMfA';

    private CreatorService $creators;

    private ChannelClaimService $claims;

    private Platform $youtube;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        $this->seed(VtualRoleSeeder::class);
        $this->creators = app(CreatorService::class);
        $this->claims = app(ChannelClaimService::class);
        $this->youtube = Platform::query()->where('key', 'youtube')->firstOrFail();
        config([
            'vtual.channel_claim.discord_verify_enabled' => true,
            'vtual.channel_claim.verify_cooldown_seconds' => 0,
        ]);
    }

    #[Test]
    public function owner_can_verify_channel_claim_via_discord(): void
    {
        $owner = $this->userWithRole('Vtual vTuber');
        app(ProductActivationService::class)->activateImplicit($owner, 'Vtual');

        $creator = $this->creators->create($owner, ['display_name' => 'Discord Channel Owner']);

        Http::fake([
            'www.googleapis.com/youtube/v3/channels*' => Http::response([
                'items' => [
                    [
                        'id' => self::YT_ID,
                        'snippet' => [
                            'title' => 'Stream',
                            'description' => 'bio',
                            'customUrl' => '@stream',
                            'publishedAt' => '2017-04-19T14:57:55Z',
                            'thumbnails' => ['high' => ['url' => 'https://img.example/hi.jpg']],
                        ],
                        'brandingSettings' => ['image' => ['bannerExternalUrl' => 'https://img.example/banner.jpg']],
                    ],
                ],
            ], 200),
            'discord.com/api/users/@me/connections' => Http::response([
                [
                    'type' => 'youtube',
                    'id' => self::YT_ID,
                    'name' => 'stream',
                    'verified' => true,
                ],
            ], 200),
            // Channel activation subscribes the channel to YouTube WebSub;
            // never hit the real hub in tests.
            'pubsubhubbub.appspot.com/*' => Http::response('', 202),
        ]);

        $this->activateYoutubeCredential($owner);

        $claim = $this->claims->start($owner, $creator->id, [
            'platform_key' => 'youtube',
            'profile' => self::YT_ID,
        ]);

        $account = new IdentityOAuthAccount();
        $account->user_id = $owner->id;
        $account->provider = OAuthProvider::Discord;
        $account->provider_user_id = 'discord-user';
        $account->forceFill([
            'access_token' => 'discord-access',
            'refresh_token' => 'discord-refresh',
        ])->save();

        $this->actingAsJwt($owner)
            ->postJson('/api/v1/vtual/channel-claims/'.$claim->id.'/verify-discord')
            ->assertOk()
            ->assertJsonPath('data.status', CreatorClaimStatus::Verified->value)
            ->assertJsonPath('data.verified_via', 'discord_connection');
    }

    #[Test]
    public function non_claimant_cannot_verify_channel_claim_via_discord(): void
    {
        $owner = $this->userWithRole('Vtual vTuber');
        $other = $this->userWithRole('Vtual vTuber');
        app(ProductActivationService::class)->activateImplicit($owner, 'Vtual');
        app(ProductActivationService::class)->activateImplicit($other, 'Vtual');

        $creator = $this->creators->create($owner, ['display_name' => 'Owner Profile']);

        Http::fake([
            'www.googleapis.com/youtube/v3/channels*' => Http::response([
                'items' => [
                    [
                        'id' => self::YT_ID,
                        'snippet' => [
                            'title' => 'Stream',
                            'description' => 'bio',
                            'customUrl' => '@stream',
                            'publishedAt' => '2017-04-19T14:57:55Z',
                            'thumbnails' => ['high' => ['url' => 'https://img.example/hi.jpg']],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $this->activateYoutubeCredential($owner);

        $claim = $this->claims->start($owner, $creator->id, [
            'platform_key' => 'youtube',
            'profile' => self::YT_ID,
        ]);

        $this->actingAsJwt($other)
            ->postJson('/api/v1/vtual/channel-claims/'.$claim->id.'/verify-discord')
            ->assertForbidden();
    }

    private function activateYoutubeCredential(User $admin): void
    {
        $credentialService = app(\App\Domain\Vtual\Services\PlatformCredentialService::class);
        $credential = $credentialService->create($admin, $this->youtube, [
            'label' => 'yt-ch-discord-api',
            'type' => \App\Domain\Vtual\Enums\PlatformCredentialType::ApiKey->value,
            'secret' => ['api_key' => 'yt-api-test-key'],
        ]);
        $credential->forceFill([
            'status' => \App\Domain\Vtual\Enums\PlatformCredentialStatus::Active,
            'verified_at' => now(),
        ])->save();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function actingAsJwt(User $user): static
    {
        $token = auth('api')->login($user);

        return $this->withHeader('Authorization', 'Bearer '.$token);
    }
}
