<?php

namespace Tests\Domain\Vtual\Feature\Http\Controllers\API\V1;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\ChannelStatus;
use App\Domain\Vtual\Enums\ChannelVisibility;
use App\Domain\Vtual\Enums\ContentStatus;
use App\Domain\Vtual\Enums\ContentType;
use App\Domain\Vtual\Enums\EntityStatus;
use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Models\Platform;
use App\Domain\Vtual\Services\ChannelService;
use App\Domain\Vtual\Services\ChannelStubContentBackfillService;
use App\Domain\Vtual\Services\ContentService;
use App\Domain\Vtual\Services\CreatorService;
use App\Support\Product\Services\ProductActivationService;
use App\Support\SpaPublicUrl;
use Database\Seeders\VtualRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class VtuberProfileApiTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private CreatorService $creators;

    private ContentService $contents;

    private ChannelService $channels;

    private ProductActivationService $activations;

    private Platform $youtube;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        $this->seed(VtualRoleSeeder::class);
        config(['vtual.channel_lifecycle.cooldown_seconds' => 0]);
        $this->creators = app(CreatorService::class);
        $this->contents = app(ContentService::class);
        $this->channels = app(ChannelService::class);
        $this->activations = app(ProductActivationService::class);
        $this->youtube = Platform::query()->where('key', 'youtube')->firstOrFail();

        // Activation hooks fire before per-test Http::fake() registration —
        // fake the WebSub hub up front so no test ever reaches the real hub.
        Http::fake(['pubsubhubbub.appspot.com/*' => Http::response('', 202)]);
    }

    #[Test]
    public function authenticated_user_can_view_profile_without_creator_membership(): void
    {
        $staff = $this->userWithRole('Admin');
        $viewer = $this->userWithRole('User');
        $this->activations->activateImplicit($viewer, 'Vtual');

        $creator = $this->creators->createOrphan($staff, ['display_name' => 'Aizawa Ema']);

        $channel = Channel::query()->create([
            'id' => (string) Str::uuid7(),
            'creator_id' => $creator->id,
            'platform_id' => $this->youtube->id,
            'external_id' => 'UCaizawaema1234567890abcd',
            'handle' => 'aizawaema',
            'display_name' => 'Aizawa Ema',
            'avatar_url' => 'https://img.example/avatar.jpg',
            'url' => 'https://www.youtube.com/channel/UCaizawaema1234567890abcd',
            'subscriber_count' => 125000,
            'status' => ChannelStatus::Active,
        ]);

        $this->contents->upsertFromPrimary($channel, [
            'external_id' => 'vid-api-1',
            'type' => ContentType::Vod->value,
            'status' => ContentStatus::Uploaded->value,
            'title' => 'Archive Stream 1',
            'thumbnail_url' => 'https://img.example/thumb.jpg',
            'url' => 'https://www.youtube.com/watch?v=vid-api-1',
        ]);

        $response = $this->actingAsJwt($viewer)
            ->getJson('/api/v1/vtual/vtubers/'.$creator->id);

        $response->assertOk()
            ->assertJsonPath('data.display_name', 'Aizawa Ema')
            ->assertJsonPath('data.slug', $creator->slug)
            ->assertJsonPath('data.profile_url', SpaPublicUrl::vtuberProfile($creator->slug))
            ->assertJsonPath('data.is_claimable', true)
            ->assertJsonPath('data.claim_process_url', SpaPublicUrl::vtuberClaim($creator->slug))
            ->assertJsonPath('data.channels.0.handle', 'aizawaema')
            ->assertJsonPath('data.channels.0.subscriber_count', 125000)
            ->assertJsonPath('data.contents.0.title', 'Archive Stream 1')
            ->assertJsonPath('data.contents.0.external_id', 'vid-api-1');
    }

    #[Test]
    public function authenticated_user_can_view_profile_by_slug(): void
    {
        $staff = $this->userWithRole('Admin');
        $viewer = $this->userWithRole('User');
        $this->activations->activateImplicit($viewer, 'Vtual');

        $creator = $this->creators->createOrphan($staff, ['display_name' => 'Slug Lookup']);

        $this->actingAsJwt($viewer)
            ->getJson('/api/v1/vtual/vtubers/'.$creator->slug)
            ->assertOk()
            ->assertJsonPath('data.id', $creator->id)
            ->assertJsonPath('data.display_name', 'Slug Lookup');
    }

    #[Test]
    public function owned_creator_profile_is_not_claimable(): void
    {
        $owner = $this->userWithRole('User');
        $viewer = $this->userWithRole('User');
        $this->activations->activateImplicit($owner, 'Vtual');
        $this->activations->activateImplicit($viewer, 'Vtual');

        $creator = $this->creators->create($owner, ['display_name' => 'Owned Profile']);

        $this->actingAsJwt($viewer)
            ->getJson('/api/v1/vtual/vtubers/'.$creator->slug)
            ->assertOk()
            ->assertJsonPath('data.is_claimable', false)
            ->assertJsonPath('data.claim_process_url', null);
    }

    #[Test]
    public function archived_channel_hides_contents_from_profile_api(): void
    {
        $staff = $this->userWithRole('Admin');
        $viewer = $this->userWithRole('User');
        $this->activations->activateImplicit($viewer, 'Vtual');

        $creator = $this->creators->createOrphan($staff, ['display_name' => 'Archived Channel Fox']);

        $channel = Channel::query()->create([
            'id' => (string) Str::uuid7(),
            'creator_id' => $creator->id,
            'platform_id' => $this->youtube->id,
            'external_id' => 'UCarchivedprofileapi1',
            'handle' => 'archivedfox',
            'display_name' => 'Archived Fox',
            'status' => ChannelStatus::Active,
            'visibility' => ChannelVisibility::Public,
        ]);

        $this->contents->upsertFromPrimary($channel, [
            'external_id' => 'vid-archived-api',
            'type' => ContentType::Vod->value,
            'status' => ContentStatus::Uploaded->value,
            'title' => 'Hidden When Archived',
        ]);

        $this->channels->archive($staff, (string) $channel->id);

        $this->actingAsJwt($viewer)
            ->getJson('/api/v1/vtual/vtubers/'.$creator->slug)
            ->assertOk()
            ->assertJsonPath('data.channels', [])
            ->assertJsonPath('data.contents', []);
    }

    #[Test]
    public function restored_channel_shows_contents_after_stub_backfill(): void
    {
        $staff = $this->userWithRole('Admin');
        $viewer = $this->userWithRole('User');
        $this->activations->activateImplicit($viewer, 'Vtual');

        $creator = $this->creators->createOrphan($staff, ['display_name' => 'Restore Profile Fox']);

        $channel = Channel::query()->create([
            'id' => (string) Str::uuid7(),
            'creator_id' => $creator->id,
            'platform_id' => $this->youtube->id,
            'external_id' => 'UCrestoreprofileapi1',
            'handle' => 'restorefox',
            'display_name' => 'Restore Fox',
            'status' => ChannelStatus::Active,
            'visibility' => ChannelVisibility::Public,
        ]);

        $this->contents->upsertStubFromPrimary($channel, [
            'external_id' => 'vid-restore-api',
            'title' => 'Stub Before Hydrate',
        ]);

        $this->channels->archive($staff, (string) $channel->id);
        $restored = $this->channels->restore($staff, (string) $channel->id);

        $fetch = \Mockery::mock(\App\Domain\Vtual\Services\ChannelContentFetchService::class);
        $fetch->shouldReceive('fetchVideosAndPersist')
            ->once()
            ->with(\Mockery::type(Channel::class), ['vid-restore-api'])
            ->andReturnUsing(function (Channel $ch, array $ids) {
                app(ContentService::class)->upsertFromPrimary($ch, [
                    'external_id' => 'vid-restore-api',
                    'type' => ContentType::Vod->value,
                    'status' => ContentStatus::Uploaded->value,
                    'title' => 'Hydrated After Restore',
                ]);

                return ['requested' => 1, 'fetched' => 1, 'upserted' => 1];
            });

        $backfill = new ChannelStubContentBackfillService($fetch);
        $result = $backfill->run($restored);
        $this->assertSame('completed', $result['status']);

        $this->actingAsJwt($viewer)
            ->getJson('/api/v1/vtual/vtubers/'.$creator->slug)
            ->assertOk()
            ->assertJsonPath('data.channels.0.handle', 'restorefox')
            ->assertJsonPath('data.contents.0.external_id', 'vid-restore-api')
            ->assertJsonPath('data.contents.0.title', 'Hydrated After Restore');
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function archived_creator_returns_not_found(): void
    {
        $user = $this->userWithRole('User');
        $this->activations->activateImplicit($user, 'Vtual');

        $creator = $this->creators->createOrphan($this->userWithRole('Admin'), ['display_name' => 'Gone']);
        $creator->forceFill(['status' => EntityStatus::Archived])->save();

        $this->actingAsJwt($user)
            ->getJson('/api/v1/vtual/vtubers/'.$creator->id)
            ->assertNotFound();
    }

    private function actingAsJwt(User $user): static
    {
        $token = auth('api')->login($user);

        return $this->withHeader('Authorization', 'Bearer '.$token);
    }

    private function userWithRole(string $role): User
    {
        $user = User::query()->create([
            'name' => $role.' VtuberApi',
            'email' => strtolower($role).'-vtuber-api-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole($role);

        return $user;
    }
}
