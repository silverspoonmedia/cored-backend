<?php

namespace App\Http\Middleware;

use App\Domain\Identity\Services\Auth\AuthService;
use App\Support\Auth\AuthCookie;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\JWTGuard;
use Symfony\Component\HttpFoundation\Response;

/**
 * Silent JWT refresh for the Blade admin.
 *
 * Identity lives in the HttpOnly {@see AuthCookie::NAME} cookie — not the Laravel
 * session. Access tokens hard-expire at `jwt.ttl`; without this middleware the
 * browser keeps a dead cookie and every `web.auth` hit looks like a "session
 * expired" logout even while the user is still active.
 *
 * Runs after {@see AuthenticateWithJwtCookie}:
 *  - Valid token near `exp` → refresh, re-bind guard, queue new cookie on response
 *  - Expired token still inside `jwt.refresh_ttl` → same (re-bind so `web.auth` passes)
 *  - Invalid / blacklisted / past refresh window → leave guest (existing login redirect)
 *
 * Concurrent tabs: pair with `jwt.blacklist_grace_period` so the blacklisted
 * predecessor is accepted briefly after refresh.
 */
class RefreshJwtCookie
{
    public function __construct(
        private readonly AuthService $authService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->cookie(AuthCookie::NAME);

        if (! is_string($token) || $token === '') {
            return $next($request);
        }

        if (! $this->shouldRefresh($token)) {
            return $next($request);
        }

        try {
            $payload = $this->authService->refreshFromToken($token);
        } catch (JWTException) {
            return $next($request);
        }

        $newToken = $payload['token'];
        $expiresIn = (int) $payload['expires_in'];

        $this->bindToken($newToken);

        // ApiClient reads the request cookie for the Bearer header — swap in the
        // fresh token so server→API calls on this same request do not use the
        // blacklisted predecessor.
        $request->cookies->set(AuthCookie::NAME, $newToken);

        // Queue via the cookie jar so AddQueuedCookiesToResponse attaches the
        // Set-Cookie on any response type (Illuminate or Symfony).
        cookie()->queue(AuthCookie::make($newToken, $expiresIn));

        return $next($request);
    }

    /**
     * Refresh when the token is already expired (guest after AuthenticateWithJwtCookie)
     * or when remaining life is at or below the configured skew window.
     */
    private function shouldRefresh(string $token): bool
    {
        /** @var JWTGuard $guard */
        $guard = Auth::guard('api');

        if (! $guard->check()) {
            // Cookie present but guard is guest → expired / invalid; attempt refresh.
            return true;
        }

        try {
            $payload = $guard->setToken($token)->payload();
            $exp = (int) $payload->get('exp');
        } catch (JWTException) {
            return true;
        }

        // Carbon so PHPUnit travel() / setTestNow freezes skew math (native time() does not).
        $remaining = $exp - Carbon::now()->getTimestamp();
        $skewSeconds = $this->skewSeconds();

        return $remaining <= $skewSeconds;
    }

    private function skewSeconds(): int
    {
        $skewMinutes = (int) config('jwt.refresh_skew', 5);

        if ($skewMinutes < 0) {
            $skewMinutes = 0;
        }

        return $skewMinutes * 60;
    }

    private function bindToken(string $token): void
    {
        Auth::forgetGuards();

        /** @var JWTGuard $guard */
        $guard = Auth::guard('api');

        try {
            $guard->setToken($token)->user();
        } catch (JWTException) {
            // Leave guest; web.auth will redirect.
        }
    }
}
