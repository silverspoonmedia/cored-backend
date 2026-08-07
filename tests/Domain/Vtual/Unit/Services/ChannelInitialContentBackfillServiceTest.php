<?php

namespace Tests\Domain\Vtual\Unit\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\ChannelStatus;
use App\Domain\Vtual\Integrations\Dto\YoutubeFeedVideoEntryDto;
use App\Domain\Vtual\Integrations\Youtube\YoutubeChannelVideoFeedClient;
use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Models\Platform;
use App\Domain\Vtual\Services\ChannelContentFetchService;
use App\Domain\Vtual\Services\ChannelInitialContentBackfillService;
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

class ChannelInitialContentBackfillServiceTest extends TestCase
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
    public function run_skips_when_already_backfilled(): void
    {
        $channel = $this->seedChannel();
        $channel->forceFill([
            'content_initial_backfill_at' => now(),
        ])->save();

        $contentFetch = Mockery::mock(ChannelContentFetchService::class);
        $contentFetch->shouldNotReceive('fetchAndPersist');

        $feed = Mockery::mock(YoutubeChannelVideoFeedClient::class);
        $feed->shouldNotReceive('listVideos');

        $service = new ChannelInitialContentBackfillService($contentFetch, $feed);
        $result = $service->run($channel);

        $this->assertSame('skipped', $result['status']);
        $this->assertSame('already_backfilled', $result['reason']);
    }

    #[Test]
    public function run_fetches_content_and_sets_feed_watermark(): void
    {
        $channel = $this->seedChannel();

        $contentFetch = Mockery::mock(ChannelContentFetchService::class);
        $contentFetch->shouldReceive('fetchAndPersist')
            ->once()
            ->with(Mockery::type(Channel::class), 50)
            ->andReturn(['listed' => 2, 'fetched' => 2, 'upserted' => 2]);

        $feed = Mockery::mock(YoutubeChannelVideoFeedClient::class);
        $feed->shouldReceive('listVideos')
            ->once()
            ->with('UCPkKpOHxEDcwmUAnRpIu-Ng')
            ->andReturn(collect([
                new YoutubeFeedVideoEntryDto('vid-a', null, Carbon::parse('2026-08-06T08:30:00Z'), 'A', null),
                new YoutubeFeedVideoEntryDto('vid-b', null, Carbon::parse('2026-08-06T07:30:00Z'), 'B', null),
            ]));

        $service = new ChannelInitialContentBackfillService($contentFetch, $feed);
        $result = $service->run($channel);

        $this->assertSame('completed', $result['status']);
        $this->assertSame(2, $result['upserted']);

        $fresh = $channel->fresh();
        $this->assertNotNull($fresh->content_initial_backfill_at);
        $this->assertNotNull($fresh->feed_last_polled_at);
        $this->assertSame(
            '2026-08-06T08:30:00+00:00',
            $fresh->feed_last_entry_updated_at?->toIso8601String(),
        );
    }

    #[Test]
    public function run_does_not_mark_complete_when_fetch_fails(): void
    {
        $channel = $this->seedChannel();

        $contentFetch = Mockery::mock(ChannelContentFetchService::class);
        $contentFetch->shouldReceive('fetchAndPersist')
            ->once()
            ->andThrow(new \RuntimeException('quota exceeded'));

        $feed = Mockery::mock(YoutubeChannelVideoFeedClient::class);
        $feed->shouldNotReceive('listVideos');

        $service = new ChannelInitialContentBackfillService($contentFetch, $feed);
        $result = $service->run($channel);

        $this->assertSame('failed', $result['status']);
        $this->assertNull($channel->fresh()->content_initial_backfill_at);
    }

    private function seedChannel(): Channel
    {
        $user = User::query()->create([
            'name' => 'Backfill User',
            'email' => 'backfill-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole('Admin');
        $creator = $this->creators->createOrphan($user, ['display_name' => 'Backfill Fox']);

        return Channel::query()->create([
            'id' => (string) Str::uuid7(),
            'creator_id' => $creator->id,
            'platform_id' => $this->youtube->id,
            'external_id' => 'UCPkKpOHxEDcwmUAnRpIu-Ng',
            'handle' => 'backfillfox',
            'display_name' => 'Backfill Fox',
            'status' => ChannelStatus::Active,
        ]);
    }
}
