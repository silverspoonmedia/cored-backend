<?php

namespace App\Domain\Playground\Http\Controllers\API;

use App\Http\Controllers\Concerns\RespondsWithListDisplayMode;
use App\Http\Controllers\Controller;
use App\Domain\Playground\Http\Requests\BulkDestroyOrderRequest;
use App\Domain\Playground\Http\Requests\IndexOrderRequest;
use App\Domain\Playground\Http\Requests\StoreOrderRequest;
use App\Domain\Playground\Http\Requests\UpdateOrderRequest;
use App\Http\Resources\Concerns\ResolvesResourceArray;
use App\Domain\Playground\Http\Resources\PlaygroundOrderResource;
use App\Domain\Playground\Models\Order;
use App\Domain\Playground\Services\OrderService;
use App\Support\DataTable\DataTableResponseFactory;
use App\Domain\Playground\Support\DataTable\PlaygroundOrderRowActionResolver;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Playground master-detail CRUD (Order + OrderDetail).
 *
 * Thin controller: validated input in, OrderService call, resource out. The
 * service owns business rules and totals; OrderRepository performs the atomic
 * two-table write inside a DB transaction (no orphaned data). Lives on the
 * global playground surface as a reference; production orders would sit on a
 * versioned route (see docs/platform/api-routing.md).
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
        $record = $this->orderService->create(
            $request->validated(),
            $request->validated('staged_attachments', []),
        );

        return $this->successResponse(
            $this->resolveResource(new PlaygroundOrderResource($record)),
            __('api/playground-orders.created'),
            201,
        );
    }

    public function update(UpdateOrderRequest $request, string $order): JsonResponse
    {
        $record = $this->orderService->update(
            $order,
            $request->validated(),
            $request->validated('staged_attachments', []),
        );

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

    /**
     * Mass-delete: remove every order in the `ids` set in one atomic batch.
     * The service freezes the whole batch if any order is completed, mirroring
     * the single-row delete rule.
     */
    public function bulkDestroy(BulkDestroyOrderRequest $request): JsonResponse
    {
        $ids = $request->validated('ids');

        $this->orderService->deleteMany($ids);

        return $this->successResponse(
            ['deleted' => count($ids)],
            __('api/playground-orders.bulk_deleted', ['count' => count($ids)]),
        );
    }
}
