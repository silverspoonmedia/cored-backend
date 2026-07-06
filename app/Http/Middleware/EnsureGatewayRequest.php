<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * When gateway enforcement is enabled, API routes accept only requests that
 * carry a valid X-Gateway-Secret header or originate from an allowlisted IP.
 */
class EnsureGatewayRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('gateway.enforce')) {
            return $next($request);
        }

        $secret = (string) config('gateway.secret');

        if ($secret !== '' && $request->header('X-Gateway-Secret') === $secret) {
            return $next($request);
        }

        $allowedIps = config('gateway.allowed_ips', []);

        if ($allowedIps !== [] && in_array($request->ip(), $allowedIps, true)) {
            return $next($request);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Forbidden.',
        ], 403);
    }
}
