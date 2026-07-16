<?php

namespace App\Domain\FileHosting\Http\Controllers\API\V1;

use App\Domain\FileHosting\Models\HostedFile;
use App\Domain\FileHosting\Services\AccessService;
use App\Domain\FileHosting\Services\QuotaService;
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
            __('api/file-hosting.quota_success'),
        );
    }
}
