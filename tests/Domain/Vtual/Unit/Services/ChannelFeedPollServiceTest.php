<?php

namespace Tests\Domain\Vtual\Unit\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\ChannelStatus;
use App\Domain\Vtual\Enums\ContentFetchPoolSource;
use App\Domain\Vtual\Integrations\Youtube\YoutubeChannelVideoFeedClient;
use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Integrations\Dto\YoutubeFeedVideoEntryDto;
use App\Domain\Vtual\Models\Platform;
use App\Domain\Vtual\Services\ChannelFeedPollService;
use App\Domain\Vtual\Services\ContentFetchPoolService;
use App\Domain\Vtual\Services\CreatorService;
use Database\Seeders\VtualRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class ChannelFeedPollServiceTest extends TestCase
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
    public function poll_channel_enqueues_entries_newer_than_watermark(): void
    {
        $channel = $this->seedChannel();
        $channel->forceFill([
            'content_initial_backfill_at' => now(),
            'feed_last_entry_updated_at' => Carbon::parse('2026-08-06T08:00:00Z'),
        ])->save();

        $feed = Mockery::mock(YoutubeChannelVideoFeedClient::class);
        $feed->shouldReceive('listVideos')
            ->once()
            ->andReturn(collect([
                new YoutubeFeedVideoEntryDto('VgdAA0Gdzn0', null, Carbon::parse('2026-08-06T08:30:00Z'), 'Live', null),
                new YoutubeFeedVideoEntryDto('olderVideoId12', null, Carbon::parse('2026-08-01T09:00:00Z'), 'Old', null),
            ]));

        $pool = Mockery::mock(ContentFetchPoolService::class);
        $pool->shouldReceive('enqueue')
            ->once()
            ->with(Mockery::type(Channel::class), 'VgdAA0Gdzn0', ContentFetchPoolSource::FeedPoll);

        $contents = Mockery::mock(\App\Domain\Vtual\Services\ContentService::class);
        $contents->shouldNotReceive('upsertStubFromPrimary');

        $service = new ChannelFeedPollService($feed, $pool, $contents);
        $result = $service->pollChannel($channel->fresh(), force: false);

        $this->assertSame(1, $result['enqueued']);
        $this->assertSame(1, $result['skipped']);
        $this->assertSame(
            '2026-08-06T08:30:00+00:00',
            $channel->fresh()->feed_last_entry_updated_at?->toIso8601String(),
        );
    }

    #[Test]
    public function first_poll_enqueues_all_entries(): void
    {
        $channel = $this->seedChannel();

        $feed = Mockery::mock(YoutubeChannelVideoFeedClient::class);
        $feed->shouldReceive('listVideos')->once()->andReturn(collect([
            new YoutubeFeedVideoEntryDto('vid-a', null, Carbon::parse('2026-08-06T08:30:00Z'), 'A', null),
            new YoutubeFeedVideoEntryDto('vid-b', null, Carbon::parse('2026-08-06T07:30:00Z'), 'B', null),
        ]));

        $pool = Mockery::mock(ContentFetchPoolService::class);
        $pool->shouldReceive('enqueue')->twice();

        $contents = Mockery::mock(\App\Domain\Vtual\Services\ContentService::class);
        $contents->shouldNotReceive('upsertStubFromPrimary');

        $service = new ChannelFeedPollService($feed, $pool, $contents);
        $result = $service->pollChannel($channel, force: true);

        $this->assertSame(2, $result['enqueued']);
    }

    #[Test]
    public function poll_channel_skips_when_initial_backfill_not_complete(): void
    {
        $channel = $this->seedChannel();

        $feed = Mockery::mock(YoutubeChannelVideoFeedClient::class);
        $feed->shouldNotReceive('listVideos');

        $pool = Mockery::mock(ContentFetchPoolService::class);
        $pool->shouldNotReceive('enqueue');

        $contents = Mockery::mock(\App\Domain\Vtual\Services\ContentService::class);
        $contents->shouldNotReceive('upsertStubFromPrimary');

        $service = new ChannelFeedPollService($feed, $pool, $contents);
        $result = $service->pollChannel($channel, force: false);

        $this->assertSame(0, $result['enqueued']);
        $this->assertSame(0, $result['skipped']);
    }

    #[Test]
    public function poll_archived_channel_upserts_stubs_not_pool(): void
    {
        $channel = $this->seedChannel();
        $channel->forceFill([
            'status' => ChannelStatus::Archived,
            'content_initial_backfill_at' => now(),
            'feed_last_entry_updated_at' => Carbon::parse('2026-08-06T08:00:00Z'),
        ])->save();

        $feed = Mockery::mock(YoutubeChannelVideoFeedClient::class);
        $feed->shouldReceive('listVideos')
            ->once()
            ->andReturn(collect([
                new YoutubeFeedVideoEntryDto(
                    'VgdAA0Gdzn0',
                    Carbon::parse('2026-08-06T08:00:00Z'),
                    Carbon::parse('2026-08-06T08:30:00Z'),
                    'Archived Live',
                    'https://www.youtube.com/watch?v=VgdAA0Gdzn0',
                ),
            ]));

        $pool = Mockery::mock(ContentFetchPoolService::class);
        $pool->shouldNotReceive('enqueue');

        $contents = Mockery::mock(\App\Domain\Vtual\Services\ContentService::class);
        $contents->shouldReceive('upsertStubFromPrimary')
            ->once()
            ->with(Mockery::type(Channel::class), Mockery::on(function (array $data): bool {
                return $data['external_id'] === 'VgdAA0Gdzn0'
                    && $data['title'] === 'Archived Live';
            }));

        $service = new ChannelFeedPollService($feed, $pool, $contents);
        $result = $service->pollChannel($channel->fresh(), force: false);

        $this->assertSame(1, $result['enqueued']);
        $this->assertSame(0, $result['skipped']);
    }

    #[Test]
    public function poll_due_channels_includes_archived_with_backfill(): void
    {
        $channel = $this->seedChannel();
        $channel->forceFill([
            'status' => ChannelStatus::Archived,
            'content_initial_backfill_at' => now(),
        ])->save();

        $feed = Mockery::mock(YoutubeChannelVideoFeedClient::class);
        $feed->shouldReceive('listVideos')->once()->andReturn(collect());

        $pool = Mockery::mock(ContentFetchPoolService::class);
        $pool->shouldNotReceive('enqueue');

        $contents = Mockery::mock(\App\Domain\Vtual\Services\ContentService::class);
        $contents->shouldNotReceive('upsertStubFromPrimary');

        $service = new ChannelFeedPollService($feed, $pool, $contents);
        $summary = $service->pollDueChannels(force: true);

        $this->assertSame(1, $summary['channels']);
        $this->assertSame(0, $summary['enqueued']);
    }

    #[Test]
    public function poll_due_channels_skips_channels_without_initial_backfill(): void
    {
        $this->seedChannel();

        $feed = Mockery::mock(YoutubeChannelVideoFeedClient::class);
        $feed->shouldNotReceive('listVideos');

        $pool = Mockery::mock(ContentFetchPoolService::class);
        $pool->shouldNotReceive('enqueue');

        $contents = Mockery::mock(\App\Domain\Vtual\Services\ContentService::class);
        $contents->shouldNotReceive('upsertStubFromPrimary');

        $service = new ChannelFeedPollService($feed, $pool, $contents);
        $summary = $service->pollDueChannels(force: true);

        $this->assertSame(0, $summary['channels']);
        $this->assertSame(0, $summary['enqueued']);
    }

    private function seedChannel(): Channel
    {
        $user = User::query()->create([
            'name' => 'Feed Poll',
            'email' => 'feed-poll-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole('Admin');
        $creator = $this->creators->createOrphan($user, ['display_name' => 'Feed Fox']);

        return Channel::query()->create([
            'id' => (string) Str::uuid7(),
            'creator_id' => $creator->id,
            'platform_id' => $this->youtube->id,
            'external_id' => 'UCPkKpOHxEDcwmUAnRpIu-Ng',
            'handle' => 'feedfox',
            'display_name' => 'Feed Fox',
            'status' => ChannelStatus::Active,
        ]);
    }
}
