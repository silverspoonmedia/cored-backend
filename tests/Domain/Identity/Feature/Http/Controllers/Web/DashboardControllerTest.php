<?php

namespace Tests\Domain\Identity\Feature\Http\Controllers\Web;

use App\Domain\Identity\Models\User;
use App\Support\Auth\AuthCookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
    }

    private function actingWithJwtCookie(User $user): static
    {
        $token = auth('api')->login($user);

        return $this->withCookie(AuthCookie::NAME, $token);
    }

    public function test_dashboard_html_includes_vue_session_island_mount(): void
    {
        $this->withoutVite();

        $user = User::factory()->create(['name' => 'Ada Lovelace', 'email' => 'ada@example.com']);
        $user->assignRole('User');

        Http::fake([
            '*/api/v1/auth/me' => Http::response([
                'status' => 'success',
                'data' => [
                    'name' => 'Ada Lovelace',
                    'email' => 'ada@example.com',
                    'roles' => ['User'],
                    'permissions' => [],
                    'email_verified_at' => now()->toIso8601String(),
                    'two_factor_enabled' => false,
                ],
            ], 200),
        ]);

        $this->actingWithJwtCookie($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('id="vue-dashboard-session"', false)
            ->assertSee('data-vue-dashboard-session', false)
            ->assertSee('Ada Lovelace', false)
            ->assertSee(__('web/dashboard.session_island.title'), false);
    }

    public function test_dashboard_json_returns_auth_me_profile(): void
    {
        $user = User::factory()->create(['name' => 'Ada Lovelace', 'email' => 'ada@example.com']);
        $user->assignRole('User');

        Http::fake([
            '*/api/v1/auth/me' => Http::response([
                'status' => 'success',
                'data' => [
                    'name' => 'Ada Lovelace',
                    'email' => 'ada@example.com',
                    'roles' => ['User'],
                    'permissions' => [],
                    'email_verified_at' => now()->toIso8601String(),
                    'two_factor_enabled' => false,
                ],
            ], 200),
        ]);

        $this->actingWithJwtCookie($user)
            ->getJson(route('dashboard'))
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.name', 'Ada Lovelace')
            ->assertJsonPath('data.email', 'ada@example.com')
            ->assertJsonPath('data.roles.0', 'User');
    }

    public function test_dashboard_json_401_clears_cookie_and_redirects_payload(): void
    {
        $user = User::factory()->create();
        $user->assignRole('User');

        Http::fake([
            '*/api/v1/auth/me' => Http::response([
                'status' => 'error',
                'message' => 'Unauthenticated.',
            ], 401),
        ]);

        $response = $this->actingWithJwtCookie($user)
            ->getJson(route('dashboard'));

        $response->assertUnauthorized()
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('redirect', route('login'));
    }
}
