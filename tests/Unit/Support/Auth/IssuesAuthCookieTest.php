<?php

namespace Tests\Unit\Support\Auth;

use App\Http\Controllers\Web\Concerns\IssuesAuthCookie;
use App\Support\Auth\AuthCookie;
use Illuminate\Http\Request;
use Tests\TestCase;

class IssuesAuthCookieTest extends TestCase
{
    public function test_redirect_authenticated_attaches_auth_token_cookie(): void
    {
        $controller = new class
        {
            use IssuesAuthCookie;

            public function go(array $payload, Request $request)
            {
                return $this->redirectAuthenticated($payload, 'dashboard', $request);
            }
        };

        $request = Request::create('/login', 'POST');
        $request->setLaravelSession($this->app['session.store']);

        $response = $controller->go([
            'token' => 'test-jwt-token-value',
            'expires_in' => 3600,
        ], $request);

        $cookie = collect($response->headers->getCookies())
            ->first(fn ($c) => $c->getName() === AuthCookie::NAME);

        $this->assertNotNull($cookie);
        $this->assertSame('test-jwt-token-value', $cookie->getValue());
    }
}
