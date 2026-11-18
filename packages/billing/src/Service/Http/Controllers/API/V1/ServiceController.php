<?php

namespace App\Domain\Service\Http\Controllers\API\V1;

use App\Domain\Identity\Models\User;
use App\Domain\Service\Enums\BillingInterval;
use App\Domain\Service\Enums\PlanKind;
use App\Domain\Service\Enums\ServiceStatus;
use App\Domain\Service\Http\Requests\CheckoutServiceRequest;
use App\Domain\Service\Http\Requests\IndexServiceRequest;
use App\Domain\Service\Http\Requests\StoreServiceRequest;
use App\Domain\Service\Http\Requests\UpdateServiceRequest;
use App\Domain\Service\Http\Resources\ServicePurchaseResource;
use App\Domain\Service\Http\Resources\ServiceResource;
use App\Domain\Service\Models\Service;
use App\Domain\Service\Services\ServiceCatalogService;
use App\Domain\Service\Services\ServiceCheckoutService;
use App\Domain\Service\Support\ListActions\ServiceQueryRowActions;
use App\Http\Controllers\Concerns\RespondsWithListDisplayMode;
use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ResolvesResourceArray;
use App\Support\ListActions\QueryRowActionAttacher;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class ServiceController extends Controller
{
    use ApiResponse;
    use ResolvesResourceArray;
    use RespondsWithListDisplayMode;

    public function __construct(
        private readonly ServiceCatalogService $catalog,
        private readonly ServiceCheckoutService $checkout,
        private readonly ServiceQueryRowActions $queryRowActions,
    ) {}

    public function index(IndexServiceRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Service::class);

        $services = $this->catalog->list();

        return $this->respondToQueryList(
            $request,
            $services,
            fn ($list) => QueryRowActionAttacher::attach(
                $this->resolveResource(ServiceResource::collection($list)),
                $list,
                $this->queryRowActions,
                $request->user(),
            ),
            __('api/services.list_success'),
        );
    }

    public function options(): JsonResponse
    {
        $this->authorize('viewAny', Service::class);

        return $this->successResponse([
            'billing_intervals' => BillingInterval::values(),
            'plan_kinds' => PlanKind::values(),
            'statuses' => ServiceStatus::values(),
            'purchase_max_quantity' => max(1, (int) setting_config('service.purchase.max_quantity', 99)),
        ], __('api/services.options_success'));
    }

    public function show(Service $service): JsonResponse
    {
        $this->authorize('view', $service);

        return $this->successResponse(
            $this->resolveResource(new ServiceResource($service->load('prices'))),
            __('api/services.show_success'),
        );
    }

    public function store(StoreServiceRequest $request): JsonResponse
    {
        $this->authorize('create', Service::class);

        $service = $this->catalog->create($request->validated());

        return $this->successResponse(
            $this->resolveResource(new ServiceResource($service)),
            __('api/services.created'),
            201,
        );
    }

    public function update(UpdateServiceRequest $request, Service $service): JsonResponse
    {
        $this->authorize('update', $service);

        $updated = $this->catalog->update($service->id, $request->validated());

        return $this->successResponse(
            $this->resolveResource(new ServiceResource($updated)),
            __('api/services.updated'),
        );
    }

    public function destroy(Service $service): JsonResponse
    {
        $this->authorize('delete', $service);

        $this->catalog->delete($service->id);

        return $this->successResponse(null, __('api/services.deleted'));
    }

    public function checkout(CheckoutServiceRequest $request, Service $service): JsonResponse
    {
        $this->authorize('purchase', $service);

        /** @var User $user */
        $user = $request->user();
        $validated = $request->validated();

        $result = $this->checkout->checkout(
            $service,
            $user,
            (string) $validated['currency'],
            (int) ($validated['quantity'] ?? 1),
        );

        return $this->successResponse(
            $this->resolveResource(new ServicePurchaseResource($result['purchase'])),
            __('api/services.checkout_success'),
            201,
        );
    }
}
