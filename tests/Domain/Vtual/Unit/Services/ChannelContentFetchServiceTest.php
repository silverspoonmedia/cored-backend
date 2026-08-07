<?php

namespace Tests\Domain\Vtual\Unit\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\ChannelStatus;
use App\Domain\Vtual\Enums\ContentAppearanceRole;
use App\Domain\Vtual\Enums\ContentStatus;
use App\Domain\Vtual\Enums\ContentType;
use App\Domain\Vtual\Enums\PlatformKey;
use App\Domain\Vtual\Exceptions\VtualException;
use App\Domain\Vtual\Integrations\Dto\PlatformVideoDto;
use App\Domain\Vtual\Integrations\PlatformApiGateway;
use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Models\ContentAppearance;
use App\Domain\Vtual\Models\Platform;
use App\Domain\Vtual\Services\ChannelContentFetchService;
use App\Domain\Vtual\Services\CreatorService;
use Database\Seeders\VtualRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class ChannelContentFetchServiceTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private ChannelContentFetchService $fetch;

    private CreatorService $creators;

    private Platform $youtube;

    private Platform $twitch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        $this->seed(VtualRoleSeeder::class);
        $this->creators = app(CreatorService::class);
        $this->youtube = Platform::query()->where('key', 'youtube')->firstOrFail();
        $this->twitch = Platform::query()->where('key', 'twitch')->firstOrFail();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function fetch_and_persist_lists_hydrates_and_upserts_contents(): void
    {
        $channel = $this->seedActiveYoutubeChannel('UCPkKpOHxEDcwmUAnRpIu-Ng');

        $gateway = Mockery::mock(PlatformApiGateway::class);
        $gateway->shouldReceive('listChannelVideos')
            ->once()
            ->with(PlatformKey::Youtube, 'UCPkKpOHxEDcwmUAnRpIu-Ng', 50)
            ->andReturn(collect(['vid1', 'vid2']));
        $gateway->shouldReceive('fetchVideos')
            ->once()
            ->with(PlatformKey::Youtube, ['vid1', 'vid2'])
            ->andReturn(collect([
                $this->videoDto('vid1', 'First'),
                $this->videoDto('vid2', 'Second'),
            ]));

        $this->fetch = app(ChannelContentFetchService::class, ['gateway' => $gateway]);

        $summary = $this->fetch->fetchAndPersist($channel, 50);

        $this->assertSame(['listed' => 2, 'fetched' => 2, 'upserted' => 2], $summary);
        $this->assertDatabaseCount('vtual_contents', 2);
        $this->assertDatabaseCount('vtual_content_appearances', 2);
        $this->assertDatabaseHas('vtual_contents', [
            'channel_id' => $channel->id,
            'external_id' => 'vid1',
            'title' => 'First',
            'type' => ContentType::Vod->value,
            'status' => ContentStatus::Uploaded->value,
        ]);

        $appearance = ContentAppearance::query()
            ->where('channel_id', $channel->id)
            ->where('role', ContentAppearanceRole::Primary)
            ->count();
        $this->assertSame(2, $appearance);
    }

    #[Test]
    public function fetch_rejects_non_youtube_platform(): void
    {
        $channel = $this->seedActiveYoutubeChannel('UCPkKpOHxEDcwmUAnRpIu-Ng');
        $channel->forceFill(['platform_id' => $this->twitch->id])->save();

        $gateway = Mockery::mock(PlatformApiGateway::class);
        $this->fetch = app(ChannelContentFetchService::class, ['gateway' => $gateway]);

        $this->expectException(VtualException::class);
        $this->fetch->fetchAndPersist($channel->fresh('platform'));
    }

    #[Test]
    public function fetch_rejects_pending_channel(): void
    {
        $channel = $this->seedActiveYoutubeChannel('UCPkKpOHxEDcwmUAnRpIu-Ng');
        $channel->forceFill(['status' => ChannelStatus::Pending])->save();

        $gateway = Mockery::mock(PlatformApiGateway::class);
        $this->fetch = app(ChannelContentFetchService::class, ['gateway' => $gateway]);

        $this->expectException(VtualException::class);
        $this->fetch->fetchAndPersist($channel->fresh('platform'));
    }

    #[Test]
    public function fetch_videos_and_persist_stores_live_content_with_concurrent_viewers(): void
    {
        $channel = $this->seedActiveYoutubeChannel('UCPkKpOHxEDcwmUAnRpIu-Ng');

        $gateway = Mockery::mock(PlatformApiGateway::class);
        $gateway->shouldReceive('fetchVideos')
            ->once()
            ->with(PlatformKey::Youtube, ['VgdAA0Gdzn0'])
            ->andReturn(collect([
                $this->videoDto('VgdAA0Gdzn0', 'Live Now', 'live', 1234),
            ]));

        $this->fetch = app(ChannelContentFetchService::class, ['gateway' => $gateway]);

        $summary = $this->fetch->fetchVideosAndPersist($channel, ['VgdAA0Gdzn0']);

        $this->assertSame(['requested' => 1, 'fetched' => 1, 'upserted' => 1], $summary);
        $this->assertDatabaseHas('vtual_contents', [
            'channel_id' => $channel->id,
            'external_id' => 'VgdAA0Gdzn0',
            'title' => 'Live Now',
            'type' => ContentType::Live->value,
            'status' => ContentStatus::Live->value,
            'concurrent_viewers' => 1234,
        ]);
    }

    #[Test]
    public function fetch_videos_and_persist_rejects_video_from_other_channel(): void
    {
        $channel = $this->seedActiveYoutubeChannel('UCPkKpOHxEDcwmUAnRpIu-Ng');

        $gateway = Mockery::mock(PlatformApiGateway::class);
        $gateway->shouldReceive('fetchVideos')
            ->once()
            ->andReturn(collect([
                $this->videoDto('other-vid', 'Wrong Channel', 'live', null, 'UCotherchannel000000000000'),
            ]));

        $this->fetch = app(ChannelContentFetchService::class, ['gateway' => $gateway]);

        $this->expectException(VtualException::class);
        $this->fetch->fetchVideosAndPersist($channel, ['other-vid']);
    }

    #[Test]
    public function discover_live_video_ids_returns_search_results(): void
    {
        $channel = $this->seedActiveYoutubeChannel('UCPkKpOHxEDcwmUAnRpIu-Ng');

        $gateway = Mockery::mock(PlatformApiGateway::class);
        $gateway->shouldReceive('searchChannelVideos')
            ->once()
            ->with(PlatformKey::Youtube, 'UCPkKpOHxEDcwmUAnRpIu-Ng', 'live', 5)
            ->andReturn(collect(['VgdAA0Gdzn0']));

        $this->fetch = app(ChannelContentFetchService::class, ['gateway' => $gateway]);

        $this->assertSame(['VgdAA0Gdzn0'], $this->fetch->discoverLiveVideoIds($channel));
    }

    #[Test]
    public function fetch_returns_zero_counts_when_playlist_empty(): void
    {
        $channel = $this->seedActiveYoutubeChannel('UCPkKpOHxEDcwmUAnRpIu-Ng');

        $gateway = Mockery::mock(PlatformApiGateway::class);
        $gateway->shouldReceive('listChannelVideos')
            ->once()
            ->andReturn(Collection::make());
        $gateway->shouldNotReceive('fetchVideos');

        $this->fetch = app(ChannelContentFetchService::class, ['gateway' => $gateway]);

        $summary = $this->fetch->fetchAndPersist($channel);

        $this->assertSame(['listed' => 0, 'fetched' => 0, 'upserted' => 0], $summary);
        $this->assertDatabaseCount('vtual_contents', 0);
    }

    private function seedActiveYoutubeChannel(string $externalId): Channel
    {
        $user = $this->userWithRole('User');
        $creator = $this->creators->create($user, ['display_name' => 'Fetch Creator']);

        return Channel::query()->create([
            'id' => (string) Str::uuid7(),
            'creator_id' => $creator->id,
            'platform_id' => $this->youtube->id,
            'external_id' => $externalId,
            'handle' => 'aizawaema',
            'display_name' => 'Aizawa Ema',
            'url' => 'https://www.youtube.com/channel/'.$externalId,
            'status' => ChannelStatus::Active,
        ]);
    }

    private function videoDto(
        string $id,
        string $title,
        string $status = 'uploaded',
        ?int $concurrentViewers = null,
        string $channelExternalId = 'UCPkKpOHxEDcwmUAnRpIu-Ng',
    ): PlatformVideoDto {
        return new PlatformVideoDto(
            platform: PlatformKey::Youtube,
            externalId: $id,
            channelExternalId: $channelExternalId,
            title: $title,
            description: null,
            thumbnailUrl: 'https://img.example/'.$id.'.jpg',
            url: 'https://www.youtube.com/watch?v='.$id,
            status: $status,
            publishedAt: Carbon::parse('2026-01-01T00:00:00Z'),
            startedAt: Carbon::parse('2026-01-01T00:00:00Z'),
            endedAt: null,
            durationSeconds: 60,
            viewCount: 1,
            concurrentViewers: $concurrentViewers,
        );
    }

    private function userWithRole(string $role): User
    {
        $user = User::query()->create([
            'name' => $role.' Fetch',
            'email' => strtolower($role).'-fetch-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole($role);

        return $user;
    }
}
