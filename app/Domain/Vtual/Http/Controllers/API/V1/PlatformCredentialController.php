<?php

namespace App\Domain\Vtual\Http\Controllers\API\V1;

use App\Domain\Vtual\Http\Requests\StorePlatformCredentialRequest;
use App\Domain\Vtual\Http\Requests\UpdatePlatformCredentialRequest;
use App\Domain\Vtual\Http\Resources\PlatformCredentialResource;
use App\Domain\Vtual\Models\Platform;
use App\Domain\Vtual\Models\PlatformCredential;
use App\Domain\Vtual\Services\PlatformCredentialService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ResolvesResourceArray;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class PlatformCredentialController extends Controller
{
    use ApiResponse;
    use ResolvesResourceArray;

    public function __construct(
        private readonly PlatformCredentialService $credentials,
    ) {}

    public function index(Platform $platform): JsonResponse
    {
        $this->authorize('viewAny', PlatformCredential::class);

        return $this->successResponse(
            $this->resolveResource(PlatformCredentialResource::collection(
                $this->credentials->listForPlatform($platform)
            )),
            __('api/vtual.credential.list_success'),
        );
    }

    public function store(StorePlatformCredentialRequest $request, Platform $platform): JsonResponse
    {
        $this->authorize('create', PlatformCredential::class);

        $credential = $this->credentials->create($request->user(), $platform, $request->validated());

        return $this->successResponse(
            $this->resolveResource(new PlatformCredentialResource($credential)),
            __('api/vtual.credential.created'),
            201,
        );
    }

    public function show(PlatformCredential $credential): JsonResponse
    {
        $this->authorize('view', $credential);

        $loaded = $this->credentials->findOrFail($credential->id);

        return $this->successResponse(
            $this->resolveResource(new PlatformCredentialResource($loaded)),
            __('api/vtual.credential.show_success'),
        );
    }

    public function update(UpdatePlatformCredentialRequest $request, PlatformCredential $credential): JsonResponse
    {
        $this->authorize('update', $credential);

        $data = $request->validated();
        if (array_key_exists('secret', $data) && ( ! is_array($data['secret']) || $data['secret'] === [])) {
            unset($data['secret']);
        }

        $updated = $this->credentials->update($credential, $data);

        return $this->successResponse(
            $this->resolveResource(new PlatformCredentialResource($updated)),
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

    public function disable(PlatformCredential $credential): JsonResponse
    {
        $this->authorize('update', $credential);

        $updated = $this->credentials->disable($credential);

        return $this->successResponse(
            $this->resolveResource(new PlatformCredentialResource($updated)),
            __('api/vtual.credential.disabled'),
        );
    }

    public function test(PlatformCredential $credential): JsonResponse
    {
        $this->authorize('update', $credential);

        $result = $this->credentials->test($credential);
        $loaded = $result->credential ?? $this->credentials->findOrFail($credential->id);

        $payload = [
            'credential' => $this->resolveResource(new PlatformCredentialResource($loaded)),
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
}
