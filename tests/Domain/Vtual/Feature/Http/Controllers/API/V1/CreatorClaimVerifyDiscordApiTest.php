<?php

namespace Tests\Domain\Vtual\Feature\Http\Controllers\API\V1;

use App\Domain\Identity\Enums\OAuthProvider;
use App\Domain\Identity\Models\IdentityOAuthAccount;
use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\ChannelStatus;
use App\Domain\Vtual\Enums\CreatorClaimStatus;
use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Models\Platform;
use App\Domain\Vtual\Services\CreatorClaimService;
use App\Domain\Vtual\Services\CreatorService;
use App\Support\Product\Services\ProductActivationService;
use Database\Seeders\VtualRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class CreatorClaimVerifyDiscordApiTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private const YT_ID = 'UCEMMrXP3_WeKiOunPYixMfA';

    private CreatorService $creators;

    private CreatorClaimService $claims;

    private Platform $youtube;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        $this->seed(VtualRoleSeeder::class);
        $this->creators = app(CreatorService::class);
        $this->claims = app(CreatorClaimService::class);
        $this->youtube = Platform::query()->where('key', 'youtube')->firstOrFail();
        $this->admin = $this->userWithRole('Admin');
        config([
            'vtual.claim.discord_verify_enabled' => true,
            'vtual.claim.verify_cooldown_seconds' => 0,
        ]);
    }

    #[Test]
    public function verify_discord_succeeds_when_connection_matches_different_profile_channel(): void
    {
        $claimant = $this->userWithRole('Vtual Viewer');
        app(ProductActivationService::class)->activateImplicit($claimant, 'Vtual');

        $orphan = $this->creators->createOrphan($this->admin, ['display_name' => 'Multi Channel Orphan']);
        $channelA = Channel::query()->create([
            'id' => (string) Str::uuid7(),
            'creator_id' => $orphan->id,
            'platform_id' => $this->youtube->id,
            'external_id' => 'UC-CHANNEL-A',
            'handle' => 'channel-a',
            'display_name' => 'Channel A',
            'status' => ChannelStatus::Active,
        ]);
        Channel::query()->create([
            'id' => (string) Str::uuid7(),
            'creator_id' => $orphan->id,
            'platform_id' => $this->youtube->id,
            'external_id' => self::YT_ID,
            'handle' => 'channel-b',
            'display_name' => 'Channel B',
            'status' => ChannelStatus::Active,
        ]);

        $claim = $this->claims->start($claimant, $orphan->id, $channelA->id);

        $account = new IdentityOAuthAccount();
        $account->user_id = $claimant->id;
        $account->provider = OAuthProvider::Discord;
        $account->provider_user_id = 'discord-user';
        $account->forceFill([
            'access_token' => 'discord-access',
            'refresh_token' => 'discord-refresh',
            'connections_snapshot' => [
                [
                    'type' => 'youtube',
                    'id' => self::YT_ID,
                    'name' => 'channel-b',
                    'verified' => true,
                ],
            ],
            'connections_synced_at' => now(),
        ])->save();

        Http::fake();

        $this->actingAsJwt($claimant)
            ->postJson('/api/v1/vtual/claims/'.$claim->id.'/verify-discord')
            ->assertOk()
            ->assertJsonPath('data.status', CreatorClaimStatus::Verified->value)
            ->assertJsonPath('data.verified_via', 'discord_connection');

        Http::assertNothingSent();
    }

    #[Test]
    public function verify_discord_returns_success_for_claimant_not_forbidden(): void
    {
        $claimant = $this->userWithRole('Vtual Viewer');
        app(ProductActivationService::class)->activateImplicit($claimant, 'Vtual');

        [$orphan, $channel] = $this->seedOrphanWithChannel();
        $claim = $this->claims->start($claimant, $orphan->id, $channel->id);

        $account = new IdentityOAuthAccount();
        $account->user_id = $claimant->id;
        $account->provider = OAuthProvider::Discord;
        $account->provider_user_id = 'discord-user';
        $account->forceFill([
            'access_token' => 'discord-access',
            'refresh_token' => 'discord-refresh',
        ])->save();

        Http::fake([
            'discord.com/api/users/@me/connections' => Http::response([
                [
                    'type' => 'youtube',
                    'id' => self::YT_ID,
                    'name' => 'neuron',
                    'verified' => true,
                ],
            ], 200),
        ]);

        $this->actingAsJwt($claimant)
            ->postJson('/api/v1/vtual/claims/'.$claim->id.'/verify-discord')
            ->assertOk()
            ->assertJsonPath('data.status', CreatorClaimStatus::Verified->value)
            ->assertJsonPath('data.verified_via', 'discord_connection');
    }

    /**
     * @return array{0: \App\Domain\Vtual\Models\Creator, 1: Channel}
     */
    private function seedOrphanWithChannel(): array
    {
        $orphan = $this->creators->createOrphan($this->admin, ['display_name' => 'Discord API Orphan']);
        $channel = Channel::query()->create([
            'id' => (string) Str::uuid7(),
            'creator_id' => $orphan->id,
            'platform_id' => $this->youtube->id,
            'external_id' => self::YT_ID,
            'handle' => 'discord-api-orphan',
            'display_name' => 'Discord API Orphan',
            'status' => ChannelStatus::Active,
        ]);

        return [$orphan, $channel];
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
