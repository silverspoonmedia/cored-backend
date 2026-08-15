<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tags API requests brokered by the Vue-Workers BFF (header `X-SPA-Origin`)
 * and audits the ones that were not. Never blocks: PHPUnit, mobile clients,
 * and third-party integrations hit the API directly by design — the audit
 * trail exists so abuse patterns stand out from legitimate direct traffic.
 */
class RequireSpaOrigin
{
    public const ATTRIBUTE = 'spa_origin';

    public function handle(Request $request, Closure $next): Response
    {
        $viaWorker = $request->headers->get('X-SPA-Origin') === '1';

        $request->attributes->set(self::ATTRIBUTE, $viaWorker);

        if (! $viaWorker) {
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
