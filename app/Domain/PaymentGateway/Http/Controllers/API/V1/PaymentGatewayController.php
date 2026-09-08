<?php

namespace App\Domain\PaymentGateway\Http\Controllers\API\V1;

use App\Domain\PaymentGateway\Http\Requests\IndexPaymentGatewayRequest;
use App\Domain\PaymentGateway\Http\Requests\StorePaymentGatewayRequest;
use App\Domain\PaymentGateway\Http\Requests\UpdatePaymentGatewayRequest;
use App\Domain\PaymentGateway\Http\Resources\PaymentGatewayResource;
use App\Domain\PaymentGateway\Models\PaymentGateway;
use App\Domain\PaymentGateway\Services\PaymentGatewayService;
use App\Http\Controllers\Concerns\RespondsWithListDisplayMode;
use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ResolvesResourceArray;
use App\Support\ListActions\Builders\PaymentGatewayQueryRowActions;
use App\Support\ListActions\QueryRowActionAttacher;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class PaymentGatewayController extends Controller
{
    use ApiResponse;
    use ResolvesResourceArray;
    use RespondsWithListDisplayMode;

    public function __construct(
        private readonly PaymentGatewayService $service,
        private readonly PaymentGatewayQueryRowActions $queryRowActions,
    ) {}

    public function index(IndexPaymentGatewayRequest $request): JsonResponse
    {
        $gateways = $this->service->list();

        return $this->respondToQueryList(
            $request,
            $gateways,
            fn ($list) => QueryRowActionAttacher::attach(
                $this->resolveResource(PaymentGatewayResource::collection($list)),
                $list,
                $this->queryRowActions,
                $request->user(),
            ),
            __('api/payments.gateways.list'),
        );
    }

    public function show(PaymentGateway $paymentGateway): JsonResponse
    {
        return $this->successResponse(
            $this->resolveResource(new PaymentGatewayResource($this->service->get($paymentGateway->id))),
            __('api/payments.gateways.show'),
        );
    }

    public function store(StorePaymentGatewayRequest $request): JsonResponse
    {
        $record = $this->service->create($request->validated());

        return $this->successResponse(
            $this->resolveResource(new PaymentGatewayResource($record)),
            __('api/payments.gateways.created'),
            201,
        );
    }

    public function update(UpdatePaymentGatewayRequest $request, PaymentGateway $paymentGateway): JsonResponse
    {
        $record = $this->service->update($paymentGateway->id, $request->validated());

        return $this->successResponse(
            $this->resolveResource(new PaymentGatewayResource($record)),
            __('api/payments.gateways.updated'),
        );
    }

    public function destroy(PaymentGateway $paymentGateway): JsonResponse
    {
        $this->service->delete($paymentGateway->id);

        return $this->successResponse(null, __('api/payments.gateways.deleted'));
    }
}
