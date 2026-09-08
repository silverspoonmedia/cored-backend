<?php

namespace Tests\Domain\Vtual\Unit\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\ChannelStatus;
use App\Domain\Vtual\Enums\PlatformCredentialStatus;
use App\Domain\Vtual\Enums\PlatformCredentialType;
use App\Domain\Vtual\Enums\PlatformKey;
use App\Domain\Vtual\Enums\TwitchEventSubStatus;
use App\Domain\Vtual\Enums\TwitchEventSubType;
use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Models\EventsubSubscription;
use App\Domain\Vtual\Models\Platform;
use App\Domain\Vtual\Services\CreatorService;
use App\Domain\Vtual\Services\PlatformCredentialService;
use App\Domain\Vtual\Services\TwitchEventSubSubscriptionService;
use Database\Seeders\VtualRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

/**
 * EventSub subscription lifecycle: eligible channel subscribes all three event
 * types against Helix; missing credential skips silently; renewal only picks
 * due rows.
 */
class TwitchEventSubSubscriptionServiceTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private const string HELIX_BASE = 'https://api.twitch.tv/helix';

    private TwitchEventSubSubscriptionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        $this->seed(VtualRoleSeeder::class);
        $this->service = app(TwitchEventSubSubscriptionService::class);
    }

    #[Test]
    public function subscribe_creates_rows_for_all_event_types(): void
    {
        $channel = $this->seedTwitchChannel();
        $this->seedActiveOauthCredential($channel->platform);

        Http::fake([
            self::HELIX_BASE.'/eventsub/subscriptions' => Http::sequence([
                ['data' => [['id' => 'twitch-sub-1', 'type' => 'channel.update', 'transport' => ['expires_at' => now()->addDays(10)->toIso8601String()]]]],
                ['data' => [['id' => 'twitch-sub-2', 'type' => 'stream.online', 'transport' => ['expires_at' => now()->addDays(10)->toIso8601String()]]]],
                ['data' => [['id' => 'twitch-sub-3', 'type' => 'stream.offline', 'transport' => ['expires_at' => now()->addDays(10)->toIso8601String()]]]],
            ]),
        ]);

        $this->service->subscribe($channel);

        $rows = EventsubSubscription::query()->where('channel_id', $channel->id)->get();
        $this->assertSame(3, $rows->count());
        $this->assertSame(
            [TwitchEventSubType::ChannelUpdate->value, TwitchEventSubType::StreamOffline->value, TwitchEventSubType::StreamOnline->value],
            $rows->map(fn ($r) => $r->event_type instanceof TwitchEventSubType ? $r->event_type->value : (string) $r->event_type)->sort()->values()->all(),
        );

        Http::assertSentCount(3);
        Http::assertSent(function (Request $request) {
            $payload = $request->data();

            return isset($payload['condition']['broadcaster_user_id'])
                && isset($payload['transport']['callback'])
                && isset($payload['transport']['secret'])
                && str_contains((string) $payload['transport']['callback'], 'twitch-eventsub');
        });
    }

    #[Test]
    public function subscribe_without_active_credential_skips_silently(): void
    {
        $channel = $this->seedTwitchChannel();

        $this->service->subscribe($channel);

        $this->assertSame(0, EventsubSubscription::query()->count());
        Http::assertNothingSent();
    }

    #[Test]
    public function subscribe_marks_failed_row_on_http_error(): void
    {
        $channel = $this->seedTwitchChannel();
        $this->seedActiveOauthCredential($channel->platform);

        Http::fake([
            self::HELIX_BASE.'/eventsub/subscriptions' => Http::response(['message' => 'validation failed'], 400),
        ]);

        $this->service->subscribe($channel);

        $rows = EventsubSubscription::query()->where('channel_id', $channel->id)->get();
        $this->assertSame(3, $rows->count());
        $this->assertTrue($rows->every(fn ($r) => $r->status === TwitchEventSubStatus::Failed));
        $this->assertStringContainsString('HTTP 400', (string) $rows->first()->last_error);
    }

    #[Test]
    public function renew_due_subscriptions_only_touches_due_rows(): void
    {
        $dueChannel = $this->seedTwitchChannel();
        $this->seedActiveOauthCredential($dueChannel->platform);
        $farChannel = $this->seedTwitchChannel('100000002');

        // Both active, one due within renew window, one far out.
        EventsubSubscription::query()->create([
            'channel_id' => $dueChannel->id,
            'external_id' => $dueChannel->external_id,
            'event_type' => TwitchEventSubType::ChannelUpdate,
            'twitch_subscription_id' => 'sub-due',
            'secret' => 's1',
            'status' => TwitchEventSubStatus::Active,
            'lease_expires_at' => now()->addHours(2),
        ]);
        EventsubSubscription::query()->create([
            'channel_id' => $farChannel->id,
            'external_id' => $farChannel->external_id,
            'event_type' => TwitchEventSubType::ChannelUpdate,
            'twitch_subscription_id' => 'sub-far',
            'secret' => 's2',
            'status' => TwitchEventSubStatus::Active,
            'lease_expires_at' => now()->addDays(9),
        ]);

        Http::fake([
            self::HELIX_BASE.'/eventsub/subscriptions' => Http::sequence([
                ['data' => [['id' => 'twitch-sub-renewed-1', 'type' => 'channel.update', 'transport' => ['expires_at' => now()->addDays(10)->toIso8601String()]]]],
                ['data' => [['id' => 'twitch-sub-renewed-2', 'type' => 'stream.online', 'transport' => ['expires_at' => now()->addDays(10)->toIso8601String()]]]],
                ['data' => [['id' => 'twitch-sub-renewed-3', 'type' => 'stream.offline', 'transport' => ['expires_at' => now()->addDays(10)->toIso8601String()]]]],
            ]),
        ]);

        $count = $this->service->renewDueSubscriptions();

        // Renewal triggers full re-subscribe of the channel → 3 POSTs, only for the due one.
        $this->assertSame(1, $count);
        Http::assertSentCount(3);
    }

    #[Test]
    public function ineligible_platform_is_skipped(): void
    {
        $channel = $this->seedYoutubeChannel();

        $this->service->subscribe($channel);

        $this->assertSame(0, EventsubSubscription::query()->count());
        Http::assertNothingSent();
    }

    private function seedTwitchChannel(string $externalId = '40934651'): Channel
    {
        $twitch = Platform::query()->where('key', PlatformKey::Twitch->value)->firstOrFail();
        $user = User::query()->create([
            'name' => 'EventSub Svc User',
            'email' => 'eventsub-svc-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole('Admin');
        $creator = app(CreatorService::class)->createOrphan($user, ['display_name' => 'EventSub Svc Fox']);

        return Channel::query()->create([
            'id' => (string) Str::uuid7(),
            'creator_id' => $creator->id,
            'platform_id' => $twitch->id,
            'external_id' => $externalId,
            'status' => ChannelStatus::Active,
        ]);
    }

    private function seedYoutubeChannel(): Channel
    {
        $youtube = Platform::query()->where('key', PlatformKey::Youtube->value)->firstOrFail();
        $user = User::query()->create([
            'name' => 'EventSub Yt User',
            'email' => 'eventsub-yt-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole('Admin');
        $creator = app(CreatorService::class)->createOrphan($user, ['display_name' => 'EventSub Yt Fox']);

        return Channel::query()->create([
            'id' => (string) Str::uuid7(),
            'creator_id' => $creator->id,
            'platform_id' => $youtube->id,
            'external_id' => 'UCSVCHECK0001',
            'status' => ChannelStatus::Active,
        ]);
    }

    private function seedActiveOauthCredential(Platform $platform): \App\Domain\Vtual\Models\PlatformCredential
    {
        $admin = User::query()->create([
            'name' => 'EventSub Cred Admin',
            'email' => 'eventsub-cred-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
        ]);
        $admin->assignRole('Admin');

        $credential = app(PlatformCredentialService::class)->create($admin, $platform, [
            'label' => 'eventsub-test-'.uniqid(),
            'type' => PlatformCredentialType::OauthApp->value,
            'secret' => [
                'client_id' => 'cid',
                'client_secret' => 'csecret',
            ],
        ]);
        $credential->forceFill([
            'status' => PlatformCredentialStatus::Active,
            'verified_at' => now(),
            // Seed valid runtime so getAppAccessToken skips mint (mint also hits Twitch).
            'runtime_payload' => [
                'access_token' => 'app-token-seeded',
                'token_type' => 'bearer',
                'expires_at' => now()->addHour()->toIso8601String(),
                'minted_at' => now()->toIso8601String(),
            ],
        ])->save();

        return $credential;
    }
}
