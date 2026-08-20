<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tags API requests brokered by the Vue-Workers BFF (header `X-SPA-Origin`)
 * and audits the ones that were not.
 *
 * Audit-only. Direct browser-to-API traffic is now the sanctioned data path
 * (the Worker only brokers auth), so a missing header is no longer a violation
 * — it is simply logged for observability. The header is trivially spoofable
 * and was never an auth control; real protection lives in JWT auth, RBAC,
 * throttling, and the WAF. OPTIONS preflights always pass.
 */
class RequireSpaOrigin
{
    public const ATTRIBUTE = 'spa_origin';

    public function handle(Request $request, Closure $next): Response
    {
        $viaWorker = $request->headers->get('X-SPA-Origin') === '1';

        $request->attributes->set(self::ATTRIBUTE, $viaWorker);

        if (! $viaWorker && $request->method() !== 'OPTIONS') {
            Log::channel('spa_audit')->info('api.direct', [
                'method' => $request->method(),
                'path' => $request->path(),
                'ip' => $request->ip(),
                'user_id' => $request->user()?->getAuthIdentifier(),
            ]);
        }

        return $next($request);
    }
}
