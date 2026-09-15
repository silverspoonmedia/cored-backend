<?php

namespace App\Domain\PaymentGateway\Http\Controllers\API\V1;

use App\Domain\PaymentGateway\Http\Requests\PaymentIncomeRequest;
use App\Domain\PaymentGateway\Http\Resources\PaymentIncomeInvoiceResource;
use App\Domain\PaymentGateway\Services\PaymentIncomeAnalyticsService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ResolvesResourceArray;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class PaymentIncomeController extends Controller
{
    use ApiResponse;
    use ResolvesResourceArray;

    public function __construct(
        private readonly PaymentIncomeAnalyticsService $analytics,
    ) {}

    public function overview(PaymentIncomeRequest $request): JsonResponse
    {
        return $this->successResponse(
            $this->analytics->overview($request->filters()),
            __('api/payments.income.overview'),
        );
    }

    public function invoices(PaymentIncomeRequest $request): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->input('per_page', 20)));
        $paginator = $this->analytics->invoicesPaginated($request->filters(), $perPage);

        return $this->successResponse([
            'items' => $this->resolveResource(
                PaymentIncomeInvoiceResource::collection($paginator->getCollection()),
            ),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ], __('api/payments.income.invoices'));
    }
}
