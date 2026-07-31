<?php

namespace App\Domain\PaymentGateway\Http\Controllers\Web;

use App\Domain\PaymentGateway\Services\PaymentIncomeAnalyticsService;
use App\Http\Controllers\Web\Concerns\ConsumesApi;
use App\Http\Controllers\Web\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentIncomeController extends Controller
{
    use ConsumesApi;

    public function index(Request $request, PaymentIncomeAnalyticsService $analytics): View
    {
        $defaults = $analytics->defaultFilters();
        $filters = [
            'from' => $request->query('from', $defaults['from']),
            'to' => $request->query('to', $defaults['to']),
            'basis' => $request->query('basis', $defaults['basis']),
            'gateway' => $request->query('gateway'),
        ];

        $overviewResponse = $this->api()->get('payments/income', array_filter($filters, fn ($value) => $value !== null && $value !== ''));
        $invoicesResponse = $this->api()->get('payments/income/invoices', array_filter(
            array_merge($filters, ['per_page' => 20]),
            fn ($value) => $value !== null && $value !== '',
        ));

        return $this->webView('payments.income.index', [
            'filters' => $filters,
            'overview' => $overviewResponse->successful() ? $overviewResponse->json('data', []) : [],
            'invoices' => $invoicesResponse->successful() ? $invoicesResponse->json('data', []) : ['items' => [], 'pagination' => []],
            'loadError' => ! $overviewResponse->successful()
                ? $this->apiMessage($overviewResponse)
                : null,
        ]);
    }

    public function overview(Request $request): JsonResponse
    {
        $response = $this->api()->get('payments/income', $request->query());

        if (! $response->successful()) {
            return response()->json([
                'status' => 'error',
                'message' => $this->apiMessage($response),
            ], $response->status() >= 400 ? $response->status() : 400);
        }

        return response()->json($response->json());
    }

    public function invoices(Request $request): JsonResponse
    {
        $response = $this->api()->get('payments/income/invoices', $request->query());

        if (! $response->successful()) {
            return response()->json([
                'status' => 'error',
                'message' => $this->apiMessage($response),
            ], $response->status() >= 400 ? $response->status() : 400);
        }

        return response()->json($response->json());
    }
}
