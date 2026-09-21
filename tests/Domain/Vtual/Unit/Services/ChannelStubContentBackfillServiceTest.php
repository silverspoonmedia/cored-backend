<?php

namespace Tests\Domain\Vtual\Unit\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\ChannelStatus;
use App\Domain\Vtual\Enums\ContentStatus;
use App\Domain\Vtual\Enums\ContentType;
use App\Domain\Vtual\Integrations\Dto\PlatformVideoDto;
use App\Domain\Vtual\Enums\PlatformKey;
use App\Domain\Vtual\Integrations\PlatformApiGateway;
use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Models\Content;
use App\Domain\Vtual\Models\Platform;
use App\Domain\Vtual\Services\ChannelContentFetchService;
use App\Domain\Vtual\Services\ChannelStubContentBackfillService;
use App\Domain\Vtual\Services\ContentService;
use App\Domain\Vtual\Services\CreatorService;
use App\Domain\Vtual\Services\PlatformVideoDtoMapper;
use Database\Seeders\VtualRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class ChannelStubContentBackfillServiceTest extends TestCase
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

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function run_skips_when_no_stubs(): void
    {
        $channel = $this->seedChannel();

        $fetch = Mockery::mock(ChannelContentFetchService::class);
        $fetch->shouldNotReceive('fetchVideosAndPersist');

        $service = new ChannelStubContentBackfillService($fetch);
        $result = $service->run($channel);

        $this->assertSame('skipped', $result['status']);
        $this->assertSame('no_stubs', $result['reason']);
    }

    #[Test]
    public function run_skips_when_channel_not_active(): void
    {
        $channel = $this->seedChannel();
        $channel->forceFill(['status' => ChannelStatus::Archived])->save();
        $this->seedStub($channel, 'stub-vid-1');

        $fetch = Mockery::mock(ChannelContentFetchService::class);
        $fetch->shouldNotReceive('fetchVideosAndPersist');

        $service = new ChannelStubContentBackfillService($fetch);
        $result = $service->run($channel);

        $this->assertSame('skipped', $result['status']);
        $this->assertSame('channel_not_active', $result['reason']);
    }

    #[Test]
    public function run_hydrates_stub_rows_via_api_batch(): void
    {
        $channel = $this->seedChannel();
        $this->seedStub($channel, 'stub-vid-hydrate');

        $gateway = Mockery::mock(PlatformApiGateway::class);
        $gateway->shouldReceive('fetchVideos')
            ->once()
            ->with(PlatformKey::Youtube, ['stub-vid-hydrate'])
            ->andReturn(collect([
                new PlatformVideoDto(
                    platform: PlatformKey::Youtube,
                    externalId: 'stub-vid-hydrate',
                    channelExternalId: 'UCstubbackfill00001',
                    title: 'Fully Hydrated Title',
                    description: 'desc',
                    thumbnailUrl: 'https://img.example/thumb.jpg',
                    url: 'https://www.youtube.com/watch?v=stub-vid-hydrate',
                    status: 'uploaded',
                    publishedAt: Carbon::parse('2026-08-06T08:00:00Z'),
                    startedAt: null,
                    endedAt: null,
                    durationSeconds: 600,
                    viewCount: 42,
                    concurrentViewers: null,
                    categoryId: '10',
                ),
            ]));

        $contentFetch = new ChannelContentFetchService(
            $gateway,
            app(ContentService::class),
            app(PlatformVideoDtoMapper::class),
        );

        $service = new ChannelStubContentBackfillService($contentFetch);
        $result = $service->run($channel);

        $this->assertSame('completed', $result['status']);
        $this->assertSame(1, $result['stubs']);
        $this->assertSame(1, $result['upserted']);

        $content = Content::query()
            ->where('channel_id', $channel->id)
            ->where('external_id', 'stub-vid-hydrate')
            ->first();

        $this->assertNotNull($content);
        $this->assertNotNull($content->hydrated_at);
        $this->assertSame('Fully Hydrated Title', $content->title);
        $this->assertSame(ContentStatus::Uploaded, $content->status);
        $this->assertSame(ContentType::Vod, $content->type);
    }

    private function seedChannel(): Channel
    {
        $user = User::query()->create([
            'name' => 'Stub Backfill',
            'email' => 'stub-backfill-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole('Admin');
        $creator = $this->creators->createOrphan($user, ['display_name' => 'Stub Fox']);

        return Channel::query()->create([
            'id' => (string) Str::uuid7(),
            'creator_id' => $creator->id,
            'platform_id' => $this->youtube->id,
            'external_id' => 'UCstubbackfill00001',
            'handle' => 'stubfox',
            'display_name' => 'Stub Fox',
            'status' => ChannelStatus::Active,
        ]);
    }

    private function seedStub(Channel $channel, string $externalId): Content
    {
        return app(ContentService::class)->upsertStubFromPrimary($channel, [
            'external_id' => $externalId,
            'title' => 'Stub title',
        ]);
    }
}
