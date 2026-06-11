<?php

namespace App\Http\Middleware;

use App\Support\Localization\LocaleCookie;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the active application locale for the current request.
 *
 * Priority:
 * 1. Authenticated user's DB preference (users.locale)
 * 2. Locale cookie (guests and users without a DB value)
 * 3. config('app.locale')
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale($this->resolveLocale($request));

        return $next($request);
    }

    private function resolveLocale(Request $request): string
    {
        $supported = config('localization.supported_locales', ['en', 'id']);
        $default = (string) config('app.locale', 'en');

        $userColumn = config('localization.user_column');

        if (is_string($userColumn) && $userColumn !== '') {
            $user = Auth::guard('api')->user();

            if ($user !== null) {
                $userLocale = $user->{$userColumn} ?? null;

                if (is_string($userLocale) && in_array($userLocale, $supported, true)) {
                    return $userLocale;
                }
            }
        }

        $cookieLocale = $request->cookie(LocaleCookie::name());

        if (is_string($cookieLocale) && in_array($cookieLocale, $supported, true)) {
            return $cookieLocale;
        }

        if (in_array($default, $supported, true)) {
            return $default;
        }

        return $supported[0] ?? 'en';
    }
}
