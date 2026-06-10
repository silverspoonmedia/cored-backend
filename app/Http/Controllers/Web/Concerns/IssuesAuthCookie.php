<?php

namespace App\Http\Controllers\Web\Concerns;

use App\Support\Auth\AuthCookie;
use Illuminate\Http\RedirectResponse;

/**
 * Shared by the login and register web controllers: takes the `data` payload
 * returned by `POST /api/v1/auth/{login,register}` and persists its JWT in the
 * HttpOnly cookie, then redirects.
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
