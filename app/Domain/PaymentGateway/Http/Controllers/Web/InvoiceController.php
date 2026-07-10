<?php

namespace App\Domain\PaymentGateway\Http\Controllers\Web;

use App\Domain\PaymentGateway\Http\Controllers\API\V1\InvoiceController as ApiInvoiceController;
use App\Http\Controllers\Web\Concerns\ConsumesApi;
use App\Http\Controllers\Web\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Web invoice management — a pure consumer of the versioned API.
 *
 * Forwards to `/api/v1/invoices` (see {@see ApiInvoiceController})
 * via {@see ConsumesApi} and renders Blade views or redirects.
 */
class InvoiceController extends Controller
{
    use ConsumesApi;

    public function index(): View
    {
        return $this->webView('invoices.index');
    }

    public function datatable(Request $request): JsonResponse
    {
        $response = $this->api()->get('invoices', array_merge(
            $request->query(),
            ['display_mode' => 'datatable'],
        ));

        if (! $response->successful()) {
            return response()->json([
                'draw' => (int) $request->query('draw'),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => $this->apiMessage($response),
            ], $response->status() >= 400 ? $response->status() : 400);
        }

        $payload = $response->json();
        $payload['data'] = array_map(
            fn (array $row): array => $this->mapRowActions($row),
            $payload['data'] ?? [],
        );

        return response()->json($payload);
    }

    public function show(string $invoice): View|RedirectResponse
    {
        $response = $this->api()->get("invoices/{$invoice}");

        if (! $response->successful()) {
            return redirect()->route('invoices.index')->with('error', $this->apiMessage($response));
        }

        return $this->webView('invoices.show', [
            'invoice' => $response->json('data', []),
        ]);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function mapRowActions(array $row): array
    {
        $allowed = array_column($row['actions'] ?? [], 'key');
        $id = $row['id'] ?? null;
        $actions = [];

        if ($id !== null && in_array('show', $allowed, true)) {
            $actions[] = [
                'key' => 'show',
                'label' => __('ui.datatable.view'),
                'method' => 'GET',
                'url' => route('invoices.show', $id),
                'style' => 'info',
                'icon' => 'bi bi-eye',
            ];
        }

        $row['actions'] = $actions;

        return $row;
    }
}
