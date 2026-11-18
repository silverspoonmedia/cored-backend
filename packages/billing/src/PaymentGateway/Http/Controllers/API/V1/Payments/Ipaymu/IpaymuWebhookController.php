<?php

namespace App\Domain\PaymentGateway\Http\Controllers\API\V1\Payments\Ipaymu;

use App\Domain\PaymentGateway\Services\Payment\Ipaymu\IpaymuPaymentWebhookService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IpaymuWebhookController extends Controller
{
    public function __construct(
        private IpaymuPaymentWebhookService $webhook
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        return $this->webhook->handle($request);
    }
}
