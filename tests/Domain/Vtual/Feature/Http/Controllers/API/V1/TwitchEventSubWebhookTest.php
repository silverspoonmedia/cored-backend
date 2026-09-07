<?php

namespace Tests\Domain\Vtual\Feature\Http\Controllers\API\V1;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\ChannelStatus;
use App\Domain\Vtual\Enums\ContentStatus;
use App\Domain\Vtual\Enums\ContentType;
use App\Domain\Vtual\Enums\PlatformCredentialStatus;
use App\Domain\Vtual\Enums\PlatformCredentialType;
use App\Domain\Vtual\Enums\TwitchEventSubStatus;
use App\Domain\Vtual\Enums\TwitchEventSubType;
use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Models\ChannelFetchPoolItem;
use App\Domain\Vtual\Models\Content;
use App\Domain\Vtual\Models\EventsubSubscription;
use App\Domain\Vtual\Models\Platform;
use App\Domain\Vtual\Services\CreatorService;
use App\Domain\Vtual\Services\PlatformCredentialService;
use Database\Seeders\VtualRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

/**
 * Twitch EventSub webhook: challenge verification, signed notifications →
 * channel pool enqueue, duplicate suppression, revocation, body cap.
 */
class TwitchEventSubWebhookTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private const string WEBHOOK_URI = '/api/v1/vtual/webhooks/twitch-eventsub';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        $this->seed(VtualRoleSeeder::class);
    }

    #[Test]
    public function verification_message_echoes_challenge_and_activates_row(): void
    {
        $subscription = $this->seedSubscription(TwitchEventSubType::ChannelUpdate, TwitchEventSubStatus::Pending);

        $payload = $this->verificationPayload($subscription);
        $body = json_encode($payload);
        $this->assertIsString($body);
        $headers = $this->signedHeaders($body, $subscription->secret, 'webhook_callback_verification');

        $response = $this->call('POST', self::WEBHOOK_URI, [], [], [], $headers, $body);

        $response->assertOk();
        $this->assertSame('challenge-token-abc', $response->getContent());
        $this->assertSame(
            TwitchEventSubStatus::Active,
            $subscription->fresh()->status,
        );
    }

    #[Test]
    public function notification_valid_signature_enqueues_channel_pool_item(): void
    {
        $subscription = $this->seedSubscription(TwitchEventSubType::ChannelUpdate, TwitchEventSubStatus::Active);
        $body = $this->notificationPayloadBody($subscription);
        $headers = $this->signedHeaders($body, $subscription->secret, 'notification');

        $response = $this->call('POST', self::WEBHOOK_URI, [], [], [], $headers, $body);

        $response->assertNoContent();
        $this->assertDatabaseHas('vtual_channel_fetch_pool', [
            'channel_id' => $subscription->channel_id,
            'external_id' => (string) $subscription->external_id,
        ]);
        $this->assertNotNull($subscription->fresh()->last_notify_at);
    }

    #[Test]
    public function notification_invalid_signature_skips_enqueue(): void
    {
        $subscription = $this->seedSubscription(TwitchEventSubType::StreamOnline, TwitchEventSubStatus::Active);
        $body = $this->notificationPayloadBody($subscription);
        $headers = $this->signedHeaders($body, $subscription->secret, 'notification');
        $headers['HTTP_Twitch-Eventsub-Message-Signature'] = 'sha256='.str_repeat('0', 64);

        $response = $this->call('POST', self::WEBHOOK_URI, [], [], [], $headers, $body);

        $response->assertNoContent();
        $this->assertDatabaseMissing('vtual_channel_fetch_pool', [
            'channel_id' => $subscription->channel_id,
        ]);
        $this->assertStringContainsString(
            'signature verification failed',
            (string) $subscription->fresh()->last_error,
        );
    }

    #[Test]
    public function duplicate_message_id_is_ignored(): void
    {
        $subscription = $this->seedSubscription(TwitchEventSubType::ChannelUpdate, TwitchEventSubStatus::Active);
        $body = $this->notificationPayloadBody($subscription);
        $headers = $this->signedHeaders($body, $subscription->secret, 'notification');

        $this->call('POST', self::WEBHOOK_URI, [], [], [], $headers, $body);
        $this->call('POST', self::WEBHOOK_URI, [], [], [], $headers, $body);

        // Second delivery is deduped — still only one pool item.
        $this->assertSame(
            1,
            ChannelFetchPoolItem::query()->where('channel_id', $subscription->channel_id)->count(),
        );
    }

    #[Test]
    public function revocation_marks_subscription_unsubscribed(): void
    {
        $subscription = $this->seedSubscription(TwitchEventSubType::StreamOffline, TwitchEventSubStatus::Active);
        $payload = [
            'subscription' => [
                'id' => $subscription->twitch_subscription_id,
                'type' => TwitchEventSubType::StreamOffline->value,
                'version' => '1',
                'status' => 'authorization_revoked',
                'condition' => ['broadcaster_user_id' => $subscription->external_id],
            ],
        ];
        $body = json_encode($payload);
        $this->assertIsString($body);
        $headers = $this->signedHeaders($body, $subscription->secret, 'revocation');

        $response = $this->call('POST', self::WEBHOOK_URI, [], [], [], $headers, $body);

        $response->assertNoContent();
        $fresh = $subscription->fresh();
        $this->assertSame(TwitchEventSubStatus::Unsubscribed, $fresh->status);
        $this->assertStringContainsString('revoked by twitch', (string) $fresh->last_error);
    }

    #[Test]
    public function oversized_body_returns_413(): void
    {
        $subscription = $this->seedSubscription(TwitchEventSubType::ChannelUpdate, TwitchEventSubStatus::Active);
        $body = str_repeat('x', 1_048_577);
        $headers = $this->signedHeaders($body, $subscription->secret, 'notification');

        $response = $this->call('POST', self::WEBHOOK_URI, [], [], [], $headers, $body);

        $response->assertStatus(413);
    }

    #[Test]
    public function unknown_subscription_returns_204_without_enqueue(): void
    {
        $this->seedSubscription(TwitchEventSubType::ChannelUpdate, TwitchEventSubStatus::Active);
        $body = json_encode([
            'subscription' => [
                'id' => 'unknown-twitch-sub-id',
                'type' => TwitchEventSubType::ChannelUpdate->value,
                'version' => '2',
                'condition' => ['broadcaster_user_id' => '40934651'],
            ],
            'event' => ['broadcaster_user_id' => '40934651'],
        ]);
        $this->assertIsString($body);
        $headers = $this->signedHeaders($body, 'some-other-secret', 'notification');

        $response = $this->call('POST', self::WEBHOOK_URI, [], [], [], $headers, $body);

        $response->assertNoContent();
        $this->assertSame(0, ChannelFetchPoolItem::query()->count());
    }

    #[Test]
    public function stream_online_creates_live_content_row(): void
    {
        $subscription = $this->seedSubscription(TwitchEventSubType::StreamOnline, TwitchEventSubStatus::Active);
        $this->seedActiveOauthCredential($subscription->channel->platform);
        $this->fakeStreamsEndpoint();

        $body = $this->notificationPayloadBody($subscription);
        $headers = $this->signedHeaders($body, $subscription->secret, 'notification');

        $response = $this->call('POST', self::WEBHOOK_URI, [], [], [], $headers, $body);

        $response->assertNoContent();

        $this->assertDatabaseHas('vtual_contents', [
            'channel_id' => $subscription->channel_id,
            'external_id' => 'stream-session-1',
            'type' => ContentType::Live->value,
            'status' => ContentStatus::Live->value,
            'title' => 'Twitch live title',
            'concurrent_viewers' => 1234,
        ]);

        // Snapshot refresh still enqueued alongside the live row.
        $this->assertDatabaseHas('vtual_channel_fetch_pool', [
            'channel_id' => $subscription->channel_id,
        ]);
    }

    #[Test]
    public function stream_online_repeat_refreshes_same_row(): void
    {
        $subscription = $this->seedSubscription(TwitchEventSubType::StreamOnline, TwitchEventSubStatus::Active);
        $this->seedActiveOauthCredential($subscription->channel->platform);
        $this->fakeStreamsEndpoint(viewers: 999);

        $body = $this->notificationPayloadBody($subscription);
        $headers = $this->signedHeaders($body, $subscription->secret, 'notification');

        $this->call('POST', self::WEBHOOK_URI, [], [], [], $headers, $body);
        $this->call('POST', self::WEBHOOK_URI, [], [], [], $headers, $body);

        // Same stream session id → one row, refreshed in place.
        $this->assertDatabaseHas('vtual_contents', [
            'channel_id' => $subscription->channel_id,
            'external_id' => 'stream-session-1',
            'concurrent_viewers' => 999,
        ]);
        $this->assertSame(
            1,
            Content::query()->where('channel_id', $subscription->channel_id)->count(),
        );
    }

    #[Test]
    public function stream_offline_ends_live_content_rows(): void
    {
        $subscription = $this->seedSubscription(TwitchEventSubType::StreamOffline, TwitchEventSubStatus::Active);
        $this->seedActiveOauthCredential($subscription->channel->platform);

        Content::query()->create([
            'id' => (string) Str::uuid7(),
            'creator_id' => $subscription->channel->creator_id,
            'channel_id' => $subscription->channel_id,
            'platform_id' => $subscription->channel->platform_id,
            'external_id' => 'stream-session-old',
            'type' => ContentType::Live,
            'status' => ContentStatus::Live,
            'title' => 'Old live',
        ]);

        $body = $this->notificationPayloadBody($subscription);
        $headers = $this->signedHeaders($body, $subscription->secret, 'notification');

        $response = $this->call('POST', self::WEBHOOK_URI, [], [], [], $headers, $body);

        $response->assertNoContent();

        $row = Content::query()->where('external_id', 'stream-session-old')->firstOrFail();
        $this->assertSame(ContentType::Archive, $row->type);
        $this->assertSame(ContentStatus::Ended, $row->status);
        $this->assertNotNull($row->ended_at);
    }

    #[Test]
    public function stream_online_without_credential_skips_live_row_but_enqueues_pool(): void
    {
        $subscription = $this->seedSubscription(TwitchEventSubType::StreamOnline, TwitchEventSubStatus::Active);
        // No OauthApp credential seeded — gateway throws before HTTP.

        $body = $this->notificationPayloadBody($subscription);
        $headers = $this->signedHeaders($body, $subscription->secret, 'notification');

        $response = $this->call('POST', self::WEBHOOK_URI, [], [], [], $headers, $body);

        $response->assertNoContent();
        $this->assertSame(0, Content::query()->where('channel_id', $subscription->channel_id)->count());
        $this->assertDatabaseHas('vtual_channel_fetch_pool', [
            'channel_id' => $subscription->channel_id,
        ]);
    }

    #[Test]
    public function channel_update_does_not_touch_live_rows(): void
    {
        $subscription = $this->seedSubscription(TwitchEventSubType::ChannelUpdate, TwitchEventSubStatus::Active);
        $this->seedActiveOauthCredential($subscription->channel->platform);
        $this->fakeStreamsEndpoint();

        Content::query()->create([
            'id' => (string) Str::uuid7(),
            'creator_id' => $subscription->channel->creator_id,
            'channel_id' => $subscription->channel_id,
            'platform_id' => $subscription->channel->platform_id,
            'external_id' => 'stream-session-live',
            'type' => ContentType::Live,
            'status' => ContentStatus::Live,
            'title' => 'Still live',
        ]);

        $body = $this->notificationPayloadBody($subscription);
        $headers = $this->signedHeaders($body, $subscription->secret, 'notification');

        $response = $this->call('POST', self::WEBHOOK_URI, [], [], [], $headers, $body);

        $response->assertNoContent();

        $row = Content::query()->where('external_id', 'stream-session-live')->firstOrFail();
        $this->assertSame(ContentType::Live, $row->type);
        $this->assertSame(ContentStatus::Live, $row->status);
    }

    /**
     * @return array<string, string>
     */
    private function signedHeaders(string $body, string $secret, string $messageType): array
    {
        $messageId = 'msg-'.Str::uuid7()->toString();
        $timestamp = now()->toIso8601String();
        $signature = 'sha256='.hash_hmac('sha256', $messageId.$timestamp.$body, $secret);

        return [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_Twitch-Eventsub-Message-Id' => $messageId,
            'HTTP_Twitch-Eventsub-Message-Timestamp' => $timestamp,
            'HTTP_Twitch-Eventsub-Message-Signature' => $signature,
            'HTTP_Twitch-Eventsub-Message-Type' => $messageType,
            'HTTP_Twitch-Eventsub-Subscription-Type' => 'channel.update',
            'HTTP_Twitch-Eventsub-Subscription-Version' => '2',
        ];
    }

    private function verificationPayload(EventsubSubscription $subscription): array
    {
        return [
            'subscription' => [
                'id' => $subscription->twitch_subscription_id,
                'type' => $subscription->event_type instanceof TwitchEventSubType ? $subscription->event_type->value : (string) $subscription->event_type,
                'version' => '2',
                'status' => 'webhook_callback_verification_pending',
                'condition' => ['broadcaster_user_id' => $subscription->external_id],
            ],
            'challenge' => 'challenge-token-abc',
        ];
    }

    private function notificationPayloadBody(EventsubSubscription $subscription): string
    {
        return (string) json_encode([
            'subscription' => [
                'id' => $subscription->twitch_subscription_id,
                'type' => $subscription->event_type instanceof TwitchEventSubType ? $subscription->event_type->value : (string) $subscription->event_type,
                'version' => '2',
                'condition' => ['broadcaster_user_id' => $subscription->external_id],
            ],
            'event' => [
                'broadcaster_user_id' => $subscription->external_id,
                'broadcaster_user_login' => 'ludwig',
                'title' => 'New stream title',
            ],
        ]);
    }

    private function seedSubscription(TwitchEventSubType $type, TwitchEventSubStatus $status): EventsubSubscription
    {
        $twitch = Platform::query()->where('key', 'twitch')->firstOrFail();
        $user = User::query()->create([
            'name' => 'EventSub User',
            'email' => 'eventsub-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole('Admin');
        $creator = app(CreatorService::class)->createOrphan($user, ['display_name' => 'EventSub Fox']);
        $channel = Channel::query()->create([
            'id' => (string) Str::uuid7(),
            'creator_id' => $creator->id,
            'platform_id' => $twitch->id,
            'external_id' => '40934651',
            'status' => ChannelStatus::Active,
        ]);

        return EventsubSubscription::query()->create([
            'channel_id' => $channel->id,
            'external_id' => '40934651',
            'event_type' => $type,
            'twitch_subscription_id' => 'twitch-sub-'.Str::uuid7()->toString(),
            'secret' => 'eventsub-webhook-secret',
            'status' => $status,
            'lease_expires_at' => now()->addDays(7),
        ]);
    }

    private function seedActiveOauthCredential(Platform $platform): void
    {
        $admin = User::query()->create([
            'name' => 'EventSub Live Admin',
            'email' => 'eventsub-live-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
        ]);
        $admin->assignRole('Admin');

        $credential = app(PlatformCredentialService::class)->create($admin, $platform, [
            'label' => 'eventsub-live-test-'.uniqid(),
            'type' => PlatformCredentialType::OauthApp->value,
            'secret' => [
                'client_id' => 'cid',
                'client_secret' => 'csecret',
            ],
        ]);
        $credential->forceFill([
            'status' => PlatformCredentialStatus::Active,
            'verified_at' => now(),
            // Valid runtime so getAppAccessToken skips minting (also hits Twitch).
            'runtime_payload' => [
                'access_token' => 'app-token-seeded',
                'token_type' => 'bearer',
                'expires_at' => now()->addHour()->toIso8601String(),
                'minted_at' => now()->toIso8601String(),
            ],
        ])->save();
    }

    /**
     * Fake Helix GET /streams returning one running stream for user 40934651.
     */
    private function fakeStreamsEndpoint(int $viewers = 1234): void
    {
        Http::fake([
            'api.twitch.tv/helix/streams*' => Http::response([
                'data' => [
                    [
                        'id' => 'stream-session-1',
                        'user_id' => '40934651',
                        'user_login' => 'ludwig',
                        'user_name' => 'Ludwig',
                        'title' => 'Twitch live title',
                        'game_name' => 'Just Chatting',
                        'thumbnail_url' => 'https://static-cdn.jtvnw.net/previews-ttv/live_user_ludwig-{width}x{height}.jpg',
                        'viewer_count' => $viewers,
                        'started_at' => now()->subMinutes(30)->toIso8601String(),
                        'language' => 'en',
                    ],
                ],
            ]),
        ]);
    }
}
