<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Web\Concerns\ConsumesApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Proxies paginated lookup endpoints for Blade Select2 AJAX fields.
 */
class LookupController extends Controller
{
    use ConsumesApi;

    public function users(Request $request): JsonResponse
    {
        return $this->proxyLookup('lookups/users', $request);
    }

    public function menus(Request $request): JsonResponse
    {
        return $this->proxyLookup('lookups/menus', $request);
    }

    public function roles(Request $request): JsonResponse
    {
        return $this->proxyLookup('lookups/roles', $request);
    }

    private function proxyLookup(string $uri, Request $request): JsonResponse
    {
        $response = $this->api()->get($uri, $request->query());

        return response()->json($response->json(), $response->status());
    }
}
