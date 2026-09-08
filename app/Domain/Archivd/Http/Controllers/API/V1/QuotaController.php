<?php

namespace App\Domain\Archivd\Http\Controllers\API\V1;

use App\Domain\Archivd\Models\HostedFile;
use App\Domain\Archivd\Services\AccessService;
use App\Domain\Archivd\Services\QuotaService;
use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuotaController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly QuotaService $quota,
        private readonly AccessService $access,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $this->authorize('viewAny', HostedFile::class);

        $ownerId = $this->access->resolveOwnerId(
            $request->user(),
            $request->query('user_id'),
        );

        return $this->successResponse(
            $this->quota->usage($ownerId),
            __('api/archivd.quota_success'),
        );
    }
}
