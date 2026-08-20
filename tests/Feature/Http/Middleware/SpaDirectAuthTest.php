<?php

namespace Tests\Feature\Http\Middleware;

use App\Domain\Identity\Models\User;
use App\Support\Auth\SpaAuthCookie;
use App\Support\Auth\SpaCsrf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

/**
 * Covers the Worker-less data path: the SPA authenticates direct API calls with
 * the cross-site HttpOnly {@see SpaAuthCookie}, and mutations must pass the
 * double-submit CSRF check.
 *
 * `withCredentials()` mirrors the browser's `credentials: 'include'`: Laravel's
 * JSON test verbs only attach cookies when it is set.
 */
class SpaDirectAuthTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_csrf_endpoint_issues_token_and_cookie(): void
    {
        $response = $this->getJson('/api/v1/csrf');

        $response->assertOk()
            ->assertJsonPath('data.token', fn ($token): bool => is_string($token) && $token !== '');

        $response->assertCookie(SpaCsrf::COOKIE_NAME);
    }

    public function test_cookie_authenticated_get_authenticates_user(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);

        $this->withCookie(SpaAuthCookie::NAME, $token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_mutation_with_cookie_but_no_csrf_header_is_rejected(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);

        // Non-auth-session mutation with a cookie session but no CSRF header.
        $this->withCredentials()
            ->withCookie(SpaAuthCookie::NAME, $token)
            ->postJson('/api/v1/auth/locale', ['locale' => 'en'])
            ->assertStatus(419);
    }

    public function test_mutation_with_cookie_and_matching_csrf_passes(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);
        $csrf = SpaCsrf::token();

        // spa_csrf is excluded from EncryptCookies (JS must read/echo it), so the
        // browser sends it plaintext; mirror that with an unencrypted cookie.
        $this->withCredentials()
            ->withCookie(SpaAuthCookie::NAME, $token)
            ->withUnencryptedCookie(SpaCsrf::COOKIE_NAME, $csrf)
            ->withHeader(SpaCsrf::HEADER, $csrf)
            ->postJson('/api/v1/auth/locale', ['locale' => 'en'])
            ->assertOk();
    }

    public function test_mutation_with_mismatched_csrf_is_rejected(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);

        $this->withCredentials()
            ->withCookie(SpaAuthCookie::NAME, $token)
            ->withUnencryptedCookie(SpaCsrf::COOKIE_NAME, SpaCsrf::token())
            ->withHeader(SpaCsrf::HEADER, SpaCsrf::token())
            ->postJson('/api/v1/auth/locale', ['locale' => 'en'])
            ->assertStatus(419);
    }

    public function test_bearer_mutation_skips_csrf_check(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);

        // Bearer (non-cookie) requests are not CSRF-able; no CSRF token needed.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/logout')
            ->assertOk();
    }

    public function test_auth_session_login_with_stale_cookie_and_no_csrf_passes(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('secret-password'),
        ]);
        $staleToken = $this->tokenFor($user);

        // A stale __Host-spa_jwt (from a prior session) plus no usable spa_csrf
        // must not block re-login: the auth-session path is CSRF-skipped so the
        // client can establish a fresh, consistent session.
        $this->withCredentials()
            ->withCookie(SpaAuthCookie::NAME, $staleToken)
            ->postJson('/api/v1/auth/login', [
                'email' => $user->email,
                'password' => 'secret-password',
            ])
            ->assertOk();
    }

    public function test_auth_session_refresh_is_not_blocked_by_csrf(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);

        // Refresh is CSRF-skipped: it must not 419. Whether it returns 200 (token
        // bound from cookie) or 401 (service-level token resolution) is outside
        // the CSRF concern — the point is the middleware lets it through.
        $response = $this->withCredentials()
            ->withCookie(SpaAuthCookie::NAME, $token)
            ->postJson('/api/v1/auth/refresh');

        $this->assertNotSame(419, $response->getStatusCode());
    }

    public function test_refresh_via_cookie_without_bearer_succeeds(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);

        // Cookie-mode SPA sends no Authorization header: refresh must resolve the
        // token from the HttpOnly cookie and return a fresh payload.
        $response = $this->withCredentials()
            ->withCookie(SpaAuthCookie::NAME, $token)
            ->postJson('/api/v1/auth/refresh');

        $response->assertOk()
            ->assertJsonPath('status', 'success');
    }

    private function tokenFor(User $user, int $ttlMinutes = 60): string
    {
        auth('api')->factory()->setTTL($ttlMinutes);
        $token = auth('api')->login($user);
        $this->assertIsString($token);

        auth()->forgetGuards();

        return $token;
    }
}
