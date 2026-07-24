<?php

namespace Tests\Domain\Vtual\Feature\Http\Controllers\API\V1;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\PlatformCredentialStatus;
use App\Domain\Vtual\Enums\PlatformCredentialType;
use App\Domain\Vtual\Models\Platform;
use App\Domain\Vtual\Services\PlatformCredentialService;
use App\Domain\Vtual\Services\PlatformService;
use App\Support\Product\Services\ProductActivationService;
use Database\Seeders\VtualRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class PlatformApiProbeApiTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private ProductActivationService $activations;

    private PlatformCredentialService $credentials;

    private Platform $youtube;

    private Platform $twitch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        $this->seed(VtualRoleSeeder::class);
        $this->activations = app(ProductActivationService::class);
        $this->credentials = app(PlatformCredentialService::class);
        app(PlatformService::class)->seedDefaults();
        $this->youtube = Platform::query()->where('key', 'youtube')->firstOrFail();
        $this->twitch = Platform::query()->where('key', 'twitch')->firstOrFail();
    }

    #[Test]
    public function admin_can_probe_fetch_videos(): void
    {
        $admin = $this->userWithRole('Admin');
        $this->activations->activateImplicit($admin, 'Vtual');
        $this->activateYoutubeCredential($admin, 'probe-key-aaaa');

        Http::fake([
            'www.googleapis.com/youtube/v3/videos*' => Http::response([
                'items' => [
                    [
                        'id' => 'vid1',
                        'snippet' => [
                            'title' => 'Probe Video',
                            'description' => 'D',
                            'channelId' => 'UCabc',
                            'publishedAt' => '2026-01-01T00:00:00Z',
                            'liveBroadcastContent' => 'none',
                            'thumbnails' => ['high' => ['url' => 'https://img.example/hi.jpg']],
                        ],
                        'contentDetails' => ['duration' => 'PT10S'],
                        'statistics' => ['viewCount' => '9'],
                        'status' => ['privacyStatus' => 'public'],
                        'liveStreamingDetails' => [],
                    ],
                ],
            ], 200),
        ]);

        $data = $this->actingAsJwt($admin)
            ->postJson('/api/v1/vtual/admin/platform-api/probe', [
                'platform' => 'youtube',
                'method' => 'fetch_videos',
                'ids' => ['vid1', 'vid2'],
            ])
            ->assertOk()
            ->json('data');

        $this->assertSame('youtube', $data['platform']);
        $this->assertSame('fetch_videos', $data['method']);
        $this->assertSame(1, $data['count']);
        $this->assertSame('vid1', $data['data'][0]['external_id']);
        $this->assertSame('Probe Video', $data['data'][0]['title']);
        $this->assertArrayNotHasKey('raw', $data['data'][0]);
        $this->assertSame([], $data['resolved'] ?? []);
    }

    #[Test]
    public function twitch_fetch_streams_normalizes_login_to_user_id(): void
    {
        $admin = $this->userWithRole('Admin');
        $this->activations->activateImplicit($admin, 'Vtual');
        $this->activateTwitchCredential($admin);

        Http::fake([
            'api.twitch.tv/helix/users*' => Http::response([
                'data' => [
                    [
                        'id' => '997729120',
                        'login' => '2xrakai',
                        'display_name' => '2xRakai',
                        'description' => '',
                        'profile_image_url' => 'https://img.example/u.png',
                    ],
                ],
            ], 200),
            'api.twitch.tv/helix/streams*' => Http::response([
                'data' => [
                    [
                        'id' => 'stream-1',
                        'user_id' => '997729120',
                        'user_login' => '2xrakai',
                        'user_name' => '2xRakai',
                        'title' => 'Live now',
                        'game_name' => 'Just Chatting',
                        'thumbnail_url' => 'https://img.example/{width}x{height}.jpg',
                        'viewer_count' => 42,
                        'started_at' => '2026-07-22T00:00:00Z',
                        'language' => 'en',
                        'type' => 'live',
                    ],
                ],
            ], 200),
        ]);

        $data = $this->actingAsJwt($admin)
            ->postJson('/api/v1/vtual/admin/platform-api/probe', [
                'platform' => 'twitch',
                'method' => 'fetch_streams',
                'ids' => ['2xrakai'],
            ])
            ->assertOk()
            ->json('data');

        $this->assertSame('fetch_streams', $data['method']);
        $this->assertSame(1, $data['count']);
        $this->assertSame('2xrakai', $data['resolved'][0]['input']);
        $this->assertSame('997729120', $data['resolved'][0]['external_id']);
        $this->assertSame('2xrakai', $data['resolved'][0]['handle']);
        $this->assertSame('stream-1', $data['data'][0]['external_id']);
        $this->assertSame('997729120', $data['data'][0]['user_external_id']);

        Http::assertSent(function ($request): bool {
            if (! str_contains($request->url(), 'helix/streams')) {
                return false;
            }

            return str_contains($request->url(), 'user_id=997729120');
        });
    }

    #[Test]
    public function twitch_fetch_streams_numeric_id_passthrough(): void
    {
        $admin = $this->userWithRole('Admin');
        $this->activations->activateImplicit($admin, 'Vtual');
        $this->activateTwitchCredential($admin);

        Http::fake([
            'api.twitch.tv/helix/streams*' => Http::response(['data' => []], 200),
        ]);

        $data = $this->actingAsJwt($admin)
            ->postJson('/api/v1/vtual/admin/platform-api/probe', [
                'platform' => 'twitch',
                'method' => 'fetch_streams',
                'ids' => ['997729120'],
            ])
            ->assertOk()
            ->json('data');

        $this->assertSame('997729120', $data['resolved'][0]['input']);
        $this->assertSame('997729120', $data['resolved'][0]['external_id']);
        $this->assertNull($data['resolved'][0]['handle']);

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), 'helix/streams')
                && str_contains($request->url(), 'user_id=997729120');
        });

        Http::assertNotSent(function ($request): bool {
            return str_contains($request->url(), 'helix/users');
        });
    }

    #[Test]
    public function twitch_fetch_channels_normalizes_login(): void
    {
        $admin = $this->userWithRole('Admin');
        $this->activations->activateImplicit($admin, 'Vtual');
        $this->activateTwitchCredential($admin);

        $userPayload = [
            'data' => [
                [
                    'id' => '997729120',
                    'login' => '2xrakai',
                    'display_name' => '2xRakai',
                    'description' => 'bio',
                    'profile_image_url' => 'https://img.example/u.png',
                ],
            ],
        ];

        Http::fake([
            'api.twitch.tv/helix/users*' => Http::response($userPayload, 200),
        ]);

        $data = $this->actingAsJwt($admin)
            ->postJson('/api/v1/vtual/admin/platform-api/probe', [
                'platform' => 'twitch',
                'method' => 'fetch_channels',
                'ids' => '2xrakai',
            ])
            ->assertOk()
            ->json('data');

        $this->assertSame(1, $data['count']);
        $this->assertSame('997729120', $data['resolved'][0]['external_id']);
        $this->assertSame('997729120', $data['data'][0]['external_id']);
        $this->assertSame('2xrakai', $data['data'][0]['handle']);
    }

    #[Test]
    public function non_admin_cannot_probe(): void
    {
        $user = $this->userWithRole('User');
        $this->activations->activateImplicit($user, 'Vtual');

        $this->actingAsJwt($user)
            ->postJson('/api/v1/vtual/admin/platform-api/probe', [
                'platform' => 'youtube',
                'method' => 'fetch_videos',
                'ids' => ['vid1'],
            ])
            ->assertForbidden();
    }

    #[Test]
    public function probe_accepts_comma_separated_ids_string(): void
    {
        $admin = $this->userWithRole('Admin');
        $this->activations->activateImplicit($admin, 'Vtual');
        $this->activateYoutubeCredential($admin, 'probe-key-bbbb');

        Http::fake([
            'www.googleapis.com/youtube/v3/videos*' => Http::response(['items' => []], 200),
        ]);

        $this->actingAsJwt($admin)
            ->postJson('/api/v1/vtual/admin/platform-api/probe', [
                'platform' => 'youtube',
                'method' => 'fetch_videos',
                'ids' => 'vid1, vid2',
            ])
            ->assertOk()
            ->assertJsonPath('data.count', 0);

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), 'id=vid1%2Cvid2')
                || str_contains($request->url(), 'id=vid1,vid2');
        });
    }

    private function activateYoutubeCredential(User $admin, string $apiKey): void
    {
        $credential = $this->credentials->create($admin, $this->youtube, [
            'label' => 'yt-probe-'.uniqid(),
            'type' => PlatformCredentialType::ApiKey->value,
            'secret' => ['api_key' => $apiKey],
        ]);
        $credential->forceFill([
            'status' => PlatformCredentialStatus::Active,
            'verified_at' => now(),
        ])->save();
    }

    private function activateTwitchCredential(User $admin): void
    {
        $credential = $this->credentials->create($admin, $this->twitch, [
            'label' => 'tw-probe-'.uniqid(),
            'type' => PlatformCredentialType::OauthApp->value,
            'secret' => [
                'client_id' => 'tw-probe-client',
                'client_secret' => 'tw-probe-secret',
            ],
        ]);
        // Seed valid runtime so getAppAccessToken skips mint (mint also hits Helix users).
        $credential->forceFill([
            'status' => PlatformCredentialStatus::Active,
            'verified_at' => now(),
            'runtime_payload' => [
                'access_token' => 'app-token-seeded',
                'token_type' => 'bearer',
                'expires_at' => now()->addHour()->toIso8601String(),
                'minted_at' => now()->toIso8601String(),
            ],
        ])->save();
    }

    private function userWithRole(string $role): User
    {
        $user = User::query()->create([
            'name' => $role.' Probe',
            'email' => strtolower($role).'-probe-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function actingAsJwt(User $user): static
    {
        $token = auth('api')->login($user);

        return $this->withHeader('Authorization', 'Bearer '.$token);
    }
}
