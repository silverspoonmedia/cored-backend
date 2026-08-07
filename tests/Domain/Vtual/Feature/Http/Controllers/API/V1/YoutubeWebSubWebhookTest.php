<?php

namespace Tests\Domain\Vtual\Feature\Http\Controllers\API\V1;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\ChannelStatus;
use App\Domain\Vtual\Enums\WebsubSubscriptionStatus;
use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Models\Platform;
use App\Domain\Vtual\Models\WebsubSubscription;
use App\Domain\Vtual\Services\CreatorService;
use Database\Seeders\VtualRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class YoutubeWebSubWebhookTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private const string WEBHOOK_URI = '/api/v1/vtual/webhooks/youtube-websub';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        $this->seed(VtualRoleSeeder::class);
    }

    #[Test]
    public function verify_get_echoes_challenge_for_valid_subscribe_request(): void
    {
        $subscription = $this->seedSubscription(WebsubSubscriptionStatus::Pending);

        $response = $this->get(self::WEBHOOK_URI.'?'.http_build_query([
            'hub_mode' => 'subscribe',
            'hub_topic' => $subscription->topic,
            'hub_challenge' => 'challenge-token-123',
        ]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $this->assertSame('challenge-token-123', $response->getContent());
        $this->assertSame(
            WebsubSubscriptionStatus::Active,
            $subscription->fresh()->status,
        );
    }

    #[Test]
    public function verify_get_rejects_invalid_mode(): void
    {
        $subscription = $this->seedSubscription(WebsubSubscriptionStatus::Pending);

        $response = $this->get(self::WEBHOOK_URI.'?'.http_build_query([
            'hub_mode' => 'invalid',
            'hub_topic' => $subscription->topic,
            'hub_challenge' => 'challenge-token-123',
        ]));

        $response->assertForbidden();
    }

    #[Test]
    public function notify_post_returns_204_for_valid_signature(): void
    {
        $subscription = $this->seedSubscription(WebsubSubscriptionStatus::Active);
        $body = file_get_contents(base_path('tests/fixtures/vtual/youtube-channel-feed.xml'));
        $this->assertIsString($body);
        $signature = 'sha1='.base64_encode(hash_hmac('sha1', $body, $subscription->secret, true));

        $response = $this->call(
            'POST',
            self::WEBHOOK_URI,
            [],
            [],
            [],
            [
                'HTTP_X-Hub-Topic' => $subscription->topic,
                'HTTP_X-Hub-Signature' => $signature,
                'CONTENT_TYPE' => 'application/atom+xml',
            ],
            $body,
        );

        $response->assertNoContent();
    }

    #[Test]
    public function notify_post_returns_204_for_invalid_signature(): void
    {
        $subscription = $this->seedSubscription(WebsubSubscriptionStatus::Active);
        $body = file_get_contents(base_path('tests/fixtures/vtual/youtube-channel-feed.xml'));
        $this->assertIsString($body);

        $response = $this->call(
            'POST',
            self::WEBHOOK_URI,
            [],
            [],
            [],
            [
                'HTTP_X-Hub-Topic' => $subscription->topic,
                'HTTP_X-Hub-Signature' => 'sha1=invalid',
                'CONTENT_TYPE' => 'application/atom+xml',
            ],
            $body,
        );

        $response->assertNoContent();
        $this->assertStringContainsString(
            'signature verification failed',
            (string) $subscription->fresh()->last_error,
        );
    }

    private function seedSubscription(WebsubSubscriptionStatus $status): WebsubSubscription
    {
        $youtube = Platform::query()->where('key', 'youtube')->firstOrFail();
        $user = User::query()->create([
            'name' => 'Webhook User',
            'email' => 'webhook-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole('Admin');
        $creator = app(CreatorService::class)->createOrphan($user, ['display_name' => 'Webhook Fox']);
        $channel = Channel::query()->create([
            'id' => (string) Str::uuid7(),
            'creator_id' => $creator->id,
            'platform_id' => $youtube->id,
            'external_id' => 'UCWEBHOOKTEST0001',
            'status' => ChannelStatus::Active,
        ]);

        $topic = 'https://www.youtube.com/feeds/videos.xml?channel_id=UCWEBHOOKTEST0001';

        return WebsubSubscription::query()->create([
            'channel_id' => $channel->id,
            'topic' => $topic,
            'callback_url' => 'https://example.test/callback',
            'secret' => 'webhook-secret',
            'status' => $status,
        ]);
    }
}
