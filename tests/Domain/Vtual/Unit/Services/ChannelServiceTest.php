<?php

namespace Tests\Domain\Vtual\Unit\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\ChannelFetchPoolStatus;
use App\Domain\Vtual\Enums\ChannelStatus;
use App\Domain\Vtual\Enums\PlatformCredentialStatus;
use App\Domain\Vtual\Enums\PlatformCredentialType;
use App\Domain\Vtual\Enums\PlatformKey;
use App\Domain\Vtual\Enums\WebsubSubscriptionStatus;
use App\Domain\Vtual\Exceptions\VtualException;
use App\Domain\Vtual\Integrations\Dto\PlatformChannelDto;
use App\Domain\Vtual\Integrations\Support\ChannelProfileResolver;
use App\Domain\Vtual\Jobs\RunChannelInitialContentBackfillJob;
use App\Domain\Vtual\Jobs\RunChannelStubContentBackfillJob;
use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Models\ChannelClaim;
use App\Domain\Vtual\Models\ChannelFetchPoolItem;
use App\Domain\Vtual\Models\Platform;
use App\Domain\Vtual\Models\WebsubSubscription;
use App\Domain\Vtual\Services\ChannelService;
use App\Domain\Vtual\Services\CreatorService;
use App\Domain\Vtual\Services\PlatformCredentialService;
use Carbon\Carbon;
use Database\Seeders\VtualRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

/**
 * ChannelService: seedForStaff (manage any), activateFromDto, refresh, archive, restore, deletePending.
 * Owner/editor add-channel is ChannelClaimService (see ChannelClaimServiceTest).
 */
class ChannelServiceTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private const YT_ID = 'UCPkKpOHxEDcwmUAnRpIu-Ng';

    private ChannelService $channels;

    private CreatorService $creators;

    private ChannelProfileResolver $profiles;

    private PlatformCredentialService $credentials;

    private User $admin;

    private Platform $youtube;

    private Platform $twitch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        $this->seed(VtualRoleSeeder::class);
        $this->channels = app(ChannelService::class);
        $this->creators = app(CreatorService::class);
        $this->profiles = app(ChannelProfileResolver::class);
        $this->credentials = app(PlatformCredentialService::class);
        $this->admin = $this->userWithRole('Admin');
        $this->youtube = Platform::query()->where('key', 'youtube')->firstOrFail();
        $this->twitch = Platform::query()->where('key', 'twitch')->firstOrFail();
        Bus::fake();

        // Activation hooks fire before per-test Http::fake() registration —
        // fake the WebSub hub up front so no test ever reaches the real hub.
        Http::fake(['pubsubhubbub.appspot.com/*' => Http::response('', 202)]);
    }

    #[Test]
    public function register_pending_for_staff_stores_profile_without_http(): void
    {
        $httpCalls = 0;
        Http::fake(function () use (&$httpCalls) {
            $httpCalls++;

            return Http::response([], 404);
        });

        $orphan = $this->creators->createOrphan($this->admin, ['display_name' => 'Orphan Pending']);

        $channel = $this->channels->registerPendingForStaff($this->admin, $orphan->id, [
            'platform_key' => 'youtube',
            'profile' => '@stream',
        ]);

        $this->assertSame(0, $httpCalls);
        $this->assertSame(ChannelStatus::Pending, $channel->status);
        $this->assertSame('@stream', $channel->profile_input);
        $this->assertStringStartsWith('pending:', $channel->external_id);
    }

    #[Test]
    public function register_pending_normalizes_percent_encoded_profile_input(): void
    {
        Http::fake(fn () => Http::response([], 404));

        $orphan = $this->creators->createOrphan($this->admin, ['display_name' => 'Encoded Orphan']);

        $channel = $this->channels->registerPendingForStaff($this->admin, $orphan->id, [
            'platform_key' => 'youtube',
            'profile' => 'https://www.youtube.com/@%E5%85%AB%E9%9B%B2%E3%81%B9%E3%81%AB',
        ]);

        $this->assertSame('https://www.youtube.com/@八雲べに', $channel->profile_input);
    }

    #[Test]
    public function register_pending_rejects_youtube_url_when_twitch_selected(): void
    {
        Http::fake(fn () => Http::response([], 404));

        $orphan = $this->creators->createOrphan($this->admin, ['display_name' => 'Mismatch Orphan']);

        $this->expectException(ValidationException::class);
        $this->channels->registerPendingForStaff($this->admin, $orphan->id, [
            'platform_key' => 'twitch',
            'profile' => 'https://www.youtube.com/@AizawaEma',
        ]);
    }

    #[Test]
    public function register_pending_rejects_twitch_url_when_youtube_selected(): void
    {
        Http::fake(fn () => Http::response([], 404));

        $orphan = $this->creators->createOrphan($this->admin, ['display_name' => 'Mismatch Orphan 2']);

        $this->expectException(ValidationException::class);
        $this->channels->registerPendingForStaff($this->admin, $orphan->id, [
            'platform_key' => 'youtube',
            'profile' => 'https://www.twitch.tv/ludwig',
        ]);
    }

    #[Test]
    public function register_pending_rejects_duplicate_profile_on_same_creator(): void
    {
        Http::fake(fn () => Http::response([], 404));

        $orphan = $this->creators->createOrphan($this->admin, ['display_name' => 'Dup Orphan']);

        $this->channels->registerPendingForStaff($this->admin, $orphan->id, [
            'platform_key' => 'youtube',
            'profile' => 'https://www.youtube.com/@AizawaEma',
        ]);

        $this->expectException(ValidationException::class);
        $this->channels->registerPendingForStaff($this->admin, $orphan->id, [
            'platform_key' => 'youtube',
            'profile' => '@AizawaEma',
        ]);
    }

    #[Test]
    public function register_pending_for_staff_rejects_non_staff(): void
    {
        $user = $this->userWithRole('User');
        $creator = $this->creators->create($user, ['display_name' => 'Owned']);

        $this->expectException(VtualException::class);
        $this->channels->registerPendingForStaff($user, $creator->id, [
            'platform_key' => 'youtube',
            'profile' => self::YT_ID,
        ]);
    }

    #[Test]
    public function seed_for_staff_resolves_and_activates_without_claim(): void
    {
        $this->activateYoutubeCredential();
        $this->fakeYoutubeChannel(self::YT_ID, 'stream', 'Stream Channel');

        $orphan = $this->creators->createOrphan($this->admin, ['display_name' => 'Orphan Seed']);

        $channel = $this->channels->seedForStaff($this->admin, $orphan->id, [
            'platform_key' => 'youtube',
            'profile' => '@stream',
        ]);

        $this->assertSame(ChannelStatus::Active, $channel->status);
        $this->assertSame(self::YT_ID, $channel->external_id);
        $this->assertSame('stream', $channel->handle);
        $this->assertSame('Stream Channel', $channel->display_name);
        $this->assertSame((string) $orphan->id, (string) $channel->creator_id);
        $this->assertSame(0, ChannelClaim::query()->count());
    }

    #[Test]
    public function seed_for_staff_rejects_non_staff(): void
    {
        $this->activateYoutubeCredential();
        $this->fakeYoutubeChannel(self::YT_ID, 'stream', 'Stream Channel');

        $user = $this->userWithRole('User');
        $creator = $this->creators->create($user, ['display_name' => 'Owned']);

        $this->expectException(VtualException::class);
        $this->channels->seedForStaff($user, $creator->id, [
            'platform_key' => 'youtube',
            'profile' => self::YT_ID,
        ]);
    }

    #[Test]
    public function activate_youtube_dto_stores_canonical_snapshot(): void
    {
        $this->activateYoutubeCredential();
        $this->fakeYoutubeChannel(self::YT_ID, 'stream', 'Stream Channel');

        $user = $this->userWithRole('User');
        $creator = $this->creators->create($user, ['display_name' => 'Stream']);

        $dto = $this->profiles->resolve(PlatformKey::Youtube, '@stream');
        $channel = $this->channels->activateFromDto($creator->id, $this->youtube, $dto);

        $this->assertSame(ChannelStatus::Active, $channel->status);
        $this->assertSame(self::YT_ID, $channel->external_id);
        $this->assertSame('stream', $channel->handle);
        $this->assertSame('Stream Channel', $channel->display_name);
        $this->assertSame('YouTube channel bio', $channel->description);
        $this->assertSame('https://img.example/hi.jpg', $channel->avatar_url);
        $this->assertSame('https://img.example/banner.jpg', $channel->banner_url);
        $this->assertNotNull($channel->platform_created_at);
        $this->assertSame('2017-04-19', $channel->platform_created_at->toDateString());
        $this->assertNotNull($channel->last_synced_at);
    }

    #[Test]
    public function activate_youtube_dispatches_initial_content_backfill_job(): void
    {
        $user = $this->userWithRole('User');
        $creator = $this->creators->create($user, ['display_name' => 'Stream']);

        $dto = new PlatformChannelDto(
            platform: PlatformKey::Youtube,
            externalId: self::YT_ID,
            handle: 'stream',
            displayName: 'Stream Channel',
            description: 'bio',
            thumbnailUrl: null,
            url: null,
        );

        $channel = $this->channels->activateFromDto($creator->id, $this->youtube, $dto);

        Bus::assertDispatched(RunChannelInitialContentBackfillJob::class, function (RunChannelInitialContentBackfillJob $job) use ($channel): bool {
            return $job->channelId === (string) $channel->id;
        });
    }

    #[Test]
    public function activate_twitch_does_not_dispatch_initial_content_backfill_job(): void
    {
        $this->activateTwitchCredential();
        Http::fake([
            'id.twitch.tv/oauth2/token*' => Http::response([
                'access_token' => 'app-token',
                'expires_in' => 3600,
                'token_type' => 'bearer',
            ], 200),
            'api.twitch.tv/helix/users*' => Http::response([
                'data' => [
                    [
                        'id' => '40934651',
                        'login' => 'ludwig',
                        'display_name' => 'Ludwig',
                        'description' => 'Twitch bio here',
                        'profile_image_url' => 'https://img.example/l.png',
                        'offline_image_url' => 'https://img.example/offline.png',
                        'created_at' => '2019-10-10T07:35:05Z',
                    ],
                ],
            ], 200),
        ]);

        $user = $this->userWithRole('User');
        $creator = $this->creators->create($user, ['display_name' => 'Stream']);

        $dto = $this->profiles->resolve(PlatformKey::Twitch, 'https://www.twitch.tv/ludwig');
        $channel = $this->channels->activateFromDto($creator->id, $this->twitch, $dto);

        $this->assertSame('40934651', $channel->external_id);
        $this->assertSame('ludwig', $channel->handle);
        $this->assertSame('Twitch bio here', $channel->description);
        $this->assertSame('https://img.example/l.png', $channel->avatar_url);
        $this->assertSame('https://img.example/offline.png', $channel->banner_url);
        $this->assertNotNull($channel->platform_created_at);
        $this->assertSame('2019-10-10', $channel->platform_created_at->toDateString());

        Bus::assertNotDispatched(RunChannelInitialContentBackfillJob::class);
    }

    #[Test]
    public function duplicate_platform_external_id_rejected(): void
    {
        $user = $this->userWithRole('User');
        $creator = $this->creators->create($user, ['display_name' => 'Stream']);

        $dto = new PlatformChannelDto(
            platform: PlatformKey::Youtube,
            externalId: self::YT_ID,
            handle: 'stream',
            displayName: 'Stream',
            description: 'bio',
            thumbnailUrl: null,
            url: 'https://youtube.com/channel/'.self::YT_ID,
            bannerUrl: null,
            platformCreatedAt: Carbon::parse('2017-04-19T14:57:55Z'),
        );

        $this->channels->activateFromDto($creator->id, $this->youtube, $dto);

        $this->expectException(VtualException::class);
        $this->channels->activateFromDto($creator->id, $this->youtube, $dto);
    }

    #[Test]
    public function refresh_enqueues_pool_item_without_immediate_http(): void
    {
        $this->activateYoutubeCredential();

        Http::fake([
            'www.googleapis.com/youtube/v3/channels*' => Http::response([
                'items' => [
                    [
                        'id' => self::YT_ID,
                        'snippet' => [
                            'title' => 'Old Name',
                            'description' => 'Old bio',
                            'customUrl' => '@oldhandle',
                            'publishedAt' => '2017-04-19T14:57:55Z',
                            'thumbnails' => [
                                'high' => ['url' => 'https://img.example/old-av.jpg'],
                            ],
                        ],
                        'brandingSettings' => [
                            'image' => [
                                'bannerExternalUrl' => 'https://img.example/old-banner.jpg',
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $user = $this->userWithRole('User');
        $creator = $this->creators->create($user, ['display_name' => 'Stream']);

        $dto = $this->profiles->resolve(PlatformKey::Youtube, self::YT_ID);
        $channel = $this->channels->activateFromDto($creator->id, $this->youtube, $dto);

        Http::fake([
            'www.googleapis.com/youtube/v3/channels*' => function (): void {
                $this->fail('refresh() must not call platform HTTP directly; it should enqueue.');
            },
        ]);

        $result = $this->channels->refresh($user, $channel->id);

        $this->assertSame((string) $channel->id, (string) $result->id);
        $this->assertSame(1, ChannelFetchPoolItem::query()
            ->where('platform_id', $this->youtube->id)
            ->where('external_id', self::YT_ID)
            ->where('status', ChannelFetchPoolStatus::Pending->value)
            ->count());
    }

    #[Test]
    public function archive_sets_status_and_hides_from_list(): void
    {
        $user = $this->userWithRole('User');
        $creator = $this->creators->create($user, ['display_name' => 'Stream']);

        $dto = new PlatformChannelDto(
            platform: PlatformKey::Youtube,
            externalId: self::YT_ID,
            handle: 'stream',
            displayName: 'Stream Channel',
            description: 'bio',
            thumbnailUrl: null,
            url: null,
        );
        $channel = $this->channels->activateFromDto($creator->id, $this->youtube, $dto);

        $archived = $this->channels->archive($user, $channel->id);

        $this->assertSame(ChannelStatus::Archived, $archived->status);
        $this->assertCount(0, $this->channels->listForCreator($user, $creator->id));
        $this->assertCount(1, $this->channels->listArchivedForCreator($user, $creator->id));
    }

    #[Test]
    public function archive_rejects_pending_channel(): void
    {
        Http::fake(fn () => Http::response([], 404));

        $orphan = $this->creators->createOrphan($this->admin, ['display_name' => 'Pending Archive']);
        $channel = $this->channels->registerPendingForStaff($this->admin, $orphan->id, [
            'platform_key' => 'youtube',
            'profile' => '@pending-archive',
        ]);

        $this->expectException(VtualException::class);
        $this->channels->archive($this->admin, $channel->id);
    }

    #[Test]
    public function delete_pending_force_deletes_and_frees_fingerprint(): void
    {
        Http::fake(fn () => Http::response([], 404));

        $orphan = $this->creators->createOrphan($this->admin, ['display_name' => 'Pending Delete']);
        $channel = $this->channels->registerPendingForStaff($this->admin, $orphan->id, [
            'platform_key' => 'youtube',
            'profile' => '@pending-delete',
        ]);
        $channelId = (string) $channel->id;

        $this->channels->deletePending($this->admin, $channelId);

        $this->assertSame(0, Channel::withTrashed()->whereKey($channelId)->count());

        $again = $this->channels->registerPendingForStaff($this->admin, $orphan->id, [
            'platform_key' => 'youtube',
            'profile' => '@pending-delete',
        ]);
        $this->assertSame(ChannelStatus::Pending, $again->status);
    }

    #[Test]
    public function delete_pending_rejects_active_channel(): void
    {
        $user = $this->userWithRole('User');
        $creator = $this->creators->create($user, ['display_name' => 'Bound']);

        $dto = new PlatformChannelDto(
            platform: PlatformKey::Youtube,
            externalId: self::YT_ID,
            handle: 'bound',
            displayName: 'Bound Channel',
            description: 'bio',
            thumbnailUrl: null,
            url: null,
        );
        $channel = $this->channels->activateFromDto($creator->id, $this->youtube, $dto);

        $this->expectException(VtualException::class);
        $this->channels->deletePending($user, $channel->id);
    }

    #[Test]
    public function archive_keeps_websub_subscription_active(): void
    {
        Http::fake([
            'pubsubhubbub.appspot.com/*' => Http::response('', 202),
        ]);

        $user = $this->userWithRole('User');
        $creator = $this->creators->create($user, ['display_name' => 'Stream']);

        $dto = new PlatformChannelDto(
            platform: PlatformKey::Youtube,
            externalId: self::YT_ID,
            handle: 'stream',
            displayName: 'Stream Channel',
            description: 'bio',
            thumbnailUrl: null,
            url: null,
        );
        $channel = $this->channels->activateFromDto($creator->id, $this->youtube, $dto);

        $subscription = WebsubSubscription::query()->where('channel_id', $channel->id)->first();
        $this->assertNotNull($subscription);
        $subscription->forceFill(['status' => WebsubSubscriptionStatus::Active])->save();

        $this->channels->archive($user, $channel->id);

        $this->assertSame(
            WebsubSubscriptionStatus::Active,
            WebsubSubscription::query()->where('channel_id', $channel->id)->value('status'),
        );
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'pubsubhubbub.appspot.com')
            && ($request->data()['hub.mode'] ?? null) === 'unsubscribe');
    }

    #[Test]
    public function restore_dispatches_stub_backfill_job(): void
    {
        Http::fake([
            'pubsubhubbub.appspot.com/*' => Http::response('', 202),
        ]);

        $user = $this->userWithRole('User');
        $creator = $this->creators->create($user, ['display_name' => 'Stream']);

        $dto = new PlatformChannelDto(
            platform: PlatformKey::Youtube,
            externalId: self::YT_ID,
            handle: 'stream',
            displayName: 'Stream Channel',
            description: 'bio',
            thumbnailUrl: null,
            url: null,
        );
        $channel = $this->channels->activateFromDto($creator->id, $this->youtube, $dto);
        $this->channels->archive($user, $channel->id);

        Bus::fake();
        $this->clearChannelLifecycleLimits($user, $channel->id);
        $this->channels->restore($user, $channel->id);

        Bus::assertDispatched(RunChannelStubContentBackfillJob::class, function (RunChannelStubContentBackfillJob $job) use ($channel): bool {
            return $job->channelId === (string) $channel->id;
        });
    }

    #[Test]
    public function restore_returns_channel_to_active_list(): void
    {
        $user = $this->userWithRole('User');
        $creator = $this->creators->create($user, ['display_name' => 'Stream']);

        $dto = new PlatformChannelDto(
            platform: PlatformKey::Youtube,
            externalId: self::YT_ID,
            handle: 'stream',
            displayName: 'Stream Channel',
            description: 'bio',
            thumbnailUrl: null,
            url: null,
        );
        $channel = $this->channels->activateFromDto($creator->id, $this->youtube, $dto);
        $this->channels->archive($user, $channel->id);

        $this->clearChannelLifecycleLimits($user, $channel->id);
        $restored = $this->channels->restore($user, $channel->id);

        $this->assertSame(ChannelStatus::Active, $restored->status);
        $this->assertCount(1, $this->channels->listForCreator($user, $creator->id));
        $this->assertCount(0, $this->channels->listArchivedForCreator($user, $creator->id));
    }

    #[Test]
    public function restore_rejects_non_archived_channel(): void
    {
        $user = $this->userWithRole('User');
        $creator = $this->creators->create($user, ['display_name' => 'Stream']);

        $dto = new PlatformChannelDto(
            platform: PlatformKey::Youtube,
            externalId: self::YT_ID,
            handle: 'stream',
            displayName: 'Stream Channel',
            description: 'bio',
            thumbnailUrl: null,
            url: null,
        );
        $channel = $this->channels->activateFromDto($creator->id, $this->youtube, $dto);

        $this->expectException(VtualException::class);
        $this->channels->restore($user, $channel->id);
    }

    #[Test]
    public function restore_rejects_non_member(): void
    {
        $owner = $this->userWithRole('User');
        $peer = $this->userWithRole('User');
        $creator = $this->creators->create($owner, ['display_name' => 'Stream']);

        $dto = new PlatformChannelDto(
            platform: PlatformKey::Youtube,
            externalId: self::YT_ID,
            handle: 'stream',
            displayName: 'Stream Channel',
            description: 'bio',
            thumbnailUrl: null,
            url: null,
        );
        $channel = $this->channels->activateFromDto($creator->id, $this->youtube, $dto);
        $this->channels->archive($owner, $channel->id);

        $this->expectException(VtualException::class);
        $this->channels->restore($peer, $channel->id);
    }

    #[Test]
    public function restore_resubscribes_websub_from_unsubscribed_state(): void
    {
        Http::fake([
            'pubsubhubbub.appspot.com/*' => Http::response('', 202),
        ]);

        $user = $this->userWithRole('User');
        $creator = $this->creators->create($user, ['display_name' => 'Legacy WebSub']);

        $dto = new PlatformChannelDto(
            platform: PlatformKey::Youtube,
            externalId: self::YT_ID,
            handle: 'stream',
            displayName: 'Stream Channel',
            description: 'bio',
            thumbnailUrl: null,
            url: null,
        );
        $channel = $this->channels->activateFromDto($creator->id, $this->youtube, $dto);

        WebsubSubscription::query()->where('channel_id', $channel->id)->update([
            'status' => WebsubSubscriptionStatus::Unsubscribed->value,
        ]);

        $this->channels->archive($user, $channel->id);
        $this->clearChannelLifecycleLimits($user, $channel->id);

        $this->channels->restore($user, $channel->id);

        $this->assertSame(
            WebsubSubscriptionStatus::Pending,
            WebsubSubscription::query()->where('channel_id', $channel->id)->value('status'),
        );
        Http::assertSent(fn ($request) => str_contains($request->url(), 'pubsubhubbub.appspot.com')
            && ($request->data()['hub.mode'] ?? null) === 'subscribe');
    }

    #[Test]
    public function archive_rate_limited_when_channel_cooldown_active(): void
    {
        $user = $this->userWithRole('User');
        $creator = $this->creators->create($user, ['display_name' => 'Cooldown Fox']);

        $dto = new PlatformChannelDto(
            platform: PlatformKey::Youtube,
            externalId: self::YT_ID,
            handle: 'stream',
            displayName: 'Stream Channel',
            description: 'bio',
            thumbnailUrl: null,
            url: null,
        );
        $channel = $this->channels->activateFromDto($creator->id, $this->youtube, $dto);

        $this->channels->archive($user, $channel->id);

        try {
            $this->channels->restore($user, $channel->id);
            $this->fail('Expected VtualException for channel cooldown');
        } catch (VtualException $e) {
            $this->assertSame(429, $e->statusCode());
            $this->assertStringContainsString('seconds', strtolower($e->getMessage()));
        }
    }

    #[Test]
    public function restore_rate_limited_when_user_burst_exceeded(): void
    {
        $user = $this->userWithRole('User');
        $creator = $this->creators->create($user, ['display_name' => 'Burst Fox']);

        $dto = new PlatformChannelDto(
            platform: PlatformKey::Youtube,
            externalId: self::YT_ID,
            handle: 'stream',
            displayName: 'Stream Channel',
            description: 'bio',
            thumbnailUrl: null,
            url: null,
        );
        $channel = $this->channels->activateFromDto($creator->id, $this->youtube, $dto);

        $userKey = 'vtual:channel-lifecycle:user:'.$user->id;
        for ($i = 0; $i < 10; $i++) {
            RateLimiter::hit($userKey, 60);
        }

        try {
            $this->channels->archive($user, $channel->id);
            $this->fail('Expected VtualException for user burst limit');
        } catch (VtualException $e) {
            $this->assertSame(429, $e->statusCode());
            $this->assertStringContainsString('archive/restore', strtolower($e->getMessage()));
        }
    }

    #[Test]
    public function refresh_rejects_archived_channel(): void
    {
        $this->activateYoutubeCredential();
        $this->fakeYoutubeChannel(self::YT_ID, 'stream', 'Stream Channel');

        $user = $this->userWithRole('User');
        $creator = $this->creators->create($user, ['display_name' => 'Stream']);

        $dto = $this->profiles->resolve(PlatformKey::Youtube, self::YT_ID);
        $channel = $this->channels->activateFromDto($creator->id, $this->youtube, $dto);
        $this->channels->archive($user, $channel->id);

        $this->expectException(VtualException::class);
        $this->channels->refresh($user, $channel->id);
    }

    private function clearChannelLifecycleLimits(User $user, string $channelId): void
    {
        RateLimiter::clear('vtual:channel-lifecycle:channel:'.$channelId);
        RateLimiter::clear('vtual:channel-lifecycle:user:'.$user->id);
    }

    private function fakeYoutubeChannel(string $id, string $handle, string $title): void
    {
        Http::fake([
            'www.googleapis.com/youtube/v3/channels*' => Http::response([
                'items' => [
                    [
                        'id' => $id,
                        'snippet' => [
                            'title' => $title,
                            'description' => 'YouTube channel bio',
                            'customUrl' => '@'.$handle,
                            'publishedAt' => '2017-04-19T14:57:55Z',
                            'thumbnails' => [
                                'high' => ['url' => 'https://img.example/hi.jpg'],
                            ],
                        ],
                        'brandingSettings' => [
                            'image' => [
                                'bannerExternalUrl' => 'https://img.example/banner.jpg',
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);
    }

    private function activateYoutubeCredential(): void
    {
        $credential = $this->credentials->create($this->admin, $this->youtube, [
            'label' => 'yt-channel-test',
            'type' => PlatformCredentialType::ApiKey->value,
            'secret' => ['api_key' => 'yt-api-test-key'],
        ]);
        $credential->forceFill([
            'status' => PlatformCredentialStatus::Active,
            'verified_at' => now(),
        ])->save();
    }

    private function activateTwitchCredential(): void
    {
        $credential = $this->credentials->create($this->admin, $this->twitch, [
            'label' => 'tw-channel-test',
            'type' => PlatformCredentialType::OauthApp->value,
            'secret' => [
                'client_id' => 'tw-client',
                'client_secret' => 'tw-secret',
            ],
        ]);
        $credential->forceFill([
            'status' => PlatformCredentialStatus::Active,
            'verified_at' => now(),
        ])->save();
    }

    private function userWithRole(string $role): User
    {
        $user = User::query()->create([
            'name' => $role.' User',
            'email' => strtolower($role).'-ch-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        $user->assignRole($role);

        return $user;
    }
}
