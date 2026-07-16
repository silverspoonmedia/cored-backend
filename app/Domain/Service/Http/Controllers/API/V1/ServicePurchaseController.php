<?php

namespace App\Domain\Service\Http\Controllers\API\V1;

use App\Domain\Service\Http\Resources\ServicePurchaseResource;
use App\Domain\Service\Models\ServicePurchase;
use App\Domain\Service\Services\ServiceCheckoutService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ResolvesResourceArray;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class ServicePurchaseController extends Controller
{
    use ApiResponse;
    use ResolvesResourceArray;

    public function __construct(
        private readonly ServiceCheckoutService $checkout,
    ) {}

    public function show(string $purchase): JsonResponse
    {
        $record = $this->checkout->getPurchase($purchase);
        $this->authorize('view', $record);

        return $this->successResponse(
            $this->resolveResource(new ServicePurchaseResource($record)),
            __('api/services.purchase_show_success'),
        );
    }
}
