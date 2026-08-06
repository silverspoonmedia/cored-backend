<?php

namespace App\Domain\Vtual\Http\Controllers\Web;

use App\Http\Controllers\Web\Concerns\ConsumesApi;
use App\Http\Controllers\Web\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * vTual external product hub — pure consumer of `/api/v1/vtual/*`.
 */
class VtualController extends Controller
{
    use ConsumesApi;

    public function index(): View|RedirectResponse
    {
        $platforms = $this->api()->get('vtual/platforms');

        if (! $platforms->successful()) {
            return redirect()->route('dashboard')->with('error', $this->apiMessage($platforms));
        }

        return $this->webView('vtual.index', [
            'platforms' => collect($platforms->json('data', [])),
        ]);
    }

    public function storeCreator(Request $request): RedirectResponse|JsonResponse
    {
        $response = $this->api()->post('vtual/creators', $this->apiPayload($request, [
            'display_name',
            'slug',
            'bio',
            'avatar_url',
        ]));

        return $this->respondFromApi(
            $request,
            $response,
            fn () => redirect()->route('vtual.index')
                ->with('success', $this->apiMessage($response)),
            __('web/vtual.ajax.profile_saved'),
        );
    }

    public function storeManagement(Request $request): RedirectResponse|JsonResponse
    {
        $response = $this->api()->post('vtual/managements', $this->apiPayload($request, [
            'name',
            'slug',
            'description',
            'logo_url',
        ]));

        return $this->respondFromApi(
            $request,
            $response,
            fn () => redirect()->route('vtual.index')
                ->with('success', $this->apiMessage($response)),
            __('web/vtual.ajax.profile_saved'),
        );
    }
}
