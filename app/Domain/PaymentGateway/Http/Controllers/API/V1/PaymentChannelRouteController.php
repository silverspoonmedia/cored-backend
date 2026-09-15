<?php

namespace App\Domain\PaymentGateway\Http\Controllers\API\V1;

use App\Domain\PaymentGateway\Http\Requests\UpdatePaymentChannelRouteRequest;
use App\Domain\PaymentGateway\Http\Resources\PaymentChannelRouteResource;
use App\Domain\PaymentGateway\Services\PaymentChannelService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ResolvesResourceArray;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class PaymentChannelRouteController extends Controller
{
    use ApiResponse;
    use ResolvesResourceArray;

    public function __construct(
        private PaymentChannelService $service,
    ) {}

    public function update(UpdatePaymentChannelRouteRequest $request, string $route): JsonResponse
    {
        $updated = $this->service->updateRoute($route, $request->validated());

        return $this->successResponse(
            $this->resolveResource(new PaymentChannelRouteResource($updated)),
            __('api/payments.channels.route_updated'),
        );
    }
}
