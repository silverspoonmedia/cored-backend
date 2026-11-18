<?php

namespace App\Domain\PaymentGateway\Http\Controllers\API\V1;

use App\Domain\PaymentGateway\Http\Requests\InvoiceCheckoutChannelsRequest;
use App\Domain\PaymentGateway\Http\Resources\PaymentChannelResource;
use App\Domain\PaymentGateway\Models\Invoice;
use App\Domain\PaymentGateway\Services\PaymentChannelService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ResolvesResourceArray;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class InvoiceCheckoutChannelsController extends Controller
{
    use ApiResponse;
    use ResolvesResourceArray;

    public function __construct(
        private PaymentChannelService $channels,
    ) {}

    public function __invoke(InvoiceCheckoutChannelsRequest $request, Invoice $invoice): JsonResponse
    {
        if (! in_array((string) $invoice->status, ['pending', 'challenge'], true)) {
            return $this->errorResponse(__('api/payments.invoices.checkout_channels_not_payable'), 422);
        }

        $channels = $this->channels->listAvailable((float) $invoice->subtotal);

        return $this->successResponse(
            $this->resolveResource(PaymentChannelResource::collection($channels)),
            __('api/payments.invoices.checkout_channels'),
        );
    }
}
