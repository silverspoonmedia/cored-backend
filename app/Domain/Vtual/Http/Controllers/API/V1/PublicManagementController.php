<?php

namespace App\Domain\Vtual\Http\Controllers\API\V1;

use App\Domain\Vtual\Http\Resources\PublicManagementProfileResource;
use App\Domain\Vtual\Services\ManagementService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ResolvesResourceArray;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Guest-safe public management (talent org) profile read.
 * No auth — the service layer filters to active managements with
 * publicly listed roster creators only.
 */
class PublicManagementController extends Controller
{
    use ApiResponse;
    use ResolvesResourceArray;

    public function __construct(
        private readonly ManagementService $managements,
    ) {}

    public function show(string $management): JsonResponse
    {
        $loaded = $this->managements->showForPublic($management);

        return $this->successResponse(
            $this->resolveResource(new PublicManagementProfileResource($loaded)),
            __('api/vtual.public.management_show_success'),
        );
    }
}
