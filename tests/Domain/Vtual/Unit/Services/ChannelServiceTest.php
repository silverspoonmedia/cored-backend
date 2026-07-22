<?php

namespace Tests\Domain\Vtual\Unit\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\ChannelStatus;
use App\Domain\Vtual\Enums\PlatformCredentialStatus;
use App\Domain\Vtual\Enums\PlatformCredentialType;
use App\Domain\Vtual\Exceptions\VtualException;
use App\Domain\Vtual\Models\Platform;
use App\Domain\Vtual\Services\ChannelService;
use App\Domain\Vtual\Services\CreatorService;
use App\Domain\Vtual\Services\PlatformCredentialService;
use Database\Seeders\VtualRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class ChannelServiceTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private const YT_ID = 'UCPkKpOHxEDcwmUAnRpIu-Ng';

    private ChannelService $channels;

    private CreatorService $creators;

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
        $this->credentials = app(PlatformCredentialService::class);
        $this->admin = $this->userWithRole('Admin');
        $this->youtube = Platform::query()->where('key', 'youtube')->firstOrFail();
        $this->twitch = Platform::query()->where('key', 'twitch')->firstOrFail();
    }

    #[Test]
    public function create_youtube_by_handle_stores_canonical_id(): void
    {
        $this->activateYoutubeCredential();
        $this->fakeYoutubeChannel(self::YT_ID, 'stream', 'Stream Channel');

        $user = $this->userWithRole('User');
        $creator = $this->creators->create($user, ['display_name' => 'Stream']);

        $channel = $this->channels->create($user, $creator->id, [
            'platform_key' => 'youtube',
            'profile' => '@stream',
        ]);

        $this->assertSame(ChannelStatus::Pending, $channel->status);
        $this->assertSame(self::YT_ID, $channel->external_id);
        $this->assertSame('stream', $channel->handle);
        $this->assertSame('Stream Channel', $channel->display_name);
        $this->assertNotNull($channel->last_synced_at);
    }

    #[Test]
    public function create_youtube_by_id_fills_handle_from_api(): void
    {
        $this->activateYoutubeCredential();
        $this->fakeYoutubeChannel(self::YT_ID, 'fromid', 'From Id');

        $user = $this->userWithRole('User');
        $creator = $this->creators->create($user, ['display_name' => 'Stream']);

        $channel = $this->channels->create($user, $creator->id, [
            'platform_key' => 'youtube',
            'profile' => self::YT_ID,
        ]);

        $this->assertSame(self::YT_ID, $channel->external_id);
        $this->assertSame('fromid', $channel->handle);
    }

    #[Test]
    public function create_twitch_by_login_stores_numeric_id(): void
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
                        'description' => '',
                        'profile_image_url' => 'https://img.example/l.png',
                    ],
                ],
            ], 200),
        ]);

        $user = $this->userWithRole('User');
        $creator = $this->creators->create($user, ['display_name' => 'Stream']);

        $channel = $this->channels->create($user, $creator->id, [
            'platform_key' => 'twitch',
            'profile' => 'https://www.twitch.tv/ludwig',
        ]);

        $this->assertSame('40934651', $channel->external_id);
        $this->assertSame('ludwig', $channel->handle);
    }

    #[Test]
    public function kick_platform_is_rejected_while_disabled(): void
    {
        $user = $this->userWithRole('User');
        $creator = $this->creators->create($user, ['display_name' => 'Stream']);

        $this->expectException(VtualException::class);

        $this->channels->create($user, $creator->id, [
            'platform_key' => 'kick',
            'profile' => 'kick-user-1',
        ]);
    }

    #[Test]
    public function duplicate_platform_external_id_rejected(): void
    {
        $this->activateYoutubeCredential();
        $this->fakeYoutubeChannel(self::YT_ID, 'stream', 'Stream');

        $user = $this->userWithRole('User');
        $creator = $this->creators->create($user, ['display_name' => 'Stream']);

        $this->channels->create($user, $creator->id, [
            'platform_key' => 'youtube',
            'profile' => self::YT_ID,
        ]);

        $this->expectException(VtualException::class);

        $this->channels->create($user, $creator->id, [
            'platform_key' => 'youtube',
            'profile' => '@stream',
        ]);
    }

    #[Test]
    public function refresh_updates_handle_keeps_external_id(): void
    {
        $this->activateYoutubeCredential();

        Http::fake([
            'www.googleapis.com/youtube/v3/channels*' => Http::sequence()
                ->push([
                    'items' => [
                        [
                            'id' => self::YT_ID,
                            'snippet' => [
                                'title' => 'Old Name',
                                'description' => '',
                                'customUrl' => '@oldhandle',
                                'thumbnails' => [],
                            ],
                        ],
                    ],
                ], 200)
                ->push([
                    'items' => [
                        [
                            'id' => self::YT_ID,
                            'snippet' => [
                                'title' => 'New Name',
                                'description' => '',
                                'customUrl' => '@newhandle',
                                'thumbnails' => [],
                            ],
                        ],
                    ],
                ], 200),
        ]);

        $user = $this->userWithRole('User');
        $creator = $this->creators->create($user, ['display_name' => 'Stream']);

        $channel = $this->channels->create($user, $creator->id, [
            'platform_key' => 'youtube',
            'profile' => self::YT_ID,
        ]);

        $this->assertSame('oldhandle', $channel->handle);

        $refreshed = $this->channels->refresh($user, $channel->id);

        $this->assertSame(self::YT_ID, $refreshed->external_id);
        $this->assertSame('newhandle', $refreshed->handle);
        $this->assertSame('New Name', $refreshed->display_name);
    }

    #[Test]
    public function profile_not_found_throws(): void
    {
        $this->activateYoutubeCredential();
        Http::fake([
            'www.googleapis.com/youtube/v3/channels*' => Http::response(['items' => []], 200),
        ]);

        $user = $this->userWithRole('User');
        $creator = $this->creators->create($user, ['display_name' => 'Stream']);

        $this->expectException(VtualException::class);

        $this->channels->create($user, $creator->id, [
            'platform_key' => 'youtube',
            'profile' => '@missingchannel',
        ]);
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
                            'description' => '',
                            'customUrl' => '@'.$handle,
                            'thumbnails' => [
                                'high' => ['url' => 'https://img.example/hi.jpg'],
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
            'label' => 'yt-ch-'.uniqid(),
            'type' => PlatformCredentialType::ApiKey->value,
            'secret' => ['api_key' => 'yt-ch-key'],
        ]);
        $credential->forceFill([
            'status' => PlatformCredentialStatus::Active,
            'verified_at' => now(),
        ])->save();
    }

    private function activateTwitchCredential(): void
    {
        $credential = $this->credentials->create($this->admin, $this->twitch, [
            'label' => 'tw-ch-'.uniqid(),
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
        $user = User::factory()->create([
            'email' => 'u'.uniqid('', true).'@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole($role);

        return $user;
    }
}
