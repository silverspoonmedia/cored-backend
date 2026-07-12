<?php

namespace App\Domain\PaymentGateway\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Domain\PaymentGateway\Http\Requests\InvoiceChannelCheckoutRequest;
use App\Domain\PaymentGateway\Models\Invoice;
use App\Domain\PaymentGateway\Services\Payment\InvoiceChannelCheckoutService;
use App\Support\ApiResponder;
use Illuminate\Http\JsonResponse;

class InvoiceChannelCheckoutController extends Controller
{
    public function __construct(
        private InvoiceChannelCheckoutService $checkout,
    ) {}

    public function __invoke(InvoiceChannelCheckoutRequest $request, Invoice $invoice): JsonResponse
    {
        $result = $this->checkout->checkout($invoice, $request->validated());

        if (! $result['ok']) {
            $errorData = null;

            if (($result['snap_fallback_available'] ?? false) === true) {
                $errorData = [
                    'snap_fallback_available' => true,
                    'failed_routes' => $result['failed_routes'] ?? 0,
                ];
            }

            return ApiResponder::error($result['message'], $result['http'], $errorData);
        }

        return ApiResponder::success(
            $result['data'],
            __($result['message_key']),
            $result['http'],
        );
    }
}
