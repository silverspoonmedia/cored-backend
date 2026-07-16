<?php

namespace App\Domain\PaymentGateway\Http\Controllers\API\V1;

use App\Domain\PaymentGateway\Http\Requests\IndexPaymentChannelRequest;
use App\Domain\PaymentGateway\Http\Requests\UpdatePaymentChannelRequest;
use App\Domain\PaymentGateway\Http\Resources\PaymentChannelResource;
use App\Domain\PaymentGateway\Services\PaymentChannelService;
use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class PaymentChannelController extends Controller
{
    use ApiResponse;

    public function __construct(
        private PaymentChannelService $service,
    ) {}

    public function index(IndexPaymentChannelRequest $request): JsonResponse
    {
        $channels = $request->input('scope') === 'admin'
            ? $this->service->listAll()
            : $this->service->listAvailable();

        return $this->successResponse(
            PaymentChannelResource::collection($channels)->resolve($request),
            __('api/payments.channels.list'),
        );
    }

    public function show(string $code): JsonResponse
    {
        $channel = $this->service->getByCode($code);

        if ($channel === null) {
            return $this->errorResponse(__('api/payments.channels.not_found'), 404);
        }

        return $this->successResponse(
            (new PaymentChannelResource($channel))->resolve(),
            __('api/payments.channels.show'),
        );
    }

    public function update(UpdatePaymentChannelRequest $request, string $code): JsonResponse
    {
        $channel = $this->service->update($code, $request->validated());

        return $this->successResponse(
            (new PaymentChannelResource($channel))->resolve(),
            __('api/payments.channels.updated'),
        );
    }
}
