<?php

namespace Tests\Domain\Vtual\Unit\Integrations;

use App\Domain\ErrorLog\Models\ErrorLog;
use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\PlatformCredentialStatus;
use App\Domain\Vtual\Enums\PlatformCredentialType;
use App\Domain\Vtual\Enums\PlatformKey;
use App\Domain\Vtual\Exceptions\PlatformApiException;
use App\Domain\Vtual\Exceptions\UnsupportedPlatformOperationException;
use App\Domain\Vtual\Exceptions\VtualException;
use App\Domain\Vtual\Integrations\Dto\PlatformVideoDto;
use App\Domain\Vtual\Integrations\PlatformApiGateway;
use App\Domain\Vtual\Models\Platform;
use App\Domain\Vtual\Services\PlatformCredentialService;
use App\Domain\Vtual\Services\PlatformService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class PlatformApiGatewayTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private PlatformApiGateway $gateway;

    private PlatformCredentialService $credentials;

    private Platform $youtube;

    private Platform $twitch;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        app(PlatformService::class)->seedDefaults();
        $this->gateway = app(PlatformApiGateway::class);
        $this->credentials = app(PlatformCredentialService::class);
        $this->youtube = Platform::query()->where('key', 'youtube')->firstOrFail();
        $this->twitch = Platform::query()->where('key', 'twitch')->firstOrFail();
        $this->admin = $this->userWithRole('Admin');
    }

    #[Test]
    public function youtube_fetch_videos_maps_dto_and_sends_parts_key_and_comma_ids(): void
    {
        $this->activateYoutubeCredential('yt-key-secret-aaaa');

        Http::fake([
            'www.googleapis.com/youtube/v3/videos*' => Http::response([
                'items' => [
                    [
                        'id' => 'vid1',
                        'snippet' => [
                            'title' => 'Hello',
                            'description' => 'Desc',
                            'channelId' => 'UCabc',
                            'publishedAt' => '2026-01-01T00:00:00Z',
                            'liveBroadcastContent' => 'none',
                            'thumbnails' => [
                                'high' => ['url' => 'https://img.example/hi.jpg'],
                            ],
                        ],
                        'contentDetails' => ['duration' => 'PT1H2M3S'],
                        'statistics' => ['viewCount' => '42'],
                        'status' => ['privacyStatus' => 'public'],
                        'liveStreamingDetails' => [],
                    ],
                ],
            ], 200),
        ]);

        $result = $this->gateway->fetchVideos(PlatformKey::Youtube, ['vid1', 'vid2']);
        $this->assertCount(1, $result);
        /** @var PlatformVideoDto $video */
        $video = $result->first();
        $this->assertSame(PlatformKey::Youtube, $video->platform);
        $this->assertSame('vid1', $video->externalId);
        $this->assertSame('Hello', $video->title);
        $this->assertSame('UCabc', $video->channelExternalId);
        $this->assertSame('uploaded', $video->status);
        $this->assertSame(3723, $video->durationSeconds);
        $this->assertSame(42, $video->viewCount);
        $this->assertStringContainsString('watch?v=vid1', (string) $video->url);

        Http::assertSent(function ($request): bool {
            if (! str_contains($request->url(), 'youtube/v3/videos')) {
                return false;
            }
            $url = $request->url();
            $this->assertStringContainsString('id=vid1%2Cvid2', $url);
            $this->assertStringContainsString('key=yt-key-secret-aaaa', $url);
            $this->assertStringContainsString('part=', $url);
            $this->assertStringContainsString('snippet', $url);
            $this->assertStringNotContainsString('access_token', $url);

            return true;
        });
    }

    #[Test]
    public function youtube_invalid_part_throws(): void
    {
        $this->activateYoutubeCredential('yt-key');

        $this->expectException(VtualException::class);
        $this->gateway->fetchVideos(PlatformKey::Youtube, ['vid1'], ['notARealPart']);
    }

    #[Test]
    public function batch_over_50_throws_without_http(): void
    {
        $this->activateYoutubeCredential('yt-key');
        Http::fake();

        $ids = array_map(fn (int $i): string => 'v'.$i, range(1, 51));
        try {
            $this->gateway->fetchVideos(PlatformKey::Youtube, $ids);
            $this->fail('Expected batch too large');
        } catch (VtualException $e) {
            $this->assertStringContainsString('50', $e->getMessage());
        }

        Http::assertNothingSent();
    }

    #[Test]
    public function batch_of_50_sends_one_request(): void
    {
        $this->activateYoutubeCredential('yt-key');
        Http::fake([
            'www.googleapis.com/youtube/v3/videos*' => Http::response(['items' => []], 200),
        ]);

        $ids = array_map(fn (int $i): string => 'v'.$i, range(1, 50));
        $this->gateway->fetchVideos(PlatformKey::Youtube, $ids);
        Http::assertSentCount(1);
    }

    #[Test]
    public function fetch_videos_chunked_splits(): void
    {
        $this->activateYoutubeCredential('yt-key');
        Http::fake([
            'www.googleapis.com/youtube/v3/videos*' => Http::response(['items' => []], 200),
        ]);

        $ids = array_map(fn (int $i): string => 'v'.$i, range(1, 75));
        $this->gateway->fetchVideosChunked(PlatformKey::Youtube, $ids);
        Http::assertSentCount(2);
    }

    #[Test]
    public function twitch_fetch_videos_uses_bearer_and_repeated_ids(): void
    {
        $this->activateTwitchCredential();

        Http::fake([
            'id.twitch.tv/oauth2/token' => Http::response([
                'access_token' => 'app-token-secret-value',
                'expires_in' => 3600,
                'token_type' => 'bearer',
            ], 200),
            'api.twitch.tv/helix/users*' => Http::response([
                'data' => [['id' => '1', 'login' => 'twitch']],
            ], 200),
            'api.twitch.tv/helix/videos*' => Http::response([
                'data' => [
                    [
                        'id' => '9001',
                        'user_id' => '123',
                        'title' => 'VOD',
                        'description' => '',
                        'created_at' => '2026-01-01T00:00:00Z',
                        'published_at' => '2026-01-01T00:00:00Z',
                        'url' => 'https://www.twitch.tv/videos/9001',
                        'thumbnail_url' => 'https://static-cdn.jtvnw.net/cf_vods/x-%{width}x%{height}.jpg',
                        'view_count' => 10,
                        'type' => 'archive',
                        'duration' => '1h2m3s',
                    ],
                ],
            ], 200),
        ]);

        // Test mints token into runtime.
        $cred = $this->credentials->pickActive($this->twitch);
        $this->assertNotNull($cred);
        $this->assertTrue($this->credentials->test($cred)->ok);

        $result = $this->gateway->fetchVideos(PlatformKey::Twitch, ['9001', '9002']);
        $this->assertCount(1, $result);
        $video = $result->first();
        $this->assertSame('9001', $video->externalId);
        $this->assertSame('ended', $video->status);
        $this->assertSame(3723, $video->durationSeconds);

        Http::assertSent(function ($request): bool {
            if (! str_contains($request->url(), 'helix/videos')) {
                return false;
            }
            $this->assertStringContainsString('id=9001', $request->url());
            $this->assertStringContainsString('id=9002', $request->url());
            $this->assertSame('Bearer app-token-secret-value', $request->header('Authorization')[0] ?? null);
            $this->assertSame('tw-client-id', $request->header('Client-Id')[0] ?? null);
            $this->assertStringNotContainsString('app-token-secret-value', json_encode($request->data()) ?: '');

            return true;
        });
    }

    #[Test]
    public function youtube_fetch_streams_unsupported(): void
    {
        $this->activateYoutubeCredential('yt-key');
        $this->expectException(UnsupportedPlatformOperationException::class);
        $this->gateway->fetchStreamsByUserIds(PlatformKey::Youtube, ['UCabc']);
    }

    #[Test]
    public function twitch_fetch_streams_by_user_ids(): void
    {
        $this->activateTwitchCredential();
        Http::fake([
            'id.twitch.tv/oauth2/token' => Http::response([
                'access_token' => 'tok',
                'expires_in' => 3600,
                'token_type' => 'bearer',
            ], 200),
            'api.twitch.tv/helix/users*' => Http::response(['data' => [['id' => '1', 'login' => 'twitch']]], 200),
            'api.twitch.tv/helix/streams*' => Http::response([
                'data' => [
                    [
                        'id' => 'stream1',
                        'user_id' => '99',
                        'user_login' => 'streamer',
                        'user_name' => 'Streamer',
                        'title' => 'Live now',
                        'game_name' => 'Just Chatting',
                        'viewer_count' => 120,
                        'started_at' => '2026-07-22T01:00:00Z',
                        'language' => 'en',
                        'thumbnail_url' => 'https://static-cdn.jtvnw.net/previews-ttv/live_user_x-{width}x{height}.jpg',
                    ],
                ],
            ], 200),
        ]);

        $cred = $this->credentials->pickActive($this->twitch);
        $this->assertTrue($this->credentials->test($cred)->ok);

        $streams = $this->gateway->fetchStreamsByUserIds(PlatformKey::Twitch, ['99']);
        $this->assertCount(1, $streams);
        $stream = $streams->first();
        $this->assertSame('stream1', $stream->externalId);
        $this->assertSame(120, $stream->concurrentViewers);
        $this->assertTrue($stream->isLive);
    }

    #[Test]
    public function no_active_credential_throws(): void
    {
        $this->expectException(VtualException::class);
        $this->gateway->fetchVideos(PlatformKey::Youtube, ['vid1']);
    }

    #[Test]
    public function youtube_401_marks_failed_and_message_hides_key(): void
    {
        $this->activateYoutubeCredential('super-secret-api-key-zzzz');

        Http::fake([
            'www.googleapis.com/youtube/v3/videos*' => Http::response([
                'error' => ['message' => 'API key not valid. Please pass a valid API key.'],
            ], 401),
        ]);

        try {
            $this->gateway->fetchVideos(PlatformKey::Youtube, ['vid1']);
            $this->fail('Expected PlatformApiException');
        } catch (PlatformApiException $e) {
            $this->assertSame(401, $e->upstreamStatus);
            $this->assertStringNotContainsString('super-secret-api-key-zzzz', $e->getMessage());
        }

        // Still active status; recordAuthFailure stamps error + counter; default failover OFF → no skip.
        $failed = $this->youtube->credentials()->first();
        $this->assertNotNull($failed?->last_error_at);
        $this->assertStringNotContainsString('super-secret-api-key-zzzz', (string) $failed->last_error_message);
        $this->assertSame(1, (int) $failed->consecutive_auth_failures);
        $this->assertNull($failed->failover_skip_until);
        $this->assertSame(PlatformCredentialStatus::Active, $failed->status);
        Http::assertSentCount(1);

        // Ops signal: ErrorLog via PlatformHttp (BRE PlatformApiException would not auto-store).
        $this->assertDatabaseCount('error_logs', 1);
        $row = ErrorLog::query()->firstOrFail();
        $this->assertSame(ErrorLog::TYPE_LOG, $row->type);
        $this->assertStringContainsString('youtube', strtolower($row->message));
        $this->assertStringNotContainsString('super-secret-api-key-zzzz', $row->message);
        $context = $row->context ?? [];
        $this->assertSame('vtual', $context['domain'] ?? null);
        $this->assertSame('platform_http', $context['component'] ?? null);
        $this->assertSame('youtube', $context['platform'] ?? null);
        $this->assertSame(401, $context['upstream_status'] ?? null);
        $this->assertSame((string) $failed->id, $context['credential_id'] ?? null);
        $this->assertSame('www.googleapis.com', $context['url_host'] ?? null);
        $this->assertStringContainsString('/youtube/v3/videos', (string) ($context['url_path'] ?? ''));
        $this->assertStringNotContainsString('key=', json_encode($context) ?: '');
        $this->assertStringNotContainsString('super-secret-api-key-zzzz', json_encode($context) ?: '');
    }

    #[Test]
    public function youtube_500_writes_error_log_without_mark_failed(): void
    {
        $this->activateYoutubeCredential('yt-key-no-leak');

        Http::fake([
            'www.googleapis.com/youtube/v3/videos*' => Http::response([
                'error' => ['message' => 'Backend Error'],
            ], 500),
        ]);

        try {
            $this->gateway->fetchVideos(PlatformKey::Youtube, ['vid1']);
            $this->fail('Expected PlatformApiException');
        } catch (PlatformApiException $e) {
            $this->assertSame(500, $e->upstreamStatus);
        }

        $cred = $this->youtube->credentials()->first();
        $this->assertNull($cred?->last_error_at);

        $this->assertDatabaseCount('error_logs', 1);
        $row = ErrorLog::query()->firstOrFail();
        $context = $row->context ?? [];
        $this->assertSame(500, $context['upstream_status'] ?? null);
        $this->assertSame('youtube', $context['platform'] ?? null);
        $this->assertSame((string) $cred->id, $context['credential_id'] ?? null);
        $this->assertStringNotContainsString('yt-key-no-leak', $row->message);
        $this->assertStringNotContainsString('yt-key-no-leak', json_encode($context) ?: '');
    }

    #[Test]
    public function credential_mark_failed_writes_error_log(): void
    {
        $this->activateYoutubeCredential('yt-mark-failed-key');
        $credential = $this->youtube->credentials()->firstOrFail();

        $this->credentials->markFailed($credential, 'Test probe rejected api key');

        $this->assertDatabaseCount('error_logs', 1);
        $row = ErrorLog::query()->firstOrFail();
        $this->assertStringContainsString('Test probe rejected', $row->message);
        $context = $row->context ?? [];
        $this->assertSame('platform_credential', $context['component'] ?? null);
        $this->assertSame('credential_mark_failed', $context['event'] ?? null);
        $this->assertSame((string) $credential->id, $context['credential_id'] ?? null);
        $this->assertSame('youtube', $context['platform'] ?? null);
    }

    #[Test]
    public function failover_on_switches_to_next_credential_after_401(): void
    {
        config([
            'vtual.credential.failover.enabled' => true,
            'vtual.credential.failover.auth_fail_threshold' => 1,
            'vtual.credential.failover.max_retries_per_call' => 1,
            'vtual.credential.failover.skip_minutes' => 30,
        ]);

        $primary = $this->activateYoutubeCredential('bad-key-primary', priority: 10, label: 'yt-primary');
        $backup = $this->activateYoutubeCredential('good-key-backup', priority: 20, label: 'yt-backup');

        Http::fake([
            'www.googleapis.com/youtube/v3/videos*' => Http::sequence()
                ->push(['error' => ['message' => 'API key not valid']], 401)
                ->push([
                    'items' => [
                        [
                            'id' => 'vid1',
                            'snippet' => [
                                'title' => 'OK',
                                'description' => '',
                                'channelId' => 'UCabc',
                                'publishedAt' => '2026-01-01T00:00:00Z',
                                'liveBroadcastContent' => 'none',
                                'thumbnails' => [],
                            ],
                            'contentDetails' => ['duration' => 'PT1M'],
                            'statistics' => ['viewCount' => '1'],
                            'status' => ['privacyStatus' => 'public'],
                            'liveStreamingDetails' => [],
                        ],
                    ],
                ], 200),
        ]);

        $result = $this->gateway->fetchVideos(PlatformKey::Youtube, ['vid1']);
        $this->assertCount(1, $result);
        Http::assertSentCount(2);

        $primary->refresh();
        $backup->refresh();
        $this->assertSame(1, (int) $primary->consecutive_auth_failures);
        $this->assertNotNull($primary->failover_skip_until);
        $this->assertSame(PlatformCredentialStatus::Active, $primary->status);
        $this->assertSame(0, (int) $backup->consecutive_auth_failures);
        $this->assertNull($backup->failover_skip_until);
        $this->assertNotNull($backup->last_used_at);

        $picked = $this->credentials->pickActive($this->youtube);
        $this->assertSame((string) $backup->id, (string) $picked?->id);
    }

    #[Test]
    public function failover_on_single_credential_does_not_loop(): void
    {
        config([
            'vtual.credential.failover.enabled' => true,
            'vtual.credential.failover.auth_fail_threshold' => 1,
            'vtual.credential.failover.max_retries_per_call' => 1,
        ]);

        $only = $this->activateYoutubeCredential('only-bad-key', priority: 10);

        Http::fake([
            'www.googleapis.com/youtube/v3/videos*' => Http::response([
                'error' => ['message' => 'API key not valid'],
            ], 401),
        ]);

        try {
            $this->gateway->fetchVideos(PlatformKey::Youtube, ['vid1']);
            $this->fail('Expected PlatformApiException');
        } catch (PlatformApiException $e) {
            $this->assertSame(401, $e->upstreamStatus);
        }

        Http::assertSentCount(1);
        $only->refresh();
        $this->assertNotNull($only->failover_skip_until);
        $this->assertSame(1, (int) $only->consecutive_auth_failures);
    }

    #[Test]
    public function youtube_500_does_not_soft_demote_or_switch(): void
    {
        config([
            'vtual.credential.failover.enabled' => true,
            'vtual.credential.failover.auth_fail_threshold' => 1,
            'vtual.credential.failover.max_retries_per_call' => 1,
        ]);

        $primary = $this->activateYoutubeCredential('key-a', priority: 10, label: 'yt-a');
        $this->activateYoutubeCredential('key-b', priority: 20, label: 'yt-b');

        Http::fake([
            'www.googleapis.com/youtube/v3/videos*' => Http::response([
                'error' => ['message' => 'Backend Error'],
            ], 500),
        ]);

        try {
            $this->gateway->fetchVideos(PlatformKey::Youtube, ['vid1']);
            $this->fail('Expected PlatformApiException');
        } catch (PlatformApiException $e) {
            $this->assertSame(500, $e->upstreamStatus);
        }

        Http::assertSentCount(1);
        $primary->refresh();
        $this->assertSame(0, (int) $primary->consecutive_auth_failures);
        $this->assertNull($primary->failover_skip_until);
        $this->assertNull($primary->last_error_at);
    }

    #[Test]
    public function pick_active_skips_soft_demoted_until_cooldown(): void
    {
        $primary = $this->activateYoutubeCredential('key-primary', priority: 10, label: 'yt-p');
        $backup = $this->activateYoutubeCredential('key-backup', priority: 20, label: 'yt-b');

        $primary->forceFill([
            'failover_skip_until' => now()->addMinutes(30),
            'consecutive_auth_failures' => 2,
        ])->save();

        $picked = $this->credentials->pickActive($this->youtube);
        $this->assertSame((string) $backup->id, (string) $picked?->id);

        $primary->forceFill(['failover_skip_until' => now()->subMinute()])->save();
        $pickedAgain = $this->credentials->pickActive($this->youtube);
        $this->assertSame((string) $primary->id, (string) $pickedAgain?->id);
    }

    #[Test]
    public function mark_used_clears_auth_failure_state(): void
    {
        $cred = $this->activateYoutubeCredential('key-clear');
        $cred->forceFill([
            'consecutive_auth_failures' => 3,
            'failover_skip_until' => now()->addHour(),
        ])->save();

        $this->credentials->markUsed($cred->fresh());
        $cred->refresh();

        $this->assertSame(0, (int) $cred->consecutive_auth_failures);
        $this->assertNull($cred->failover_skip_until);
        $this->assertNotNull($cred->last_used_at);
    }

    #[Test]
    public function kick_disabled(): void
    {
        $this->expectException(VtualException::class);
        $this->gateway->fetchVideos(PlatformKey::Kick, ['x']);
    }

    #[Test]
    public function youtube_resolve_channel_by_handle(): void
    {
        $this->activateYoutubeCredential('yt-key');
        Http::fake([
            'www.googleapis.com/youtube/v3/channels*' => Http::response([
                'items' => [
                    [
                        'id' => 'UChandle',
                        'snippet' => [
                            'title' => 'Handle Chan',
                            'customUrl' => '@handlechan',
                            'description' => 'bio',
                            'thumbnails' => ['default' => ['url' => 'https://img/x.jpg']],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $channel = $this->gateway->resolveChannel(PlatformKey::Youtube, '@handlechan');
        $this->assertNotNull($channel);
        $this->assertSame('UChandle', $channel->externalId);
        $this->assertSame('handlechan', $channel->handle);
    }

    private function activateYoutubeCredential(
        string $apiKey,
        int $priority = 100,
        ?string $label = null,
    ): \App\Domain\Vtual\Models\PlatformCredential {
        $credential = $this->credentials->create($this->admin, $this->youtube, [
            'label' => $label ?? 'yt-gateway-'.uniqid(),
            'type' => PlatformCredentialType::ApiKey->value,
            'priority' => $priority,
            'secret' => ['api_key' => $apiKey],
        ]);
        $credential->forceFill([
            'status' => PlatformCredentialStatus::Active,
            'verified_at' => now(),
            'priority' => $priority,
        ])->save();

        return $credential->fresh();
    }

    private function activateTwitchCredential(): void
    {
        $credential = $this->credentials->create($this->admin, $this->twitch, [
            'label' => 'tw-gateway-'.uniqid(),
            'type' => PlatformCredentialType::OauthApp->value,
            'secret' => [
                'client_id' => 'tw-client-id',
                'client_secret' => 'tw-client-secret',
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
            'name' => $role.' Gateway',
            'email' => strtolower($role).'-gateway-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole($role);

        return $user;
    }
}
