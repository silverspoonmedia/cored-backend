<?php

namespace App\Domain\PaymentGateway\Http\Controllers\API\V1\Payments\Ipaymu;

use App\Domain\PaymentGateway\Http\Requests\Payments\Ipaymu\IpaymuDirectPaymentRequest;
use App\Domain\PaymentGateway\Http\Requests\Payments\Ipaymu\IpaymuSnapTokenRequest;
use App\Domain\PaymentGateway\Models\Invoice;
use App\Domain\PaymentGateway\Services\Payment\Ipaymu\IpaymuPaymentService;
use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class IpaymuPaymentController extends Controller
{
    use ApiResponse;

    public function __construct(
        private IpaymuPaymentService $payments
    ) {}

    public function createSnapToken(IpaymuSnapTokenRequest $request): JsonResponse
    {
        return $this->fromServiceResult($this->payments->createSnapToken($request->validated()));
    }

    public function charge(IpaymuDirectPaymentRequest $request): JsonResponse
    {
        return $this->fromServiceResult($this->payments->charge($request->validated()));
    }

    public function show(string $orderId): JsonResponse
    {
        return $this->fromServiceResult($this->payments->getTransactionStatus($orderId));
    }

    public function syncInvoiceStatus(Invoice $invoice): JsonResponse
    {
        return $this->fromServiceResult($this->payments->syncInvoiceStatus($invoice));
    }

    /**
     * @param  array{ok: true, data: array<string, mixed>, message_key: string, http?: int}|array{ok: false, message: string, http: int}  $result
     */
    protected function fromServiceResult(array $result): JsonResponse
    {
        if (! $result['ok']) {
            return $this->errorResponse($result['message'], $result['http']);
        }

        $code = $result['http'] ?? 200;

        return $this->successResponse($result['data'], __($result['message_key']), $code);
    }
}
