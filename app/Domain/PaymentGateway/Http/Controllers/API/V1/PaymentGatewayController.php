<?php

namespace App\Domain\PaymentGateway\Http\Controllers\API\V1;

use App\Http\Controllers\Concerns\RespondsWithListDisplayMode;
use App\Http\Controllers\Controller;
use App\Domain\PaymentGateway\Http\Requests\IndexPaymentGatewayRequest;
use App\Domain\PaymentGateway\Http\Requests\StorePaymentGatewayRequest;
use App\Domain\PaymentGateway\Http\Requests\UpdatePaymentGatewayRequest;
use App\Http\Resources\Concerns\ResolvesResourceArray;
use App\Domain\PaymentGateway\Http\Resources\PaymentGatewayResource;
use App\Domain\PaymentGateway\Models\PaymentGateway;
use App\Domain\PaymentGateway\Services\PaymentGatewayService;
use App\Support\ApiResponder;
use App\Support\DataTable\DataTableResponseFactory;
use App\Domain\PaymentGateway\Support\DataTable\PaymentGatewayRowActionResolver;
use Illuminate\Http\JsonResponse;

class PaymentGatewayController extends Controller
{
    use ResolvesResourceArray;
    use RespondsWithListDisplayMode;

    public function __construct(
        private readonly PaymentGatewayService $service,
        private readonly DataTableResponseFactory $dataTables,
        private readonly PaymentGatewayRowActionResolver $rowActions,
    ) {}

    public function index(IndexPaymentGatewayRequest $request): JsonResponse
    {
        $gateways = $this->service->list();

        return $this->respondToListDisplayMode(
            $request,
            fn (): JsonResponse => ApiResponder::success(
                $this->resolveResource(PaymentGatewayResource::collection($gateways)),
                __('api/payments.gateways.list'),
            ),
            fn (): JsonResponse => $this->dataTables->respond(
                $gateways,
                fn (PaymentGateway $gateway) => $this->resolveResource(new PaymentGatewayResource($gateway)),
                $this->rowActions,
            ),
        );
    }

    public function show(PaymentGateway $paymentGateway): JsonResponse
    {
        return ApiResponder::success(
            $this->resolveResource(new PaymentGatewayResource($this->service->get($paymentGateway->id))),
            __('api/payments.gateways.show'),
        );
    }

    public function store(StorePaymentGatewayRequest $request): JsonResponse
    {
        $record = $this->service->create($request->validated());

        return ApiResponder::success(
            $this->resolveResource(new PaymentGatewayResource($record)),
            __('api/payments.gateways.created'),
            201,
        );
    }

    public function update(UpdatePaymentGatewayRequest $request, PaymentGateway $paymentGateway): JsonResponse
    {
        $record = $this->service->update($paymentGateway->id, $request->validated());

        return ApiResponder::success(
            $this->resolveResource(new PaymentGatewayResource($record)),
            __('api/payments.gateways.updated'),
        );
    }

    public function destroy(PaymentGateway $paymentGateway): JsonResponse
    {
        $this->service->delete($paymentGateway->id);

        return ApiResponder::success(null, __('api/payments.gateways.deleted'));
    }
}
