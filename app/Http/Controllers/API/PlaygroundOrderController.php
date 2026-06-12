<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Concerns\RespondsWithListDisplayMode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Playground\IndexOrderRequest;
use App\Http\Requests\Playground\StoreOrderRequest;
use App\Http\Requests\Playground\UpdateOrderRequest;
use App\Http\Resources\Concerns\ResolvesResourceArray;
use App\Http\Resources\Playground\PlaygroundOrderResource;
use App\Models\Order;
use App\Services\Playground\OrderService;
use App\Support\DataTable\DataTableResponseFactory;
use App\Support\DataTable\Resolvers\PlaygroundOrderRowActionResolver;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Playground master-detail CRUD (Order + OrderDetail).
 *
 * Thin controller: validated input in, OrderService call, resource out. The
 * service owns business rules and totals; OrderRepository performs the atomic
 * two-table write inside a DB transaction (no orphaned data). Lives on the
 * global playground surface as a reference; production orders would sit on a
 * versioned route (see docs/api-routing.md).
 */
class PlaygroundOrderController extends Controller
{
    use ApiResponse;
    use ResolvesResourceArray;
    use RespondsWithListDisplayMode;

    public function __construct(
        private readonly OrderService $orderService,
        private readonly DataTableResponseFactory $dataTables,
        private readonly PlaygroundOrderRowActionResolver $rowActions,
    ) {}

    public function index(IndexOrderRequest $request): JsonResponse
    {
        return $this->respondToListDisplayMode(
            $request,
            fn (): JsonResponse => $this->successResponse(
                $this->resolveResource(PlaygroundOrderResource::collection($this->orderService->list())),
                __('api/playground-orders.list'),
            ),
            fn (): JsonResponse => $this->dataTables->respond(
                $this->orderService->list(),
                fn (Order $order) => $this->resolveResource(new PlaygroundOrderResource($order)),
                $this->rowActions,
            ),
        );
    }

    public function show(string $order): JsonResponse
    {
        return $this->successResponse(
            $this->resolveResource(new PlaygroundOrderResource($this->orderService->get($order))),
            __('api/playground-orders.show'),
        );
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $record = $this->orderService->create($request->validated());

        return $this->successResponse(
            $this->resolveResource(new PlaygroundOrderResource($record)),
            __('api/playground-orders.created'),
            201,
        );
    }

    public function update(UpdateOrderRequest $request, string $order): JsonResponse
    {
        $record = $this->orderService->update($order, $request->validated());

        return $this->successResponse(
            $this->resolveResource(new PlaygroundOrderResource($record)),
            __('api/playground-orders.updated'),
        );
    }

    public function destroy(string $order): JsonResponse
    {
        $this->orderService->delete($order);

        return $this->successResponse(null, __('api/playground-orders.deleted'));
    }
}
