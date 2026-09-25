<?php

namespace Tests\Domain\Vtual\Feature\Http\Controllers\API\V1;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\ChannelStatus;
use App\Domain\Vtual\Enums\ChannelVisibility;
use App\Domain\Vtual\Enums\ContentAppearanceRole;
use App\Domain\Vtual\Enums\ContentStatus;
use App\Domain\Vtual\Enums\ContentType;
use App\Domain\Vtual\Enums\EntityStatus;
use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Models\Content;
use App\Domain\Vtual\Models\Creator;
use App\Domain\Vtual\Models\Management;
use App\Domain\Vtual\Models\ManagementCreator;
use App\Domain\Vtual\Models\Platform;
use App\Domain\Vtual\Services\ContentService;
use App\Domain\Vtual\Services\CreatorService;
use Database\Seeders\VtualRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

/**
 * The guest "this creator is live" predicate has exactly one owner —
 * `App\Domain\Vtual\Support\LiveContentScope`.
 *
 * A creator whose only live row is an **appearance** (collaborator / network on
 * someone else's stream, not the primary host) must read the same on the public
 * profile badge, the directory card and the guest management roster.
 */
class VtualLivePredicateTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private Platform $youtube;

    private CreatorService $creators;

    private ContentService $contents;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        $this->seed(VtualRoleSeeder::class);
        $this->youtube = Platform::query()->where('key', 'youtube')->firstOrFail();
        $this->creators = app(CreatorService::class);
        $this->contents = app(ContentService::class);
    }

    #[Test]
    public function appearance_only_live_creator_is_live_on_profile_and_directory(): void
    {
        $staff = $this->userWithRole('Admin');

        $host = $this->creator($staff, 'Host Fox');
        $hostChannel = $this->publicChannel($host, 'UChostfox1234567890abcde', 'hostfox');
        $live = $this->liveContent($host, $hostChannel, 'live-collab-1');

        $collaborator = $this->creator($staff, 'Guest Wolf');
        $collaboratorChannel = $this->publicChannel($collaborator, 'UCguestwolf1234567890abc', 'guestwolf');
        $this->contents->attachAppearance($live, $collaboratorChannel, ContentAppearanceRole::Collaborator);

        // Profile badge — appearance counts.
        $this->getJson('/api/v1/vtual/public/vtubers/guest-wolf')
            ->assertOk()
            ->assertJsonPath('data.is_live', true);

        // Directory card — must agree with the profile badge.
        $items = collect($this->getJson('/api/v1/vtual/public/vtubers')->assertOk()->json('data.items'))
            ->keyBy('slug');

        $this->assertTrue($items['guest-wolf']['is_live']);
        $this->assertTrue($items['host-fox']['is_live']);
    }

    #[Test]
    public function appearance_only_live_creator_sorts_above_offline_creators(): void
    {
        $staff = $this->userWithRole('Admin');

        // Alphabetically first, but offline — must not outrank the live one.
        $offline = $this->creator($staff, 'Aardvark Owl');
        $this->publicChannel($offline, 'UCaardvarkowl1234567890ab', 'aardvark-owl');

        $host = $this->creator($staff, 'Zebra Fox');
        $hostChannel = $this->publicChannel($host, 'UCzebrafox1234567890abcde', 'zebrafox');
        $live = $this->liveContent($host, $hostChannel, 'live-collab-2');

        $collaborator = $this->creator($staff, 'Guest Wolf');
        $collaboratorChannel = $this->publicChannel($collaborator, 'UCguestwolf1234567890abc', 'guestwolf');
        $this->contents->attachAppearance($live, $collaboratorChannel, ContentAppearanceRole::Collaborator);

        $items = collect($this->getJson('/api/v1/vtual/public/vtubers')->assertOk()->json('data.items'));

        $this->assertSame(['guest-wolf', 'zebra-fox', 'aardvark-owl'], $items->pluck('slug')->all());
    }

    #[Test]
    public function appearance_on_an_archived_primary_channel_does_not_mark_the_collaborator_live(): void
    {
        $staff = $this->userWithRole('Admin');

        $host = $this->creator($staff, 'Host Fox');
        $archived = Channel::query()->create([
            'id' => (string) Str::uuid7(),
            'creator_id' => $host->id,
            'platform_id' => $this->youtube->id,
            'external_id' => 'UChostarchived1234567890ab',
            'handle' => 'hostarchived',
            'display_name' => 'Host Fox',
            'status' => ChannelStatus::Archived,
            'visibility' => ChannelVisibility::Public,
        ]);
        // Public channel keeps the host listed in the directory.
        $this->publicChannel($host, 'UChostfox1234567890abcde', 'hostfox');
        $live = $this->liveContent($host, $archived, 'live-archived-1');

        $collaborator = $this->creator($staff, 'Guest Wolf');
        $collaboratorChannel = $this->publicChannel($collaborator, 'UCguestwolf1234567890abc', 'guestwolf');
        $this->contents->attachAppearance($live, $collaboratorChannel, ContentAppearanceRole::Collaborator);

        $this->getJson('/api/v1/vtual/public/vtubers/guest-wolf')
            ->assertOk()
            ->assertJsonPath('data.is_live', false);

        $items = collect($this->getJson('/api/v1/vtual/public/vtubers')->assertOk()->json('data.items'))
            ->keyBy('slug');

        $this->assertFalse($items['guest-wolf']['is_live']);
        $this->assertFalse($items['host-fox']['is_live']);
    }

    #[Test]
    public function guest_management_roster_marks_appearance_only_creator_live(): void
    {
        $staff = $this->userWithRole('Admin');
        $management = Management::query()->create([
            'id' => (string) Str::uuid7(),
            'slug' => 'collab-org',
            'name' => 'Collab Org',
            'status' => EntityStatus::Active,
        ]);

        $host = $this->creator($staff, 'Host Fox');
        $hostChannel = $this->publicChannel($host, 'UChostfox1234567890abcde', 'hostfox');
        $live = $this->liveContent($host, $hostChannel, 'live-collab-3');

        $collaborator = $this->creator($staff, 'Guest Wolf');
        $collaboratorChannel = $this->publicChannel($collaborator, 'UCguestwolf1234567890abc', 'guestwolf');
        $this->contents->attachAppearance($live, $collaboratorChannel, ContentAppearanceRole::Collaborator);

        ManagementCreator::query()->create([
            'id' => (string) Str::uuid7(),
            'management_id' => $management->id,
            'creator_id' => $collaborator->id,
            'joined_at' => now(),
        ]);

        $this->getJson('/api/v1/vtual/public/managements/collab-org')
            ->assertOk()
            ->assertJsonCount(1, 'data.creators')
            ->assertJsonPath('data.creators.0.is_live', true)
            ->assertJsonPath('data.stats.live', 1);
    }

    private function creator(User $actor, string $displayName): Creator
    {
        return $this->creators->createOrphan($actor, [
            'display_name' => $displayName,
            'slug' => Str::slug($displayName),
        ]);
    }

    private function publicChannel(Creator $creator, string $externalId, string $handle): Channel
    {
        return Channel::query()->create([
            'id' => (string) Str::uuid7(),
            'creator_id' => $creator->id,
            'platform_id' => $this->youtube->id,
            'external_id' => $externalId,
            'handle' => $handle,
            'display_name' => $creator->display_name,
            'subscriber_count' => 1000,
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
            'name' => $role.' LivePredicate',
            'email' => strtolower($role).'-live-predicate-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole($role);

        return $user;
    }
}
