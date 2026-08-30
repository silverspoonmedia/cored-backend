<?php

namespace App\Domain\Vtual\Http\Controllers\API\V1;

use App\Domain\Vtual\Http\Resources\PlatformResource;
use App\Domain\Vtual\Models\Creator;
use App\Domain\Vtual\Services\PlatformService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ResolvesResourceArray;
use App\Support\ListActions\Builders\PlatformQueryRowActions;
use App\Support\ListActions\QueryRowActionAttacher;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformController extends Controller
{
    use ApiResponse;
    use ResolvesResourceArray;

    public function __construct(
        private readonly PlatformService $platforms,
        private readonly PlatformQueryRowActions $queryRowActions,
    ) {}

    public function index(Request $request): JsonResponse
    {
        // Platforms catalog is readable by any user who can view creators.
        $this->authorize('viewAny', Creator::class);

        $list = $this->platforms->listAll();

        return $this->successResponse(
            QueryRowActionAttacher::attach(
                $this->resolveResource(PlatformResource::collection($list)),
                $list,
                $this->queryRowActions,
                $request->user(),
            ),
            __('api/vtual.platform.list_success'),
        );
    }
}
