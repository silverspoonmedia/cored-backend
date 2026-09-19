<?php

namespace Tests\Domain\Vtual\Feature\Http\Controllers\API\V1;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\ChannelStatus;
use App\Domain\Vtual\Enums\ChannelVisibility;
use App\Domain\Vtual\Enums\ContentStatus;
use App\Domain\Vtual\Enums\ContentType;
use App\Domain\Vtual\Enums\EntityStatus;
use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Models\Content;
use App\Domain\Vtual\Models\Creator;
use App\Domain\Vtual\Models\Management;
use App\Domain\Vtual\Models\ManagementCreator;
use App\Domain\Vtual\Models\Platform;
use App\Domain\Vtual\Services\CreatorService;
use Database\Seeders\VtualRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class PublicManagementProfileApiTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private CreatorService $creators;

    private Platform $youtube;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        $this->seed(VtualRoleSeeder::class);
        $this->creators = app(CreatorService::class);
        $this->youtube = Platform::query()->where('key', 'youtube')->firstOrFail();
    }

    #[Test]
    public function guest_can_view_active_management_profile_with_public_roster(): void
    {
        $staff = $this->userWithRole('Admin');
        $management = $this->management('neuron-talent', 'Neuron Talent');

        $listed = $this->creators->createOrphan($staff, ['display_name' => 'Listed Fox']);
        $this->publicChannel($listed, 'UClistedfox1234567890abc', 'listedfox', 42000);

        // Creator without any public channel must be hidden from roster.
        $hidden = $this->creators->createOrphan($staff, ['display_name' => 'Hidden Cat']);

        $this->assign($management, [$listed, $hidden]);

        $response = $this->getJson('/api/v1/vtual/public/managements/neuron-talent');

        $response->assertOk()
            ->assertJsonPath('data.slug', 'neuron-talent')
            ->assertJsonPath('data.name', 'Neuron Talent')
            ->assertJsonPath('data.logo_url', 'https://img.example/logo.png')
            ->assertJsonCount(1, 'data.creators')
            ->assertJsonPath('data.creators.0.display_name', 'Listed Fox')
            ->assertJsonPath('data.creators.0.is_live', false)
            ->assertJsonPath('data.creators.0.total_subscribers', 42000)
            ->assertJsonPath('data.stats.creators', 1)
            ->assertJsonPath('data.stats.live', 0)
            ->assertJsonPath('data.stats.channels', 1)
            ->assertJsonPath('data.stats.subscribers_total', 42000)
            ->assertJsonPath('data.stats.subscribers_total_formatted', '42,000');

        $this->assertArrayNotHasKey('id', $response->json('data'));
    }

    #[Test]
    public function roster_creator_with_live_content_is_marked_live_and_counted(): void
    {
        $staff = $this->userWithRole('Admin');
        $management = $this->management('live-org', 'Live Org');

        $creator = $this->creators->createOrphan($staff, ['display_name' => 'Live Fox']);
        $channel = $this->publicChannel($creator, 'UClivefox1234567890abcde', 'livefox', 1200);
        $this->liveContent($creator, $channel, 'live-video-1');

        $this->assign($management, [$creator]);

        $this->getJson('/api/v1/vtual/public/managements/live-org')
            ->assertOk()
            ->assertJsonPath('data.creators.0.is_live', true)
            ->assertJsonPath('data.stats.live', 1)
            ->assertJsonPath('data.stats.creators', 1);
    }

    #[Test]
    public function live_content_on_an_archived_channel_does_not_mark_the_roster_creator_live(): void
    {
        $staff = $this->userWithRole('Admin');
        $management = $this->management('archived-live-org', 'Archived Live Org');

        $creator = $this->creators->createOrphan($staff, ['display_name' => 'Archived Fox']);
        // Public channel keeps the creator listed in the roster.
        $this->publicChannel($creator, 'UCarchivedfox1234567890ab', 'archivedfox', 800);

        $archived = Channel::query()->create([
            'id' => (string) Str::uuid7(),
            'creator_id' => $creator->id,
            'platform_id' => $this->youtube->id,
            'external_id' => 'UCarchivedold1234567890ab',
            'handle' => 'archivedold',
            'display_name' => 'Archived Old',
            'status' => ChannelStatus::Archived,
            'visibility' => ChannelVisibility::Public,
        ]);

        $this->liveContent($creator, $archived, 'live-video-archived');

        $this->assign($management, [$creator]);

        $this->getJson('/api/v1/vtual/public/managements/archived-live-org')
            ->assertOk()
            ->assertJsonPath('data.creators.0.is_live', false)
            ->assertJsonPath('data.stats.live', 0);
    }

    #[Test]
    public function management_without_roster_returns_zeroed_stats(): void
    {
        $this->management('empty-org', 'Empty Org');

        $this->getJson('/api/v1/vtual/public/managements/empty-org')
            ->assertOk()
            ->assertJsonCount(0, 'data.creators')
            ->assertJsonPath('data.stats.creators', 0)
            ->assertJsonPath('data.stats.live', 0)
            ->assertJsonPath('data.stats.channels', 0)
            ->assertJsonPath('data.stats.subscribers_total', 0)
            ->assertJsonPath('data.stats.subscribers_total_formatted', '0');
    }

    #[Test]
    public function archived_management_returns_not_found(): void
    {
        Management::query()->create([
            'id' => (string) Str::uuid7(),
            'slug' => 'gone-org',
            'name' => 'Gone Org',
            'status' => EntityStatus::Archived,
        ]);

        $this->getJson('/api/v1/vtual/public/managements/gone-org')
            ->assertNotFound();
    }

    #[Test]
    public function unknown_slug_returns_not_found(): void
    {
        $this->getJson('/api/v1/vtual/public/managements/does-not-exist')
            ->assertNotFound();
    }

    private function management(string $slug, string $name): Management
    {
        return Management::query()->create([
            'id' => (string) Str::uuid7(),
            'slug' => $slug,
            'name' => $name,
            'description' => 'A talent org.',
            'logo_url' => 'https://img.example/logo.png',
            'status' => EntityStatus::Active,
        ]);
    }

    /**
     * @param  list<Creator>  $creators
     */
    private function assign(Management $management, array $creators): void
    {
        foreach ($creators as $creator) {
            ManagementCreator::query()->create([
                'id' => (string) Str::uuid7(),
                'management_id' => $management->id,
                'creator_id' => $creator->id,
                'joined_at' => now(),
            ]);
        }
    }

    private function publicChannel(
        Creator $creator,
        string $externalId,
        string $handle,
        int $subscribers,
    ): Channel {
        return Channel::query()->create([
            'id' => (string) Str::uuid7(),
            'creator_id' => $creator->id,
            'platform_id' => $this->youtube->id,
            'external_id' => $externalId,
            'handle' => $handle,
            'display_name' => $creator->display_name,
            'subscriber_count' => $subscribers,
            'status' => ChannelStatus::Active,
            'visibility' => ChannelVisibility::Public,
        ]);
    }

    private function liveContent(Creator $creator, Channel $channel, string $externalId): Content
    {
        return Content::query()->create([
            'creator_id' => $creator->id,
            'channel_id' => $channel->id,
            'platform_id' => $this->youtube->id,
            'external_id' => $externalId,
            'type' => ContentType::Live,
            'status' => ContentStatus::Live,
            'title' => 'Live now',
            'url' => 'https://youtube.com/watch?v='.$externalId,
            'published_at' => now(),
        ]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::query()->create([
            'name' => $role.' MgmtApi',
            'email' => strtolower($role).'-mgmt-api-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole($role);

        return $user;
    }
}
