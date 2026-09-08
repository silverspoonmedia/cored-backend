<?php

namespace App\Domain\Microstock\Http\Controllers\API\V1;

use App\Domain\Microstock\Http\Resources\BillingLedgerEntryResource;
use App\Domain\Microstock\Http\Resources\BillingUsageEventResource;
use App\Domain\Microstock\Services\AiBillingService;
use App\Domain\Microstock\Services\AiCreditService;
use App\Domain\Microstock\Services\AiDailyFreeService;
use App\Domain\Microstock\Services\BillingLedgerService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ResolvesResourceArray;
use App\Support\ListQueryPaginator;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    use ApiResponse;
    use ResolvesResourceArray;

    public function __construct(
        private readonly AiCreditService $credits,
        private readonly AiBillingService $billing,
        private readonly AiDailyFreeService $dailyFree,
        private readonly BillingLedgerService $ledger,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $userId = (string) $request->user()->id;
        $balanceMicro = $this->credits->balance($userId);
        $estimateMicro = $this->billing->estimateForAssets(1);

        return $this->successResponse([
            'balance_micro_usd' => $balanceMicro,
            'balance_usd' => $balanceMicro < 0
                ? $this->ledger->formatUsd($balanceMicro, signed: true)
                : $this->ledger->formatUsd($balanceMicro),
            'estimated_per_analysis_micro_usd' => $estimateMicro,
            'estimated_per_analysis_usd' => $this->billing->microUsdToUsd($estimateMicro),
            'daily_free' => $this->dailyFree->snapshot($userId),
        ], __('api/microstock.billing_loaded'));
    }

    public function ledger(Request $request): JsonResponse
    {
        $perPage = min(50, max(1, (int) $request->query('per_page', 20)));
        $paginator = $this->ledger->ledgerPaginated(
            (string) $request->user()->id,
            $perPage,
            $request->query('from'),
            $request->query('to'),
        );

        return $this->successResponse([
            'items' => $this->resolveResource(BillingLedgerEntryResource::collection($paginator->getCollection())),
            'pagination' => ListQueryPaginator::meta($paginator),
        ], __('api/microstock.ledger_loaded'));
    }

    public function usageEvents(Request $request): JsonResponse
    {
        $perPage = min(50, max(1, (int) $request->query('per_page', 20)));
        $paginator = $this->ledger->usageEventsPaginated(
            (string) $request->user()->id,
            $perPage,
            $request->query('from'),
            $request->query('to'),
        );

        return $this->successResponse([
            'items' => $this->resolveResource(BillingUsageEventResource::collection($paginator->getCollection())),
            'pagination' => ListQueryPaginator::meta($paginator),
        ], __('api/microstock.usage_events_loaded'));
    }
}
