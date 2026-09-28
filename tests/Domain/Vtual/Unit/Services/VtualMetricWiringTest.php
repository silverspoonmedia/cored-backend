<?php

namespace Tests\Domain\Vtual\Unit\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\ChannelFetchPoolReason;
use App\Domain\Vtual\Enums\ChannelStatus;
use App\Domain\Vtual\Enums\PlatformCredentialStatus;
use App\Domain\Vtual\Enums\PlatformCredentialType;
use App\Domain\Vtual\Enums\PlatformKey;
use App\Domain\Vtual\Integrations\Dto\PlatformChannelDto;
use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Models\ChannelMetric;
use App\Domain\Vtual\Models\ContentMetric;
use App\Domain\Vtual\Models\Platform;
use App\Domain\Vtual\Services\ChannelActivationService;
use App\Domain\Vtual\Services\ChannelFetchPoolService;
use App\Domain\Vtual\Services\ChannelService;
use App\Domain\Vtual\Services\ContentService;
use App\Domain\Vtual\Services\CreatorService;
use App\Domain\Vtual\Services\PlatformCredentialService;
use App\Domain\Vtual\Services\PlatformService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

/**
 * The metric history is only worth its tables if the writers that already hold a
 * fresh value actually call the recorder. Each test here pins one seam:
 *
 * - channel activation (new channel row with a first snapshot),
 * - pending → active promotion (batch profile resolve),
 * - the channel fetch-pool flush (scheduled resync / refresh),
 * - content hydrate (live CCV from the poll, an EventSub push or a backfill).
 *
 * A missing call is silent by design — the recorder never throws — so nothing
 * else in the suite would catch it.
 */
class VtualMetricWiringTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private Platform $youtube;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        app(PlatformService::class)->seedDefaults();
        $this->youtube = Platform::query()->where('key', 'youtube')->firstOrFail();
        $this->admin = $this->userWithRole('Admin');
        config([
            'vtual.metrics.enabled' => true,
            'vtual.metrics.channel_interval_minutes' => 15,
            'vtual.metrics.content_interval_minutes' => 1,
        ]);

        // Activation hooks reach the WebSub hub; never let a test hit it.
        Http::fake(['pubsubhubbub.appspot.com/*' => Http::response('', 202)]);
    }

    #[Test]
    public function activating_a_channel_writes_the_first_subscriber_sample(): void
    {
        Bus::fake();

        $channel = $this->activateChannel('UCMETRICACT01', subscriberCount: 33000);

        $row = ChannelMetric::query()->sole();
        $this->assertSame((string) $channel->id, $row->channel_id);
        $this->assertSame(33000, $row->subscriber_count);
    }

    #[Test]
    public function promoting_a_pending_channel_writes_a_subscriber_sample(): void
    {
        Bus::fake();

        $creator = app(CreatorService::class)->create($this->userWithRole('User'), ['display_name' => 'Promoted']);
        $channel = Channel::query()->create([
            'creator_id' => $creator->id,
            'platform_id' => $this->youtube->id,
            'external_id' => 'UCMETRICPENDING',
            'handle' => 'pending-metric',
            'display_name' => 'Pending',
            'status' => ChannelStatus::Pending,
        ]);

        $dto = new PlatformChannelDto(
            platform: PlatformKey::Youtube,
            externalId: 'UCMETRICPROMO1',
            handle: 'promoted',
            displayName: 'Promoted',
            description: null,
            thumbnailUrl: null,
            url: null,
            subscriberCount: 12345,
        );

        app(ChannelActivationService::class)->activatePendingFromDto($channel, $this->youtube, $dto);

        $this->assertSame(12345, ChannelMetric::query()->sole()->subscriber_count);
    }

    #[Test]
    public function a_pool_flush_writes_a_subscriber_sample(): void
    {
        Bus::fake();
        $this->activateYoutubeCredential();

        $channel = $this->activateChannel('UCMETRICPOOL01', subscriberCount: 100);
        $pool = app(ChannelFetchPoolService::class);
        $pool->enqueue($this->youtube, 'UCMETRICPOOL01', (string) $channel->id, ChannelFetchPoolReason::Manual);

        // The activation sample already occupies this bucket, so move the clock
        // to prove the flush appends rather than dedupes.
        Carbon::setTestNow(now()->addMinutes(30));

        Http::fake([
            'www.googleapis.com/youtube/v3/channels*' => Http::response([
                'items' => [[
                    'id' => 'UCMETRICPOOL01',
                    'snippet' => ['title' => 'Refreshed', 'customUrl' => '@metric-pool'],
                    'statistics' => ['subscriberCount' => '51500', 'hiddenSubscriberCount' => false],
                ]],
            ], 200),
        ]);

        $pool->flush(PlatformKey::Youtube, force: true);

        $samples = ChannelMetric::query()
            ->where('channel_id', $channel->id)
            ->orderBy('bucket_start')
            ->pluck('subscriber_count')
            ->all();

        // Activation sample + flush sample, in bucket order.
        $this->assertSame([100, 51500], $samples);

        Carbon::setTestNow();
    }

    #[Test]
    public function hydrating_a_live_row_writes_a_ccv_sample(): void
    {
        Bus::fake();
        $channel = $this->activateChannel('UCMETRICLIVE01');
        ChannelMetric::query()->delete();

        app(ContentService::class)->upsertFromPrimary($channel, [
            'external_id' => 'live-metric-1',
            'type' => 'live',
            'status' => 'live',
            'title' => 'Live now',
            'url' => 'https://www.youtube.com/watch?v=live-metric-1',
            'concurrent_viewers' => 4321,
        ]);

        $this->assertSame(4321, ContentMetric::query()->sole()->concurrent_viewers);
    }

    #[Test]
    public function hydrating_a_vod_writes_no_ccv_sample(): void
    {
        Bus::fake();
        $channel = $this->activateChannel('UCMETRICVOD01');
        ChannelMetric::query()->delete();

        app(ContentService::class)->upsertFromPrimary($channel, [
            'external_id' => 'vod-metric-1',
            'type' => 'vod',
            'status' => 'ended',
            'title' => 'A VOD',
            'url' => 'https://www.youtube.com/watch?v=vod-metric-1',
        ]);

        $this->assertSame(0, ContentMetric::query()->count());
    }

    #[Test]
    public function writers_stay_silent_while_history_is_disabled(): void
    {
        Bus::fake();
        $channel = $this->activateChannel('UCMETRICOFF01');
        config(['vtual.metrics.enabled' => false]);
        ChannelMetric::query()->delete();

        app(ContentService::class)->upsertFromPrimary($channel, [
            'external_id' => 'live-metric-off',
            'type' => 'live',
            'status' => 'live',
            'title' => 'Live now',
            'url' => 'https://www.youtube.com/watch?v=live-metric-off',
            'concurrent_viewers' => 10,
        ]);

        $this->assertSame(0, ChannelMetric::query()->count());
        $this->assertSame(0, ContentMetric::query()->count());
    }

    private function activateChannel(string $externalId, int $subscriberCount = 1000): Channel
    {
        $creator = app(CreatorService::class)->create($this->userWithRole('User'), [
            'display_name' => 'Metric '.$externalId,
        ]);

        return app(ChannelService::class)->activateFromDto(
            $creator->id,
            $this->youtube,
            new PlatformChannelDto(
                platform: PlatformKey::Youtube,
                externalId: $externalId,
                handle: 'metric-'.strtolower($externalId),
                displayName: 'Metric '.$externalId,
                description: null,
                thumbnailUrl: null,
                url: null,
                subscriberCount: $subscriberCount,
            ),
        );
    }

    private function activateYoutubeCredential(): void
    {
        $credential = app(PlatformCredentialService::class)->create($this->admin, $this->youtube, [
            'label' => 'yt-metric-test',
            'type' => PlatformCredentialType::ApiKey->value,
            'secret' => ['api_key' => 'yt-metric-key'],
        ]);
        $credential->forceFill([
            'status' => PlatformCredentialStatus::Active,
            'verified_at' => now(),
        ])->save();
    }

    private function userWithRole(string $role): User
    {
        $user = User::query()->create([
            'name' => $role.' Metric',
            'email' => strtolower($role).'-metric-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole($role);

        return $user;
    }
}
