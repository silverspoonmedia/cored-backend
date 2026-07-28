<?php

namespace App\Domain\Microstock\Http\Controllers\API\V1;

use App\Domain\Microstock\Http\Resources\MarketplaceCredentialResource;
use App\Domain\Microstock\Services\CredentialService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ResolvesResourceArray;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CredentialController extends Controller
{
    use ApiResponse;
    use ResolvesResourceArray;

    public function __construct(
        private readonly CredentialService $credentials,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $list = $this->credentials->list($request->user());

        return $this->successResponse(
            $this->resolveResource(MarketplaceCredentialResource::collection($list)),
            __('api/microstock.credentials_loaded'),
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'marketplace' => ['required', 'string', 'max:100'],
            'label' => ['required', 'string', 'max:100'],
            'secret' => ['required', 'array'],
        ]);

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
