<?php

namespace Tests\Domain\Vtual\Feature\Http\Controllers\API\V1;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\PlatformCredentialStatus;
use App\Domain\Vtual\Enums\PlatformCredentialType;
use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Models\ChannelClaim;
use App\Domain\Vtual\Models\Platform;
use App\Domain\Vtual\Services\PlatformCredentialService;
use App\Support\Product\Enums\ProductActivationState;
use App\Support\Product\Services\ProductActivationService;
use Database\Seeders\VtualRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class VtualApiTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private ProductActivationService $activations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        $this->seed(VtualRoleSeeder::class);
        $this->activations = app(ProductActivationService::class);
    }

    #[Test]
    public function user_can_create_and_list_creators(): void
    {
        $user = $this->userWithRole('User');
        $this->activations->activateImplicit($user, 'Vtual');

        $create = $this->actingAsJwt($user)
            ->postJson('/api/v1/vtual/creators', [
                'display_name' => 'Neon Fox',
            ])
            ->assertCreated()
            ->json('data');

        $this->assertSame('neon-fox', $create['slug']);
        $this->assertSame('owner', $create['my_role']);

        $this->actingAsJwt($user)
            ->getJson('/api/v1/vtual/creators')
            ->assertOk()
            ->assertJsonPath('data.0.display_name', 'Neon Fox');
    }

    #[Test]
    public function suspended_activation_blocks_api(): void
    {
        $user = $this->userWithRole('User');
        $activation = $this->activations->activateImplicit($user, 'Vtual');
        $this->activations->suspend($activation);

        $this->actingAsJwt($user)
            ->getJson('/api/v1/vtual/creators')
            ->assertForbidden();
    }

    #[Test]
    public function lazy_create_activation_on_first_hit(): void
    {
        $user = $this->userWithRole('User');

        $this->assertDatabaseMissing('product_activations', [
            'user_id' => $user->id,
            'product_key' => 'Vtual',
        ]);

        $this->actingAsJwt($user)
            ->getJson('/api/v1/vtual/platforms')
            ->assertOk();

        $this->assertDatabaseHas('product_activations', [
            'user_id' => $user->id,
            'product_key' => 'Vtual',
            'state' => ProductActivationState::Active->value,
            'source' => 'lazy_create',
        ]);
    }

    #[Test]
    public function channel_registration_and_management_assign(): void
    {
        $user = $this->userWithRole('User');
        $this->activations->activateImplicit($user, 'Vtual');

        $admin = $this->userWithRole('Admin');
        $youtube = Platform::query()->where('key', 'youtube')->firstOrFail();
        $credential = app(PlatformCredentialService::class)->create($admin, $youtube, [
            'label' => 'yt-api-test',
            'type' => PlatformCredentialType::ApiKey->value,
            'secret' => ['api_key' => 'yt-api-test-key'],
        ]);
        $credential->forceFill([
            'status' => PlatformCredentialStatus::Active,
            'verified_at' => now(),
        ])->save();

        $ytId = 'UCPkKpOHxEDcwmUAnRpIu-Ng';
        Http::fake([
            'www.googleapis.com/youtube/v3/channels*' => Http::response([
                'items' => [
                    [
                        'id' => $ytId,
                        'snippet' => [
                            'title' => 'Talent YT',
                            'description' => '',
                            'customUrl' => '@talent',
                            'thumbnails' => [],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $creator = $this->actingAsJwt($user)
            ->postJson('/api/v1/vtual/creators', ['display_name' => 'Talent'])
            ->assertCreated()
            ->json('data');

        // Non-staff POST channels starts a bio-token claim (not an active channel row).
        $claim = $this->actingAsJwt($user)
            ->postJson('/api/v1/vtual/creators/'.$creator['id'].'/channels', [
                'platform_key' => 'youtube',
                'profile' => '@talent',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.external_id', $ytId)
            ->assertJsonPath('data.handle', 'talent')
            ->json('data');

        $this->assertNotEmpty($claim['token'] ?? null);
        $this->assertNull($claim['channel_id'] ?? null);

        // Staff Accept Anyway activates the channel.
        $this->actingAsJwt($admin)
            ->postJson('/api/v1/vtual/channel-claims/'.$claim['id'].'/accept-anyway')
            ->assertOk()
            ->assertJsonPath('data.status', 'verified');

        $this->actingAsJwt($user)
            ->getJson('/api/v1/vtual/creators/'.$creator['id'].'/channels')
            ->assertOk()
            ->assertJsonPath('data.0.external_id', $ytId)
            ->assertJsonPath('data.0.status', 'active');

        $management = $this->actingAsJwt($user)
            ->postJson('/api/v1/vtual/managements', ['name' => 'Agency'])
            ->assertCreated()
            ->json('data');

        $this->actingAsJwt($user)
            ->postJson('/api/v1/vtual/managements/'.$management['id'].'/creators', [
                'creator_id' => $creator['id'],
            ])
            ->assertOk();

        $show = $this->actingAsJwt($user)
            ->getJson('/api/v1/vtual/managements/'.$management['id'])
            ->assertOk()
            ->json('data');

        $this->assertNotEmpty($show['creators'] ?? []);
    }

    #[Test]
    public function staff_post_channel_queues_pending_without_immediate_http(): void
    {
        $admin = $this->userWithRole('Admin');
        $this->activations->activateImplicit($admin, 'Vtual');

        $httpCalls = 0;
        Http::fake(function () use (&$httpCalls) {
            $httpCalls++;

            return Http::response([], 404);
        });

        $orphan = $this->actingAsJwt($admin)
            ->postJson('/api/v1/vtual/creators/orphan', ['display_name' => 'Staff Orphan'])
            ->assertCreated()
            ->json('data');

        $channel = $this->actingAsJwt($admin)
            ->postJson('/api/v1/vtual/creators/'.$orphan['id'].'/channels', [
                'platform_key' => 'youtube',
                'profile' => '@orphan',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.profile_input', '@orphan')
            ->json('data');

        $this->assertSame(0, $httpCalls);
        $this->assertStringStartsWith('pending:', $channel['external_id']);
        $this->assertArrayNotHasKey('token', $channel);
        $this->assertSame(0, ChannelClaim::query()->count());
    }

    #[Test]
    public function staff_owner_member_post_channel_starts_claim_not_pending(): void
    {
        $admin = $this->userWithRole('Admin');
        $this->activations->activateImplicit($admin, 'Vtual');

        $youtube = Platform::query()->where('key', 'youtube')->firstOrFail();
        $credential = app(PlatformCredentialService::class)->create($admin, $youtube, [
            'label' => 'yt-staff-owner-claim',
            'type' => PlatformCredentialType::ApiKey->value,
            'secret' => ['api_key' => 'yt-staff-owner-claim-key'],
        ]);
        $credential->forceFill([
            'status' => PlatformCredentialStatus::Active,
            'verified_at' => now(),
        ])->save();

        $ytId = 'UCPkKpOHxEDcwmUAnRpIu-Ng';
        Http::fake([
            'www.googleapis.com/youtube/v3/channels*' => Http::response([
                'items' => [
                    [
                        'id' => $ytId,
                        'snippet' => [
                            'title' => 'Staff Owned YT',
                            'description' => '',
                            'customUrl' => '@staffowned',
                            'thumbnails' => [],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $creator = $this->actingAsJwt($admin)
            ->postJson('/api/v1/vtual/creators', ['display_name' => 'Staff Owned'])
            ->assertCreated()
            ->json('data');

        $claim = $this->actingAsJwt($admin)
            ->postJson('/api/v1/vtual/creators/'.$creator['id'].'/channels', [
                'platform_key' => 'youtube',
                'profile' => '@staffowned',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.external_id', $ytId)
            ->json('data');

        $this->assertNotEmpty($claim['token'] ?? null);
        $this->assertArrayHasKey('attempt_count', $claim);
        $this->assertSame(0, Channel::query()->count());
        $this->assertSame(1, ChannelClaim::query()->count());
    }

    #[Test]
    public function staff_can_delete_pending_channel(): void
    {
        $admin = $this->userWithRole('Admin');
        $this->activations->activateImplicit($admin, 'Vtual');

        Http::fake(fn () => Http::response([], 404));

        $orphan = $this->actingAsJwt($admin)
            ->postJson('/api/v1/vtual/creators/orphan', ['display_name' => 'Delete Pending Orphan'])
            ->assertCreated()
            ->json('data');

        $channel = $this->actingAsJwt($admin)
            ->postJson('/api/v1/vtual/creators/'.$orphan['id'].'/channels', [
                'platform_key' => 'youtube',
                'profile' => '@delete-me',
            ])
            ->assertCreated()
            ->json('data');

        $this->actingAsJwt($admin)
            ->deleteJson('/api/v1/vtual/channels/'.$channel['id'])
            ->assertOk();

        $this->assertSame(0, Channel::withTrashed()->whereKey($channel['id'])->count());
    }

    #[Test]
    public function archive_rejects_pending_channel(): void
    {
        $admin = $this->userWithRole('Admin');
        $this->activations->activateImplicit($admin, 'Vtual');

        Http::fake(fn () => Http::response([], 404));

        $orphan = $this->actingAsJwt($admin)
            ->postJson('/api/v1/vtual/creators/orphan', ['display_name' => 'Archive Pending Orphan'])
            ->assertCreated()
            ->json('data');

        $channel = $this->actingAsJwt($admin)
            ->postJson('/api/v1/vtual/creators/'.$orphan['id'].'/channels', [
                'platform_key' => 'youtube',
                'profile' => '@no-archive',
            ])
            ->assertCreated()
            ->json('data');

        $this->actingAsJwt($admin)
            ->postJson('/api/v1/vtual/channels/'.$channel['id'].'/archive')
            ->assertStatus(422)
            ->assertJsonPath('message', __('api/vtual.channel.pending_not_archivable'));
    }

    #[Test]
    public function staff_post_channel_rejects_duplicate_profile_on_creator(): void
    {
        $admin = $this->userWithRole('Admin');
        $this->activations->activateImplicit($admin, 'Vtual');

        Http::fake(fn () => Http::response([], 404));

        $orphan = $this->actingAsJwt($admin)
            ->postJson('/api/v1/vtual/creators/orphan', ['display_name' => 'Dup Staff Orphan'])
            ->assertCreated()
            ->json('data');

        $this->actingAsJwt($admin)
            ->postJson('/api/v1/vtual/creators/'.$orphan['id'].'/channels', [
                'platform_key' => 'youtube',
                'profile' => 'https://www.youtube.com/@AizawaEma',
            ])
            ->assertCreated();

        $this->actingAsJwt($admin)
            ->postJson('/api/v1/vtual/creators/'.$orphan['id'].'/channels', [
                'platform_key' => 'youtube',
                'profile' => '@AizawaEma',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['profile']);
    }

    #[Test]
    public function staff_post_channel_rejects_cross_platform_profile_url(): void
    {
        $admin = $this->userWithRole('Admin');
        $this->activations->activateImplicit($admin, 'Vtual');

        Http::fake(fn () => Http::response([], 404));

        $orphan = $this->actingAsJwt($admin)
            ->postJson('/api/v1/vtual/creators/orphan', ['display_name' => 'Mismatch Staff Orphan'])
            ->assertCreated()
            ->json('data');

        $this->actingAsJwt($admin)
            ->postJson('/api/v1/vtual/creators/'.$orphan['id'].'/channels', [
                'platform_key' => 'twitch',
                'profile' => 'https://www.youtube.com/@AizawaEma',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['profile']);
    }

    #[Test]
    public function peer_cannot_view_others_creator_without_any_perm(): void
    {
        $owner = $this->userWithRole('User');
        $peer = $this->userWithRole('User');
        $this->activations->activateImplicit($owner, 'Vtual');
        $this->activations->activateImplicit($peer, 'Vtual');

        $creator = $this->actingAsJwt($owner)
            ->postJson('/api/v1/vtual/creators', ['display_name' => 'Private'])
            ->assertCreated()
            ->json('data');

        $this->actingAsJwt($peer)
            ->getJson('/api/v1/vtual/creators/'.$creator['id'])
            ->assertForbidden();
    }

    #[Test]
    public function platforms_include_disabled_kick(): void
    {
        $user = $this->userWithRole('User');
        $this->activations->activateImplicit($user, 'Vtual');

        $data = $this->actingAsJwt($user)
            ->getJson('/api/v1/vtual/platforms')
            ->assertOk()
            ->json('data');

        $keys = collect($data)->pluck('key')->all();
        $this->assertContains('youtube', $keys);
        $this->assertContains('twitch', $keys);
        $this->assertContains('kick', $keys);

        $kick = collect($data)->firstWhere('key', 'kick');
        $this->assertFalse((bool) ($kick['enabled'] ?? true));
    }

    private function actingAsJwt(User $user): static
    {
        $token = auth('api')->login($user);

        return $this->withHeader('Authorization', 'Bearer '.$token);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create([
            'email' => 'u'.uniqid('', true).'@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole($role);

        return $user;
    }
}
