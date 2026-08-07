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

class YoutubeChannelMappingTest extends TestCase
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
    public function fetch_channels_maps_subscriber_count_from_statistics(): void
    {
        $this->activateYoutubeCredential('yt-key-subs');

        Http::fake([
            'www.googleapis.com/youtube/v3/channels*' => Http::response([
                'items' => [[
                    'id' => 'UCsubs1234567890abcdefghij',
                    'snippet' => [
                        'title' => 'Sub Channel',
                        'description' => 'bio',
                        'customUrl' => '@subchannel',
                        'publishedAt' => '2017-04-19T14:57:55Z',
                        'thumbnails' => ['high' => ['url' => 'https://img.example/sub.jpg']],
                    ],
                    'statistics' => [
                        'subscriberCount' => '125000',
                        'hiddenSubscriberCount' => false,
                    ],
                ]],
            ], 200),
        ]);

        $dto = $this->gateway->fetchChannels(PlatformKey::Youtube, ['UCsubs1234567890abcdefghij'])->first();

        $this->assertNotNull($dto);
        $this->assertSame(125000, $dto->subscriberCount);
    }

    #[Test]
    public function fetch_channels_returns_null_subscriber_count_when_hidden(): void
    {
        $this->activateYoutubeCredential('yt-key-hidden-subs');

        Http::fake([
            'www.googleapis.com/youtube/v3/channels*' => Http::response([
                'items' => [[
                    'id' => 'UChidden1234567890abcdef',
                    'snippet' => [
                        'title' => 'Hidden Subs',
                        'description' => 'bio',
                        'customUrl' => '@hidden',
                        'publishedAt' => '2017-04-19T14:57:55Z',
                        'thumbnails' => [],
                    ],
                    'statistics' => [
                        'subscriberCount' => '999',
                        'hiddenSubscriberCount' => true,
                    ],
                ]],
            ], 200),
        ]);

        $dto = $this->gateway->fetchChannels(PlatformKey::Youtube, ['UChidden1234567890abcdef'])->first();

        $this->assertNotNull($dto);
        $this->assertNull($dto->subscriberCount);
    }

    private function activateYoutubeCredential(string $apiKey): void
    {
        $credential = $this->credentials->create($this->admin, $this->youtube, [
            'label' => 'yt-subs-'.uniqid(),
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
            'name' => $role.' Subs',
            'email' => strtolower($role).'-subs-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole($role);

        return $user;
    }
}
