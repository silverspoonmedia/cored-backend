<?php

namespace Tests\Feature\Http\Controllers\Web;

use App\Domain\Identity\Models\User;
use App\Support\Auth\AuthCookie;
use App\Support\Settings\Models\SystemSetting;
use App\Support\Settings\Repositories\Eloquent\SystemSettingRepository;
use App\Support\Settings\SettingType;
use Database\Seeders\VtualRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class VtuberProfileWebTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private string $creatorId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seedRolesAndPermissions();
        $this->seed(VtualRoleSeeder::class);
        $this->creatorId = (string) Str::uuid();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('vtual.vtuber.show', $this->creatorId))
            ->assertRedirect(route('login'));
    }

    public function test_guest_is_redirected_to_login_from_hub(): void
    {
        $this->get(route('vtual.vtuber.index'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_sees_blank_hub_page(): void
    {
        $user = $this->userWithRole('User');
        $this->fakeAuthMe();

        $this->actingWithJwtCookie($user)
            ->get(route('vtual.vtuber.index'))
            ->assertOk()
            ->assertViewIs(config('web.layout').'.vtual.vtuber.index');
    }

    public function test_authenticated_user_sees_profile_sections(): void
    {
        $user = $this->userWithRole('User');
        $this->fakeAuthMe();

        Http::fake([
            '*/api/v1/vtual/vtubers/aizawa-ema' => Http::response([
                'status' => 'success',
                'data' => [
                    'id' => $this->creatorId,
                    'slug' => 'aizawa-ema',
                    'profile_url' => route('vtual.vtuber.show', 'aizawa-ema'),
                    'is_claimable' => true,
                    'claim_process_url' => route('vtual.vtuber.claim', 'aizawa-ema'),
                    'display_name' => 'Aizawa Ema',
                    'bio' => 'Virtual singer from Japan.',
                    'avatar_url' => 'https://img.example/creator.jpg',
                    'channels' => [
                        [
                            'id' => (string) Str::uuid(),
                            'platform' => ['key' => 'youtube', 'name' => 'YouTube'],
                            'handle' => 'aizawaema',
                            'display_name' => 'Aizawa Ema',
                            'avatar_url' => 'https://img.example/channel.jpg',
                            'url' => 'https://www.youtube.com/@AizawaEma',
                            'subscriber_count' => 125000,
                            'subscriber_count_formatted' => '125,000',
                            'status' => 'active',
                        ],
                    ],
                    'contents' => [
                        [
                            'id' => (string) Str::uuid(),
                            'external_id' => 'vid1',
                            'type' => 'vod',
                            'status' => 'uploaded',
                            'title' => 'Morning stream archive',
                            'thumbnail_url' => 'https://img.example/vid1.jpg',
                            'url' => 'https://www.youtube.com/watch?v=vid1',
                            'published_at' => '2026-01-01T00:00:00Z',
                            'published_at_formatted' => 'Jan 1, 2026',
                            'platform' => ['key' => 'youtube', 'name' => 'YouTube'],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $html = $this->actingWithJwtCookie($user)
            ->get(route('vtual.vtuber.show', 'aizawa-ema'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Aizawa Ema', $html);
        $this->assertStringContainsString('Virtual singer from Japan.', $html);
        $this->assertStringContainsString('aizawaema', $html);
        $this->assertStringContainsString('Morning stream archive', $html);
        $this->assertStringContainsString(__('web/vtual-vtuber.section_channels'), $html);
        $this->assertStringContainsString(__('web/vtual-vtuber.section_contents'), $html);
        $this->assertStringContainsString(__('web/vtual-vtuber.section_about'), $html);
        $this->assertStringContainsString(__('web/vtual-vtuber.stat_channels'), $html);
        $this->assertStringContainsString('row-cols', $html);
        $this->assertStringContainsString('min-w-0', $html);
        $this->assertStringContainsString('vtuber-content-card', $html);
        $this->assertStringContainsString('vtuber-content-card__title', $html);
        $this->assertStringContainsString('vtuber-content-card__meta', $html);
        $this->assertStringContainsString('vtuber-content-card__badges', $html);
        $this->assertStringContainsString('position-relative z-1', $html);
        $this->assertStringContainsString('monolith-line-clamp-2', $html);
        $this->assertStringContainsString('ratio-16x9', $html);
        $this->assertStringContainsString(__('web/vtual-vtuber.subscribers', ['count' => '125,000']), $html);
        $this->assertStringContainsString('https://www.youtube.com/@AizawaEma', $html);
        $this->assertStringContainsString('https://img.example/channel.jpg', $html);
        $this->assertStringContainsString('rel="canonical"', $html);
        $this->assertStringContainsString(route('vtual.vtuber.show', 'aizawa-ema'), $html);
        $this->assertStringContainsString(__('web/vtual-vtuber.claim_button'), $html);
        $this->assertStringContainsString(route('vtual.vtuber.claim', 'aizawa-ema'), $html);
    }

    public function test_owned_profile_does_not_show_claim_button(): void
    {
        $user = $this->userWithRole('User');
        $this->fakeAuthMe();

        Http::fake([
            '*/api/v1/vtual/vtubers/owned-profile' => Http::response([
                'status' => 'success',
                'data' => [
                    'id' => $this->creatorId,
                    'slug' => 'owned-profile',
                    'profile_url' => route('vtual.vtuber.show', 'owned-profile'),
                    'is_claimable' => false,
                    'claim_process_url' => null,
                    'display_name' => 'Owned Profile',
                    'bio' => null,
                    'avatar_url' => null,
                    'channels' => [],
                    'contents' => [],
                ],
            ], 200),
        ]);

        $html = $this->actingWithJwtCookie($user)
            ->get(route('vtual.vtuber.show', 'owned-profile'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString(__('web/vtual-vtuber.claim_button'), $html);
        $this->assertStringNotContainsString(route('vtual.vtuber.claim', 'owned-profile'), $html);
    }

    public function test_guest_is_redirected_to_login_from_claim_page(): void
    {
        $this->get(route('vtual.vtuber.claim', 'aizawa-ema'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_sees_claim_info_page_for_orphan(): void
    {
        $user = $this->userWithRole('User');
        $this->fakeAuthMe();

        Http::fake([
            '*/api/v1/vtual/vtubers/aizawa-ema' => Http::response([
                'status' => 'success',
                'data' => [
                    'id' => $this->creatorId,
                    'slug' => 'aizawa-ema',
                    'profile_url' => route('vtual.vtuber.show', 'aizawa-ema'),
                    'is_claimable' => true,
                    'claim_process_url' => route('vtual.vtuber.claim', 'aizawa-ema'),
                    'claim_blocked_until' => null,
                    'display_name' => 'Aizawa Ema',
                    'bio' => null,
                    'avatar_url' => null,
                    'channels' => [
                        [
                            'id' => (string) Str::uuid(),
                            'platform' => ['key' => 'youtube', 'name' => 'YouTube'],
                            'handle' => 'aizawaema',
                            'url' => 'https://www.youtube.com/@AizawaEma',
                            'status' => 'active',
                        ],
                    ],
                    'contents' => [],
                ],
            ], 200),
            '*/api/v1/vtual/claims/mine' => Http::response([
                'status' => 'success',
                'data' => [],
            ], 200),
        ]);

        $html = $this->actingWithJwtCookie($user)
            ->get(route('vtual.vtuber.claim', 'aizawa-ema'))
            ->assertOk()
            ->assertViewIs(config('web.layout').'.vtual.vtuber.claim')
            ->getContent();

        $this->assertStringContainsString(__('web/vtual-vtuber.claim_page_title'), $html);
        $this->assertStringContainsString(__('web/vtual-vtuber.claim_steps_heading'), $html);
        $this->assertStringContainsString(__('web/vtual-vtuber.claim_step_1'), $html);
        $this->assertStringContainsString('id="vue-vtual-claim"', $html);
        $this->assertStringContainsString('data-vue-vtual-claim', $html);
        $this->assertClaimConfigJsonIsParseable($html);
    }

    public function test_claim_page_shows_active_claim_token_and_verify_button(): void
    {
        $user = $this->userWithRole('User');
        $this->fakeAuthMe();
        $channelId = (string) Str::uuid();
        $claimId = (string) Str::uuid();

        Http::fake([
            '*/api/v1/vtual/vtubers/aizawa-ema' => Http::response([
                'status' => 'success',
                'data' => [
                    'id' => $this->creatorId,
                    'slug' => 'aizawa-ema',
                    'profile_url' => route('vtual.vtuber.show', 'aizawa-ema'),
                    'is_claimable' => true,
                    'claim_process_url' => route('vtual.vtuber.claim', 'aizawa-ema'),
                    'claim_blocked_until' => null,
                    'display_name' => 'Aizawa Ema',
                    'bio' => null,
                    'avatar_url' => null,
                    'channels' => [
                        [
                            'id' => $channelId,
                            'platform' => ['key' => 'youtube', 'name' => 'YouTube'],
                            'handle' => 'aizawaema',
                            'url' => 'https://www.youtube.com/@AizawaEma',
                            'status' => 'active',
                        ],
                    ],
                    'contents' => [],
                ],
            ], 200),
            '*/api/v1/vtual/claims/mine' => Http::response([
                'status' => 'success',
                'data' => [
                    [
                        'id' => $claimId,
                        'creator_id' => $this->creatorId,
                        'channel_id' => $channelId,
                        'status' => 'pending',
                        'token' => 'vtual-claim-testtoken123',
                        'attempts_remaining' => 2,
                        'max_attempts' => 3,
                        'verify_queue_status' => null,
                        'can_verify' => true,
                        'blocked_until' => null,
                        'channel' => [
                            'id' => $channelId,
                            'platform' => ['key' => 'youtube', 'name' => 'YouTube'],
                            'handle' => 'aizawaema',
                            'display_name' => 'Aizawa Ema',
                            'url' => 'https://www.youtube.com/@AizawaEma',
                            'status' => 'active',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $html = $this->actingWithJwtCookie($user)
            ->get(route('vtual.vtuber.claim', 'aizawa-ema'))
            ->assertOk()
            ->getContent();

        // Vue island: the mountpoint + bootstrap JSON are server-rendered.
        // Vue DOM (token, verify button, active claim panel) renders client-side.
        $this->assertStringContainsString('id="vue-vtual-claim"', $html);
        $this->assertClaimConfigJsonIsParseable($html);
        $bootstrap = $this->extractClaimBootstrapArray($html);
        $this->assertNotNull($bootstrap, 'Bootstrap JSON must be parseable.');
        $this->assertArrayHasKey('activeClaim', $bootstrap);
        $this->assertNotNull($bootstrap['activeClaim'], 'activeClaim should be present when user has a pending claim.');
        $this->assertSame('vtual-claim-testtoken123', $bootstrap['activeClaim']['token'] ?? '');
        $this->assertSame($claimId, $bootstrap['activeClaim']['id'] ?? '');
        $this->assertTrue($bootstrap['activeClaim']['can_verify'] ?? false);

        // Translation labels are serialized into the bootstrap JSON for Vue
        $this->assertStringContainsString(__('web/vtual-vtuber.claim_active_heading'), $html);
        $this->assertStringContainsString(__('web/vtual-vtuber.claim_proof_channel_label'), $html);
        $this->assertStringContainsString(__('web/vtual-vtuber.claim_verify_button'), $html);
    }

    public function test_claim_page_hides_discord_cta_when_oauth_not_configured(): void
    {
        $user = $this->userWithRole('User');
        $this->fakeAuthMe();
        $this->enableDiscordClaimVerifySetting();
        config([
            'services.discord.client_id' => '',
            'services.discord.client_secret' => '',
            'services.discord.redirect' => 'https://example.test/apps/auth/discord/callback',
        ]);

        Http::fake([
            '*/api/v1/vtual/vtubers/aizawa-ema' => Http::response([
                'status' => 'success',
                'data' => [
                    'id' => $this->creatorId,
                    'slug' => 'aizawa-ema',
                    'profile_url' => route('vtual.vtuber.show', 'aizawa-ema'),
                    'is_claimable' => true,
                    'claim_process_url' => route('vtual.vtuber.claim', 'aizawa-ema'),
                    'claim_blocked_until' => null,
                    'display_name' => 'Aizawa Ema',
                    'bio' => null,
                    'avatar_url' => null,
                    'channels' => [
                        [
                            'id' => (string) Str::uuid(),
                            'platform' => ['key' => 'youtube', 'name' => 'YouTube'],
                            'handle' => 'aizawaema',
                            'url' => 'https://www.youtube.com/@AizawaEma',
                            'status' => 'active',
                        ],
                    ],
                    'contents' => [],
                ],
            ], 200),
            '*/api/v1/vtual/claims/mine' => Http::response([
                'status' => 'success',
                'data' => [],
            ], 200),
        ]);

        $html = $this->actingWithJwtCookie($user)
            ->get(route('vtual.vtuber.claim', 'aizawa-ema'))
            ->assertOk()
            ->getContent();

        $bootstrap = $this->extractClaimBootstrapArray($html);
        $this->assertNotNull($bootstrap, 'Bootstrap JSON must be parseable.');
        $this->assertFalse($bootstrap['discordVerifyEnabled'] ?? true, 'discordVerifyEnabled must be false when OAuth is not configured.');
    }

    public function test_claim_page_redirects_when_profile_not_claimable(): void
    {
        $user = $this->userWithRole('User');
        $this->fakeAuthMe();

        Http::fake([
            '*/api/v1/vtual/vtubers/owned-profile' => Http::response([
                'status' => 'success',
                'data' => [
                    'id' => $this->creatorId,
                    'slug' => 'owned-profile',
                    'profile_url' => route('vtual.vtuber.show', 'owned-profile'),
                    'is_claimable' => false,
                    'claim_process_url' => null,
                    'display_name' => 'Owned Profile',
                    'bio' => null,
                    'avatar_url' => null,
                    'channels' => [],
                    'contents' => [],
                ],
            ], 200),
        ]);

        $this->actingWithJwtCookie($user)
            ->get(route('vtual.vtuber.claim', 'owned-profile'))
            ->assertRedirect(route('vtual.vtuber.show', 'owned-profile'));
    }

    public function test_staff_sees_accept_anyway_flag_in_bootstrap(): void
    {
        $this->enableAcceptAnywaySetting();
        $user = $this->userWithRole('Admin');
        $this->fakeAuthMe();

        Http::fake([
            '*/api/v1/vtual/vtubers/aizawa-ema' => Http::response([
                'status' => 'success',
                'data' => [
                    'id' => $this->creatorId,
                    'slug' => 'aizawa-ema',
                    'profile_url' => route('vtual.vtuber.show', 'aizawa-ema'),
                    'is_claimable' => true,
                    'claim_process_url' => route('vtual.vtuber.claim', 'aizawa-ema'),
                    'claim_blocked_until' => null,
                    'display_name' => 'Aizawa Ema',
                    'bio' => null,
                    'avatar_url' => null,
                    'channels' => [
                        [
                            'id' => (string) Str::uuid(),
                            'platform' => ['key' => 'youtube', 'name' => 'YouTube'],
                            'handle' => 'aizawaema',
                            'url' => 'https://www.youtube.com/@AizawaEma',
                            'status' => 'active',
                        ],
                    ],
                    'contents' => [],
                ],
            ], 200),
            '*/api/v1/vtual/claims/mine' => Http::response([
                'status' => 'success',
                'data' => [],
            ], 200),
        ]);

        $html = $this->actingWithJwtCookie($user)
            ->get(route('vtual.vtuber.claim', 'aizawa-ema'))
            ->assertOk()
            ->getContent();

        $bootstrap = $this->extractClaimBootstrapArray($html);
        $this->assertNotNull($bootstrap);
        $this->assertTrue($bootstrap['canAcceptAnyway'] ?? false, 'canAcceptAnyway must be true for staff with feature flag enabled.');
        $this->assertArrayHasKey('acceptAnywayClaim', $bootstrap['routes'] ?? []);
    }

    public function test_non_staff_sees_accept_anyway_when_feature_enabled(): void
    {
        $this->enableAcceptAnywaySetting();
        $user = $this->userWithRole('User');
        $this->fakeAuthMe();

        Http::fake([
            '*/api/v1/vtual/vtubers/aizawa-ema' => Http::response([
                'status' => 'success',
                'data' => [
                    'id' => $this->creatorId,
                    'slug' => 'aizawa-ema',
                    'profile_url' => route('vtual.vtuber.show', 'aizawa-ema'),
                    'is_claimable' => true,
                    'claim_process_url' => route('vtual.vtuber.claim', 'aizawa-ema'),
                    'claim_blocked_until' => null,
                    'display_name' => 'Aizawa Ema',
                    'bio' => null,
                    'avatar_url' => null,
                    'channels' => [
                        [
                            'id' => (string) Str::uuid(),
                            'platform' => ['key' => 'youtube', 'name' => 'YouTube'],
                            'handle' => 'aizawaema',
                            'url' => 'https://www.youtube.com/@AizawaEma',
                            'status' => 'active',
                        ],
                    ],
                    'contents' => [],
                ],
            ], 200),
            '*/api/v1/vtual/claims/mine' => Http::response([
                'status' => 'success',
                'data' => [],
            ], 200),
        ]);

        $html = $this->actingWithJwtCookie($user)
            ->get(route('vtual.vtuber.claim', 'aizawa-ema'))
            ->assertOk()
            ->getContent();

        $bootstrap = $this->extractClaimBootstrapArray($html);
        $this->assertNotNull($bootstrap);
        $this->assertTrue($bootstrap['canAcceptAnyway'] ?? false, 'canAcceptAnyway must be true when feature flag is enabled, regardless of role.');
    }

    public function test_staff_sees_accept_anyway_false_when_feature_flag_off(): void
    {
        config(['vtual.claim.accept_anyway_enabled' => false]);
        // Reset the DB cache so the config override takes effect
        // (Cache::forever survives RefreshDatabase — the prior test's
        // enableAcceptAnywaySetting persisted the DB row + cache).
        app(SystemSettingRepository::class)->flushCache();

        // Feature flag disabled (no setting record)
        $user = $this->userWithRole('Admin');
        $this->fakeAuthMe();

        Http::fake([
            '*/api/v1/vtual/vtubers/aizawa-ema' => Http::response([
                'status' => 'success',
                'data' => [
                    'id' => $this->creatorId,
                    'slug' => 'aizawa-ema',
                    'profile_url' => route('vtual.vtuber.show', 'aizawa-ema'),
                    'is_claimable' => true,
                    'claim_process_url' => route('vtual.vtuber.claim', 'aizawa-ema'),
                    'claim_blocked_until' => null,
                    'display_name' => 'Aizawa Ema',
                    'bio' => null,
                    'avatar_url' => null,
                    'channels' => [],
                    'contents' => [],
                ],
            ], 200),
            '*/api/v1/vtual/claims/mine' => Http::response([
                'status' => 'success',
                'data' => [],
            ], 200),
        ]);

        $html = $this->actingWithJwtCookie($user)
            ->get(route('vtual.vtuber.claim', 'aizawa-ema'))
            ->assertOk()
            ->getContent();

        $bootstrap = $this->extractClaimBootstrapArray($html);
        $this->assertNotNull($bootstrap);
        $this->assertFalse($bootstrap['canAcceptAnyway'] ?? true, 'canAcceptAnyway must be false when feature flag is off, even for staff.');
    }

    public function test_uuid_profile_redirects_to_slug(): void
    {
        $user = $this->userWithRole('User');
        $this->fakeAuthMe();

        Http::fake([
            '*/api/v1/vtual/vtubers/'.$this->creatorId => Http::response([
                'status' => 'success',
                'data' => [
                    'id' => $this->creatorId,
                    'slug' => 'aizawa-ema',
                    'profile_url' => route('vtual.vtuber.show', 'aizawa-ema'),
                    'is_claimable' => true,
                    'claim_process_url' => route('vtual.vtuber.claim', 'aizawa-ema'),
                    'display_name' => 'Aizawa Ema',
                    'bio' => null,
                    'avatar_url' => null,
                    'channels' => [],
                    'contents' => [],
                ],
            ], 200),
        ]);

        $this->actingWithJwtCookie($user)
            ->get(route('vtual.vtuber.show', $this->creatorId))
            ->assertRedirect(route('vtual.vtuber.show', 'aizawa-ema'));
    }

    public function test_missing_profile_returns_not_found(): void
    {
        $user = $this->userWithRole('User');
        $this->fakeAuthMe();

        Http::fake([
            '*/api/v1/vtual/vtubers/'.$this->creatorId => Http::response([
                'status' => 'error',
                'message' => 'Not found',
            ], 404),
        ]);

        $this->actingWithJwtCookie($user)
            ->get(route('vtual.vtuber.show', $this->creatorId))
            ->assertNotFound();
    }

    private function enableDiscordClaimVerifySetting(): void
    {
        SystemSetting::query()->create([
            'key' => 'vtual.claim.discord_verify_enabled',
            'group' => 'vtual',
            'type' => SettingType::Bool->value,
            'value' => true,
        ]);

        app(SystemSettingRepository::class)->rebuildCache();
    }

    private function enableAcceptAnywaySetting(): void
    {
        SystemSetting::query()->create([
            'key' => 'vtual.claim.accept_anyway_enabled',
            'group' => 'vtual',
            'type' => SettingType::Bool->value,
            'value' => true,
        ]);

        app(SystemSettingRepository::class)->rebuildCache();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function actingWithJwtCookie(User $user): static
    {
        $token = auth('api')->login($user);

        return $this->withCookie(AuthCookie::NAME, $token);
    }

    private function fakeAuthMe(): void
    {
        Http::fake([
            '*/api/v1/auth/me' => Http::response([
                'status' => 'success',
                'data' => [
                    'id' => 'test',
                    'name' => 'Test',
                    'email' => 'test@example.com',
                    'roles' => [],
                    'permissions' => [],
                    'email_verified_at' => now()->toIso8601String(),
                    'two_factor_enabled' => false,
                ],
            ], 200),
        ]);
    }

    private function assertClaimConfigJsonIsParseable(string $html): void
    {
        $body = $this->extractClaimBootstrapBody($html);
        $this->assertNotFalse(
            $body,
            'Claim page missing bootstrap JSON (data-vue-vtual-claim script).',
        );
        $this->assertStringNotContainsString(
            '&quot;',
            (string) $body,
            'Claim bootstrap JSON must not be HTML-escaped (use @json in Blade).',
        );
        $decoded = json_decode((string) $body, true);
        $this->assertIsArray(
            $decoded,
            'Claim bootstrap script must be valid JSON: '.json_last_error_msg(),
        );
        $this->assertArrayHasKey('profile', $decoded, 'Bootstrap must have profile key.');
        $this->assertArrayHasKey('realtime', $decoded, 'Bootstrap must have realtime key.');
        $this->assertArrayHasKey('routes', $decoded, 'Bootstrap must have routes key.');
        $this->assertArrayHasKey('labels', $decoded, 'Bootstrap must have labels key.');
    }

    private function extractClaimBootstrapBody(string $html): string|false
    {
        if (! preg_match('/<script[^>]*data-vue-vtual-claim[^>]*>(.*?)<\/script>/s', $html, $matches)) {
            return false;
        }

        return trim($matches[1]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function extractClaimBootstrapArray(string $html): ?array
    {
        $body = $this->extractClaimBootstrapBody($html);

        if ($body === false || $body === '') {
            return null;
        }

        $decoded = json_decode($body, true);

        return is_array($decoded) ? $decoded : null;
    }
}
