<?php

namespace App\Domain\Inference\Http\Controllers\API\V1;

use App\Domain\Inference\Http\Requests\IndexInferenceProviderRequest;
use App\Domain\Inference\Http\Requests\StoreInferenceProviderRequest;
use App\Domain\Inference\Http\Requests\SyncProviderModelRatesRequest;
use App\Domain\Inference\Http\Requests\UpdateInferenceProviderRequest;
use App\Domain\Inference\Http\Resources\InferenceProviderResource;
use App\Domain\Inference\Models\InferenceProvider;
use App\Domain\Inference\Services\InferenceCatalogService;
use App\Domain\Inference\Support\DataTable\InferenceProviderRowActionResolver;
use App\Http\Controllers\Concerns\RespondsWithListDisplayMode;
use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ResolvesResourceArray;
use App\Support\DataTable\DataTableResponseFactory;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class InferenceProviderController extends Controller
{
    use ApiResponse;
    use ResolvesResourceArray;
    use RespondsWithListDisplayMode;

    public function __construct(
        private readonly InferenceCatalogService $catalog,
        private readonly DataTableResponseFactory $dataTables,
        private readonly InferenceProviderRowActionResolver $rowActions,
    ) {}

    public function index(IndexInferenceProviderRequest $request): JsonResponse
    {
        $providers = $this->catalog->listProviders();

        return $this->respondToListDisplayMode(
            $request,
            fn (): JsonResponse => $this->respondToQueryList(
                $request,
                $providers,
                fn ($list) => $this->resolveResource(InferenceProviderResource::collection($list)),
                __('api/inference.providers.list'),
            ),
            fn (): JsonResponse => $this->dataTables->respond(
                $providers,
                fn (InferenceProvider $provider) => $this->resolveResource(new InferenceProviderResource($provider)),
                $this->rowActions,
            ),
        );
    }

    public function show(InferenceProvider $provider): JsonResponse
    {
        abort_unless(request()->user()?->can('view inference catalog'), 403);

        $record = $this->catalog->getProvider($provider->id);

        return $this->successResponse(
            $this->resolveResource(new InferenceProviderResource($record)),
            __('api/inference.providers.show'),
        );
    }

    public function store(StoreInferenceProviderRequest $request): JsonResponse
    {
        $record = $this->catalog->createProvider($request->validated());

        return $this->successResponse(
            $this->resolveResource(new InferenceProviderResource($record)),
            __('api/inference.providers.created'),
            201,
        );
    }

    public function update(UpdateInferenceProviderRequest $request, InferenceProvider $provider): JsonResponse
    {
        $record = $this->catalog->updateProvider($provider->id, $request->validated());

        return $this->successResponse(
            $this->resolveResource(new InferenceProviderResource($record)),
            __('api/inference.providers.updated'),
        );
    }

    public function destroy(InferenceProvider $provider): JsonResponse
    {
        abort_unless(request()->user()?->can('manage inference catalog'), 403);

        $this->catalog->deleteProvider($provider->id);

        return $this->successResponse(null, __('api/inference.providers.deleted'));
    }

    public function syncRates(SyncProviderModelRatesRequest $request, InferenceProvider $provider): JsonResponse
    {
        $record = $this->catalog->syncProviderRates($provider->id, $request->validated('rates'));

        return $this->successResponse(
            $this->resolveResource(new InferenceProviderResource($record)),
            __('api/inference.providers.rates_synced'),
        );
    }
}
