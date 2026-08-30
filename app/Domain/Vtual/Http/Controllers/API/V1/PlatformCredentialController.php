<?php

namespace App\Domain\Vtual\Http\Controllers\API\V1;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Http\Requests\StorePlatformCredentialRequest;
use App\Domain\Vtual\Http\Requests\UpdatePlatformCredentialRequest;
use App\Domain\Vtual\Http\Resources\PlatformCredentialResource;
use App\Domain\Vtual\Models\Platform;
use App\Domain\Vtual\Models\PlatformCredential;
use App\Domain\Vtual\Services\PlatformCredentialService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ResolvesResourceArray;
use App\Support\ListActions\Builders\PlatformCredentialQueryRowActions;
use App\Support\ListActions\QueryRowActionAttacher;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformCredentialController extends Controller
{
    use ApiResponse;
    use ResolvesResourceArray;

    public function __construct(
        private readonly PlatformCredentialService $credentials,
        private readonly PlatformCredentialQueryRowActions $queryRowActions,
    ) {}

    public function index(Request $request, Platform $platform): JsonResponse
    {
        $this->authorize('viewAny', PlatformCredential::class);

        $list = $this->credentials->listForPlatform($platform);

        return $this->successResponse(
            QueryRowActionAttacher::attach(
                $this->resolveResource(PlatformCredentialResource::collection($list)),
                $list,
                $this->queryRowActions,
                $request->user(),
            ),
            __('api/vtual.credential.list_success'),
        );
    }

    public function store(StorePlatformCredentialRequest $request, Platform $platform): JsonResponse
    {
        $this->authorize('create', PlatformCredential::class);

        $credential = $this->credentials->create($request->user(), $platform, $request->validated());

        return $this->successResponse(
            $this->presentCredential($credential, $request->user()),
            __('api/vtual.credential.created'),
            201,
        );
    }

    public function show(Request $request, PlatformCredential $credential): JsonResponse
    {
        $this->authorize('view', $credential);

        $loaded = $this->credentials->findOrFail($credential->id);

        return $this->successResponse(
            $this->presentCredential($loaded, $request->user()),
            __('api/vtual.credential.show_success'),
        );
    }

    public function update(UpdatePlatformCredentialRequest $request, PlatformCredential $credential): JsonResponse
    {
        $this->authorize('update', $credential);

        $data = $request->validated();
        if (array_key_exists('secret', $data) && (! is_array($data['secret']) || $data['secret'] === [])) {
            unset($data['secret']);
        }

        $updated = $this->credentials->update($credential, $data);

        return $this->successResponse(
            $this->presentCredential($updated, $request->user()),
            __('api/vtual.credential.updated'),
        );
    }

    public function destroy(PlatformCredential $credential): JsonResponse
    {
        $this->authorize('delete', $credential);

        $this->credentials->delete($credential);

        return $this->successResponse(
            null,
            __('api/vtual.credential.deleted'),
        );
    }

    public function revoke(Request $request, PlatformCredential $credential): JsonResponse
    {
        $this->authorize('update', $credential);

        $updated = $this->credentials->revoke($credential);

        return $this->successResponse(
            $this->presentCredential($updated, $request->user()),
            __('api/vtual.credential.revoked'),
        );
    }

    public function test(Request $request, PlatformCredential $credential): JsonResponse
    {
        $this->authorize('update', $credential);

        $result = $this->credentials->test($credential);
        $loaded = $result->credential ?? $this->credentials->findOrFail($credential->id);

        $payload = [
            'credential' => $this->presentCredential($loaded, $request->user()),
            'test' => $result->toPublicArray(),
        ];

        if (! $result->ok) {
            return $this->errorResponse($result->message, 422, $payload);
        }

        return $this->successResponse(
            $payload,
            __('api/vtual.credential.test_success'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function presentCredential(PlatformCredential $credential, mixed $actor): array
    {
        return QueryRowActionAttacher::attach(
            [$this->resolveResource(new PlatformCredentialResource($credential))],
            [$credential],
            $this->queryRowActions,
            $actor instanceof User ? $actor : null,
        )[0];
    }
}
