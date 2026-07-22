<?php

namespace Tests\Domain\Vtual\Unit\Integrations;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\PlatformCredentialStatus;
use App\Domain\Vtual\Enums\PlatformCredentialType;
use App\Domain\Vtual\Enums\PlatformKey;
use App\Domain\Vtual\Exceptions\VtualException;
use App\Domain\Vtual\Integrations\Support\ChannelProfileResolver;
use App\Domain\Vtual\Models\Platform;
use App\Domain\Vtual\Services\PlatformCredentialService;
use App\Domain\Vtual\Services\PlatformService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class ChannelProfileResolverTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private const YT_ID = 'UCPkKpOHxEDcwmUAnRpIu-Ng';

    private ChannelProfileResolver $resolver;

    private PlatformCredentialService $credentials;

    private Platform $youtube;

    private Platform $twitch;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        app(PlatformService::class)->seedDefaults();
        $this->resolver = app(ChannelProfileResolver::class);
        $this->credentials = app(PlatformCredentialService::class);
        $this->youtube = Platform::query()->where('key', 'youtube')->firstOrFail();
        $this->twitch = Platform::query()->where('key', 'twitch')->firstOrFail();
        $this->admin = $this->userWithRole('Admin');
    }

    #[Test]
    public function youtube_handle_resolves_to_canonical_id(): void
    {
        $this->activateYoutubeCredential();

        Http::fake([
            'www.googleapis.com/youtube/v3/channels*' => Http::response([
                'items' => [
                    $this->youtubeChannelItem(self::YT_ID, 'aizawaema', 'Aizawa Ema'),
                ],
            ], 200),
        ]);

        $dto = $this->resolver->resolve(PlatformKey::Youtube, '@aizawaema');

        $this->assertSame(self::YT_ID, $dto->externalId);
        $this->assertSame('aizawaema', $dto->handle);
        $this->assertSame('Aizawa Ema', $dto->displayName);

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), 'forHandle=')
                || str_contains($request->url(), 'forHandle%3D');
        });
    }

    #[Test]
    public function youtube_channel_id_uses_fetch_channels(): void
    {
        $this->activateYoutubeCredential();

        Http::fake([
            'www.googleapis.com/youtube/v3/channels*' => Http::response([
                'items' => [
                    $this->youtubeChannelItem(self::YT_ID, 'aizawaema', 'Aizawa Ema'),
                ],
            ], 200),
        ]);

        $dto = $this->resolver->resolve(PlatformKey::Youtube, self::YT_ID);

        $this->assertSame(self::YT_ID, $dto->externalId);
        $this->assertSame('aizawaema', $dto->handle);

        Http::assertSent(function ($request): bool {
            $url = $request->url();

            return str_contains($url, 'channels')
                && (str_contains($url, 'id='.self::YT_ID) || str_contains($url, 'id='.rawurlencode(self::YT_ID)));
        });
    }

    #[Test]
    public function twitch_login_resolves_to_numeric_id(): void
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

        $dto = $this->resolver->resolve(PlatformKey::Twitch, 'https://www.twitch.tv/ludwig');

        $this->assertSame('40934651', $dto->externalId);
        $this->assertSame('ludwig', $dto->handle);
        $this->assertSame('Ludwig', $dto->displayName);
    }

    #[Test]
    public function not_found_throws(): void
    {
        $this->activateYoutubeCredential();

        Http::fake([
            'www.googleapis.com/youtube/v3/channels*' => Http::response(['items' => []], 200),
        ]);

        $this->expectException(VtualException::class);
        $this->resolver->resolve(PlatformKey::Youtube, '@ghostchannel999');
    }

    /**
     * @return array<string, mixed>
     */
    private function youtubeChannelItem(string $id, string $handle, string $title): array
    {
        return [
            'id' => $id,
            'snippet' => [
                'title' => $title,
                'description' => 'bio',
                'customUrl' => '@'.$handle,
                'thumbnails' => [
                    'high' => ['url' => 'https://img.example/hi.jpg'],
                ],
            ],
        ];
    }

    private function activateYoutubeCredential(): void
    {
        $credential = $this->credentials->create($this->admin, $this->youtube, [
            'label' => 'yt-resolver-'.uniqid(),
            'type' => PlatformCredentialType::ApiKey->value,
            'secret' => ['api_key' => 'yt-resolver-key'],
        ]);
        $credential->forceFill([
            'status' => PlatformCredentialStatus::Active,
            'verified_at' => now(),
        ])->save();
    }

    private function activateTwitchCredential(): void
    {
        $credential = $this->credentials->create($this->admin, $this->twitch, [
            'label' => 'tw-resolver-'.uniqid(),
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
            'name' => $role.' Resolver',
            'email' => strtolower($role).'-resolver-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole($role);

        return $user;
    }
}
