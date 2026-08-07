<?php

namespace Tests\Domain\Vtual\Unit\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\ChannelStatus;
use App\Domain\Vtual\Enums\ContentFetchPoolSource;
use App\Domain\Vtual\Enums\WebsubSubscriptionStatus;
use App\Domain\Vtual\Enums\YoutubeWebSubSkipReason;
use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Models\Platform;
use App\Domain\Vtual\Models\WebsubSubscription;
use App\Domain\Vtual\Services\ContentFetchPoolService;
use App\Domain\Vtual\Services\CreatorService;
use App\Domain\Vtual\Services\YoutubeWebSubNotificationService;
use Database\Seeders\VtualRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class YoutubeWebSubNotificationServiceTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        $this->seed(VtualRoleSeeder::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function notification_enqueues_video_when_signature_valid(): void
    {
        [$channel, $topic, $secret, $body] = $this->seedSubscription();

        $signature = 'sha1='.base64_encode(hash_hmac('sha1', $body, $secret, true));

        $pool = Mockery::mock(ContentFetchPoolService::class);
        $pool->shouldReceive('enqueue')
            ->once()
            ->with(Mockery::type(Channel::class), 'VgdAA0Gdzn0', ContentFetchPoolSource::Websub);
        $pool->shouldReceive('enqueue')
            ->once()
            ->with(Mockery::type(Channel::class), 'olderVideoId12', ContentFetchPoolSource::Websub);

        $service = $this->service($pool);
        $result = $service->handleNotification($topic, $body, $signature);

        $this->assertSame(2, $result->enqueued);
        $this->assertFalse($result->skipped());
    }

    #[Test]
    public function notification_accepts_sha256_signature(): void
    {
        [$channel, $topic, $secret, $body] = $this->seedSubscription();

        $signature = 'sha256='.base64_encode(hash_hmac('sha256', $body, $secret, true));

        $pool = Mockery::mock(ContentFetchPoolService::class);
        $pool->shouldReceive('enqueue')->twice();

        $service = $this->service($pool);
        $result = $service->handleNotification($topic, $body, $signature);

        $this->assertSame(2, $result->enqueued);
    }

    #[Test]
    public function bad_signature_sets_last_error_and_skips_enqueue(): void
    {
        [$channel, $topic, $secret, $body] = $this->seedSubscription();

        $pool = Mockery::mock(ContentFetchPoolService::class);
        $pool->shouldNotReceive('enqueue');

        $service = $this->service($pool);
        $result = $service->handleNotification($topic, $body, 'sha1=invalid');

        $this->assertSame(YoutubeWebSubSkipReason::BadSignature, $result->skipReason);
        $this->assertStringContainsString(
            'signature verification failed',
            (string) WebsubSubscription::query()->where('channel_id', $channel->id)->value('last_error'),
        );
    }

    #[Test]
    public function topic_fallback_lookup_matches_by_channel_external_id(): void
    {
        [$channel, , $secret, $body] = $this->seedSubscription();

        $alternateTopic = 'https://www.youtube.com/feeds/videos.xml?channel_id=UCPkKpOHxEDcwmUAnRpIu-Ng&extra=1';

        $signature = 'sha1='.base64_encode(hash_hmac('sha1', $body, $secret, true));

        $pool = Mockery::mock(ContentFetchPoolService::class);
        $pool->shouldReceive('enqueue')->twice();

        $service = $this->service($pool);
        $result = $service->handleNotification($alternateTopic, $body, $signature);

        $this->assertSame(2, $result->enqueued);
    }

    /**
     * @return array{0: Channel, 1: string, 2: string, 3: string}
     */
    private function seedSubscription(): array
    {
        $youtube = Platform::query()->where('key', 'youtube')->firstOrFail();
        $user = User::query()->create([
            'name' => 'WebSub',
            'email' => 'websub-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole('Admin');
        $creator = app(CreatorService::class)->createOrphan($user, ['display_name' => 'WebSub Fox']);
        $channel = Channel::query()->create([
            'id' => (string) Str::uuid7(),
            'creator_id' => $creator->id,
            'platform_id' => $youtube->id,
            'external_id' => 'UCPkKpOHxEDcwmUAnRpIu-Ng',
            'status' => ChannelStatus::Active,
        ]);

        $topic = 'https://www.youtube.com/feeds/videos.xml?channel_id=UCPkKpOHxEDcwmUAnRpIu-Ng';
        $secret = 'test-secret';
        $body = file_get_contents(base_path('tests/fixtures/vtual/youtube-channel-feed.xml'));
        $this->assertIsString($body);

        WebsubSubscription::query()->create([
            'channel_id' => $channel->id,
            'topic' => $topic,
            'callback_url' => 'https://example.test/callback',
            'secret' => $secret,
            'status' => WebsubSubscriptionStatus::Active,
        ]);

        return [$channel, $topic, $secret, $body];
    }

    private function service(ContentFetchPoolService $pool): YoutubeWebSubNotificationService
    {
        return new YoutubeWebSubNotificationService(
            $pool,
            app(\App\Domain\Vtual\Services\YoutubeWebSubSubscriptionService::class),
            app(\App\Domain\Vtual\Integrations\Youtube\YoutubeAtomFeedParser::class),
        );
    }
}
