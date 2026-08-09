<?php

namespace Tests\Domain\Vtual\Unit\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\ChannelStatus;
use App\Domain\Vtual\Enums\ContentFetchPoolStatus;
use App\Domain\Vtual\Enums\ContentStatus;
use App\Domain\Vtual\Enums\ContentType;
use App\Domain\Vtual\Enums\PlatformKey;
use App\Domain\Vtual\Integrations\Dto\PlatformVideoDto;
use App\Domain\Vtual\Integrations\PlatformApiGateway;
use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Models\Content;
use App\Domain\Vtual\Models\ContentFetchPoolItem;
use App\Domain\Vtual\Models\Platform;
use App\Domain\Vtual\Services\ChannelContentFetchService;
use App\Domain\Vtual\Services\ContentFetchPoolService;
use App\Domain\Vtual\Services\CreatorService;
use App\Domain\Vtual\Services\LiveContentPollService;
use App\Domain\Vtual\Services\PlatformVideoDtoMapper;
use App\Domain\Vtual\Services\ContentService;
use Database\Seeders\VtualRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class ContentIngestionPoolTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private Platform $youtube;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        $this->seed(VtualRoleSeeder::class);
        $this->youtube = Platform::query()->where('key', 'youtube')->firstOrFail();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function content_pool_flush_hydrates_pending_items(): void
    {
        $channel = $this->seedChannel();

        $fetch = Mockery::mock(ChannelContentFetchService::class);
        $fetch->shouldReceive('fetchVideosAndPersist')
            ->once()
            ->with(Mockery::type(Channel::class), ['VgdAA0Gdzn0'])
            ->andReturn(['requested' => 1, 'fetched' => 1, 'upserted' => 1]);

        $pool = app(ContentFetchPoolService::class, ['contentFetch' => $fetch]);
        $pool->enqueue($channel, 'VgdAA0Gdzn0');

        $summary = $pool->flush(PlatformKey::Youtube, force: true);

        $this->assertSame('flushed', $summary['platforms']['youtube']['status']);
        $this->assertDatabaseHas('vtual_content_fetch_pool', [
            'video_external_id' => 'VgdAA0Gdzn0',
            'status' => ContentFetchPoolStatus::Done->value,
        ]);
    }

    #[Test]
    public function content_pool_flush_upserts_stubs_for_archived_channel(): void
    {
        $channel = $this->seedChannel();
        $channel->forceFill(['status' => ChannelStatus::Archived])->save();

        $fetch = Mockery::mock(ChannelContentFetchService::class);
        $fetch->shouldNotReceive('fetchVideosAndPersist');

        $pool = app(ContentFetchPoolService::class, ['contentFetch' => $fetch]);
        $pool->enqueue($channel, 'VgdAA0Gdzn0');

        $summary = $pool->flush(PlatformKey::Youtube, force: true);

        $this->assertSame('flushed', $summary['platforms']['youtube']['status']);
        $this->assertDatabaseHas('vtual_contents', [
            'external_id' => 'VgdAA0Gdzn0',
            'channel_id' => $channel->id,
            'hydrated_at' => null,
        ]);
        $this->assertDatabaseHas('vtual_content_fetch_pool', [
            'video_external_id' => 'VgdAA0Gdzn0',
            'status' => ContentFetchPoolStatus::Done->value,
        ]);
    }

    #[Test]
    public function live_poll_marks_ended_stream_as_archive(): void
    {
        $channel = $this->seedChannel();

        Content::query()->create([
            'id' => (string) Str::uuid7(),
            'creator_id' => $channel->creator_id,
            'channel_id' => $channel->id,
            'platform_id' => $this->youtube->id,
            'external_id' => 'VgdAA0Gdzn0',
            'type' => ContentType::Live,
            'status' => ContentStatus::Live,
            'title' => 'Was Live',
        ]);

        $gateway = Mockery::mock(PlatformApiGateway::class);
        $gateway->shouldReceive('fetchVideos')
            ->once()
            ->andReturn(collect([
                new PlatformVideoDto(
                    platform: PlatformKey::Youtube,
                    externalId: 'VgdAA0Gdzn0',
                    channelExternalId: 'UCPkKpOHxEDcwmUAnRpIu-Ng',
                    title: 'Ended Stream',
                    description: null,
                    thumbnailUrl: null,
                    url: 'https://www.youtube.com/watch?v=VgdAA0Gdzn0',
                    status: 'ended',
                    publishedAt: Carbon::parse('2026-08-06T08:00:00Z'),
                    startedAt: Carbon::parse('2026-08-06T08:00:00Z'),
                    endedAt: Carbon::parse('2026-08-06T10:00:00Z'),
                    durationSeconds: 7200,
                    viewCount: 100,
                    concurrentViewers: null,
                ),
            ]));

        $poll = new LiveContentPollService(
            $gateway,
            app(PlatformVideoDtoMapper::class),
            app(ContentService::class),
        );

        $summary = $poll->poll();

        $this->assertSame(1, $summary['ended']);
        $this->assertDatabaseHas('vtual_contents', [
            'external_id' => 'VgdAA0Gdzn0',
            'type' => ContentType::Archive->value,
            'status' => ContentStatus::Ended->value,
        ]);
    }

    private function seedChannel(): Channel
    {
        $user = User::query()->create([
            'name' => 'Pool User',
            'email' => 'pool-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole('Admin');
        $creator = app(CreatorService::class)->createOrphan($user, ['display_name' => 'Pool Fox']);

        return Channel::query()->create([
            'id' => (string) Str::uuid7(),
            'creator_id' => $creator->id,
            'platform_id' => $this->youtube->id,
            'external_id' => 'UCPkKpOHxEDcwmUAnRpIu-Ng',
            'handle' => 'poolfox',
            'display_name' => 'Pool Fox',
            'status' => ChannelStatus::Active,
        ]);
    }
}
