<?php

namespace App\Domain\Vtual\Http\Controllers\API\V1;

use App\Domain\Vtual\Http\Resources\PlatformResource;
use App\Domain\Vtual\Models\Creator;
use App\Domain\Vtual\Services\PlatformService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ResolvesResourceArray;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class PlatformController extends Controller
{
    use ApiResponse;
    use ResolvesResourceArray;

    public function __construct(
        private readonly PlatformService $platforms,
    ) {}

    public function index(): JsonResponse
    {
        // Platforms catalog is readable by any user who can view creators.
        $this->authorize('viewAny', Creator::class);

        return $this->successResponse(
            $this->resolveResource(PlatformResource::collection($this->platforms->listAll())),
            __('api/vtual.platform.list_success'),
        );
    }
}
