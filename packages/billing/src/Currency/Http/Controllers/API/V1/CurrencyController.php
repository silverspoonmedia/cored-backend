<?php

namespace App\Domain\Currency\Http\Controllers\API\V1;

use App\Domain\Currency\Http\Requests\UpdateCurrencyRequest;
use App\Domain\Currency\Http\Resources\CurrencyResource;
use App\Domain\Currency\Services\CurrencyAdminService;
use App\Domain\Currency\Support\ListActions\CurrencyQueryRowActions;
use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ResolvesResourceArray;
use App\Support\ListActions\QueryRowActionAttacher;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CurrencyController extends Controller
{
    use ApiResponse;
    use ResolvesResourceArray;

    public function __construct(
        private readonly CurrencyAdminService $service,
        private readonly CurrencyQueryRowActions $queryRowActions,
    ) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('view settings'), 403);

        $list = $this->service->list();

        return $this->successResponse(
            QueryRowActionAttacher::attach(
                $this->resolveResource(CurrencyResource::collection($list)),
                $list,
                $this->queryRowActions,
                $request->user(),
            ),
            __('api/currencies.list'),
        );
    }

    public function update(UpdateCurrencyRequest $request, string $code): JsonResponse
    {
        $currency = $this->service->update(strtoupper($code), $request->validated());

        return $this->successResponse(
            QueryRowActionAttacher::attach(
                [$this->resolveResource(new CurrencyResource($currency))],
                [$currency],
                $this->queryRowActions,
                $request->user(),
            )[0],
            __('api/currencies.updated'),
        );
    }
}
