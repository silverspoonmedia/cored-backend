<?php

namespace Tests\Domain\Vtual\Feature\Http\Controllers\Web;

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
use Tests\Concerns\InteractsWithWebAuth;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class PlatformApiProbeWebTest extends TestCase
{
    use InteractsWithWebAuth;
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private ProductActivationService $activations;

    private PlatformCredentialService $credentials;

    private Platform $youtube;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        $this->seed(VtualRoleSeeder::class);
        $this->activations = app(ProductActivationService::class);
        $this->credentials = app(PlatformCredentialService::class);
        app(PlatformService::class)->seedDefaults();
        $this->youtube = Platform::query()->where('key', 'youtube')->firstOrFail();
    }

    #[Test]
    public function admin_can_open_probe_page(): void
    {
        $admin = $this->userWithRole('Admin');
        $this->activations->activateImplicit($admin, 'Vtual');

        $this->actingWithJwtCookie($admin)
            ->get(route('vtual.admin.platform-api-probe'))
            ->assertOk()
            ->assertSee(__('web/vtual.probe.title'), false);
    }

    #[Test]
    public function non_admin_cannot_open_probe_page(): void
    {
        $user = $this->userWithRole('User');
        $this->activations->activateImplicit($user, 'Vtual');

        $this->actingWithJwtCookie($user)
            ->get(route('vtual.admin.platform-api-probe'))
            ->assertForbidden();
    }

    #[Test]
    public function admin_can_run_probe_and_see_json_result(): void
    {
        $admin = $this->userWithRole('Admin');
        $this->activations->activateImplicit($admin, 'Vtual');
        $this->activateYoutubeCredential($admin, 'web-probe-key');

        Http::fake([
            'www.googleapis.com/youtube/v3/videos*' => Http::response([
                'items' => [
                    [
                        'id' => 'abc123',
                        'snippet' => [
                            'title' => 'Web Probe Hit',
                            'description' => '',
                            'channelId' => 'UCxyz',
                            'publishedAt' => '2026-02-01T00:00:00Z',
                            'liveBroadcastContent' => 'none',
                            'thumbnails' => [],
                        ],
                        'contentDetails' => ['duration' => 'PT1S'],
                        'statistics' => ['viewCount' => '1'],
                        'status' => ['privacyStatus' => 'public'],
                        'liveStreamingDetails' => [],
                    ],
                ],
            ], 200),
        ]);

        $this->actingWithJwtCookie($admin)
            ->post(route('vtual.admin.platform-api-probe.run'), [
                'platform' => 'youtube',
                'method' => 'fetch_videos',
                'ids' => 'abc123',
            ])
            ->assertOk()
            ->assertSee('Web Probe Hit', false)
            ->assertSee('abc123', false);
    }

    private function activateYoutubeCredential(User $admin, string $apiKey): void
    {
        $credential = $this->credentials->create($admin, $this->youtube, [
            'label' => 'yt-web-probe-'.uniqid(),
            'type' => PlatformCredentialType::ApiKey->value,
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
            'name' => $role.' WebProbe',
            'email' => strtolower($role).'-webprobe-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole($role);

        return $user;
    }
}
