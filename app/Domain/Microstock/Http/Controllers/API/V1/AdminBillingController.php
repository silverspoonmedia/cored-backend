<?php

namespace App\Domain\Microstock\Http\Controllers\API\V1;

use App\Domain\Identity\Models\User;
use App\Domain\Microstock\Http\Resources\AdminBillingUsageEventResource;
use App\Domain\Microstock\Services\AdminBillingAnalyticsService;
use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminBillingController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly AdminBillingAnalyticsService $analytics,
    ) {}

    public function overview(Request $request): JsonResponse
    {
        $this->authorizePlatformBilling($request->user());

        return $this->successResponse(
            $this->analytics->platformOverview(
                $request->query('from'),
                $request->query('to'),
            ),
            __('api/microstock.admin_billing_overview_loaded'),
        );
    }

    public function usageEvents(Request $request): JsonResponse
    {
        $this->authorizePlatformBilling($request->user());

        $perPage = min(50, max(1, (int) $request->query('per_page', 20)));
        $paginator = $this->analytics->usageEventsPaginated(
            $perPage,
            $request->query('from'),
            $request->query('to'),
            $request->query('user_id'),
        );

        return $this->successResponse([
            'items' => AdminBillingUsageEventResource::collection($paginator->getCollection()),
            'pagination' => $this->paginationMeta($paginator),
        ], __('api/microstock.admin_usage_events_loaded'));
    }

    private function authorizePlatformBilling(?User $user): void
    {
        if ($user === null || ! $user->hasAnyRole(['Root', 'Admin', 'Moderator'])) {
            abort(403, __('api/microstock.admin_billing_forbidden'));
        }
    }

    /**
     * @return array<string, int|bool>
     */
    private function paginationMeta(\Illuminate\Contracts\Pagination\LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'has_more' => $paginator->hasMorePages(),
        ];
    }
}
