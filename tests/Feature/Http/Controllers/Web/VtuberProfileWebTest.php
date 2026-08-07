<?php

namespace Tests\Feature\Http\Controllers\Web;

use App\Domain\Identity\Models\User;
use App\Support\Auth\AuthCookie;
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
        $this->seedRolesAndPermissions();
        $this->seed(VtualRoleSeeder::class);
        $this->creatorId = (string) Str::uuid();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('vtual.vtuber.show', $this->creatorId))
            ->assertRedirect(route('login'));
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
}
