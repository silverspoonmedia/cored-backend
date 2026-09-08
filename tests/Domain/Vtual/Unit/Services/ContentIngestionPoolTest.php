<?php

namespace Tests\Domain\Vtual\Unit\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\ChannelStatus;
use App\Domain\Vtual\Enums\ContentFetchPoolStatus;
use App\Domain\Vtual\Enums\ContentStatus;
use App\Domain\Vtual\Enums\ContentType;
use App\Domain\Vtual\Enums\PlatformKey;
use App\Domain\Vtual\Integrations\Dto\PlatformStreamDto;
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
use App\Domain\Vtual\Services\TwitchLiveStateService;
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
            app(TwitchLiveStateService::class),
        );

        $summary = $poll->poll();

        $this->assertSame(1, $summary['ended']);
        $this->assertDatabaseHas('vtual_contents', [
            'external_id' => 'VgdAA0Gdzn0',
            'type' => ContentType::Archive->value,
            'status' => ContentStatus::Ended->value,
        ]);
    }

    #[Test]
    public function live_poll_twitch_branch_refreshes_live_and_ends_missing(): void
    {
        $liveChannel = $this->seedTwitchChannel('40934651');
        $deadChannel = $this->seedTwitchChannel('40934652');

        foreach ([$liveChannel, $deadChannel] as $channel) {
            Content::query()->create([
                'id' => (string) Str::uuid7(),
                'creator_id' => $channel->creator_id,
                'channel_id' => $channel->id,
                'platform_id' => $channel->platform_id,
                'external_id' => 'stream-'.$channel->external_id,
                'type' => ContentType::Live,
                'status' => ContentStatus::Live,
                'title' => 'Was Live',
            ]);
        }

        $gateway = Mockery::mock(PlatformApiGateway::class);
        $gateway->shouldReceive('fetchStreamsByUserIds')
            ->once()
            ->with(PlatformKey::Twitch, ['40934651', '40934652'])
            ->andReturn(collect([
                new PlatformStreamDto(
                    platform: PlatformKey::Twitch,
                    externalId: 'stream-session-777',
                    userExternalId: '40934651',
                    userLogin: 'stilllive',
                    userName: 'Still Live',
                    title: 'Stream title v2',
                    gameName: 'Games',
                    thumbnailUrl: null,
                    url: 'https://www.twitch.tv/stilllive',
                    concurrentViewers: 555,
                    startedAt: Carbon::parse('2026-09-07T00:00:00Z'),
                    language: 'en',
                ),
            ]));

        $poll = new LiveContentPollService(
            $gateway,
            app(PlatformVideoDtoMapper::class),
            app(ContentService::class),
            app(TwitchLiveStateService::class),
        );

        $summary = $poll->poll();

        // Twitch branch: 2 polled, 1 refreshed, 1 ended.
        $this->assertSame(2, $summary['polled']);
        $this->assertSame(1, $summary['updated']);
        $this->assertSame(1, $summary['ended']);

        // Live stream got a fresh row under the new session id.
        $this->assertDatabaseHas('vtual_contents', [
            'channel_id' => $liveChannel->id,
            'external_id' => 'stream-session-777',
            'type' => ContentType::Live->value,
            'status' => ContentStatus::Live->value,
            'title' => 'Stream title v2',
            'concurrent_viewers' => 555,
        ]);

        // Missing stream ended.
        $this->assertDatabaseHas('vtual_contents', [
            'channel_id' => $deadChannel->id,
            'external_id' => 'stream-40934652',
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

    private function seedTwitchChannel(string $externalId): Channel
    {
        $twitch = Platform::query()->where('key', 'twitch')->firstOrFail();
        $user = User::query()->create([
            'name' => 'Twitch Pool User',
            'email' => 'twitch-pool-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole('Admin');
        $creator = app(CreatorService::class)->createOrphan($user, ['display_name' => 'Twitch Pool Fox']);

        return Channel::query()->create([
            'id' => (string) Str::uuid7(),
            'creator_id' => $creator->id,
            'platform_id' => $twitch->id,
            'external_id' => $externalId,
            'display_name' => 'Twitch Pool Fox',
            'status' => ChannelStatus::Active,
        ]);
    }
}
