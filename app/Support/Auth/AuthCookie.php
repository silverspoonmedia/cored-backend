<?php

namespace App\Support\Auth;

use App\Http\Middleware\AuthenticateWithJwtCookie;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Centralizes the HttpOnly cookie that carries the stateless JWT for browser
 * sessions. The web layer never touches Laravel's session guard; it stores the
 * same token the API issues and feeds it back into the `api` guard on each
 * request (see {@see AuthenticateWithJwtCookie}).
 */
class AuthCookie
{
    public const NAME = 'auth_token';

    /**
     * Build the cookie that persists a freshly issued JWT.
     *
     * @param  int  $ttlSeconds  Lifetime in seconds (mirrors the token's `expires_in`).
     */
    public static function make(string $token, int $ttlSeconds): Cookie
    {
        return cookie()->make(
            name: self::NAME,
            value: $token,
            minutes: (int) ceil($ttlSeconds / 60),
            path: '/',
            domain: null,
            secure: (bool) config('session.secure', false),
            httpOnly: true,
            raw: false,
            sameSite: 'lax',
        );
    }

    /**
     * Build the cookie that clears the stored JWT on logout.
     */
    public static function forget(): Cookie
    {
        return cookie()->forget(self::NAME, '/');
    }
}
