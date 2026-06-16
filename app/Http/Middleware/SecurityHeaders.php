<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds security headers to HTTP responses.
 *
 * Protects against common web vulnerabilities:
 * - Clickjacking (X-Frame-Options)
 * - MIME sniffing (X-Content-Type-Options)
 * - Referrer leakage (Referrer-Policy)
 * - Unwanted browser features (Permissions-Policy)
 *
 * CSP is intentionally omitted to avoid breaking AdminLTE CDN assets
 * and Vite hot reload. Add CSP headers manually if needed.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Prevent page from being framed (clickjacking protection)
        $response->headers->set('X-Frame-Options', 'DENY');

        // Prevent MIME sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Control referrer information
        $response->headers->set('Referrer-Policy', 'no-referrer-when-downgrade');

        // Disable potentially dangerous browser features
        $response->headers->set(
            'Permissions-Policy',
            'geolocation=(), microphone=(), camera=(), payment=(), usb=(), magnetometer=(), gyroscope=()'
        );

        return $response;
    }
}
