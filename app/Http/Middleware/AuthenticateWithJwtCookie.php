<?php

namespace App\Http\Middleware;

use App\Support\Auth\AuthCookie;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\JWTGuard;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bridges the stateless JWT backend to the Blade frontend.
 *
 * Reads the JWT from the {@see AuthCookie::NAME} cookie and binds it to the
 * `api` guard for the lifetime of the request. After this runs, everything that
 * already powers the API — `auth()->user()`, Spatie `permission:`/`role:`
 * middleware, and `UserPolicy` — works unchanged on web routes, with zero
 * duplication of the auth layer.
 *
 * It never rejects the request: a missing/invalid/expired token simply leaves
 * the guard as a guest. Route-level guards ({@see EnsureWebAuthenticated},
 * {@see RedirectIfWebAuthenticated}) decide what to do about that.
 */
class AuthenticateWithJwtCookie
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->cookie(AuthCookie::NAME);

        if (is_string($token) && $token !== '') {
            /** @var JWTGuard $guard */
            $guard = Auth::guard('api');

            try {
                // Resolving the user validates the token; an invalid token
                // throws and a revoked user yields null — both mean "guest".
                $guard->setToken($token)->user();
            } catch (JWTException) {
                // Invalid/expired/blacklisted token: continue as a guest.
            }
        }

        return $next($request);
    }
}
