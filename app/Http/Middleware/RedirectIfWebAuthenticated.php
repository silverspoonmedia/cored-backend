<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps already-authenticated users out of guest-only screens (login, register,
 * password reset) by bouncing them to the dashboard.
 */
class RedirectIfWebAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::guard('api')->check()) {
            return redirect()->route('dashboard');
        }

        return $next($request);
    }
}
