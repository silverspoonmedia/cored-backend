<?php

namespace App\Domain\Microstock\Http\Controllers\API\V1;

use App\Domain\Identity\Models\User;
use App\Domain\Microstock\Http\Resources\AdminBillingUsageEventResource;
use App\Domain\Microstock\Services\AdminBillingAnalyticsService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ResolvesResourceArray;
use App\Support\ListQueryPaginator;
use App\Traits\ApiResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminBillingController extends Controller
{
    use ApiResponse;
    use ResolvesResourceArray;

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
            'items' => $this->resolveResource(AdminBillingUsageEventResource::collection($paginator->getCollection())),
            'pagination' => ListQueryPaginator::meta($paginator),
        ], __('api/microstock.admin_usage_events_loaded'));
    }

    private function authorizePlatformBilling(?User $user): void
    {
        if ($user === null || ! $user->hasAnyRole(['Root', 'Admin', 'Moderator'])) {
            throw new HttpResponseException(
                $this->errorResponse(__('api/microstock.admin_billing_forbidden'), 403),
            );
        }
    }
}
