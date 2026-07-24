<?php

namespace App\Support\DomainAccess\Http\Controllers\Web;

use App\Http\Controllers\Web\Concerns\ConsumesApi;
use App\Http\Controllers\Web\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DomainAccessController extends Controller
{
    use ConsumesApi;

    public function index(): View|RedirectResponse
    {
        $response = $this->api()->get('admin/domain-access');

        if (! $response->successful()) {
            return redirect()->route('dashboard')->with('error', $this->apiMessage($response));
        }

        return $this->webView('domain-access.index', [
            'domains' => collect($response->json('data', [])),
        ]);
    }

    public function update(Request $request, string $productKey): RedirectResponse|JsonResponse
    {
        $response = $this->api()->put("admin/domain-access/{$productKey}", $request->only(['visibility']));

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => redirect()->route('domain-access.index')
                ->with('success', __('domain-access.updated')),
            __('domain-access.updated'),
        );
    }
}
