<?php

namespace App\Domain\Microstock\Http\Controllers\API\V1;

use App\Domain\Microstock\Http\Requests\StoreMarketplaceCredentialRequest;
use App\Domain\Microstock\Http\Resources\MarketplaceCredentialResource;
use App\Domain\Microstock\Services\CredentialService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ResolvesResourceArray;
use App\Support\ListActions\Builders\MicrostockCredentialQueryRowActions;
use App\Support\ListActions\QueryRowActionAttacher;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CredentialController extends Controller
{
    use ApiResponse;
    use ResolvesResourceArray;

    public function __construct(
        private readonly CredentialService $credentials,
        private readonly MicrostockCredentialQueryRowActions $queryRowActions,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $list = $this->credentials->list($request->user());

        return $this->successResponse(
            QueryRowActionAttacher::attach(
                $this->resolveResource(MarketplaceCredentialResource::collection($list)),
                $list,
                $this->queryRowActions,
                $request->user(),
            ),
            __('api/microstock.credentials_loaded'),
        );
    }

    public function store(StoreMarketplaceCredentialRequest $request): JsonResponse
    {
        $data = $request->validated();

        $credential = $this->credentials->store(
            $request->user(),
            $data['marketplace'],
            $data['label'],
            $data['secret'],
        );

        return $this->successResponse(
            $this->resolveResource(new MarketplaceCredentialResource($credential)),
            __('api/microstock.credential_saved'),
            201,
        );
    }

    public function destroy(Request $request, string $credential): JsonResponse
    {
        $this->credentials->delete($request->user(), $credential);

        return $this->successResponse(null, __('api/microstock.credential_deleted'));
    }
}
