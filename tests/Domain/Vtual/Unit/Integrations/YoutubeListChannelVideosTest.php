<?php

namespace Tests\Domain\Vtual\Unit\Integrations;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\PlatformCredentialStatus;
use App\Domain\Vtual\Enums\PlatformCredentialType;
use App\Domain\Vtual\Enums\PlatformKey;
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

class YoutubeListChannelVideosTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private PlatformApiGateway $gateway;

    private PlatformCredentialService $credentials;

    private Platform $youtube;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        app(PlatformService::class)->seedDefaults();
        $this->gateway = app(PlatformApiGateway::class);
        $this->credentials = app(PlatformCredentialService::class);
        $this->youtube = Platform::query()->where('key', 'youtube')->firstOrFail();
        $this->admin = $this->userWithRole('Admin');
    }

    #[Test]
    public function list_channel_videos_returns_ids_from_uploads_playlist(): void
    {
        $this->activateYoutubeCredential('yt-key-list-videos');

        Http::fake([
            'www.googleapis.com/youtube/v3/playlistItems*' => Http::response([
                'items' => [
                    [
                        'contentDetails' => ['videoId' => 'vid-newest'],
                        'snippet' => ['resourceId' => ['videoId' => 'vid-newest']],
                    ],
                    [
                        'contentDetails' => ['videoId' => 'vid-older'],
                        'snippet' => ['resourceId' => ['videoId' => 'vid-older']],
                    ],
                ],
            ], 200),
        ]);

        $ids = $this->gateway->listChannelVideos(PlatformKey::Youtube, 'UCabc1234567890abcdefghij', 50);

        $this->assertSame(['vid-newest', 'vid-older'], $ids->all());

        Http::assertSent(function ($request): bool {
            if (! str_contains($request->url(), 'youtube/v3/playlistItems')) {
                return false;
            }
            $url = $request->url();
            $this->assertStringContainsString('playlistId=UUabc1234567890abcdefghij', $url);
            $this->assertStringContainsString('maxResults=50', $url);
            $this->assertStringContainsString('key=yt-key-list-videos', $url);

            return true;
        });
    }

    private function activateYoutubeCredential(string $apiKey): void
    {
        $credential = $this->credentials->create($this->admin, $this->youtube, [
            'label' => 'yt-list-'.uniqid(),
            'type' => PlatformCredentialType::ApiKey->value,
            'priority' => 100,
            'secret' => ['api_key' => $apiKey],
        ]);
        $credential->forceFill([
            'status' => PlatformCredentialStatus::Active,
            'verified_at' => now(),
        ])->save();
    }

    private function userWithRole(string $role): User
    {
        $user = User::query()->create([
            'name' => $role.' List',
            'email' => strtolower($role).'-list-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole($role);

        return $user;
    }
}
