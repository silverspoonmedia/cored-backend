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

    #[Test]
    public function verify_signature_accepts_hex_digest(): void
    {
        $body = '<xml>payload</xml>';
        $secret = 's3cr3t';
        $service = $this->service(Mockery::mock(ContentFetchPoolService::class));

        $this->assertTrue($service->verifySignature($body, 'sha1='.hash_hmac('sha1', $body, $secret), $secret));
        $this->assertTrue($service->verifySignature($body, 'sha256='.hash_hmac('sha256', $body, $secret), $secret));
    }

    #[Test]
    public function verify_signature_accepts_base64_digest(): void
    {
        $body = '<xml>payload</xml>';
        $secret = 's3cr3t';
        $service = $this->service(Mockery::mock(ContentFetchPoolService::class));

        $this->assertTrue($service->verifySignature(
            $body,
            'sha1='.base64_encode(hash_hmac('sha1', $body, $secret, true)),
            $secret,
        ));
    }

    #[Test]
    public function verify_signature_rejects_mismatch(): void
    {
        $service = $this->service(Mockery::mock(ContentFetchPoolService::class));

        $this->assertFalse($service->verifySignature('body', 'sha1=deadbeef', 'secret'));
        $this->assertFalse($service->verifySignature('body', null, 'secret'));
    }

    #[Test]
    public function deleted_entry_notification_marks_content_unavailable(): void
    {
        [$channel, $topic, $secret] = $this->seedSubscription();

        $body = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<feed xmlns:at="http://www.youtube.com/xml/schemas/2015" xmlns="http://www.w3.org/2005/Atom">'
            .'<at:deleted-entry ref="yt:video:goneVideoId1" when_deleted="2026-09-01T00:00:00+00:00"/>'
            .'</feed>';

        $signature = 'sha1='.hash_hmac('sha1', $body, $secret);

        $contents = Mockery::mock(\App\Domain\Vtual\Services\ContentService::class);
        $contents->shouldReceive('markUnavailable')
            ->once()
            ->with(Mockery::type(Channel::class), 'goneVideoId1');

        $pool = Mockery::mock(ContentFetchPoolService::class);
        $pool->shouldNotReceive('enqueue');

        $service = new YoutubeWebSubNotificationService(
            $pool,
            app(\App\Domain\Vtual\Services\YoutubeWebSubSubscriptionService::class),
            app(\App\Domain\Vtual\Integrations\Youtube\YoutubeAtomFeedParser::class),
            $contents,
        );

        $result = $service->handleNotification($topic, $body, $signature);

        $this->assertSame(0, $result->enqueued);
        $this->assertFalse($result->skipped());
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
        $body = file_get_contents(base_path('tests/Fixtures/vtual/youtube-channel-feed.xml'));
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
            Mockery::mock(\App\Domain\Vtual\Services\ContentService::class),
        );
    }
}
