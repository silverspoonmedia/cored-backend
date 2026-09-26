<?php

namespace Tests\Domain\Vtual\Unit\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\ChannelStatus;
use App\Domain\Vtual\Enums\PlatformKey;
use App\Domain\Vtual\Integrations\Dto\PlatformVideoDto;
use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Models\Content;
use App\Domain\Vtual\Models\Platform;
use App\Domain\Vtual\Services\ContentService;
use App\Domain\Vtual\Services\CreatorService;
use App\Domain\Vtual\Services\PlatformVideoDtoMapper;
use Database\Seeders\VtualRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

/**
 * Region restriction travels DTO → mapper → vtual_contents → public payload.
 *
 * It is a *notice*, never a filter: a restricted video stays visible so the
 * reader can see why it will not play, instead of a row silently vanishing.
 */
class ContentRegionRestrictionTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private ContentService $contents;

    private CreatorService $creators;

    private PlatformVideoDtoMapper $mapper;

    private Platform $youtube;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        $this->seed(VtualRoleSeeder::class);
        $this->contents = app(ContentService::class);
        $this->creators = app(CreatorService::class);
        $this->mapper = app(PlatformVideoDtoMapper::class);
        $this->youtube = Platform::query()->where('key', 'youtube')->firstOrFail();
    }

    #[Test]
    public function mapper_forwards_region_lists_onto_the_upsert_payload(): void
    {
        $dto = new PlatformVideoDto(
            platform: PlatformKey::Youtube,
            externalId: 'vid-region',
            channelExternalId: 'UCabc',
            title: 'Region',
            description: null,
            thumbnailUrl: null,
            url: 'https://youtube.com/watch?v=vid-region',
            categoryId: null,
            status: 'uploaded',
            publishedAt: null,
            startedAt: null,
            endedAt: null,
            durationSeconds: null,
            viewCount: null,
            concurrentViewers: null,
            regionAllowed: ['US', 'JP'],
            regionBlocked: ['RU'],
        );

        $payload = $this->mapper->toUpsertPayload($dto);

        $this->assertSame(['US', 'JP'], $payload['region_allowed']);
        $this->assertSame(['RU'], $payload['region_blocked']);
    }

    #[Test]
    public function upsert_persists_normalized_region_lists(): void
    {
        $channel = $this->seedChannel();

        $content = $this->contents->upsertFromPrimary($channel, [
            'external_id' => 'vid-region',
            'title' => 'Region',
            // Mixed case, duplicate and invalid entries all normalize away.
            'region_allowed' => ['us', 'JP', 'us', 'X', '', 12, null],
            'region_blocked' => ['ru'],
        ]);

        $fresh = $content->fresh();
        $this->assertSame(['US', 'JP'], $fresh->region_allowed);
        $this->assertSame(['RU'], $fresh->region_blocked);
    }

    #[Test]
    public function upsert_treats_empty_or_invalid_lists_as_unrestricted(): void
    {
        $channel = $this->seedChannel();

        $content = $this->contents->upsertFromPrimary($channel, [
            'external_id' => 'vid-unrestricted',
            'title' => 'Unrestricted',
            'region_allowed' => ['x', 'too-long', 5],
            'region_blocked' => [],
        ]);

        $fresh = $content->fresh();
        $this->assertNull($fresh->region_allowed);
        $this->assertNull($fresh->region_blocked);
    }

    #[Test]
    public function rehydrate_clears_a_stale_restriction(): void
    {
        $channel = $this->seedChannel();

        $this->contents->upsertFromPrimary($channel, [
            'external_id' => 'vid-stale',
            'title' => 'Stale',
            'region_blocked' => ['RU'],
        ]);

        // Upstream stopped reporting the restriction: the notice must go too.
        $this->contents->upsertFromPrimary($channel, [
            'external_id' => 'vid-stale',
            'title' => 'Stale',
        ]);

        $row = Content::query()
            ->where('channel_id', $channel->id)
            ->where('external_id', 'vid-stale')
            ->firstOrFail();

        $this->assertNull($row->region_blocked);
    }

    private function seedChannel(): Channel
    {
        $user = $this->userWithRole('User');
        $creator = $this->creators->create($user, ['display_name' => 'Region Creator']);

        return Channel::query()->create([
            'id' => (string) Str::uuid7(),
            'creator_id' => $creator->id,
            'platform_id' => $this->youtube->id,
            'external_id' => 'UC'.substr(md5('region-'.uniqid()), 0, 22),
            'handle' => 'region-creator',
            'display_name' => 'Region Creator',
            'status' => ChannelStatus::Pending,
        ]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::query()->create([
            'name' => $role.' Region',
            'email' => strtolower($role).'-region-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole($role);

        return $user;
    }
}
