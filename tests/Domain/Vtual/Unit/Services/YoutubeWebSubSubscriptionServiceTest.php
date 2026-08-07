<?php

namespace Tests\Domain\Vtual\Unit\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\ChannelStatus;
use App\Domain\Vtual\Enums\WebsubSubscriptionStatus;
use App\Domain\Vtual\Jobs\RunChannelInitialContentBackfillJob;
use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Models\Platform;
use App\Domain\Vtual\Models\WebsubSubscription;
use App\Domain\Vtual\Services\CreatorService;
use App\Domain\Vtual\Services\YoutubeWebSubSubscriptionService;
use Database\Seeders\VtualRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class YoutubeWebSubSubscriptionServiceTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private YoutubeWebSubSubscriptionService $websub;

    private CreatorService $creators;

    private Platform $youtube;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        $this->seed(VtualRoleSeeder::class);
        $this->websub = app(YoutubeWebSubSubscriptionService::class);
        $this->creators = app(CreatorService::class);
        $this->youtube = Platform::query()->where('key', 'youtube')->firstOrFail();
        Bus::fake();
        Http::fake([
            'pubsubhubbub.appspot.com/*' => Http::response('', 202),
        ]);
    }

    #[Test]
    public function subscribe_missing_active_channels_creates_rows_for_channels_without_subscription(): void
    {
        $missing = $this->seedActiveYoutubeChannel('UCMISSINGWEBSUB01');
        $existing = $this->seedActiveYoutubeChannel('UCEXISTINGWEBSUB1');
        WebsubSubscription::query()->create([
            'channel_id' => $existing->id,
            'topic' => 'https://www.youtube.com/feeds/videos.xml?channel_id=UCEXISTINGWEBSUB1',
            'callback_url' => 'https://example.test/webhook',
            'secret' => 'secret',
            'status' => WebsubSubscriptionStatus::Active,
            'lease_expires_at' => now()->addDay(),
        ]);

        $repaired = $this->websub->subscribeMissingActiveChannels();

        $this->assertSame(1, $repaired);
        $this->assertTrue(
            WebsubSubscription::query()->where('channel_id', $missing->id)->exists()
        );
        $this->assertSame(
            1,
            WebsubSubscription::query()->where('channel_id', $existing->id)->count()
        );
    }

    #[Test]
    public function subscribe_missing_skips_pending_and_non_youtube_channels(): void
    {
        $user = User::query()->create([
            'name' => 'WebSub User',
            'email' => 'websub-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole('Admin');
        $creator = $this->creators->createOrphan($user, ['display_name' => 'WebSub Fox']);

        Channel::query()->create([
            'id' => (string) Str::uuid7(),
            'creator_id' => $creator->id,
            'platform_id' => $this->youtube->id,
            'external_id' => 'pending:'.Str::uuid7(),
            'profile_input' => '@pending',
            'status' => ChannelStatus::Pending,
        ]);

        $repaired = $this->websub->subscribeMissingActiveChannels();

        $this->assertSame(0, $repaired);
        $this->assertSame(0, WebsubSubscription::query()->count());
    }

    #[Test]
    public function attempt_mark_verified_subscribe_activates_pending_subscription(): void
    {
        $channel = $this->seedActiveYoutubeChannel('UCVERIFYTEST00001');
        $topic = 'https://www.youtube.com/feeds/videos.xml?channel_id=UCVERIFYTEST00001';
        WebsubSubscription::query()->create([
            'channel_id' => $channel->id,
            'topic' => $topic,
            'callback_url' => 'https://example.test/webhook',
            'secret' => 'secret',
            'status' => WebsubSubscriptionStatus::Pending,
        ]);

        $this->assertTrue($this->websub->attemptMarkVerified($topic, 'subscribe'));
        $this->assertSame(
            WebsubSubscriptionStatus::Active,
            WebsubSubscription::query()->where('channel_id', $channel->id)->value('status'),
        );
    }

    #[Test]
    public function attempt_mark_verified_rejects_invalid_mode(): void
    {
        $channel = $this->seedActiveYoutubeChannel('UCVERIFYTEST00002');
        $topic = 'https://www.youtube.com/feeds/videos.xml?channel_id=UCVERIFYTEST00002';
        WebsubSubscription::query()->create([
            'channel_id' => $channel->id,
            'topic' => $topic,
            'callback_url' => 'https://example.test/webhook',
            'secret' => 'secret',
            'status' => WebsubSubscriptionStatus::Pending,
        ]);

        $this->assertFalse($this->websub->attemptMarkVerified($topic, 'invalid'));
    }

    #[Test]
    public function attempt_mark_verified_unsubscribe_requires_active_or_pending(): void
    {
        $channel = $this->seedActiveYoutubeChannel('UCVERIFYTEST00003');
        $topic = 'https://www.youtube.com/feeds/videos.xml?channel_id=UCVERIFYTEST00003';
        WebsubSubscription::query()->create([
            'channel_id' => $channel->id,
            'topic' => $topic,
            'callback_url' => 'https://example.test/webhook',
            'secret' => 'secret',
            'status' => WebsubSubscriptionStatus::Unsubscribed,
        ]);

        $this->assertFalse($this->websub->attemptMarkVerified($topic, 'unsubscribe'));
    }

    #[Test]
    public function resolve_subscription_for_topic_falls_back_to_channel_external_id(): void
    {
        $channel = $this->seedActiveYoutubeChannel('UCFALLBACKTOPIC01');
        $storedTopic = 'https://www.youtube.com/feeds/videos.xml?channel_id=UCFALLBACKTOPIC01';
        WebsubSubscription::query()->create([
            'channel_id' => $channel->id,
            'topic' => $storedTopic,
            'callback_url' => 'https://example.test/webhook',
            'secret' => 'secret',
            'status' => WebsubSubscriptionStatus::Active,
        ]);

        $resolved = $this->websub->resolveSubscriptionForTopic(
            'https://www.youtube.com/feeds/videos.xml?channel_id=UCFALLBACKTOPIC01&from=hub'
        );

        $this->assertNotNull($resolved);
        $this->assertSame((string) $channel->id, (string) $resolved->channel_id);
    }

    private function seedActiveYoutubeChannel(string $externalId): Channel
    {
        $user = User::query()->create([
            'name' => 'WebSub User',
            'email' => 'websub-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole('Admin');
        $creator = $this->creators->createOrphan($user, ['display_name' => 'WebSub Fox']);

        return Channel::query()->create([
            'id' => (string) Str::uuid7(),
            'creator_id' => $creator->id,
            'platform_id' => $this->youtube->id,
            'external_id' => $externalId,
            'handle' => 'websub',
            'display_name' => 'WebSub Channel',
            'status' => ChannelStatus::Active,
        ]);
    }
}
