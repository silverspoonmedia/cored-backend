<?php

namespace App\Domain\Microstock\Http\Controllers\API\V1;

use App\Domain\Microstock\Services\AiBillingService;
use App\Domain\Microstock\Services\AiCreditService;
use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly AiCreditService $credits,
        private readonly AiBillingService $billing,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $userId = (string) $request->user()->id;
        $balanceMicro = $this->credits->balance($userId);
        $estimateMicro = $this->billing->estimateForAssets(1);

        return $this->successResponse([
            'balance_micro_usd' => $balanceMicro,
            'balance_usd' => $this->billing->microUsdToUsd($balanceMicro),
            'estimated_per_analysis_micro_usd' => $estimateMicro,
            'estimated_per_analysis_usd' => $this->billing->microUsdToUsd($estimateMicro),
        ], __('api/microstock.billing_loaded'));
    }
}
