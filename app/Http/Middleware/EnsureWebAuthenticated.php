<?php

namespace App\Http\Middleware;

use App\Support\Auth\AuthCookie;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Web counterpart of `auth:api`. The JWT is already resolved into the `api`
 * guard by {@see AuthenticateWithJwtCookie}; this guard simply redirects guests
 * to the login screen instead of returning a JSON 401. Stores the intended URL
 * so users land back where they were after signing in.
 */
class EnsureWebAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::guard('api')->check()) {
            $request->session()->put('url.intended', $request->fullUrl());

            // Drop any stale cookie so the browser stops replaying a dead token.
            return redirect()
                ->route('login')
                ->withCookie(AuthCookie::forget())
                ->with('error', __('web/session.login_required'));
        }

        return $next($request);
    }
}
