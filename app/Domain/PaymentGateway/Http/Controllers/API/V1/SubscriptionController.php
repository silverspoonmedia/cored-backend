<?php

namespace App\Domain\PaymentGateway\Http\Controllers\API\V1;

use App\Http\Controllers\Concerns\RespondsWithListDisplayMode;
use App\Http\Controllers\Controller;
use App\Domain\PaymentGateway\Http\Requests\IndexSubscriptionRequest;
use App\Domain\PaymentGateway\Http\Requests\StoreSubscriptionRequest;
use App\Domain\PaymentGateway\Http\Requests\UpdateSubscriptionRequest;
use App\Http\Resources\Concerns\ResolvesResourceArray;
use App\Domain\PaymentGateway\Http\Resources\SubscriptionResource;
use App\Domain\PaymentGateway\Models\Subscription;
use App\Domain\PaymentGateway\Services\SubscriptionService;
use App\Support\ApiResponder;
use App\Support\DataTable\DataTableResponseFactory;
use App\Domain\PaymentGateway\Support\DataTable\SubscriptionRowActionResolver;
use Illuminate\Http\JsonResponse;

class SubscriptionController extends Controller
{
    use ResolvesResourceArray;
    use RespondsWithListDisplayMode;

    public function __construct(
        private readonly SubscriptionService $service,
        private readonly DataTableResponseFactory $dataTables,
        private readonly SubscriptionRowActionResolver $rowActions,
    ) {}

    public function index(IndexSubscriptionRequest $request): JsonResponse
    {
        $subscriptions = $this->service->list();

        return $this->respondToListDisplayMode(
            $request,
            fn (): JsonResponse => ApiResponder::success(
                $this->resolveResource(SubscriptionResource::collection($subscriptions)),
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
        return ApiResponder::success(
            $this->resolveResource(new SubscriptionResource($this->service->get($subscription->id))),
            __('api/payments.subscriptions.show'),
        );
    }

    public function store(StoreSubscriptionRequest $request): JsonResponse
    {
        $record = $this->service->create($request->validated());

        return ApiResponder::success(
            $this->resolveResource(new SubscriptionResource($record)),
            __('api/payments.subscriptions.created'),
            201,
        );
    }

    public function update(UpdateSubscriptionRequest $request, Subscription $subscription): JsonResponse
    {
        $record = $this->service->update($subscription->id, $request->validated());

        return ApiResponder::success(
            $this->resolveResource(new SubscriptionResource($record)),
            __('api/payments.subscriptions.updated'),
        );
    }

    public function destroy(Subscription $subscription): JsonResponse
    {
        $this->service->delete($subscription->id);

        return ApiResponder::success(null, __('api/payments.subscriptions.deleted'));
    }
}
