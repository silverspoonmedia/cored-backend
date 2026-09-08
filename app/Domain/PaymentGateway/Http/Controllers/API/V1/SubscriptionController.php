<?php

namespace App\Domain\PaymentGateway\Http\Controllers\API\V1;

use App\Domain\PaymentGateway\Http\Requests\IndexSubscriptionRequest;
use App\Domain\PaymentGateway\Http\Requests\StoreSubscriptionRequest;
use App\Domain\PaymentGateway\Http\Requests\UpdateSubscriptionRequest;
use App\Domain\PaymentGateway\Http\Resources\SubscriptionResource;
use App\Domain\PaymentGateway\Models\Subscription;
use App\Domain\PaymentGateway\Services\SubscriptionService;
use App\Domain\PaymentGateway\Support\DataTable\SubscriptionRowActionResolver;
use App\Http\Controllers\Concerns\RespondsWithListDisplayMode;
use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ResolvesResourceArray;
use App\Support\DataTable\DataTableResponseFactory;
use App\Support\ListActions\Builders\SubscriptionQueryRowActions;
use App\Support\ListActions\QueryRowActionAttacher;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class SubscriptionController extends Controller
{
    use ApiResponse;
    use ResolvesResourceArray;
    use RespondsWithListDisplayMode;

    public function __construct(
        private readonly SubscriptionService $service,
        private readonly DataTableResponseFactory $dataTables,
        private readonly SubscriptionRowActionResolver $rowActions,
        private readonly SubscriptionQueryRowActions $queryRowActions,
    ) {}

    public function index(IndexSubscriptionRequest $request): JsonResponse
    {
        $subscriptions = $this->service->list();

        return $this->respondToListDisplayMode(
            $request,
            fn (): JsonResponse => $this->respondToQueryList(
                $request,
                $subscriptions,
                fn ($list) => QueryRowActionAttacher::attach(
                    $this->resolveResource(SubscriptionResource::collection($list)),
                    $list,
                    $this->queryRowActions,
                    $request->user(),
                ),
                __('api/payments.subscriptions.list'),
            ),
            fn (): JsonResponse => $this->dataTables->respond(
                $subscriptions,
                fn (Subscription $subscription) => $this->resolveResource(new SubscriptionResource($subscription)),
                $this->rowActions,
            ),
        );
    }

    public function show(Subscription $subscription): JsonResponse
    {
        return $this->successResponse(
            $this->resolveResource(new SubscriptionResource($this->service->get($subscription->id))),
            __('api/payments.subscriptions.show'),
        );
    }

    public function store(StoreSubscriptionRequest $request): JsonResponse
    {
        $record = $this->service->create($request->validated());

        return $this->successResponse(
            $this->resolveResource(new SubscriptionResource($record)),
            __('api/payments.subscriptions.created'),
            201,
        );
    }

    public function update(UpdateSubscriptionRequest $request, Subscription $subscription): JsonResponse
    {
        $record = $this->service->update($subscription->id, $request->validated());

        return $this->successResponse(
            $this->resolveResource(new SubscriptionResource($record)),
            __('api/payments.subscriptions.updated'),
        );
    }

    public function destroy(Subscription $subscription): JsonResponse
    {
        $this->service->delete($subscription->id);

        return $this->successResponse(null, __('api/payments.subscriptions.deleted'));
    }
}
