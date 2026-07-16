<?php

namespace App\Domain\Product\Http\Controllers\API\V1;

use App\Domain\Product\Http\Requests\StoreItemRequest;
use App\Domain\Product\Http\Requests\UpdateItemRequest;
use App\Domain\Product\Http\Resources\ItemResource;
use App\Domain\Product\Models\Item;
use App\Domain\Product\Services\ItemService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ResolvesResourceArray;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class ItemController extends Controller
{
    use ApiResponse;
    use ResolvesResourceArray;

    public function __construct(
        private readonly ItemService $items,
    ) {}

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Item::class);

        return $this->successResponse(
            $this->resolveResource(ItemResource::collection($this->items->list())),
            __('api/product-items.list_success'),
        );
    }

    public function show(Item $item): JsonResponse
    {
        $this->authorize('view', $item);

        return $this->successResponse(
            $this->resolveResource(new ItemResource($item)),
            __('api/product-items.show_success'),
        );
    }

    public function store(StoreItemRequest $request): JsonResponse
    {
        $this->authorize('create', Item::class);

        $item = $this->items->create($request->validated());

        return $this->successResponse(
            $this->resolveResource(new ItemResource($item)),
            __('api/product-items.created'),
            201,
        );
    }

    public function update(UpdateItemRequest $request, Item $item): JsonResponse
    {
        $this->authorize('update', $item);

        $updated = $this->items->update($item->id, $request->validated());

        return $this->successResponse(
            $this->resolveResource(new ItemResource($updated)),
            __('api/product-items.updated'),
        );
    }

    public function destroy(Item $item): JsonResponse
    {
        $this->authorize('delete', $item);

        $this->items->delete($item->id);

        return $this->successResponse(null, __('api/product-items.deleted'));
    }
}
