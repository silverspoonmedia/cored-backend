<?php

namespace App\Domain\Service\Http\Controllers\Web;

use App\Http\Controllers\Web\Concerns\ConsumesApi;
use App\Http\Controllers\Web\Concerns\ResolvesPaymentCheckoutContext;
use App\Http\Controllers\Web\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Service purchase show — payment hub for checkout invoices.
 */
class ServicePurchaseController extends Controller
{
    use ConsumesApi;
    use ResolvesPaymentCheckoutContext;

    public function show(string $purchase): View|RedirectResponse
    {
        $response = $this->api()->get("service-purchases/{$purchase}");

        if (! $response->successful()) {
            return redirect()->route('services.index')->with('error', $this->apiMessage($response));
        }

        return $this->webView('service-purchases.show', array_merge([
            'purchase' => $response->json('data', []),
        ], $this->paymentCheckoutContext()));
    }
}
