<?php

namespace App\Domain\PaymentGateway\Http\Controllers\API\V1\Payments\Midtrans;

use App\Http\Controllers\Controller;
use App\Domain\PaymentGateway\Http\Requests\Payments\Midtrans\MidtransChargeRequest;
use App\Domain\PaymentGateway\Http\Requests\Payments\Midtrans\MidtransRefundRequest;
use App\Domain\PaymentGateway\Http\Requests\Payments\Midtrans\MidtransSnapTokenRequest;
use App\Domain\PaymentGateway\Models\Invoice;
use App\Domain\PaymentGateway\Services\Payment\Midtrans\MidtransPaymentService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class MidtransPaymentController extends Controller
{
    use ApiResponse;

    public function __construct(
        private MidtransPaymentService $payments
    ) {}

    public function createSnapToken(MidtransSnapTokenRequest $request): JsonResponse
    {
        return $this->fromServiceResult($this->payments->createSnapToken($request->validated()));
    }

    public function charge(MidtransChargeRequest $request): JsonResponse
    {
        return $this->fromServiceResult($this->payments->charge($request->validated()));
    }

    public function show(string $orderId): JsonResponse
    {
        return $this->fromServiceResult($this->payments->getTransactionStatus($orderId));
    }

    public function cancel(string $orderId): JsonResponse
    {
        return $this->fromServiceResult($this->payments->cancelTransaction($orderId));
    }

    public function expire(string $orderId): JsonResponse
    {
        return $this->fromServiceResult($this->payments->expireTransaction($orderId));
    }

    public function refund(MidtransRefundRequest $request, string $orderId): JsonResponse
    {
        $amount = $request->filled('amount') ? (int) $request->validated('amount') : null;

        return $this->fromServiceResult($this->payments->refundTransaction($orderId, $amount));
    }

    public function approve(string $orderId): JsonResponse
    {
        return $this->fromServiceResult($this->payments->approveTransaction($orderId));
    }

    public function deny(string $orderId): JsonResponse
    {
        return $this->fromServiceResult($this->payments->denyTransaction($orderId));
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
