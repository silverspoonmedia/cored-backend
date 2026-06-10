<?php

namespace App\Http\Controllers\Web\Concerns;

use App\Services\Auth\AuthService;
use App\Support\Auth\AuthCookie;
use Illuminate\Http\RedirectResponse;

/**
 * Shared by the login and register web controllers: takes the auth payload
 * produced by {@see AuthService} (the very same payload the
 * API returns) and persists its JWT in the HttpOnly cookie, then redirects.
 */
trait IssuesAuthCookie
{
    /**
     * @param  array{token: string, expires_in: int}  $payload
     */
    protected function redirectAuthenticated(array $payload, string $route = 'dashboard'): RedirectResponse
    {
        $cookie = AuthCookie::make($payload['token'], (int) $payload['expires_in']);

        return redirect()
            ->intended(route($route))
            ->withCookie($cookie);
    }
}
