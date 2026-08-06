<?php

namespace App\Domain\Vtual\Http\Controllers\API\V1;

use App\Domain\Vtual\Http\Resources\VtuberProfileResource;
use App\Domain\Vtual\Services\VtuberProfileService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ResolvesResourceArray;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VtuberProfileController extends Controller
{
    use ApiResponse;
    use ResolvesResourceArray;

    public function __construct(
        private readonly VtuberProfileService $profiles,
    ) {}

    public function show(Request $request, string $creator): JsonResponse
    {
        $loaded = $this->profiles->show($creator);

        return $this->successResponse(
            $this->resolveResource(new VtuberProfileResource($loaded)),
            __('api/vtual.vtuber.show_success'),
        );
    }
}
