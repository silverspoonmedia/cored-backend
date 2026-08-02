<?php

namespace App\Domain\Service\Http\Controllers\Web;

use App\Http\Controllers\Web\Concerns\ConsumesApi;
use App\Http\Controllers\Web\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CurrencyController extends Controller
{
    use ConsumesApi;

    public function index(): View|RedirectResponse
    {
        $response = $this->api()->get('currencies');

        if (! $response->successful()) {
            return redirect()->route('settings.index')->with('error', $this->apiMessage($response));
        }

        return $this->webView('currencies.index', [
            'currencies' => $response->json('data', []),
        ]);
    }

    public function update(Request $request, string $code): RedirectResponse
    {
        $response = $this->api()->put("currencies/{$code}", $request->only(['is_active', 'is_settlement']));

        if (! $response->successful()) {
            return redirect()->route('currencies.index')->with('error', $this->apiMessage($response));
        }

        return redirect()->route('currencies.index')->with('success', __('api/currencies.updated'));
    }
}
