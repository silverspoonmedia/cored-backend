<?php

namespace Tests\Feature\Http\Middleware;

use App\Models\User;
use App\Support\Auth\AuthCookie;
use App\Support\Web\IntendedUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class EnsureWebAuthenticatedIntendedUrlTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_guest_full_page_navigation_stores_intended_url(): void
    {
        $response = $this->get('/users');

        $response->assertRedirect(route('login'));
        $response->assertSessionHas(IntendedUrl::SESSION_KEY, url('/users'));
    }

    public function test_guest_notification_poll_does_not_store_intended_url(): void
    {
        $response = $this->getJson('/notifications/unread-count');

        $response->assertRedirect(route('login'));
        $response->assertSessionMissing(IntendedUrl::SESSION_KEY);
    }

    public function test_notification_poll_does_not_overwrite_existing_intended_url(): void
    {
        $this->get('/users')->assertRedirect(route('login'));

        $this->getJson('/notifications/unread-count')->assertRedirect(route('login'));

        $this->assertSame(url('/users'), session(IntendedUrl::SESSION_KEY));
    }

    public function test_login_redirects_to_stored_intended_page(): void
    {
        $user = User::factory()->create([
            'email' => 'redirect@example.com',
            'password' => Hash::make('password123'),
        ]);
        $user->assignRole('Admin');

        $token = $this->makeTokenFor($user);

        Http::fake([
            '*/api/v1/auth/login' => Http::response([
                'status' => 'success',
                'message' => 'OK',
                'data' => [
                    'token' => $token,
                    'expires_in' => 3600,
                    'token_type' => 'bearer',
                ],
            ], 200),
        ]);

        $this->get('/users')->assertRedirect(route('login'));

        $response = $this->from(route('login'))->post('/login', [
            'email' => 'redirect@example.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('users.index'));
        $response->assertSessionMissing(IntendedUrl::SESSION_KEY);
    }

    public function test_login_rejects_poisoned_intended_url_and_falls_back_to_dashboard(): void
    {
        $user = User::factory()->create([
            'email' => 'sanitize@example.com',
            'password' => Hash::make('password123'),
        ]);
        $user->assignRole('Admin');

        $token = $this->makeTokenFor($user);

        Http::fake([
            '*/api/v1/auth/login' => Http::response([
                'status' => 'success',
                'message' => 'OK',
                'data' => [
                    'token' => $token,
                    'expires_in' => 3600,
                    'token_type' => 'bearer',
                ],
            ], 200),
        ]);

        $response = $this->withSession([
            IntendedUrl::SESSION_KEY => url('/notifications/unread-count'),
        ])->post('/login', [
            'email' => 'sanitize@example.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('dashboard'));
    }

    public function test_logout_clears_intended_url(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Admin');

        Http::fake([
            '*/api/v1/auth/logout' => Http::response([
                'status' => 'success',
                'message' => 'OK',
            ], 200),
        ]);

        $response = $this->actingWithJwtCookie($user)
            ->withSession([IntendedUrl::SESSION_KEY => url('/users')])
            ->post('/logout');

        $response->assertRedirect(route('login'));
        $response->assertSessionMissing(IntendedUrl::SESSION_KEY);
    }

    private function actingWithJwtCookie(User $user): static
    {
        return $this->withCookie(AuthCookie::NAME, $this->makeTokenFor($user));
    }

    private function makeTokenFor(User $user): string
    {
        $token = auth('api')->tokenById($user->id);

        $this->assertNotNull($token);

        return $token;
    }
}
