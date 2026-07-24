<?php

namespace App\Domain\Vtual\Http\Controllers\API\V1;

use App\Domain\Vtual\Http\Requests\PlatformApiProbeRequest;
use App\Domain\Vtual\Services\PlatformApiProbeService;
use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Root/Admin manual probe of PlatformApiGateway (no secrets in response).
 */
class PlatformApiProbeController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly PlatformApiProbeService $probe,
    ) {}

    public function __invoke(PlatformApiProbeRequest $request): JsonResponse
    {
        $result = $this->probe->run($request->validated());

        return $this->successResponse(
            $result,
            __('api/vtual.integrations.probe_success'),
        );
    }
}
