<?php

namespace App\Domain\Service\Http\Controllers\API\V1;

use App\Domain\Service\Http\Requests\UpdateCurrencyRequest;
use App\Domain\Service\Http\Resources\CurrencyResource;
use App\Domain\Service\Services\CurrencyAdminService;
use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CurrencyController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly CurrencyAdminService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('view settings'), 403);

        return $this->successResponse(
            CurrencyResource::collection($this->service->list()),
            __('api/currencies.list'),
        );
    }

    public function update(UpdateCurrencyRequest $request, string $code): JsonResponse
    {
        $currency = $this->service->update(strtoupper($code), $request->validated());

        return $this->successResponse(
            new CurrencyResource($currency),
            __('api/currencies.updated'),
        );
    }
}
