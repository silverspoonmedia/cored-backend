<?php

namespace App\Support\Product\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Support\ApiResponder;
use App\Support\Product\Enums\ProductActivationState;
use App\Support\Product\Http\Resources\ProductActivationResource;
use App\Support\Product\Models\ProductActivation;
use App\Support\Product\ProductRegistry;
use App\Support\Product\Services\ProductActivationService;
use App\Support\Product\Services\ProductInsightService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminProductActivationController extends Controller
{
    public function __construct(
        private readonly ProductActivationService $activations,
        private readonly ProductInsightService $insights,
    ) {}

    /**
     * List all registered products with their per-product summary (counts +
     * product-specific metrics). Drives the product-cards index UI.
     */
    public function products(): JsonResponse
    {
        $this->authorize('viewAny', ProductActivation::class);

        $products = collect(ProductRegistry::all())
            ->map(fn (array $config, string $key): array => $this->insights->productSummary($key))
            ->values();

        return ApiResponder::success(
            $products->all(),
            __('product-activations.products_list_success'),
        );
    }

    /**
     * Full per-product dashboard: summary + product metrics + paginated
     * activations with filter by state.
     */
    public function productShow(Request $request, string $productKey): JsonResponse
    {
        $this->authorize('viewAny', ProductActivation::class);

        if (! ProductRegistry::has($productKey)) {
            return ApiResponder::error(
                __('product-activations.unknown_product', ['product' => $productKey]),
                404,
            );
        }

        $validated = $request->validate([
            'state' => ['nullable', 'string', 'in:pending,active,suspended,expired'],
        ]);

        $query = ProductActivation::query()
            ->with('user')
            ->where('product_key', $productKey);

        if (isset($validated['state'])) {
            $query->where('state', $validated['state']);
        }

        $activations = $query->orderBy('updated_at', 'desc')->paginate(50);

        return ApiResponder::success(
            [
                'summary' => $this->insights->productSummary($productKey),
                'activations' => ProductActivationResource::collection($activations->items()),
                'pagination' => [
                    'current_page' => $activations->currentPage(),
                    'last_page' => $activations->lastPage(),
                    'per_page' => $activations->perPage(),
                    'total' => $activations->total(),
                ],
            ],
            __('product-activations.product_show_success'),
        );
    }

    /**
     * Per-user drill-down inside a product.
     */
    public function userDetail(string $productKey, string $userId): JsonResponse
    {
        $this->authorize('viewAny', ProductActivation::class);

        if (! ProductRegistry::has($productKey)) {
            return ApiResponder::error(
                __('product-activations.unknown_product', ['product' => $productKey]),
                404,
            );
        }

        return ApiResponder::success(
            $this->insights->userDetailInProduct($userId, $productKey),
            __('product-activations.user_detail_success'),
        );
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ProductActivation::class);

        $validated = $request->validate([
            'product_key' => ['nullable', 'string'],
            'state' => ['nullable', 'string', 'in:pending,active,suspended,expired'],
            'user_id' => ['nullable', 'uuid'],
        ]);

        $query = ProductActivation::query()->with('user');

        if (isset($validated['product_key'])) {
            $query->where('product_key', $validated['product_key']);
        }

        if (isset($validated['state'])) {
            $query->where('state', $validated['state']);
        }

        if (isset($validated['user_id'])) {
            $query->where('user_id', $validated['user_id']);
        }

        $activations = $query->orderBy('updated_at', 'desc')->paginate(50);

        return ApiResponder::success(
            ProductActivationResource::collection($activations->items()),
            __('product-activations.admin_list_success'),
        );
    }

    public function show(ProductActivation $productActivation): JsonResponse
    {
        $this->authorize('view', $productActivation);

        return ApiResponder::success(
            new ProductActivationResource($productActivation->load('user')),
            __('product-activations.show_success'),
        );
    }

    public function suspend(Request $request, ProductActivation $productActivation): JsonResponse
    {
        $this->authorize('update', $productActivation);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $updated = $this->activations->suspend(
            $productActivation,
            $validated['reason'] ?? null,
            'admin',
        );

        return ApiResponder::success(
            new ProductActivationResource($updated),
            __('product-activations.suspended'),
        );
    }

    public function reactivate(ProductActivation $productActivation): JsonResponse
    {
        $this->authorize('update', $productActivation);

        $updated = $this->activations->reactivate($productActivation, 'admin');

        return ApiResponder::success(
            new ProductActivationResource($updated),
            __('product-activations.reactivated'),
        );
    }

    public function approve(Request $request, ProductActivation $productActivation): JsonResponse
    {
        $this->authorize('update', $productActivation);

        $validated = $request->validate([
            'payload' => ['nullable', 'array'],
        ]);

        $updated = $this->activations->approve(
            $productActivation,
            $validated['payload'] ?? [],
            'admin',
        );

        return ApiResponder::success(
            new ProductActivationResource($updated),
            __('product-activations.approved'),
        );
    }

    public function reject(Request $request, ProductActivation $productActivation): JsonResponse
    {
        $this->authorize('update', $productActivation);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $updated = $this->activations->reject($productActivation, $validated['reason'], 'admin');

        return ApiResponder::success(
            new ProductActivationResource($updated),
            __('product-activations.rejected'),
        );
    }

    public function stats(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ProductActivation::class);

        $validated = $request->validate([
            'product_key' => ['nullable', 'string'],
        ]);

        $productKey = $validated['product_key'] ?? null;

        $stats = collect(ProductActivationState::cases())->map(function (ProductActivationState $state) use ($productKey): array {
            $query = ProductActivation::query()->where('state', $state->value);

            if ($productKey !== null) {
                $query->where('product_key', $productKey);
            }

            return [
                'state' => $state->value,
                'count' => $query->count(),
            ];
        });

        return ApiResponder::success(
            $stats->all(),
            __('product-activations.stats_success'),
        );
    }
}
