<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tags API requests brokered by the Vue-Workers BFF (header `X-SPA-Origin`)
 * and audits the ones that were not. On non-local environments the header is
 * enforced: the SPA is the only sanctioned consumer of the public API, so a
 * direct hit to the origin (tunnel hostname in hand) gets a 403 instead of
 * raw JSON. Local and testing stay open for PHPUnit, tinker, and tooling.
 * OPTIONS preflights always pass — browsers never send custom headers there.
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

            if ($request->method() !== 'OPTIONS' && ! app()->environment(['local', 'testing'])) {
                abort(403, __('api/common.spa_origin_required'));
            }
        }

        return $next($request);
    }
}
