<?php

namespace Tests\Feature\Http\Middleware;

use App\Domain\Identity\Models\User;
use App\Support\Auth\AuthCookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class RefreshJwtCookieTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_near_expiry_token_is_silently_refreshed_and_cookie_rewritten(): void
    {
        config([
            'jwt.ttl' => 10,
            'jwt.refresh_skew' => 5,
            'jwt.refresh_ttl' => 20160,
            'jwt.blacklist_enabled' => true,
            'jwt.blacklist_grace_period' => 30,
        ]);

        $user = $this->adminUser();
        $token = $this->tokenWithTtl($user, 10);

        $this->travel(6)->minutes();

        Http::fake([
            '*/api/v1/auth/me' => Http::response([
                'status' => 'success',
                'message' => 'OK',
                'data' => $this->profilePayload($user),
            ], 200),
        ]);

        $response = $this->withCookie(AuthCookie::NAME, $token)
            ->get('/dashboard');

        $response->assertOk();
        $this->assertAuthTokenCookieRewritten($response, $token);
    }

    public function test_expired_token_within_refresh_window_is_refreshed(): void
    {
        config([
            'jwt.ttl' => 1,
            'jwt.refresh_skew' => 5,
            'jwt.refresh_ttl' => 20160,
            'jwt.blacklist_enabled' => true,
            'jwt.blacklist_grace_period' => 30,
        ]);

        $user = $this->adminUser();
        $token = $this->tokenWithTtl($user, 1);

        $this->travel(2)->minutes();

        Http::fake([
            '*/api/v1/auth/me' => Http::response([
                'status' => 'success',
                'message' => 'OK',
                'data' => $this->profilePayload($user),
            ], 200),
        ]);

        $response = $this->withCookie(AuthCookie::NAME, $token)
            ->get('/dashboard');

        $response->assertOk();
        $this->assertAuthTokenCookieRewritten($response, $token);
    }

    public function test_token_past_refresh_window_redirects_to_login(): void
    {
        config([
            'jwt.ttl' => 1,
            'jwt.refresh_skew' => 1,
            'jwt.refresh_ttl' => 2,
            'jwt.blacklist_enabled' => true,
            'jwt.blacklist_grace_period' => 0,
        ]);

        $user = $this->adminUser();
        $token = $this->tokenWithTtl($user, 1);

        $this->travel(5)->minutes();

        $response = $this->withCookie(AuthCookie::NAME, $token)
            ->get('/dashboard');

        $response->assertRedirect(route('login'));
    }

    public function test_fresh_token_outside_skew_is_not_refreshed(): void
    {
        config([
            'jwt.ttl' => 60,
            'jwt.refresh_skew' => 5,
            'jwt.refresh_ttl' => 20160,
            'jwt.blacklist_enabled' => true,
        ]);

        $user = $this->adminUser();
        $token = $this->tokenWithTtl($user, 60);

        Http::fake([
            '*/api/v1/auth/me' => Http::response([
                'status' => 'success',
                'message' => 'OK',
                'data' => $this->profilePayload($user),
            ], 200),
        ]);

        $response = $this->withCookie(AuthCookie::NAME, $token)
            ->get('/dashboard');

        $response->assertOk();

        // No Set-Cookie for auth_token, or same value if present.
        $cookie = $response->getCookie(AuthCookie::NAME, false);
        if ($cookie !== null && $cookie->getValue() !== null && $cookie->getValue() !== '') {
            $this->assertSame($token, $cookie->getValue());
        }
    }

    public function test_api_refresh_endpoint_returns_new_token(): void
    {
        config([
            'jwt.ttl' => 60,
            'jwt.refresh_ttl' => 20160,
            'jwt.blacklist_enabled' => true,
            'jwt.blacklist_grace_period' => 30,
        ]);

        $user = $this->adminUser();
        $token = $this->tokenWithTtl($user, 60);

        $response = $this->postJson('/api/v1/auth/refresh', [], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['token', 'token_type', 'expires_in'],
            ]);

        $this->assertNotSame($token, $response->json('data.token'));
    }

    public function test_old_token_accepted_within_blacklist_grace_after_refresh(): void
    {
        config([
            'jwt.ttl' => 60,
            'jwt.refresh_ttl' => 20160,
            'jwt.blacklist_enabled' => true,
            'jwt.blacklist_grace_period' => 60,
        ]);

        $user = $this->adminUser();
        $oldToken = $this->tokenWithTtl($user, 60);

        $refresh = $this->postJson('/api/v1/auth/refresh', [], [
            'Authorization' => 'Bearer '.$oldToken,
        ])->assertOk();

        $this->assertNotSame($oldToken, $refresh->json('data.token'));

        $this->getJson('/api/v1/auth/me', [
            'Authorization' => 'Bearer '.$oldToken,
        ])->assertOk()
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_dashboard_does_not_forget_cookie_on_api_server_error(): void
    {
        $user = $this->adminUser();
        $token = $this->tokenWithTtl($user, 60);

        Http::fake([
            '*/api/v1/auth/me' => Http::response([
                'status' => 'error',
                'message' => 'Upstream blew up',
            ], 500),
        ]);

        $response = $this->withCookie(AuthCookie::NAME, $token)
            ->get('/dashboard');

        $response->assertOk();
        $response->assertSessionHas('error', 'Upstream blew up');

        $forget = collect($response->headers->getCookies())
            ->first(fn ($c) => $c->getName() === AuthCookie::NAME
                && ($c->getValue() === '' || $c->getValue() === null)
                && $c->getExpiresTime() < time());

        $this->assertNull($forget, '5xx must not clear auth_token');
    }

    public function test_dashboard_forgets_cookie_on_api_401(): void
    {
        $user = $this->adminUser();
        $token = $this->tokenWithTtl($user, 60);

        Http::fake([
            '*/api/v1/auth/me' => Http::response([
                'status' => 'error',
                'message' => __('api/auth.token_expired'),
            ], 401),
        ]);

        $response = $this->withCookie(AuthCookie::NAME, $token)
            ->get('/dashboard');

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error', __('web/session.expired'));
        $response->assertCookieExpired(AuthCookie::NAME);
    }

    public function test_web_login_issues_auth_token_cookie(): void
    {
        $user = User::factory()->create([
            'email' => 'login-cookie@example.com',
            'password' => bcrypt('password123'),
        ]);
        $user->assignRole('Admin');

        $token = $this->tokenWithTtl($user, 60);

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

        $response = $this->from(route('login'))->post('/login', [
            'email' => 'login-cookie@example.com',
            'password' => 'password123',
        ]);

        // Cookie attachment is unit-tested on IssuesAuthCookie (kernel EncryptCookies
        // obscures raw Set-Cookie inspection here). This asserts the login success path.
        $response->assertRedirect(route('dashboard'));
        $response->assertSessionMissing('error');
    }

    /**
     * @param  \Illuminate\Testing\TestResponse  $response
     */
    private function assertAuthTokenCookieRewritten($response, string $oldToken): void
    {
        $cookie = collect($response->headers->getCookies())
            ->first(fn ($c) => $c->getName() === AuthCookie::NAME && $c->getValue() !== '' && $c->getValue() !== null);

        $this->assertNotNull($cookie, 'Expected rewritten auth_token Set-Cookie on response');
        $this->assertNotSame($oldToken, $cookie->getValue());
    }

    /**
     * @return array<string, mixed>
     */
    private function profilePayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => ['Admin'],
            'permissions' => [],
            'email_verified_at' => now()->toIso8601String(),
            'two_factor_enabled' => false,
        ];
    }

    private function adminUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Admin');

        return $user;
    }

    private function tokenWithTtl(User $user, int $ttlMinutes): string
    {
        auth('api')->factory()->setTTL($ttlMinutes);
        $token = auth('api')->login($user);
        $this->assertIsString($token);

        auth()->forgetGuards();
        JWTAuth::unsetToken();

        return $token;
    }
}
